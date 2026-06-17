<?php

namespace App\Services;

use App\Models\AdvanceBalance;
use App\Models\MealEntry;
use App\Models\Member;
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
 *   - my_advance       (current AdvanceBalance)
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
     *     my_advance:float,
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
                'my_advance' => 0.0,
                'recent_payments' => new Collection,
            ];
        }

        $now = Carbon::now();
        $year = $now->year;
        $month = $now->month;

        $myMeals = $this->myMealsThisMonth($member->id, $now);
        $billRow = $this->preview->forMember($member->id, $year, $month);
        $myBill = (float) ($billRow['bill'] ?? 0.0);
        $myAdvance = (float) (AdvanceBalance::query()
            ->where('member_id', $member->id)
            ->value('balance') ?? 0.0);
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
            'my_advance' => $myAdvance,
            'recent_payments' => $recentPayments,
        ];
    }

    /**
     * Sum the member's regular meal values for the current month.
     * Matches BillPreviewService::mealTotals() — uses MealType::value()
     * for each checked B/L/D boolean. Guest meals are NOT included
     * (Open Question #3 LOCKED resolution).
     */
    private function myMealsThisMonth(int $memberId, Carbon $now): float
    {
        $start = $now->copy()->startOfMonth()->toDateString();
        $end = $now->copy()->endOfMonth()->toDateString();

        $entries = MealEntry::query()
            ->where('member_id', $memberId)
            ->whereBetween('date', [$start, $end])
            ->get(['breakfast', 'lunch', 'dinner']);

        $total = 0.0;
        foreach ($entries as $entry) {
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
