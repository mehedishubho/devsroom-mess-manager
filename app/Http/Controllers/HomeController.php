<?php

namespace App\Http\Controllers;

use App\Http\Requests\Dashboard\ChartRangeRequest;
use App\Models\Mess;
use App\Services\DashboardService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;

/**
 * HomeController — manager dashboard (D-14: 6 cards + 3 charts + alert banner).
 *
 * Replaces the old view-only index(). Loads the DASH-01 cards via
 * DashboardService::managerCards() (bill-derived cards reuse the bill-preview
 * cache; count cards use dash:counts:{mess_id}:{YYYY}-{MM}), resolves the 3
 * chart ranges from the GET query (D-03, DASH-06) with the locked defaults
 * (Meal 30d, Expense 6mo, Payment 6mo), and renders home.blade.php.
 */
class HomeController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboards,
    ) {}

    public function index(ChartRangeRequest $request): View
    {
        $messId = Mess::activeId();
        $now = now();

        // D-02 defaults (DASH-06): Meal 30 days back, Expense + Payment 6 months back.
        $mealRange = $this->resolveRange($request, 'meal',
            $now->copy()->subDays(29)->startOfDay(),
            $now->copy()->endOfDay());
        $expenseRange = $this->resolveRange($request, 'expense',
            $now->copy()->subMonths(5)->startOfMonth(),
            $now->copy()->endOfMonth());
        $paymentRange = $this->resolveRange($request, 'payment',
            $now->copy()->subMonths(5)->startOfMonth(),
            $now->copy()->endOfMonth());

        return view('home', [
            'cards' => $this->dashboards->managerCards(),
            'pendingMealOff' => $this->dashboards->pendingMealOffCount(),
            'charts' => [
                'meal' => array_merge(
                    ['type' => 'line'],
                    $this->dashboards->mealTrend($messId, $mealRange[0], $mealRange[1]),
                    ['range' => [
                        'from' => $mealRange[0]->toDateString(),
                        'to' => $mealRange[1]->toDateString(),
                    ]]
                ),
                'expense' => array_merge(
                    ['type' => 'bar'],
                    $this->dashboards->expenseTrend($messId, $expenseRange[0], $expenseRange[1]),
                    ['range' => [
                        'from' => $expenseRange[0]->toDateString(),
                        'to' => $expenseRange[1]->toDateString(),
                    ]]
                ),
                'payment' => array_merge(
                    ['type' => 'bar'],
                    $this->dashboards->paymentTrend($messId, $paymentRange[0], $paymentRange[1]),
                    ['range' => [
                        'from' => $paymentRange[0]->toDateString(),
                        'to' => $paymentRange[1]->toDateString(),
                    ]]
                ),
            ],
        ]);
    }

    /**
     * Resolve a chart's from/to range from the request, falling back to the
     * default when the param is absent or invalid.
     *
     * @return array{0:Carbon,1:Carbon}
     */
    private function resolveRange(ChartRangeRequest $request, string $prefix, Carbon $defaultFrom, Carbon $defaultTo): array
    {
        $fromInput = $request->input("{$prefix}_from");
        $toInput = $request->input("{$prefix}_to");

        $from = $fromInput ? Carbon::parse($fromInput)->startOfDay() : $defaultFrom;
        $to = $toInput ? Carbon::parse($toInput)->endOfDay() : $defaultTo;

        if ($to->lt($from)) {
            // Defensive: never let an inverted range produce a negative series.
            return [$defaultFrom, $defaultTo];
        }

        return [$from, $to];
    }
}
