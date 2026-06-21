<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backup;

use App\Http\Controllers\Controller;
use App\Http\Requests\Backup\UpdateBackupConfigRequest;
use App\Models\BackupConfig;
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
 * Mirrors the project's AuditController custom-controller style. This controller
 * is the read + trigger surface for the backup system; the destructive restore
 * lives in RestoreController (which orchestrates BackupRestoreService). It MUST
 * NOT contain restore logic itself (T-06-02-08).
 *
 * Every zip download is audit-logged (T-06-03-05 PII leak prevention) via a
 * manual OwenIt\Auditing\Models\Audit row keyed by event='backup.download'.
 */
class BackupController extends Controller
{
    public function index(): View
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

        $latestRestoreTest = RestoreTest::latest('id')->first();

        $config = BackupConfig::current();
        $spacesConfigured = BackupDestinations::spacesConfigured();

        return view('dashboard.backups.index', compact('backups', 'latestRestoreTest', 'config', 'spacesConfigured'));
    }

    public function runNow(): RedirectResponse
    {
        // Ad-hoc backup. Runs synchronously here for simplicity; the UI shows
        // a "completed" flash on redirect. For very large messes, this could
        // dispatch a queued job instead — but the spec is one small mess.
        try {
            Artisan::call('backup:run');

            return back()->with('success', __('Backup completed.'));
        } catch (\Throwable $e) {
            return back()->withErrors(['backup' => __('Backup failed: :msg', ['msg' => $e->getMessage()])]);
        }
    }

    public function runRestoreTest(): RedirectResponse
    {
        try {
            Artisan::call('backup:restore-test');

            return back()->with('success', __('Restore-test completed — see the health badge.'));
        } catch (\Throwable $e) {
            return back()->withErrors(['restore-test' => __('Restore-test failed: :msg', ['msg' => $e->getMessage()])]);
        }
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

        return back()->with('success', __('Backup deleted.'));
    }

    /**
     * Show the Configure form: backup schedule (frequency + time) and
     * retention/rotation (keep-all-days + storage cap).
     */
    public function edit(): View
    {
        $config = BackupConfig::current();
        $spacesConfigured = BackupDestinations::spacesConfigured();

        return view('dashboard.backups.configure', compact('config', 'spacesConfigured'));
    }

    /**
     * Save the Configure form. Persists the singleton row, then clears the
     * config cache so the new schedule + retention take effect immediately
     * (the scheduler reads BackupConfig at each schedule:run; backup:purge
     * reads it at runtime; spatie picks up the refreshed destination list).
     */
    public function update(UpdateBackupConfigRequest $request): RedirectResponse
    {
        $data = $request->validated();

        BackupConfig::updateOrCreate(['id' => 1], [
            'frequency' => $data['frequency'],
            'run_at' => $data['run_at'],
            'keep_all_days' => $data['keep_all_days'],
            'max_mb' => $data['max_mb'],
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
     * `backup.backup.destination.disks.0` (the plan's research code example used
     * the wrong `backup.destination.disks.0` key). This matches the key used by
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
