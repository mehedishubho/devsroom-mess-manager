<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * ChartBucketingService — D-08 auto-bucket rule.
 *
 * Resolves the granularity for a chart series based on the span of the
 * selected date range (RESEARCH Pattern 7):
 *
 *  - range ≤ ~60 days  → daily buckets   (one point per day)
 *  - range ≤ ~365 days → weekly buckets  (one point per ISO week)
 *  - range > 365 days  → monthly buckets (one point per month)
 *
 * The chosen granularity is applied in DashboardService::mealTrend() /
 * expenseTrend() / paymentTrend() to determine the GROUP BY clause and
 * the bucket-fill loop. Returns both a machine `granularity` (day/week/
 * month) and a human `step` label for debugging.
 */
class ChartBucketingService
{
    /**
     * @return array{granularity:string,step:string}
     */
    public function bucket(Carbon $from, Carbon $to): array
    {
        $days = (int) $from->diffInDays($to);

        if ($days <= 60) {
            return ['granularity' => 'day', 'step' => '1 day'];
        }
        if ($days <= 365) {
            return ['granularity' => 'week', 'step' => '1 week'];
        }

        return ['granularity' => 'month', 'step' => '1 month'];
    }
}
