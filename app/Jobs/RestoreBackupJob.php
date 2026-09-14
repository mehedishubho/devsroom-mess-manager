<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Listeners\LogScheduledBackupActivity;
use App\Models\BackupLog;
use App\Models\User;
use App\Services\BackupRestoreService;
use App\Services\BackupRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use OwenIt\Auditing\Models\Audit;

/**
 * D-06 destructive full restore, moved off the request thread.
 *
 * The restore itself is unchanged (BackupRestoreService still does
 * down → queue:restart → import → files → verify, with `up` in a finally).
 * What changed is WHERE it runs: doing it inline meant a gateway/PHP timeout
 * could hard-kill the worker before the finally block, stranding the whole
 * site in maintenance mode with no way back in except SSH.
 *
 * Three independent guarantees now return the app to live:
 *   1. BackupRestoreService's own `finally { Artisan::call('up') }`.
 *   2. This job's explicit `Artisan::call('up')` after a successful import.
 *   3. `failed()` — invoked when the worker kills the job — which also calls
 *      `up` before recording the failure.
 * Plus a manual "Bring the app back online" action on the Backups page as the
 * last resort.
 *
 * $tries = 1: a restore is destructive and absolutely must not be retried by
 * the queue after a failure.
 */
class RestoreBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1200;

    public function __construct(
        private readonly string $path,
        private readonly ?int $logId = null,
        private readonly ?int $userId = null,
        private readonly ?string $ip = null,
        private readonly ?string $userAgent = null,
    ) {}

    public function handle(BackupRestoreService $service, BackupRunner $runner): void
    {
        // A safety snapshot first, so an operator who restores the wrong file
        // has something to go back to. Best-effort: if it cannot be taken the
        // restore still proceeds (the operator already confirmed destructively).
        $safety = LogScheduledBackupActivity::muted(fn () => $runner->run());

        try {
            $service->restoreFromDisk($this->path);
        } catch (\Throwable $e) {
            Log::error('Backup restore failed', ['exception' => $e]);
            $this->writeAudit('backup.restore.failed', [
                'path' => $this->path,
                'error' => $e->getMessage(),
            ]);
            $this->finish('failure', $e->getMessage());

            return;
        }

        // Belt-and-suspenders: the service already did this in its finally.
        try {
            Artisan::call('up');
        } catch (\Throwable) {
        }

        $this->writeAudit('backup.restore', [
            'path' => $this->path,
            'mess_name_confirmed' => true,
            'safety_backup' => $safety['ok'] ?? false,
        ]);

        $this->finish('success', __('Restore completed. The app is back online.'));
    }

    public function failed(\Throwable $e): void
    {
        try {
            Artisan::call('up');
        } catch (\Throwable) {
        }

        $this->writeAudit('backup.restore.failed', [
            'path' => $this->path,
            'error' => $e->getMessage(),
            'stage' => 'job_failed',
        ]);

        $this->finish('failure', $e->getMessage());
    }

    private function finish(string $status, string $message): void
    {
        $log = $this->logId !== null ? BackupLog::find($this->logId) : null;

        if ($log) {
            $log->update(['status' => $status, 'message' => $message]);

            return;
        }

        BackupLog::record('restore', $status, $message, $this->path, $this->userId);
    }

    /** Manual, tamper-evident Audit row (a restore is not a model write). */
    private function writeAudit(string $event, array $payload): void
    {
        try {
            $audit = new Audit;
            $audit->fill([
                'user_type' => $this->userId !== null ? User::class : null,
                'user_id' => $this->userId,
                'event' => $event,
                'auditable_type' => 'backup',
                'auditable_id' => 0,
                'new_values' => array_merge($payload, ['ip' => $this->ip]),
                'url' => null,
                'ip_address' => $this->ip,
                'user_agent' => $this->userAgent,
                'tags' => 'backup',
            ])->save();
        } catch (\Throwable $e) {
            // An audit failure must never mask the restore outcome.
            Log::warning('backup audit write failed: '.$e->getMessage());
        }
    }
}
