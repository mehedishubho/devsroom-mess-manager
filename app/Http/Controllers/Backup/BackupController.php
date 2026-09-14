<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backup;

use App\Http\Controllers\Controller;
use App\Http\Requests\Backup\UpdateBackupConfigRequest;
use App\Models\BackupConfig;
use App\Models\BackupLog;
use App\Models\Mess;
use App\Support\BackupArchive;
use App\Support\BackupDestinations;
use App\Support\CloudBackupCredentials;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use OwenIt\Auditing\Models\Audit;

/**
 * D-03 super-admin Backups UI (research Pattern 3 — custom controller).
 *
 * The Backups page is the single surface for backups: the backup file list
 * (download / restore / delete), the inline Configure form (schedule +
 * retention + per-provider storage toggles), and a backup activity log so a
 * failed `backup:run` is visible instead of
 * silently swallowed. The destructive restore lives in RestoreController
 * (which orchestrates BackupRestoreService). This controller MUST NOT contain
 * restore logic itself (T-06-02-08).
 *
 * Every zip download is audit-logged (T-06-03-05 PII leak prevention) via a
 * manual OwenIt\Auditing\Models\Audit row keyed by event='backup.download'.
 */
class BackupController extends Controller
{
    /** Actions the Activity log can record — also the filter whitelist. */
    private const LOG_ACTIONS = ['backup', 'purge', 'monitor', 'download', 'delete', 'restore', 'verify', 'export', 'configure'];

    /** True when the backup_logs table isn't readable (e.g. not migrated yet). */
    private bool $backupLogUnavailable = false;

    public function index(): View
    {
        return view('dashboard.backups.index', $this->indexData());
    }

    /**
     * The Configure form now lives inline on the Backups (index) page. This
     * route is kept as a 200-returning alias so deep links + the existing
     * super-admin role gate + secret-leak tests keep working; it renders the
     * same index view.
     */
    public function edit(): View
    {
        return view('dashboard.backups.index', $this->indexData());
    }

    public function runNow(): RedirectResponse
    {
        // Ad-hoc backup. Runs synchronously; the spec is one small mess.
        //
        // CRITICAL: `backup:run` does NOT throw when the dump fails (e.g.
        // mysqldump missing on the server) — Artisan::call() just returns a
        // non-zero exit code, which the old code ignored, producing a false
        // "Backup completed." flash. Detect failure three ways: exit code,
        // the captured output, and "no new zip actually appeared on disk".
        if ($preflight = $this->preflightWritable()) {
            return $this->recordLog('backup', 'failure', $preflight);
        }

        $disk = Storage::disk($this->backupDisk());
        $before = $this->countZips($disk);

        try {
            $exitCode = (int) Artisan::call('backup:run');
            $output = (string) Artisan::output();
        } catch (\Throwable $e) {
            return $this->recordLog('backup', 'failure', $e->getMessage());
        }

        $after = $this->countZips($disk);

        if ($after <= $before || $exitCode !== 0) {
            $reason = $this->extractFailureReason($output)
                ?: __('No backup file was produced (exit code :code). Usually mysqldump is missing on the server — install it and set DUMP_BINARY_PATH.', ['code' => $exitCode]);

            return $this->recordLog('backup', 'failure', $reason, output: $output);
        }

        return $this->recordLog('backup', 'success', __('Backup completed.'), output: $output);
    }

    /**
     * Super-admin-only zip download. Access-logged via a manual Audit row
     * (T-06-03-05 — PII leak prevention: every download leaves a trail).
     */
    public function download(Request $request)
    {
        $path = (string) $request->query('path', '');
        $this->guardPath($path);
        $disk = Storage::disk($this->backupDisk());
        abort_unless($path !== '' && $disk->exists($path), 404);

        $this->writeAudit('backup.download', ['path' => $path]);
        $this->recordLog('download', 'success', path: $path, flash: false);

        // Flysystem's download() returns a BinaryFileResponse/StreamedResponse
        // that actually emits the file bytes. (Do NOT use a streamDownload
        // closure that just RETURNS readStream() — Symfony discards the return
        // value and you get a 0-byte download.)
        return $disk->download($path, basename($path));
    }

