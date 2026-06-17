<?php

namespace App\Jobs;

use App\Services\MonthCloseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
}
