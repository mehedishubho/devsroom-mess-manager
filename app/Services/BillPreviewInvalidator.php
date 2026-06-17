<?php

namespace App\Services;

use App\Models\Mess;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class BillPreviewInvalidator
{
    public function __construct(private readonly BillPreviewService $service) {}

    public function forDate(?string $date): void
    {
        if ($date === null || $date === '') {
            return;
        }

        $messId = Mess::activeId();
        if ($messId === null) {
            return;
        }

        try {
            $carbon = Carbon::parse($date);
        } catch (\Throwable) {
            return;
        }

        Cache::forget($this->service->cacheKey($messId, (int) $carbon->year, (int) $carbon->month));
    }

    public function forToday(): void
    {
        $this->forDate(now()->toDateString());
    }
}
