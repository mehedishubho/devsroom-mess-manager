<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\MealEntry;
use App\Models\MealOffRequest;
use App\Models\Member;
use App\Models\Mess;
use App\Models\Payment;
use App\Support\ExpenseKind;
use App\Support\MealOffStatus;
use App\Support\MealType;
use App\Support\MemberStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * DashboardService — manager-side dashboard (DASH-01/02/03).
 *
 *  - pendingMealOffCount(): pending MealOffRequest count for the alert banner.
 *  - managerCards(): DASH-01 cards. Bill-derived cards (meal_rate,
 *    total_member_balance) reuse the bill-preview cache via BillPreviewService::preview()
 *    (NO new key). Count-based cards (total_members, today_meals, monthly_expenses)
 *    use the composite key dash:counts:{mess_id}:{YYYY}-{MM} (1h TTL).
 *  - mealTrend/expenseTrend/paymentTrend(): D-05/D-06/D-07 series with D-08
 *    auto-bucketing (day ≤ 60d / week ≤ 365d / month otherwise).
 *
 * Cache invalidation: the AppServiceProvider::invalidateForModel() listener
 * (extended in Plan 04-03) forgets dash:counts:{mess_id}:{YYYY}-{MM} on the
 * SAME saved/deleted events that already forget the bill-preview key —
 * preserving the < 2s refresh contract (DASH-05). All cache keys are scoped
 * by Mess::activeId() so cross-mess bleed is impossible (T-04-03-01).
 *
 * Open Question #3 LOCKED: "Today's Meals" + Meal Trend EXCLUDE guest meals
 * (mirrors BillPreviewService::mealTotals() — only regular B/L/D booleans via
 * MealType::value(), not guest_meals.charge_amount).
 *
 * Open Question #5 LOCKED: "Monthly Expenses" card = total bazar + fixed.
 * The Expense Trend chart (D-06) stays bazar-only — separate decision.
 */
class DashboardService
{
    public function __construct(
        private readonly BillPreviewService $preview,
        private readonly ChartBucketingService $bucketing = new ChartBucketingService,
    ) {}

    /**
     * Pending MealOffRequest count for the DASH-03 alert banner.
     * Not cached — a single COUNT(*) is cheap and the banner is the one
     * element the manager most wants to be live.
     */
    public function pendingMealOffCount(): int
    {
        $messId = Mess::activeId();
        if ($messId === null) {
            return 0;
        }

        return (int) MealOffRequest::query()
            ->where('mess_id', $messId)
            ->where('status', MealOffStatus::PENDING)
            ->count();
    }

    /**
     * The 6 DASH-01 manager cards.
     *
     * @return array{
     *     total_members:int,
     *     today_meals:float,
     *     monthly_expenses:float,
     *     meal_rate:float,
     *     total_member_balance:float,
     * }
     */
    public function managerCards(): array
    {
        $messId = Mess::activeId();
        if ($messId === null) {
            return [
                'total_members' => 0,
                'today_meals' => 0.0,
                'monthly_expenses' => 0.0,
                'meal_rate' => 0.0,
                'total_member_balance' => 0.0,
            ];
        }

        $now = now();
        $key = $this->countsCacheKey($messId, $now);

        $counts = Cache::remember($key, now()->addHour(), function () use ($messId, $now) {
            return [
                'total_members' => (int) Member::query()
                    ->where('mess_id', $messId)
                    ->where('status', MemberStatus::ACTIVE)
                    ->count(),
                'today_meals' => $this->todayMealTotal($messId, $now),
                'monthly_expenses' => (float) Expense::query()
                    ->where('mess_id', $messId)
                    ->whereBetween('date', [
                        $now->copy()->startOfMonth()->toDateString(),
                        $now->copy()->endOfMonth()->toDateString(),
                    ])
                    ->sum('amount'),
            ];
        });

        // Bill-derived cards reuse the bill-preview:{mess}:{YYYY}-{MM} cache
        // (no new key) — see BillPreviewService::preview().
        $preview = $this->preview->preview($now->year, $now->month);
        $members = $preview['members'] ?? [];

        return [
            'total_members' => $counts['total_members'],
            'today_meals' => $counts['today_meals'],
            'monthly_expenses' => $counts['monthly_expenses'],
            'meal_rate' => (float) ($preview['meal_rate'] ?? 0.0),
            'total_member_balance' => (float) collect($members)->sum(fn ($m) => ($m['advance_balance'] ?? 0) - ($m['due_balance'] ?? 0)),
        ];
    }

