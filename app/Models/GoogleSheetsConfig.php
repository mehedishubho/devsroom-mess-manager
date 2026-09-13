<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-mess Google Sheets mirror configuration (one row per mess).
 *
 * Secret fields are encrypted at rest via the casts below — APP_KEY decrypts
 * them. Identifiers (client id, spreadsheet id) stay plaintext so the config
 * screen and diagnostics can read them without a key.
 *
 * Deliberately NOT scoped by `BelongsToActiveMess`: the queued sync worker runs
 * with no session, so `Mess::activeId()` is not meaningful there. Every read
 * goes through `forMess()` with an explicit mess id instead.
 */
#[Fillable([
    'mess_id',
    'enabled',
    'auth_mode',
    'service_account_json',
    'service_account_email',
    'oauth_client_id',
    'oauth_client_secret',
    'oauth_refresh_token',
    'spreadsheet_id',
    'tabs',
    'last_synced_at',
    'last_error',
    'last_error_at',
])]
class GoogleSheetsConfig extends Model
{
    public const AUTH_SERVICE_ACCOUNT = 'service_account';

    public const AUTH_OAUTH = 'oauth';

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'tabs' => 'array',
            'last_synced_at' => 'datetime',
            'last_error_at' => 'datetime',
            'service_account_json' => 'encrypted',
            'oauth_client_secret' => 'encrypted',
            'oauth_refresh_token' => 'encrypted',
        ];
    }

    public function mess(): BelongsTo
    {
        return $this->belongsTo(Mess::class);
    }

    public function authMode(): string
    {
        return $this->auth_mode === self::AUTH_OAUTH
            ? self::AUTH_OAUTH
            : self::AUTH_SERVICE_ACCOUNT;
    }

    /** True when the selected auth mode has a complete credential set. */
    public function hasCredentials(): bool
    {
        if ($this->authMode() === self::AUTH_OAUTH) {
            return filled($this->oauth_client_id)
                && filled($this->safeSecret('oauth_client_secret'))
                && filled($this->safeSecret('oauth_refresh_token'));
        }

        return filled($this->safeSecret('service_account_json'));
    }

    /** True when a spreadsheet is set and the selected auth mode has credentials. */
    public function isReady(): bool
    {
        return filled($this->spreadsheet_id) && $this->hasCredentials();
    }

    /** The service-account address the spreadsheet must be shared with. */
    public function serviceAccountEmail(): ?string
    {
        if (filled($this->service_account_email)) {
            return $this->service_account_email;
        }

        return $this->parseServiceAccountEmail($this->safeSecret('service_account_json'));
    }

    /**
     * The spreadsheet id, tolerating a full docs.google.com URL pasted into the
     * form. Returns null when unset.
     */
    public function resolvedSpreadsheetId(): ?string
    {
        $value = trim((string) $this->spreadsheet_id);

        if ($value === '') {
            return null;
        }

        if (preg_match('~/spreadsheets/d/([A-Za-z0-9_-]+)~', $value, $matches)) {
            return $matches[1];
        }

        return $value;
    }

    /** @return list<string> Model classes whose tab is enabled. */
    public function enabledModels(array $all): array
    {
        $tabs = is_array($this->tabs) ? $this->tabs : [];

        return array_values(array_filter($all, function (string $model) use ($tabs) {
            // Missing key = enabled (safe default for existing configs).
            return ! array_key_exists($model, $tabs) || (bool) $tabs[$model];
        }));
    }

    /**
     * Read an encrypted attribute without letting a decrypt failure (e.g. a
     * rotated APP_KEY) blow up the config screen or the worker.
     */
    public function safeSecret(string $attribute): ?string
    {
        try {
            $value = $this->getAttribute($attribute);

            return is_string($value) && $value !== '' ? $value : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseServiceAccountEmail(?string $json): ?string
    {
        if (blank($json)) {
            return null;
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            return is_string($decoded['client_email'] ?? null) ? $decoded['client_email'] : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @var array<int, self|null> */
    private static array $cache = [];

    /** The config row for a mess, memoized per process. Null when unset. */
    public static function forMess(int $messId): ?self
    {
        if (array_key_exists($messId, self::$cache)) {
            return self::$cache[$messId];
        }

        try {
            return self::$cache[$messId] = static::query()->where('mess_id', $messId)->first();
        } catch (\Throwable) {
            // Bootstrap-safe: missing table on a fresh clone / DB unreachable.
            return self::$cache[$messId] = null;
        }
    }

    public static function flushCache(?int $messId = null): void
    {
        if ($messId === null) {
            self::$cache = [];

            return;
        }

        unset(self::$cache[$messId]);
    }
}
