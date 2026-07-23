<?php

namespace App\Services;

use App\Models\AdvanceBalance;
use App\Models\MealEntry;
use App\Models\Member;
use App\Models\MemberDisabledDay;
use App\Models\Mess;
use App\Models\MessClosedDay;
use App\Models\Payment;
use App\Models\User;
use App\Support\MealType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

/**
 * MemberDashboardService — Overview landing cards for /my (DASH-04, D-16).
 *
 * Produces the 4 DASH-04 cards:
 *   - my_meals         (this month's meal value sum — regular B/L/D only,
 *                       EXCLUDES guest meals per Open Question #3 LOCKED)
 *   - my_bill          (this month's bill from BillPreviewService — cache-backed, D-17)
 *   - my_balance       (current net AdvanceBalance — credit − debt)
 *   - recent_payments  (last 5 payments for the "My Payment History" card)
 *
 * Member identity always comes from `$user->getMemberOrNull()` — never from
 * a URL param. When the user has no Member record, returns ['member' => null]
 * and the view renders a no-member empty state.
 */
class MemberDashboardService
{
    public function __construct(
        private readonly BillPreviewService $preview,
    ) {}

    /**
     * @return array{
     *     member:Member|null,
     *     my_meals:float,
     *     my_bill:float,
     *     my_balance:float,
     *     recent_payments:Collection<int,Payment>,
     * }
     */
    public function overviewCards(User $user): array
    {
        $member = $user->getMemberOrNull();

        if (! $member) {
            return [
                'member' => null,
                'my_meals' => 0.0,
                'my_bill' => 0.0,
                'my_balance' => 0.0,
                'recent_payments' => new Collection,
            ];
        }

        $now = Carbon::now();
        $year = $now->year;
        $month = $now->month;

        $myMeals = $this->myMealsThisMonth($member->id, $now);
        $billRow = $this->preview->forMember($member->id, $year, $month);
        $myBill = (float) ($billRow['bill'] ?? 0.0);
        // Net balance (credit − debt) — reading only the `balance` column used to
        // hide debt, so a member who owed saw "My Advance ৳0.00".
        $advanceRow = AdvanceBalance::query()->where('member_id', $member->id)->first();
        $myBalance = $advanceRow?->netBalance() ?? 0.0;
        $recentPayments = Payment::query()
            ->where('member_id', $member->id)
            ->with('enteredBy:id,name')
            ->latest('date')
            ->latest('id')
            ->limit(5)
            ->get();

        return [
            'member' => $member,
            'my_meals' => $myMeals,
            'my_bill' => $myBill,
            'my_balance' => $myBalance,
            'recent_payments' => $recentPayments,
        ];
    }

    /**
     * Sum the member's regular meal values for the current month.
     * Matches BillPreviewService::mealTotals() — uses MealType::value()
     * for each checked B/L/D boolean. Guest meals are NOT included
     * (Open Question #3 LOCKED resolution).
     * Excludes mess-closed dates and member-disabled dates for consistency
     * with the bill math.
     */
    private function myMealsThisMonth(int $memberId, Carbon $now): float
    {
        $start = $now->copy()->startOfMonth();
        $end = $now->copy()->endOfMonth();

        // Load mess-closed and member-disabled dates for this month
        $messId = Mess::activeId();
        $closedDatesSet = [];
        if ($messId) {
            $closedDates = MessClosedDay::query()
                ->where('mess_id', $messId)
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->pluck('date')
                ->map(fn ($d) => $d instanceof Carbon ? $d->toDateString() : (string) $d);
            foreach ($closedDates as $ds) {
                $closedDatesSet[$ds] = true;
            }
        }

        $disabledDates = MemberDisabledDay::query()
            ->where('member_id', $memberId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->pluck('date')
            ->map(fn ($d) => $d instanceof Carbon ? $d->toDateString() : (string) $d)
            ->all();

        $disabledDatesSet = array_flip($disabledDates);

        $entries = MealEntry::query()
            ->where('member_id', $memberId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get(['date', 'breakfast', 'lunch', 'dinner']);

        $total = 0.0;
        foreach ($entries as $entry) {
            $dateStr = $entry->date->toDateString();
            if (isset($closedDatesSet[$dateStr])) {
                continue;
            }
            if (isset($disabledDatesSet[$dateStr])) {
                continue;
            }
            if ($entry->breakfast) {
                $total += MealType::value(MealType::BREAKFAST);
            }
            if ($entry->lunch) {
                $total += MealType::value(MealType::LUNCH);
            }
            if ($entry->dinner) {
                $total += MealType::value(MealType::DINNER);
            }
        }

        return $total;
    }
}
