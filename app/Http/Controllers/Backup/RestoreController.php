<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backup;

use App\Http\Controllers\Controller;
use App\Http\Requests\Backup\RestoreRequest;
use App\Models\BackupLog;
use App\Services\BackupRestoreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use OwenIt\Auditing\Models\Audit;

/**
 * D-03 the guarded one-click FULL RESTORE surface.
 *
 * Pattern mirrors the project's MonthCloseController: a Form Request
 * (RestoreRequest) + a service-layer orchestration (BackupRestoreService from
 * Plan 06-02). The controller contains NO restore logic itself (T-06-02-08) —
 * it binds input, writes audit rows, and surfaces the service's result.
 *
 * Threat model (Plan 06-03):
 *   T-06-03-07 Repudiation — every restore writes a tamper-evident manual Audit
 *              row on BOTH success (event='backup.restore') AND failure
 *              (event='backup.restore.failed').
 *   T-06-03-08 DoS — BackupRestoreService owns the down + queue:restart calls;
 *              the controller's try/catch is the second layer (the service's
 *              finally always calls 'up').
 */
class RestoreController extends Controller
{
    public function __construct(private readonly BackupRestoreService $service) {}

    public function show(string $path): View
    {
        $this->guardPath($path);
        $disk = Storage::disk((string) config('backup.backup.destination.disks.0', 'backups'));
        abort_unless($disk->exists($path), 404);

        return view('dashboard.backups.restore', [
            'path' => $path,
            'expectedMessName' => BackupController::activeMessName(),
        ]);
    }

    public function store(RestoreRequest $request): RedirectResponse
    {
        $path = (string) $request->validated('path');

        $this->guardPath($path);

        try {
            $this->service->restoreFromDisk($path);

            // D-03 + research Security Domain: every restore writes a
            // tamper-evident audit row.
            $this->writeAudit('backup.restore', [
                'path' => $path,
                'mess_name_confirmed' => true,
                'ip' => $request->ip(),
            ], $request);

            $this->recordLog('restore', 'success', __('Restore completed.'), $path);

            return redirect()
                ->route('dashboard.backups.index')
                ->with('success', __('Restore completed. The app is back online.'));
        } catch (\Throwable $e) {
            Log::error('Backup restore failed', ['exception' => $e]);

            // Even failures get an audit row (a failed restore is a
            // significant event). T-06-03-07.
            $this->writeAudit('backup.restore.failed', [
                'path' => $path,
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ], $request);

            // Surface the REAL error (mysql missing, dump not found, etc.) in
            // both the activity log and the flash — "check logs" is not
            // actionable on shared hosting.
            $message = $e->getMessage();
            $this->recordLog('restore', 'failure', $message, $path);

            // BackupRestoreService::restoreFromDisk (Plan 06-02) ALWAYS calls
            // Artisan::call('up') in its finally block — the app stays live
            // even when the restore itself failed.
            return back()->withErrors(['restore' => __('Restore failed. App is back online. Reason: :msg', ['msg' => $message])]);
        }
    }

    /**
     * Write a backup_logs row (restores belong in the same activity log as
     * backups). Tolerates a missing backup_logs table.
     */
    private function recordLog(string $action, string $status, ?string $message = null, ?string $path = null): void
    {
        try {
            BackupLog::create([
                'action' => $action,
                'status' => $status,
                'path' => $path,
                'message' => $message,
                'user_id' => request()->user()?->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('backup_logs write failed: '.$e->getMessage());
        }
    }

    /**
     * Manual OwenIt\Auditing\Models\Audit row (restore is not a model write,
     * so the Auditable trait does not fire). Research Security Domain.
     */
    private function writeAudit(string $event, array $payload, RestoreRequest $request): void
    {
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
    }

    /**
     * Defense-in-depth path guard (WR-05). Flysystem normalizes `..` segments,
     * but reject traversal / absolute patterns explicitly so a malformed
     * request never reaches the disk layer or the destructive service.
     */
    private function guardPath(string $path): void
    {
        abort_if(
            str_contains($path, '..') || str_starts_with($path, '/') || str_starts_with($path, '\\'),
            404,
        );
    }
}