    /**
     * Delete a single backup archive from EVERY active destination disk.
     *
     * Deleting only from the primary disk (as this used to) left the cloud
     * mirror copy behind: the archive vanished from the page while still
     * occupying space on Drive/R2, and a later restore could silently revert
     * to "yesterday's" file. A failure on one disk never aborts the others.
     * Audit-logged (T-06-03-05).
     */
    public function destroy(Request $request): RedirectResponse
    {
        $path = (string) $request->input('path', '');
        $this->guardPath($path);

        if ($path === '') {
            return back()->withErrors(['backup' => __('Backup not found.')]);
        }

        $deletedFrom = [];
        $failures = [];

        foreach (BackupDestinations::all() as $diskName) {
            try {
                $disk = Storage::disk($diskName);
                if ($disk->exists($path)) {
                    $disk->delete($path);
                    $deletedFrom[] = $diskName;
                }
            } catch (\Throwable $e) {
                $failures[] = $diskName.': '.$e->getMessage();
            }
        }

        if ($deletedFrom === []) {
            return back()->withErrors(['backup' => __('Backup not found.')]);
        }

        $this->writeAudit('backup.delete', ['path' => $path, 'disks' => $deletedFrom]);

        $message = __('Backup deleted from: :disks.', ['disks' => implode(', ', $deletedFrom)]);

        if ($failures !== []) {
            $message .= ' '.__('Some destinations could not be reached: :errors', ['errors' => implode(' | ', $failures)]);
        }

        return $this->recordLog('delete', $failures === [] ? 'success' : 'failure', $message, path: $path);
    }

    /**
     * Delete a single backup activity-log entry. Audit-logged: clearing the
     * record of what happened is itself a significant, tamper-evident act
     * (download/delete/restore already leave a trail).
     */
    public function destroyLog(BackupLog $log): RedirectResponse
    {
        $id = $log->id;
        $log->delete();

        $this->writeAudit('backup.log.delete', ['log_id' => $id]);

        return back()->with('success', __('Log entry deleted.'));
    }

    /**
     * Clear the entire backup activity log. Audit-logged (see destroyLog).
     */
    public function clearLogs(): RedirectResponse
    {
        $count = BackupLog::query()->count();
        BackupLog::query()->delete();

        $this->writeAudit('backup.logs.clear', ['deleted' => $count]);

        return back()->with('success', __('Activity log cleared.'));
    }

    /**
     * Save the Configure form. Persists the singleton row, then clears the
     * config cache so the new schedule + retention + provider toggles take
     * effect immediately (the scheduler reads BackupConfig at each
     * schedule:run; backup:purge reads it at runtime; spatie picks up the
     * refreshed destination list; StorageProvider reads the upload-mirror
     * flags at the next request).
     */
    public function update(UpdateBackupConfigRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // Secret fields are overwritten ONLY when a new value was typed. The
        // secret inputs render blank (never pre-filled with the decrypted
        // value), so an empty box means "keep the stored value" — the operator
        // can change schedule/retention without re-entering the secret each save.
        $payload = [
            'frequency' => $data['frequency'],
            'run_at' => $data['run_at'],
            'keep_all_days' => $data['keep_all_days'],
            'max_mb' => $data['max_mb'],
            'notification_email' => ($data['notification_email'] ?? null) ?: null,
            'encrypt_backups' => (bool) ($data['encrypt_backups'] ?? false),
            'gdrive_backup' => (bool) ($data['gdrive_backup'] ?? false),
            'gdrive_uploads' => (bool) ($data['gdrive_uploads'] ?? false),
            'r2_backup' => (bool) ($data['r2_backup'] ?? false),
            'r2_uploads' => (bool) ($data['r2_uploads'] ?? false),
            // Identifiers — written as-is (empty clears them).
            'gdrive_client_id' => $data['gdrive_client_id'] ?? null,
            'gdrive_folder_id' => $data['gdrive_folder_id'] ?? null,
            'r2_key' => $data['r2_key'] ?? null,
            'r2_region' => ($data['r2_region'] ?? null) ?: 'auto',
            'r2_bucket' => $data['r2_bucket'] ?? null,
            'r2_endpoint' => $data['r2_endpoint'] ?? null,
            'r2_use_path_style' => (bool) ($data['r2_use_path_style'] ?? false),
        ];

        // Secrets are only touched when explicitly addressed. A ticked
        // "remove saved secret" box clears the stored value (a rotated or
        // leaked credential MUST be removable); otherwise a non-empty box
        // replaces it and an empty box keeps it untouched.
        foreach (['gdrive_client_secret', 'gdrive_refresh_token', 'r2_secret', 'archive_password'] as $secret) {
            if ((bool) ($data['clear_'.$secret] ?? false)) {
                $payload[$secret] = null;
            } elseif (filled($data[$secret] ?? null)) {
                $payload[$secret] = $data[$secret];
            }
        }

        // Encryption without a password would silently produce unencrypted
        // archives while the UI claims otherwise — refuse the combination.
        if ($payload['encrypt_backups'] === true) {
            $hasPassword = ! empty($payload['archive_password'])
                || (! array_key_exists('archive_password', $payload) && filled(BackupConfig::current()->archive_password));

            if (! $hasPassword) {
                return back()->withInput()->withErrors([
                    'archive_password' => __('Set an archive password, or turn archive encryption off.'),
                ]);
            }
        }

        BackupConfig::updateOrCreate(['id' => 1], $payload);
        BackupConfig::flushCache();

        try {
            Artisan::call('config:clear');
        } catch (\Throwable) {
            // Non-fatal: a failed config:clear must not block the save.
        }

        // Apply the freshly-saved creds to THIS process so a follow-up Test
        // connection (or a backup:run triggered in the same lifecycle) sees
        // them without waiting for a new process to boot.
        try {
            CloudBackupCredentials::applyToRuntimeConfig();
        } catch (\Throwable) {
        }

        return redirect()
            ->route('dashboard.backups.index')
            ->with('success', __('Backup configuration updated.'));
    }

