<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backup;

use App\Http\Controllers\Controller;
use App\Http\Requests\Backup\RestoreRequest;
use App\Http\Requests\Backup\UploadRestoreRequest;
use App\Jobs\RestoreBackupJob;
use App\Models\BackupLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use OwenIt\Auditing\Models\Audit;

/**
 * D-03 the guarded full-restore surface.
 *
 * Pattern mirrors the project's MonthCloseController: a Form Request
 * (RestoreRequest / UploadRestoreRequest) validates + confirms, this
 * controller binds input, writes an audit row, and hands the destructive work
 * to RestoreBackupJob. The controller contains NO restore logic itself
 * (T-06-02-08) and no longer RUNS the restore on the request thread either —
 * an inline restore could be killed by a gateway timeout before its
 * `finally { artisan up }`, stranding the whole site in maintenance mode.
 *
 * Threat model (Plan 06-03):
 *   T-06-03-07 Repudiation — every restore writes a tamper-evident manual
 *              Audit row. Those rows now move into the job (which may run in a
 *              different process), with the actor's id/ip captured at dispatch.
 *   T-06-03-08 DoS — BackupRestoreService owns the down + queue:restart calls;
 *              the job's try/catch + failed() are the second and third layers.
 */
class RestoreController extends Controller
{
    public function show(Request $request): View
    {
        $path = (string) $request->query('path', '');
        $this->guardPath($path);
        $disk = Storage::disk((string) config('backup.backup.destination.disks.0', 'backups-local'));
        abort_unless($path !== '' && $disk->exists($path), 404);

        return view('dashboard.backups.restore', [
            'path' => $path,
            'expectedMessName' => BackupController::activeMessName(),
        ]);
    }

    /** Queue a restore of an archive already on the backups disk. */
    public function store(RestoreRequest $request): RedirectResponse
    {
        $path = (string) $request->validated('path');
        $this->guardPath($path);

        return $this->queueRestore($request, $path);
    }

    /**
     * Disaster recovery: restore from an archive the operator uploads.
     *
     * Off-site backups are only useful if they can be brought back when the
     * server (and therefore its local archive list) is gone. The upload lands
     * on the backups disk so it behaves like any other archive from then on.
     */
    public function upload(UploadRestoreRequest $request): RedirectResponse
    {
        $file = $request->file('file');
        $name = 'uploaded-'.now()->format('Ymd-His').'-'.substr(bin2hex(random_bytes(3)), 0, 6).'.zip';

        $disk = Storage::disk((string) config('backup.backup.destination.disks.0', 'backups-local'));

        try {
            $stream = fopen($file->getRealPath(), 'r');
            try {
                $disk->writeStream($name, $stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => __('Could not store the uploaded archive: :msg', ['msg' => $e->getMessage()])]);
        }

        $this->writeAudit('backup.restore.upload', [
            'path' => $name,
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
        ], $request);

        return $this->queueRestore($request, $name);
    }

    /**
     * Write the `running` activity row and dispatch the destructive job.
     * The row is updated in place by the job, so the Activity log shows
     * progress without a polling endpoint.
     */
    private function queueRestore(Request $request, string $path): RedirectResponse
    {
        $log = BackupLog::record(
            'restore',
            'running',
            __('Queued — the app enters maintenance mode while the restore runs.'),
            $path,
            $request->user()?->id,
        );

        RestoreBackupJob::dispatch(
            $path,
            $log?->id,
            $request->user()?->id,
            $request->ip(),
            $request->userAgent(),
        );

        return redirect()
            ->route('dashboard.backups.index')
            ->with('success', __('Restore queued. The app will briefly enter maintenance mode; the Activity log shows the outcome.'));
    }

    /**
     * Manual OwenIt\Auditing\Models\Audit row (an upload/restore is not a model
     * write, so the Auditable trait does not fire). Research Security Domain.
     */
    private function writeAudit(string $event, array $payload, Request $request): void
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
