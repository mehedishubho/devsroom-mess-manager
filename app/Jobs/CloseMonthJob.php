<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\MonthCloseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;

class CloseMonthJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public readonly int $year,
        public readonly int $month,
        public readonly int $closedBy,
    ) {}

    public function handle(MonthCloseService $service): void
    {
        $service->close($this->year, $this->month, $this->closedBy);
    }

    /**
     * D-05: fires only on successful close. Captures the highest-value
     * immutable snapshot (monthly_closings + monthly_member_summaries)
     * immediately rather than waiting for the nightly run.
     *
     * Research Pattern 6a (lifecycle hook) is preferred over an Eloquent
     * event listener file — no DispatchBackupAfterClose listener is created.
     *
     * T-06-02-07: a backup failure MUST NEVER break the close path. The
     * close itself already succeeded; the backup is best-effort.
     */
    public function after(): void
    {
        try {
            Artisan::call('backup:run', ['--only-db' => true]);
        } catch (Throwable $e) {
            Log::warning('Post-close backup failed', ['exception' => $e]);
        }
    }

    /**
     * D-05: do NOT back up a half-closed state. No-op on failure.
     */
    public function failed(Throwable $exception): void
    {
        // Intentionally empty. A failed close must NOT trigger a backup.
    }
}