    /**
     * Probe a cloud provider's credentials by writing + reading + deleting a
     * tiny file on its disk. Surfaces the real error (auth, wrong bucket,
     * network) so mis-entered creds are visible immediately instead of failing
     * backups silently.
     *
     * Returns JSON for an AJAX/fetch call, or redirects back with a flash for a
     * plain form POST (matches the page's other actions and works without JS).
     */
    public function testConnection(Request $request, string $provider): JsonResponse|RedirectResponse
    {
        $disk = match ($provider) {
            'gdrive' => 'backups-gdrive',
            'r2' => 'backups-r2',
            default => null,
        };

        if ($disk === null) {
            return $this->testResult($request, false, __('Unknown provider.'));
        }

        // Apply the latest DB creds — a just-saved value may not yet be live in
        // this process, so re-read the singleton and re-key runtime config.
        try {
            CloudBackupCredentials::applyToRuntimeConfig();
        } catch (\Throwable) {
            // proceed; the probe will surface the real failure
        }

        $probe = '__connection_test.txt';

        try {
            Storage::disk($disk)->put($probe, 'connection test');
            $exists = Storage::disk($disk)->exists($probe);
            Storage::disk($disk)->delete($probe);

            return $exists
                ? $this->testResult($request, true, __(':provider connection successful — credentials work.', ['provider' => ucfirst($provider)]))
                : $this->testResult($request, false, __('Wrote the test file but could not read it back — check the bucket/folder permissions.'));
        } catch (\Throwable $e) {
            return $this->testResult($request, false, $e->getMessage());
        }
    }

