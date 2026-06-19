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

        // D-05: best-effort post-close backup. Captures the highest-value
        // immutable snapshot (monthly_closings + monthly_member_summaries)
        // immediately rather than waiting for the nightly run. Runs ONLY on a
        // successful close — if close() threw above, this line is never reached.
        //
        // CR-02: this previously lived in a dead after() method. Laravel's queue
        // runtime invokes handle() + failed() only — there is NO after() job
        // lifecycle hook, so the post-close backup never fired. Inlining it at
        // the tail of handle() is the correct, queue-invoked location.
        //
        // T-06-02-07: a backup failure MUST NEVER break the close path. The
        // close already succeeded; the backup is best-effort, so swallow + log.
        try {
            Artisan::call('backup:run', ['--only-db' => true]);
        } catch (Throwable $e) {
            Log::warning('Post-close backup failed', ['exception' => $e]);
        }
    }
}