    /**
     * Meal Trend (D-05): daily meal count across the mess.
     * EXCLUDES guest meals (Open Question #3 LOCKED — mirrors
     * BillPreviewService::mealTotals()). Granularity per D-08.
     *
     * @return array{labels:array<int,string>,values:array<int,float>}
     */
    public function mealTrend(int $messId, Carbon $from, Carbon $to): array
    {
        $b = MealType::value(MealType::BREAKFAST);
        $l = MealType::value(MealType::LUNCH);
        $d = MealType::value(MealType::DINNER);
        $bucket = $this->bucketing->bucket($from, $to);

        $rows = MealEntry::query()
            ->where('mess_id', $messId)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw(
                'date, '
                ."SUM((CASE WHEN breakfast THEN {$b} ELSE 0 END) "
                ."+ (CASE WHEN lunch THEN {$l} ELSE 0 END) "
                ."+ (CASE WHEN dinner THEN {$d} ELSE 0 END)) AS total"
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy(fn ($r) => Carbon::parse($r->date)->toDateString());

        $out = $this->fillBucketAxis($from, $to, $bucket['granularity'], function (string $dateKey) use ($rows) {
            $row = $rows[$dateKey] ?? null;

            return $row ? (float) $row->total : 0.0;
        });

        return $out;
    }

    /**
     * Expense Trend (D-06): monthly BAZAR total only.
     * Filters by ExpenseCategory::kind === BAZAR (NOT bazar + fixed).
     * Granularity per D-08.
     *
     * @return array{labels:array<int,string>,values:array<int,float>}
     */
    public function expenseTrend(int $messId, Carbon $from, Carbon $to): array
    {
        $bazarCategoryIds = ExpenseCategory::query()
            ->where('kind', ExpenseKind::BAZAR)
            ->pluck('id')
            ->all();

        if (empty($bazarCategoryIds)) {
            return $this->emptySeries($from, $to);
        }

        $bucket = $this->bucketing->bucket($from, $to);
        $rows = $this->trendRows(
            Expense::query()
                ->where('mess_id', $messId)
                ->whereIn('expense_category_id', $bazarCategoryIds),
            $from,
            $to,
            $bucket['granularity'],
            'amount'
        );

        return $rows;
    }

    /**
     * Payment Trend (D-07): monthly total collected (all methods + both types).
     * Granularity per D-08.
     *
     * @return array{labels:array<int,string>,values:array<int,float>}
     */
    public function paymentTrend(int $messId, Carbon $from, Carbon $to): array
    {
        $bucket = $this->bucketing->bucket($from, $to);

        return $this->trendRows(
            Payment::query()->where('mess_id', $messId),
            $from,
            $to,
            $bucket['granularity'],
            'amount'
        );
    }

    /**
     * Total meal value for one date — used by the "Today's Meals" card.
     * EXCLUDES guest meals (Open Question #3 LOCKED). Uses MealType::value()
     * for the configured per-type values (Pitfall A3 — never hard-code 0.5/1/1).
     */
    private function todayMealTotal(int $messId, Carbon $date): float
    {
        $b = MealType::value(MealType::BREAKFAST);
        $l = MealType::value(MealType::LUNCH);
        $d = MealType::value(MealType::DINNER);

        return (float) MealEntry::query()
            ->where('mess_id', $messId)
            ->where('date', $date->toDateString())
            ->selectRaw(
                "SUM((CASE WHEN breakfast THEN {$b} ELSE 0 END) "
                ."+ (CASE WHEN lunch THEN {$l} ELSE 0 END) "
                ."+ (CASE WHEN dinner THEN {$d} ELSE 0 END)) AS total"
            )
            ->value('total') ?? 0.0;
    }

    /**
     * Build a series from a query by GROUP-ing on the appropriate time bucket.
     *
     * @param  Builder  $query  base query (already mess-scoped + filter-clauses)
     * @return array{labels:array<int,string>,values:array<int,float>}
     */
    private function trendRows($query, Carbon $from, Carbon $to, string $granularity, string $sumColumn): array
    {
        [$groupExpr, $orderExpr] = match ($granularity) {
            'day' => [DB::raw('date'), DB::raw('date')],
            'week' => [DB::raw("DATE_FORMAT(date, '%x-W%v')"), DB::raw("DATE_FORMAT(date, '%x-W%v')")],
            default => [DB::raw("DATE_FORMAT(date, '%Y-%m')"), DB::raw("DATE_FORMAT(date, '%Y-%m')")],
        };

        $rows = $query
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw(
                (string) ($granularity === 'day' ? 'date' : $groupExpr->getValue(DB::connection()->getQueryGrammar())).' AS period, '
                ."SUM({$sumColumn}) AS total"
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->keyBy('period');

        return $this->fillBucketAxis($from, $to, $granularity, function (string $bucketKey) use ($rows) {
            $row = $rows[$bucketKey] ?? null;

            return $row ? (float) $row->total : 0.0;
        });
    }

    /**
     * Walk the bucket axis from $from to $to and produce labels + values.
     * The $lookup closure returns the value for a given bucket key
     * (Y-m-d for daily, Y-W for weekly, Y-m for monthly) — usually by
     * consulting a ->keyBy()'d collection of GROUP BY rows.
     *
     * @param  \Closure(string):float  $lookup
     * @return array{labels:array<int,string>,values:array<int,float>}
     */
    private function fillBucketAxis(Carbon $from, Carbon $to, string $granularity, \Closure $lookup): array
    {
        $labels = [];
        $values = [];
        $cursor = $from->copy();

        while ($cursor <= $to) {
            $key = match ($granularity) {
                'day' => $cursor->toDateString(),
                'week' => $cursor->format('o-\WW'),
                default => $cursor->format('Y-m'),
            };

            $label = match ($granularity) {
                'day' => $cursor->translatedFormat('d M'),
                'week' => $cursor->translatedFormat('d M'),
                default => $cursor->translatedFormat('M Y'),
            };

            $labels[] = $label;
            $values[] = (float) $lookup($key);

            match ($granularity) {
                'day' => $cursor->addDay(),
                'week' => $cursor->addWeek(),
                default => $cursor->addMonth(),
            };
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * Empty series (zero rows) when no bazar categories exist yet.
     *
     * @return array{labels:array<int,string>,values:array<int,float>}
     */
    private function emptySeries(Carbon $from, Carbon $to): array
    {
        $bucket = $this->bucketing->bucket($from, $to);

        return $this->fillBucketAxis($from, $to, $bucket['granularity'], fn () => 0.0);
    }

    public function countsCacheKey(int $messId, Carbon $date): string
    {
        return "dash:counts:{$messId}:{$date->year}-".str_pad((string) $date->month, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Members with Dues (list): active members whose net balance is in debt.
     * Cheapest source — advance_balances is a persisted running total.
     *
     * @return list<array{id:int,name:string,net:float}>
     */
    public function membersWithDues(int $messId): array
    {
        return Member::query()
            ->where('mess_id', $messId)
            ->where('status', MemberStatus::ACTIVE)
            ->with('advanceBalance')
            ->orderBy('name')
            ->get()
            ->map(fn (Member $m) => ['id' => $m->id, 'name' => $m->name, 'net' => $m->advanceBalance?->netBalance() ?? 0])
            ->filter(fn (array $m) => $m['net'] < 0)
            ->sortBy('net')
            ->take(8)
            ->values()
            ->all();
    }

    /**
     * Bazar vs Collection (this month): grocery+fixed spend vs money collected.
     * Reuses the bill-preview cache for spend; collection is one SUM.
     *
     * @return array{spend:float,collected:float}
     */
    public function bazarVsCollection(int $messId): array
    {
        $now = now();
        $preview = $this->preview->preview($now->year, $now->month);
        $spend = (float) (($preview['total_bazar'] ?? 0) + ($preview['total_fixed'] ?? 0));
        $collected = (float) Payment::query()
            ->where('mess_id', $messId)
            ->whereBetween('date', [$now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString()])
            ->sum('amount');

        return ['spend' => $spend, 'collected' => $collected];
    }

    /**
     * Expense Category Mix (this month): spend grouped by expense category,
     * descending — powers the dashboard doughnut.
     *
     * @return list<array{label:string,amount:float}>
     */
    public function expenseCategoryMix(int $messId): array
    {
        $now = now();
        $rows = Expense::query()
            ->where('mess_id', $messId)
            ->whereBetween('date', [$now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString()])
            ->with('category:id,name')
            ->get();

        $grouped = [];
        foreach ($rows as $expense) {
            $label = $expense->category?->name ?? __('Uncategorized');
            $grouped[$label] = ($grouped[$label] ?? 0) + (float) $expense->amount;
        }
        arsort($grouped);

        $out = [];
        foreach ($grouped as $label => $amount) {
            $out[] = ['label' => $label, 'amount' => (float) $amount];
        }

        return $out;
    }

    /**
     * Top Eaters (this month): members with the most meals, top 5.
     * Reuses the cached bill-preview members (no new query).
     *
     * @return list<array{id:int,name:string,meals:float}>
     */
    public function topEaters(int $messId): array
    {
        $now = now();
        $preview = $this->preview->preview($now->year, $now->month);

        return collect($preview['members'] ?? [])
            ->sortByDesc('meals')
            ->take(5)
            ->values()
            ->map(fn (array $m) => ['id' => $m['member_id'], 'name' => $m['name'], 'meals' => (float) $m['meals']])
            ->all();
    }
}
