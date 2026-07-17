<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backup;

use App\Http\Controllers\Controller;
use App\Http\Requests\Backup\UpdateBackupConfigRequest;
use App\Models\BackupConfig;
use App\Models\BackupLog;
use App\Models\Mess;
use App\Models\RestoreTest;
use App\Support\BackupDestinations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use OwenIt\Auditing\Models\Audit;

/**
 * D-03 super-admin Backups UI (research Pattern 3 — custom controller).
 *
 * The Backups page is the single surface for backups: the backup file list
 * (download / restore / delete), the inline Configure form (schedule +
 * retention + per-provider storage toggles), the restore-test health badge,
 * and a backup activity log so a failed `backup:run` is visible instead of
 * silently swallowed. The destructive restore lives in RestoreController
 * (which orchestrates BackupRestoreService). This controller MUST NOT contain
 * restore logic itself (T-06-02-08).
 *
 * Every zip download is audit-logged (T-06-03-05 PII leak prevention) via a
 * manual OwenIt\Auditing\Models\Audit row keyed by event='backup.download'.
 */
class BackupController extends Controller
{
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

    public function runRestoreTest(): RedirectResponse
    {
        try {
            $exitCode = (int) Artisan::call('backup:restore-test');
            $output = (string) Artisan::output();
        } catch (\Throwable $e) {
            return $this->recordLog('restore_test', 'failure', $e->getMessage(), key: 'restore-test');
        }

        if ($exitCode !== 0) {
            $reason = $this->extractFailureReason($output)
                ?: __('Restore-test failed (exit code :code).', ['code' => $exitCode]);

            return $this->recordLog('restore_test', 'failure', $reason, key: 'restore-test', output: $output);
        }

        return $this->recordLog('restore_test', 'success', __('Restore-test completed — see the health badge.'), key: 'restore-test', output: $output);
    }

    /**
     * Super-admin-only zip download. Access-logged via a manual Audit row
     * (T-06-03-05 — PII leak prevention: every download leaves a trail).
     */
    public function download(string $path)
    {
        $this->guardPath($path);
        $disk = Storage::disk($this->backupDisk());
        abort_unless($disk->exists($path), 404);

        $this->writeAudit('backup.download', ['path' => $path]);
        $this->recordLog('download', 'success', path: $path, flash: false);

        return response()->streamDownload(fn () => $disk->readStream($path), basename($path));
    }

    /**
     * Delete a single backup archive from the local disk. Lighter-weight than
     * a restore (only removes one zip), so a JS confirm on the button is enough
     * — no typed-mess-name gate. Audit-logged (T-06-03-05).
     */
    public function destroy(string $path): RedirectResponse
    {
        $this->guardPath($path);
        $disk = Storage::disk($this->backupDisk());

        if (! $disk->exists($path)) {
            return back()->withErrors(['backup' => __('Backup not found.')]);
        }

        $disk->delete($path);
        $this->writeAudit('backup.delete', ['path' => $path]);

        return $this->recordLog('delete', 'success', __('Backup deleted.'), path: $path);
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

        BackupConfig::updateOrCreate(['id' => 1], [
            'frequency' => $data['frequency'],
            'run_at' => $data['run_at'],
            'keep_all_days' => $data['keep_all_days'],
            'max_mb' => $data['max_mb'],
            'gdrive_backup' => (bool) ($data['gdrive_backup'] ?? false),
            'gdrive_uploads' => (bool) ($data['gdrive_uploads'] ?? false),
            'r2_backup' => (bool) ($data['r2_backup'] ?? false),
            'r2_uploads' => (bool) ($data['r2_uploads'] ?? false),
        ]);
        BackupConfig::flushCache();

        try {
            Artisan::call('config:clear');
        } catch (\Throwable) {
            // Non-fatal: a failed config:clear must not block the save.
        }

        return redirect()
            ->route('dashboard.backups.index')
            ->with('success', __('Backup configuration updated.'));
    }

    /**
     * Shared view data for the Backups page (index + the configure alias).
     */
    private function indexData(): array
    {
        $disk = Storage::disk($this->backupDisk());

        $backups = collect($disk->allFiles())
            ->filter(fn ($p) => str_ends_with($p, '.zip'))
            ->map(fn ($p) => [
                'path' => $p,
                'size' => $disk->size($p),
                'last_modified' => $disk->lastModified($p),
            ])
            ->sortByDesc('last_modified')
            ->values();

        return [
            'backups' => $backups,
            'latestRestoreTest' => RestoreTest::latest('id')->first(),
            'config' => BackupConfig::current(),
            'spacesConfigured' => BackupDestinations::spacesConfigured(),
            'gdriveConfigured' => BackupDestinations::gdriveConfigured(),
            'r2Configured' => BackupDestinations::r2Configured(),
            'backupLogs' => BackupLog::latest('id')->limit(25)->get(),
        ];
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
        BackupLog::create([
            'action' => $action,
            'status' => $status,
            'path' => $path,
            // Keep the diagnostic output alongside the short message so the
            // activity log shows the real reason (mysqldump path, etc.).
            'message' => $output && $message ? $message."\n\n".$output : ($message ?: $output),
            'user_id' => request()->user()?->id,
        ]);

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
        return (string) config('backup.backup.destination.disks.0', 'backups');
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
