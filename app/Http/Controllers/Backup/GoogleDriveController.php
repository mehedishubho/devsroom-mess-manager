<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backup;

use App\Http\Controllers\Controller;
use App\Models\BackupConfig;
use App\Support\CloudBackupCredentials;
use Google\Client as GoogleClient;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OwenIt\Auditing\Models\Audit;

/**
 * Guided Google Drive connection.
 *
 * Obtaining a Drive refresh token by hand (OAuth Playground, or a throwaway
 * script) was the single hardest step in configuring the backup system — and
 * the step most operators gave up on, leaving every backup on the same disk it
 * was meant to survive. Google's own client library is already installed as a
 * dependency of the Drive filesystem driver, so this runs the real consent flow
 * and stores the refresh token for the operator.
 *
 * Two routes, both inside the super-admin-only Backups group:
 *   GET .../google/redirect  → builds the consent URL and redirects
 *   GET .../google/callback  → exchanges the code, stores the token + folder
 *
 * The `state` parameter is kept in the session and compared on return, so a
 * forged callback cannot attach someone else's Google account to this install.
 */
class GoogleDriveController extends Controller
{
    private const STATE_KEY = 'backup.gdrive.oauth_state';

    /** Least privilege: only files this app creates/opens, not the whole Drive. */
    private const SCOPE = Drive::DRIVE_FILE;

    /** Send the operator to Google's consent screen. */
    public function redirect(Request $request): RedirectResponse
    {
        $config = BackupConfig::current();

        // Google needs the client credentials before it can start the flow, and
        // they must be *saved* because the callback runs in a later request.
        if (! filled($config->gdrive_client_id) || ! filled($config->gdrive_client_secret)) {
            return back()->withErrors([
                'gdrive' => __('Enter and save the Client ID and Client secret first — Google needs them to start the consent flow.'),
            ]);
        }

        $state = Str::random(40);
        $request->session()->put(self::STATE_KEY, $state);

        return redirect()->away($this->client($state)->createAuthUrl());
    }

    /** Handle Google's redirect back, storing the refresh token it grants us. */
    public function callback(Request $request): RedirectResponse
    {
        $state = (string) $request->session()->pull(self::STATE_KEY);

        if ($state === '' || ! hash_equals($state, (string) $request->query('state', ''))) {
            return $this->fail(__('The Google sign-in session expired or did not match. Please try connecting again.'));
        }

        if ($request->query('error')) {
            return $this->fail(__('Google returned an error: :error', ['error' => (string) $request->query('error')]));
        }

        $code = (string) $request->query('code', '');

        if ($code === '') {
            return $this->fail(__('Google did not return an authorization code.'));
        }

        try {
            $client = $this->client();
            $token = $client->fetchAccessTokenWithAuthCode($code);

            if (isset($token['error'])) {
                throw new \RuntimeException($token['error_description'] ?? $token['error']);
            }

            $refreshToken = $token['refresh_token'] ?? null;

            if (! $refreshToken) {
                throw new \RuntimeException(__('Google did not return a refresh token. Remove this app\'s access at myaccount.google.com/permissions, then connect again.'));
            }

            $payload = ['gdrive_refresh_token' => $refreshToken];

            // Remove another manual step: create the destination folder and
            // remember its id when the operator hasn't supplied one.
            if (! filled(BackupConfig::current()->gdrive_folder_id)) {
                $client->setAccessToken($token);

                $folder = new DriveFile;
                $folder->setName(config('app.name').' Backups');
                $folder->setMimeType('application/vnd.google-apps.folder');

                $created = (new Drive($client))->files->create($folder, ['fields' => 'id']);

                if ($created->getId()) {
                    $payload['gdrive_folder_id'] = $created->getId();
                }
            }

            BackupConfig::updateOrCreate(['id' => 1], $payload);
            BackupConfig::flushCache();

            // Make the new token usable in THIS request lifecycle too.
            try {
                CloudBackupCredentials::applyToRuntimeConfig();
            } catch (\Throwable) {
                // Non-fatal — the next request picks it up from the DB.
            }
        } catch (\Throwable $e) {
            return $this->fail(__('Could not complete the Google sign-in: :msg', ['msg' => $e->getMessage()]));
        }

        $this->writeAudit('backup.gdrive.connected', [], $request);

        return redirect()
            ->route('dashboard.backups.index')
            ->with('success', __('Google Drive connected. Turn on "Use for backups" and save to start mirroring.'));
    }

    /**
     * A Google client wired to our saved app credentials and callback URL.
     * `offline` + `consent` are what make Google return a refresh token.
     */
    private function client(?string $state = null): GoogleClient
    {
        $config = BackupConfig::current();

        $client = new GoogleClient;
        $client->setClientId((string) $config->gdrive_client_id);
        $client->setClientSecret((string) $config->gdrive_client_secret);
        $client->setRedirectUri($this->callbackUrl());
        $client->setScopes([self::SCOPE]);
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        if ($state !== null) {
            $client->setState($state);
        }

        return $client;
    }

    /** The URI that must be registered in the Google Cloud console. */
    public static function callbackUrl(): string
    {
        return route('dashboard.backups.google.callback');
    }

    private function fail(string $message): RedirectResponse
    {
        return redirect()->route('dashboard.backups.index')->withErrors(['gdrive' => $message]);
    }

    /** Manual Audit row — connecting a third-party account is a significant act. */
    private function writeAudit(string $event, array $payload, Request $request): void
    {
        try {
            $audit = new Audit;
            $audit->fill([
                'user_type' => $request->user() ? get_class($request->user()) : null,
                'user_id' => $request->user()?->id,
                'event' => $event,
                'auditable_type' => 'backup',
                'auditable_id' => 0,
                'new_values' => $payload,
                'url' => $request->fullUrl(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'tags' => 'backup',
            ])->save();
        } catch (\Throwable) {
            // An audit failure must not undo a successful connection.
        }
    }
}
