<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backup;

use App\Http\Controllers\Controller;
use App\Http\Requests\Backup\UpdateBackupConfigRequest;
use App\Models\BackupConfig;
use App\Models\BackupLog;
use App\Models\Mess;
use App\Support\BackupDestinations;
use App\Support\CloudBackupCredentials;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
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

        foreach (['gdrive_client_secret', 'gdrive_refresh_token', 'r2_secret'] as $secret) {
            if (filled($data[$secret] ?? null)) {
                $payload[$secret] = $data[$secret];
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
     * Shared view data for the Backups page (index + the configure alias).
     */
    private function indexData(): array
    {
        $disk = Storage::disk($this->backupDisk());
        $config = BackupConfig::current();

        $backups = collect($disk->allFiles())
            ->filter(fn ($p) => str_ends_with($p, '.zip'))
            ->map(fn ($p) => [
                'path' => $p,
                'size' => $disk->size($p),
                'last_modified' => $disk->lastModified($p),
            ])
            ->sortByDesc('last_modified')
            ->values();

        $scheduler = $this->schedulerHealth($backups);

        return [
            'backups' => $backups,
            'config' => $config,
            // "Configured" reflects EITHER a DB-stored value (UI) or the env
            // fallback — both are legitimate sources.
            'gdriveConfigured' => BackupDestinations::gdriveConfigured() || CloudBackupCredentials::gdriveConfiguredFromDb(),
            'r2Configured' => BackupDestinations::r2Configured() || CloudBackupCredentials::r2ConfiguredFromDb(),
            // Secret inputs render blank (never pre-filled); these flags drive
            // the small "saved ✓" badge so the operator knows a value is stored.
            'gdriveSecretSaved' => filled($config->gdrive_client_secret),
            'gdriveRefreshSaved' => filled($config->gdrive_refresh_token),
            'r2SecretSaved' => filled($config->r2_secret),
            // The activity log is non-critical — a fresh deploy that hasn't run
            // `php artisan migrate` yet (backup_logs table missing) must NOT 500
            // the whole Backups page and lock the super-admin out of configuring.
            'backupLogs' => $this->safeRecentLogs(),
            'backupLogUnavailable' => $this->backupLogUnavailable,
            // Scheduler health — surfaces a missing server cron (the #1 reason
            // "backups are configured but none appear"). See schedulerHealth().
            'schedulerHealthy' => $scheduler['healthy'],
            'schedulerIssue' => $scheduler['issue'],
            'schedulerCronLine' => $scheduler['cron_line'],
        ];
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
     * Read the latest backup log rows, tolerating a missing backup_logs table
     * (fresh deploy that hasn't run `php artisan migrate`). Sets the
     * $backupLogUnavailable flag so the view can show a "run migrate" banner.
     *
     * @return Collection
     */
    private function safeRecentLogs()
    {
        try {
            return BackupLog::latest('id')->limit(25)->get();
        } catch (\Throwable) {
            $this->backupLogUnavailable = true;

            return collect();
        }
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
