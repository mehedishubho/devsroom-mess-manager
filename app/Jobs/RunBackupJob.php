<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Listeners\LogScheduledBackupActivity;
use App\Models\BackupLog;
use App\Services\BackupRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs a manual "Backup now" in the background.
 *
 * The dump + zip + mirror upload easily outlives a PHP request on a shared
 * host, so an inline run could be killed mid-flight by the gateway and leave
 * a half-written archive with no record of what happened. Queued (the app
 * already uses the `database` queue for CloseMonthJob), the run survives the
 * browser and the operator gets a `running` row immediately.
 *
 * $tries = 1: a backup must never be retried blindly — a retry could race the
 * first attempt's still-open zip.
 */
class RunBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    /**
     * @param  int|null  $logId  the `running` activity row to update (created
     *                           by the controller when the operator clicked).
     */
    public function __construct(private readonly ?int $logId = null) {}

    public function handle(BackupRunner $runner): void
    {
        // Muted: this job runs inside a console worker, so the shared listener
        // would treat the run as "scheduled" and write a second row.
        $result = LogScheduledBackupActivity::muted(fn () => $runner->run());

        $message = $result['output'] !== ''
            ? $result['message']."\n\n".$result['output']
            : $result['message'];

        $this->finish($result['ok'] ? 'success' : 'failure', $message);
    }

    /**
     * A worker timeout / hard kill (SIGKILL) skips handle()'s own error path,
     * so the row would stay `running` forever — mark it failed here.
     */
    public function failed(\Throwable $e): void
    {
        $this->finish('failure', $e->getMessage());
    }

    private function finish(string $status, string $message): void
    {
        $log = $this->logId !== null ? BackupLog::find($this->logId) : null;

        if ($log) {
            $log->update(['status' => $status, 'message' => $message]);

            return;
        }

        BackupLog::record('backup', $status, $message);
    }
}