    /** Format a Test-connection outcome as JSON (AJAX) or a redirect flash (form POST). */
    private function testResult(Request $request, bool $ok, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => $ok, 'message' => $message]);
        }

        return $ok ? back()->with('success', $message) : back()->withErrors(['test' => $message]);
    }

    /**
     * Send a real test message to the configured backup-notification recipient
     * so the operator can prove the failure-alert path works BEFORE a backup
     * actually fails (with MAIL_MAILER=log the message lands in the log file,
     * which is still the honest answer).
     */
    public function testNotification(Request $request): JsonResponse|RedirectResponse
    {
        // A just-saved recipient/encryption change may not be live yet.
        try {
            CloudBackupCredentials::applyToRuntimeConfig();
        } catch (\Throwable) {
            // proceed; the send below surfaces the real failure
        }

        $to = (string) config('backup.notifications.mail.to');

        if ($to === '') {
            return $this->testResult($request, false, __('No notification recipient is configured yet.'));
        }

        try {
            Mail::raw(
                __('This is a test message from :app. If you received it, backup notifications can reach you.', ['app' => config('app.name')]),
                fn ($message) => $message->to($to)->subject(__('Backup notification test')),
            );
        } catch (\Throwable $e) {
            return $this->testResult($request, false, $e->getMessage());
        }

        return $this->testResult($request, true, __('Test notification sent to :email. With MAIL_MAILER=log it goes to the log file, not an inbox.', ['email' => $to]));
    }

    /**
     * Shared view data for the Backups page (index + the configure alias).
     *
     * The archive list is built from the filesystem (not a table), so it is
     * collected, searched, sorted and then sliced by hand into a paginator.
     * Checksums are NEVER computed here — only recalled — because hashing a
     * large zip on every page load would make the page unusable. The Verify
     * action computes one on demand.
     */
    private function indexData(): array
    {
        $disk = Storage::disk($this->backupDisk());
        $config = BackupConfig::current();

        $original = collect($disk->allFiles())
            ->filter(fn ($p) => str_ends_with($p, '.zip'))
            ->map(fn ($p) => [
                'path' => $p,
                'name' => basename($p),
                'size' => (int) $disk->size($p),
                'last_modified' => (int) $disk->lastModified($p),
            ]);

        $totalCount = $original->count();
        $totalSize = (int) $original->sum('size');

        $search = trim((string) request()->query('q', ''));
        $filtered = $original;

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $filtered = $filtered->filter(fn ($b) => str_contains(mb_strtolower($b['name']), $needle));
        }

        $sort = (string) request()->query('sort', 'date');
        $direction = strtolower((string) request()->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $filtered = match ($sort) {
            'name' => $filtered->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE),
            'size' => $filtered->sortBy('size'),
            default => $filtered->sortBy('last_modified'),
        };

        if ($direction === 'desc') {
            $filtered = $filtered->reverse();
        }

        $filtered = $filtered->values();
        $filteredSize = (int) $filtered->sum('size');

        $perPage = 20;
        $page = max(1, (int) request()->query('page', 1));

        $backups = new LengthAwarePaginator(
            $filtered->forPage($page, $perPage)->values(),
            $filtered->count(),
            $perPage,
            $page,
            ['path' => route('dashboard.backups.index'), 'query' => request()->query()],
        );

        // Per-row enrichment: where each archive actually lives, plus its
        // remembered checksum (null until "Verify" has been run once).
        $diskName = $this->backupDisk();
        $backups->getCollection()->transform(function (array $backup) use ($diskName) {
            $backup['destinations'] = BackupArchive::destinations($backup['path']);
            $backup['checksum'] = BackupArchive::cachedChecksum($diskName, $backup['path'], $backup['last_modified']);

            return $backup;
        });

        $scheduler = $this->schedulerHealth($original);

        return [
            'backups' => $backups,
            'config' => $config,
            'diskNames' => BackupDestinations::all(),
            'search' => $search,
            'sort' => $sort,
            'dir' => $direction,
            'totalCount' => $totalCount,
            'totalSize' => $totalSize,
            'filteredSize' => $filteredSize,
            'lastBackupAt' => $this->lastSuccessfulBackupAt($original),
            'nextRunAt' => $this->nextRunAt(),
            // "Configured" reflects EITHER a DB-stored value (UI) or the env
            // fallback — both are legitimate sources.
            'gdriveConfigured' => BackupDestinations::gdriveConfigured() || CloudBackupCredentials::gdriveConfiguredFromDb(),
            'r2Configured' => BackupDestinations::r2Configured() || CloudBackupCredentials::r2ConfiguredFromDb(),
            // Secret inputs render blank (never pre-filled); these flags drive
            // the small "saved ✓" badge so the operator knows a value is stored.
            'gdriveSecretSaved' => filled($config->gdrive_client_secret),
            'gdriveRefreshSaved' => filled($config->gdrive_refresh_token),
            'r2SecretSaved' => filled($config->r2_secret),
            'archivePasswordSaved' => filled($config->archive_password),
            // The activity log is non-critical — a fresh deploy that hasn't run
            // `php artisan migrate` yet (backup_logs table missing) must NOT 500
            // the whole Backups page and lock the super-admin out of configuring.
            'backupLogs' => $this->activityLogs(),
            'backupLogUnavailable' => $this->backupLogUnavailable,
            'logAction' => $this->logFilterAction(),
            'logStatus' => $this->logFilterStatus(),
            'logActions' => self::LOG_ACTIONS,
            // Scheduler health — surfaces a missing server cron (the #1 reason
            // "backups are configured but none appear"). See schedulerHealth().
            'schedulerHealthy' => $scheduler['healthy'],
            'schedulerIssue' => $scheduler['issue'],
            'schedulerCronLine' => $scheduler['cron_line'],
        ];
    }

    /**
     * When the newest backup was created: the most recent successful `backup`
     * log row, falling back to the newest archive on disk. Uses the ORIGINAL
     * (unfiltered) collection so a search can't distort the header stats.
     */
    private function lastSuccessfulBackupAt(Collection $backups): ?Carbon
    {
        $fromLog = null;

        try {
            $row = BackupLog::query()
                ->where('action', 'backup')
                ->where('status', 'success')
                ->latest('id')
                ->first();
            $fromLog = $row?->created_at;
        } catch (\Throwable) {
            // backup_logs missing — fall back to the archive mtime.
        }

        $fromDisk = $backups->isNotEmpty()
            ? Carbon::createFromTimestamp((int) $backups->max('last_modified'))
            : null;

        if ($fromLog && $fromDisk) {
            return $fromLog->greaterThan($fromDisk) ? $fromLog : $fromDisk;
        }

        return $fromLog ?? $fromDisk;
    }

    /**
     * The next moment the scheduler will run `backup:run`, derived from the
     * configured cadence. Null when automatic backups are off.
     *
     * Mirrors the schedule definitions in routes/console.php: daily() fires at
     * the configured time every day, weekly() on Sunday, monthly() on the 1st.
     */
    private function nextRunAt(): ?Carbon
    {
        $config = BackupConfig::current();

        if (! in_array($config->frequency, ['daily', 'weekly', 'monthly'], true)) {
            return null;
        }

        [$hour, $minute] = array_map('intval', explode(':', $config->runAtLabel()));
        $now = now();

        $candidate = match ($config->frequency) {
            'weekly' => $now->copy()->next(Carbon::SUNDAY)->setTime($hour, $minute),
            'monthly' => $now->copy()->startOfMonth()->setTime($hour, $minute),
            default => $now->copy()->setTime($hour, $minute),
        };

        if ($candidate->isPast()) {
            $candidate = match ($config->frequency) {
                'weekly' => $candidate->addWeek(),
                'monthly' => $candidate->addMonthNoOverflow(),
                default => $candidate->addDay(),
            };
        }

        return $candidate;
    }

    /**
     * Compute (or recompute) an archive's sha256 and report whether it matches
     * the previously recorded digest. The first run simply records it; a later
     * mismatch means the stored archive changed underneath us — corruption or
     * tampering, which is exactly what an operator wants to know before
     * restoring from it.
     */
    public function verify(Request $request): RedirectResponse
    {
        $path = (string) $request->input('path', '');
        $this->guardPath($path);

        $diskName = $this->backupDisk();
        $disk = Storage::disk($diskName);

        if ($path === '' || ! $disk->exists($path)) {
            return $this->recordLog('verify', 'failure', __('Backup not found.'), path: $path);
        }

        $modified = (int) $disk->lastModified($path);
        $previous = BackupArchive::cachedChecksum($diskName, $path, $modified);

        try {
            $hash = BackupArchive::checksum($diskName, $path, $modified);
        } catch (\Throwable $e) {
            return $this->recordLog('verify', 'failure', $e->getMessage(), path: $path);
        }

        if ($previous === null) {
            return $this->recordLog('verify', 'success', __('Checksum recorded: :hash', ['hash' => $hash]), path: $path);
        }

        if (! hash_equals($previous, $hash)) {
            $this->writeAudit('backup.verify.mismatch', ['path' => $path, 'previous' => $previous, 'current' => $hash]);

            return $this->recordLog(
                'verify',
                'failure',
                __('Archives do not match — the stored file changed since it was last verified. Do not restore from it unless you know why.'),
                path: $path,
            );
        }

        return $this->recordLog('verify', 'success', __('Archive verified — checksum matches (:hash).', ['hash' => $hash]), path: $path);
    }

    /**
     * Delete several archives at once, fanning out over every active
     * destination disk like the single-archive delete does.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $paths = (array) $request->input('paths', []);
        $paths = array_values(array_filter(array_map('strval', $paths), fn ($p) => $p !== ''));

        if ($paths === []) {
            return back()->withErrors(['backup' => __('Select at least one backup to delete.')]);
        }

        $deleted = 0;
        $failures = [];

        foreach ($paths as $path) {
            $this->guardPath($path);

            $deletedFrom = [];

            foreach (BackupDestinations::all() as $diskName) {
                try {
                    $disk = Storage::disk($diskName);
                    if ($disk->exists($path)) {
                        $disk->delete($path);
                        $deletedFrom[] = $diskName;
                    }
                } catch (\Throwable $e) {
                    $failures[] = $diskName.': '.$e->getMessage();
                }
            }

            if ($deletedFrom !== []) {
                $deleted++;
                $this->writeAudit('backup.delete', ['path' => $path, 'disks' => $deletedFrom, 'bulk' => true]);
            }
        }

        $message = __('Deleted :count backup(s).', ['count' => $deleted]);

        if ($failures !== []) {
            $message .= ' '.__('Some destinations could not be reached: :errors', ['errors' => implode(' | ', array_unique($failures))]);
        }

        return $this->recordLog('delete', $deleted > 0 ? 'success' : 'failure', $message);
    }

    /**
     * Detect whether the automatic-backup schedule is actually firing.
     *
     * Laravel's scheduler only runs when the server invokes
     * `artisan schedule:run` every minute via cron — and that cron line is the
     * ONE piece code cannot install (operator-managed on CloudPanel/cPanel). So
     * a correctly-configured daily backup silently never runs if the cron is
     * missing. This compares BackupConfig's cadence against the newest backup
     * moment (max of newest zip mtime + newest success log) and returns an
     * unhealthy verdict + the exact cron line to install when the cadence is
     * being missed.
     *
     * @param  Collection  $backups  the zip list from indexData() (desc by mtime)
     * @return array{healthy:bool, issue:?string, cron_line:string}
     */
    private function schedulerHealth(Collection $backups): array
    {
        $cronLine = '* * * * * cd '.base_path().' && '.PHP_BINARY.' artisan schedule:run >> /dev/null 2>&1';
        $config = BackupConfig::current();

        // Backups intentionally off → nothing to warn about.
        if (! in_array($config->frequency, ['daily', 'weekly', 'monthly'], true)) {
            return ['healthy' => true, 'issue' => null, 'cron_line' => $cronLine];
        }

        $newestZipAt = $backups->isNotEmpty() ? (int) $backups->first()['last_modified'] : 0;

        // The newest SUCCESS backup_logs row is the other signal (covers a
        // backup that wrote to a cloud-only disk with no local zip, and is the
        // primary signal once LogScheduledBackupActivity is logging nightly runs).
        $newestLogAt = 0;
        try {
            $row = BackupLog::query()
                ->where('action', 'backup')
                ->where('status', 'success')
                ->latest('id')
                ->first();
            $newestLogAt = $row && $row->created_at ? $row->created_at->getTimestamp() : 0;
        } catch (\Throwable) {
            // backup_logs missing — fall back to the zip mtime only.
        }

        $lastAt = max($newestZipAt, $newestLogAt);

        // Allow a buffer past the configured cadence before flagging.
        $maxHours = (int) match ($config->frequency) {
            'weekly' => 180,   // 7.5 days
            'monthly' => 744,  // 31 days
            default => 25,     // daily + ~1h buffer
        };

        $issue = null;
        if ($lastAt === 0) {
            $issue = __('No backup has ever been created, even though automatic backups are configured (:freq at :time). The server cron line below is almost certainly missing — add it via crontab -e / your panel.', [
                'freq' => ucfirst((string) $config->frequency),
                'time' => $config->runAtLabel(),
            ]);
        } else {
            $ageHours = (now()->getTimestamp() - $lastAt) / 3600;
            if ($ageHours > $maxHours) {
                $issue = __('Last backup was :ago — automatic backups appear to have stopped. The server cron (schedule:run) is likely missing or failing. Verify the cron line below is installed.', [
                    'ago' => Carbon::createFromTimestamp($lastAt)->diffForHumans(),
                ]);
            }
        }

        return ['healthy' => $issue === null, 'issue' => $issue, 'cron_line' => $cronLine];
    }

    /**
     * The paginated, filterable activity log.
     *
     * The log used to be a hard `limit(25)`, which meant an older failure
     * vanished the moment 25 newer rows accumulated — exactly when you'd want
     * to find it. Filters + paging + a CSV export make it an actual audit
     * surface, and each failure row carries an actionable hint.
     */
    private function activityLogs(): LengthAwarePaginator
    {
        $empty = fn () => new LengthAwarePaginator([], 0, 25, 1, [
            'path' => route('dashboard.backups.index'),
            'query' => request()->query(),
        ]);

        try {
            $query = BackupLog::query()->latest('id');

            if ($this->logFilterAction() !== '') {
                $query->where('action', $this->logFilterAction());
            }

            if ($this->logFilterStatus() !== '') {
                $query->where('status', $this->logFilterStatus());
            }

            $logs = $query->paginate(25, ['*'], 'log_page')->withQueryString();
        } catch (\Throwable) {
            $this->backupLogUnavailable = true;

            return $empty();
        }

        $logs->getCollection()->transform(function (BackupLog $log) {
            $log->hint = $log->status === 'failure' ? $this->failureHint($log->message) : null;

            return $log;
        });

        return $logs;
    }

    /** Whitelist the log action filter so a crafted query can't widen the scan. */
    private function logFilterAction(): string
    {
        $action = (string) request()->query('log_action', '');

        return in_array($action, self::LOG_ACTIONS, true) ? $action : '';
    }

    private function logFilterStatus(): string
    {
        $status = (string) request()->query('log_status', '');

        return in_array($status, ['success', 'failure'], true) ? $status : '';
    }

    /**
     * Turn a captured failure message into something actionable. The log
     * already tells the operator WHY a backup failed; this tells them what to
     * do about it without leaving the page.
     */
    private function failureHint(?string $message): ?string
    {
        if ($message === null || $message === '') {
            return null;
        }

        $haystack = mb_strtolower($message);

        return match (true) {
            str_contains($haystack, 'mysqldump') || str_contains($haystack, 'dump process failed') || str_contains($haystack, 'command not found') => __('mysqldump is missing or not on PATH. Install the MySQL client and/or point DUMP_BINARY_PATH at its directory, then run `php artisan backup:install` on the server for a full diagnosis.'),
            str_contains($haystack, 'not writable') || str_contains($haystack, 'permission denied') => __('storage/app is not writable by the web user. Re-own it to the account PHP runs as (`chown -R <site-user>:<site-user> storage`) — `php artisan backup:install` prints the exact commands and checks ownership.'),
            str_contains($haystack, 'invalid argument') => __('ZipArchive failed while writing the archive. This is almost always a missing/unwritable storage/app/backup-temp directory or a full disk — run `php artisan backup:install` to pinpoint it.'),
            str_contains($haystack, 'open_basedir') => __('PHP open_basedir excludes the system temp dir, which breaks ZipArchive. Point sys_temp_dir/upload_tmp_dir at storage/app/tmp, or widen open_basedir.'),
            str_contains($haystack, 'connection refused') || str_contains($haystack, 'access denied') || str_contains($haystack, 'unknown database') => __('The database connection failed. Verify the DB credentials in .env and that MySQL is reachable from the app server.'),
            str_contains($haystack, 'not found') || str_contains($haystack, 'no such file') => __('A required file or binary was not found. Run `php artisan backup:install` on the server — it checks mysqldump, the storage directories, open_basedir and the cron line.'),
            default => null,
        };
    }

    /**
     * Export the (filtered) activity log as CSV. Audit-logged: pulling the
     * operational history off-server is a significant act.
     */
    public function exportLogs(Request $request)
    {
        try {
            $query = BackupLog::query();

            if ($this->logFilterAction() !== '') {
                $query->where('action', $this->logFilterAction());
            }

            if ($this->logFilterStatus() !== '') {
                $query->where('status', $this->logFilterStatus());
            }

            $rows = $query->latest('id')->get();
        } catch (\Throwable) {
            return back()->withErrors(['backup' => __('The activity log is unavailable (the backup_logs table is missing).')]);
        }

        $this->writeAudit('backup.logs.export', [
            'count' => $rows->count(),
            'action' => $this->logFilterAction(),
            'status' => $this->logFilterStatus(),
        ]);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['id', 'created_at', 'action', 'status', 'path', 'user_id', 'message']);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->id,
                    $row->created_at?->toDateTimeString(),
                    $row->action,
                    $row->status,
                    $row->path,
                    $row->user_id,
                    $row->message,
                ]);
            }

            fclose($out);
        }, 'backup-activity-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Count .zip files on the backup disk — used to detect whether
     * `backup:run` actually produced a file (belt-and-suspenders alongside
     * the exit-code check).
     */
    private function countZips($disk): int
    {
        return collect($disk->allFiles())
            ->filter(fn ($p) => str_ends_with($p, '.zip'))
            ->count();
    }

    /**
     * Pre-flight check: ensure spatie's destination + temp + temp-archive
     * directories exist and are writable by the web/PHP user BEFORE we hand
     * off to `backup:run`. The classic shared-host failure is
     * "ZipArchive::close(): Invalid argument" — spatie silently can't write
     * the final zip because storage/app/backups is missing or not writable.
     * This turns that opaque error into an actionable one.
     */
    private function preflightWritable(): ?string
    {
        // storage/app/backups        — final zip destination (backups-local disk root).
        // storage/app/backup-temp    — spatie's temporary_directory: THIS is where the
        //                              zip is actually staged (BackupJob line 253), so a
        //                              missing/root-owned backup-temp makes close() fail
        //                              even when the destination looks writable.
        // storage/app/laravel-backup — spatie's DB-dump workdir.
        $paths = [
            'destination' => storage_path('app/backups'),
            'spatie-temp' => (string) config('backup.backup.temporary_directory', storage_path('app/backup-temp')),
            'dump-workdir' => storage_path('app/laravel-backup'),
        ];

        foreach ($paths as $label => $path) {
            if (! is_dir($path)) {
                @mkdir($path, 0o775, true);
            }
            if (! is_dir($path) || ! is_writable($path)) {
                return __('Backup :label directory (:path) is missing or not writable by the web server. Create it and fix ownership: chown -R <site-user>:<site-user> storage && chmod -R 775 storage.', ['label' => $label, 'path' => $path]);
            }
        }

        // Disk space / quota: open() can create a 0-byte file (success) but
        // close() fails when there is no room to write the actual content — a
        // classic shared-host "Invalid argument" on close().
        $free = @disk_free_space(storage_path('app'));
        if ($free !== false && $free < 50 * 1024 * 1024) {
            return __('Less than 50 MB free on the storage partition (:free MB). The backup needs room to stage the zip — free disk space or check the account quota.', ['free' => number_format($free / 1024 / 1024, 1)]);
        }

        // open_basedir / sys_temp_dir restriction: ZipArchive uses the system
        // temp dir internally; if PHP's open_basedir excludes it, close() can
        // fail even when the destination is writable.
        $openBasedir = ini_get('open_basedir');
        if ($openBasedir) {
            $systemTemp = sys_get_temp_dir();
            $allowed = array_map(fn ($p) => realpath(rtrim($p, DIRECTORY_SEPARATOR)), explode(PATH_SEPARATOR, $openBasedir));
            if (! in_array(realpath($systemTemp), $allowed, true)) {
                return __('PHP open_basedir excludes the system temp dir (:temp), which breaks ZipArchive. Set sys_temp_dir/upload_tmp_dir to a writable path inside the account (e.g. storage/app/tmp), or ask the host to widen open_basedir to include :temp.', ['temp' => $systemTemp]);
            }
        }

        return null;
    }

    /**
     * Pull the most informative failure line out of the captured artisan
     * output (mysqldump not found, connection refused, etc.). Returns null
     * when the output is empty / has no recognizable failure.
     */
    private function extractFailureReason(string $output): ?string
    {
        $output = trim($output);
        if ($output === '') {
            return null;
        }

        $lines = array_values(array_filter(array_map('trim', explode("\n", $output))));

        foreach ($lines as $line) {
            if (preg_match('/(not found|no such file|command not found|the dump failed|dumping database.*fail|connection refused|access denied|unknown database|backup failed|could not|denied|error)/i', $line)) {
                return mb_strlen($line) > 500 ? mb_substr($line, 0, 500).'…' : $line;
            }
        }

        return null;
    }

    /**
     * Write a backup_logs row and return the redirect with the right flash.
     * `$key` is the errors bag key; `$flash false` skips the redirect (used
     * by download which returns a stream, not a redirect).
     */
    private function recordLog(string $action, string $status, ?string $message = null, ?string $path = null, string $key = 'backup', bool $flash = true, ?string $output = null): ?RedirectResponse
    {
        try {
            BackupLog::create([
                'action' => $action,
                'status' => $status,
                'path' => $path,
                // Keep the diagnostic output alongside the short message so the
                // activity log shows the real reason (mysqldump path, etc.).
                'message' => $output && $message ? $message."\n\n".$output : ($message ?: $output),
                'user_id' => request()->user()?->id,
            ]);
        } catch (\Throwable $e) {
            // Logging must never break the operation it logs — e.g. a fresh
            // deploy whose `php artisan migrate` hasn't created backup_logs yet.
            // The Backup now / download / delete flow still completes.
            Log::warning('backup_logs write failed: '.$e->getMessage());
        }

        if (! $flash) {
            return null;
        }

        return $status === 'success'
            ? back()->with('success', $message)
            : back()->withErrors([$key => $message]);
    }

    /**
     * Shared audit helper for the Backups surface. A restore / download is not
     * a model write, so the Auditable trait does not fire — this writes a
     * manual OwenIt\Auditing\Models\Audit row per research Security Domain.
     */
    private function writeAudit(string $event, array $payload, ?Request $request = null): void
    {
        $request ??= request();

        $audit = new Audit;
        $audit->fill([
            'user_type' => $request->user() ? get_class($request->user()) : null,
            'user_id' => $request->user()?->id,
            'event' => $event,
            'auditable_type' => 'backup', // sentinel value (not a real model)
            'auditable_id' => 0,
            'new_values' => $payload,
            'url' => $request->fullUrl(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'tags' => 'backup',
        ])->save();
    }

    /**
     * Resolve the configured spatie backup destination disk.
     *
     * spatie/laravel-backup v10 nests the destination config under a top-level
     * `backup` key in config/backup.php — i.e. the actual disk list lives at
     * `backup.backup.destination.disks.0`. This matches the key used by
     * BackupRestoreService::downloadAndExtract() — single source of truth.
     */
    private function backupDisk(): string
    {
        return (string) config('backup.backup.destination.disks.0', 'backups-local');
    }

    /**
     * Defense-in-depth path guard (WR-05). Flysystem normalizes `..` segments,
     * but reject traversal / absolute patterns explicitly so a malformed
     * request never reaches the disk layer.
     */
    private function guardPath(string $path): void
    {
        abort_if(
            str_contains($path, '..') || str_starts_with($path, '/') || str_starts_with($path, '\\'),
            404,
        );
    }

    /**
     * Resolve the active mess name (the typed-confirm target).
     * Plan 06-03 / research Open Question #3 LOCKED: the target is the active
     * mess's `name` column. Mess exposes activeId(); we resolve the model.
     */
    public static function activeMessName(): ?string
    {
        $id = Mess::activeId();

        return $id !== null ? Mess::find($id)?->name : null;
    }
}
