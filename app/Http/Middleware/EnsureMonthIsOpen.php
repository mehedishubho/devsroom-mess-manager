<?php

namespace App\Http\Middleware;

use App\Models\Expense;
use App\Models\GuestMeal;
use App\Models\MealEntry;
use App\Models\MealOffRequest;
use App\Models\MonthlyClosing;
use App\Models\Payment;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMonthIsOpen
{
    /**
     * Refuse writes to monthly-tracked tables when the (year, month) of the
     * record being created/updated/deleted is already in monthly_closings (D-19,
     * CLOSE-10). Corrections go through the dedicated /mess/closings/{c}/corrections
     * routes which are NOT protected by this middleware.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $context = $this->resolveContext($request);

        if ($context === null) {
            // Could not determine a date — let validation handle missing input.
            return $next($request);
        }

        [$year, $month] = $context;

        $isClosed = MonthlyClosing::query()
            ->where('year', $year)
            ->where('month', $month)
            ->exists();

        if ($isClosed) {
            return back()->withErrors([
                'date' => __('MONTH CLOSED - :m/:y is locked. Corrections only via the closings page.', [
                    'm' => str_pad((string) $month, 2, '0', STR_PAD_LEFT),
                    'y' => $year,
                ]),
            ])->withInput();
        }

        return $next($request);
    }

    /**
     * Resolve (year, month) from the request by:
     *  1. Reading request input `date` (payments/expenses/guest-meals/meal-entries)
     *     or `from_date` (meal-off requests) on POST/PUT/PATCH.
     *  2. Falling back to the route-model-bound model's date column (update/delete).
     *
     * @return array{0:int,1:int}|null
     */
    private function resolveContext(Request $request): ?array
    {
        if ($request->isMethod('POST') || $request->isMethod('PUT') || $request->isMethod('PATCH')) {
            foreach (['date', 'from_date'] as $field) {
                $value = $request->input($field);
                if ($value) {
                    $ts = strtotime((string) $value);

                    if ($ts !== false) {
                        return [(int) date('Y', $ts), (int) date('n', $ts)];
                    }
                }
            }
        }

        foreach (['payment', 'expense', 'guestMeal', 'mealEntry', 'mealOffRequest'] as $param) {
            $obj = $request->route($param);
            if ($obj instanceof Model) {
                $candidate = match (true) {
                    $obj instanceof Payment, $obj instanceof Expense, $obj instanceof GuestMeal, $obj instanceof MealEntry => $obj->date ?? null,
                    $obj instanceof MealOffRequest => $obj->from_date ?? null,
                    default => null,
                };
                if ($candidate) {
                    return [(int) $candidate->format('Y'), (int) $candidate->format('n')];
                }
            }
        }

        return null;
    }
}
