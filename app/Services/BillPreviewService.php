<?php

namespace App\Services;

use App\Models\AdvanceBalance;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\GuestMeal;
use App\Models\MealEntry;
use App\Models\Member;
use App\Models\MemberDisabledDay;
use App\Models\Mess;
use App\Models\MessClosedDay;
use App\Models\Payment;
use App\Support\ExpenseKind;
use App\Support\MealType;
use App\Support\MemberStatus;
use App\Support\PaymentType;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class BillPreviewService
{
    /**
     * Compute (and cache) the bill preview for a given year/month.
     *
     * @return array{year:int,month:int,total_bazar:float,total_meals:float,meal_rate:float,total_fixed:float,days_in_month:int,members:array<int,array<string,mixed>>}
     */
    public function preview(int $year, int $month): array
    {
        $messId = Mess::activeId();
        if ($messId === null) {
            return $this->emptyPreview($year, $month);
        }

        $cacheKey = $this->cacheKey($messId, $year, $month);

        return Cache::remember($cacheKey, now()->addHour(), function () use ($messId, $year, $month) {
            return $this->compute($messId, $year, $month);
        });
    }

    public function forMember(int $memberId, int $year, int $month): ?array
    {
        $preview = $this->preview($year, $month);

        foreach ($preview['members'] as $row) {
            if ((int) $row['member_id'] === $memberId) {
                return $row;
            }
        }

        return null;
    }

    public function cacheKey(int $messId, int $year, int $month): string
    {
        return "bill-preview:{$messId}:{$year}-".str_pad((string) $month, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Forget the cached preview for a given (year, month) for the active mess.
     * Used by month-close and corrections so the next read recomputes.
     */
    public function invalidate(int $year, int $month): void
    {
        $messId = Mess::activeId();
        if ($messId === null) {
            return;
        }

        Cache::forget($this->cacheKey($messId, $year, $month));
    }

    private function emptyPreview(int $year, int $month): array
    {
        return [
            'year' => $year,
            'month' => $month,
            'total_bazar' => 0.0,
            'total_meals' => 0.0,
            'meal_rate' => 0.0,
            'total_fixed' => 0.0,
            'days_in_month' => Carbon::create($year, $month, 1)->daysInMonth,
            'members' => [],
        ];
    }

    private function compute(int $messId, int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth()->endOfDay();
        $daysInMonth = $start->daysInMonth;

        $bazarCategoryIds = ExpenseCategory::query()
            ->where('kind', ExpenseKind::BAZAR)
            ->pluck('id')
            ->all();

        $fixedCategoryIds = ExpenseCategory::query()
            ->where('kind', ExpenseKind::FIXED)
            ->pluck('id')
            ->all();

        $totalBazar = (float) Expense::query()
            ->where('mess_id', $messId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('expense_category_id', $bazarCategoryIds)
            ->sum('amount');

        $totalFixed = (float) Expense::query()
            ->where('mess_id', $messId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('expense_category_id', $fixedCategoryIds)
            ->sum('amount');

        $members = Member::query()
            ->where('mess_id', $messId)
            ->whereIn('status', [MemberStatus::ACTIVE, MemberStatus::FORMER])
            ->orderBy('name')
            ->get();

        $memberIds = $members->pluck('id')->all();

        // Pre-load closed dates (mess closed) and disabled dates (per member)
        $closedDates = MessClosedDay::query()
            ->where('mess_id', $messId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->pluck('date')
            ->map(fn ($d) => $d instanceof Carbon ? $d->toDateString() : (string) $d)
            ->values()
            ->all();

        $disabledDayRows = MemberDisabledDay::query()
            ->where('mess_id', $messId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get(['member_id', 'date']);

        $disabledDaysByMember = [];
        foreach ($disabledDayRows as $dd) {
            $ds = $dd->date instanceof Carbon ? $dd->date->toDateString() : (string) $dd->date;
            $disabledDaysByMember[$dd->member_id][] = $ds;
        }

        $closedDatesSet = array_flip($closedDates);

        $mealTotalsByMember = $this->mealTotals($memberIds, $start, $end, $closedDatesSet, $disabledDaysByMember);
        $guestTotalsByMember = $this->guestTotals($memberIds, $start, $end);
        $paymentsByMember = $this->paymentsByMember($memberIds, $start, $end);
        $advanceBalances = $this->advanceBalances($memberIds);

        // Meal-rate denominator = total meals actually eaten this month by ALL
        // loaded members (active + former). Every meal eaten consumed groceries,
        // so the total bazar cost must be spread across every meal — not just
        // those of members who were "fully present" for the whole month. The old
        // eligibleForDenominator() filter (strict joining/leaving-date bounds)
        // zeroed the rate whenever the only eaters carried a leaving_date, which
        // is why meal_rate showed ৳0.00 across reports + dashboard despite data.
        $totalMeals = 0.0;
        foreach ($members as $member) {
            $totalMeals += $mealTotalsByMember[$member->id] ?? 0.0;
        }

        $mealRate = $totalMeals > 0 ? round($totalBazar / $totalMeals, 2) : 0.0;

        // Pre-compute disabled day counts per member for activeDaysForMember
        $disabledDayCountByMember = [];
        foreach ($memberIds as $mid) {
            $disabledDayCountByMember[$mid] = count($disabledDaysByMember[$mid] ?? []);
        }

        $rows = [];
        foreach ($members as $member) {
            $meals = $mealTotalsByMember[$member->id] ?? 0.0;
            $guestTotal = $guestTotalsByMember[$member->id] ?? 0.0;
            $mealCost = round($meals * $mealRate, 2);

            $activeDays = $this->activeDaysForMember($member, $start, $end, $closedDatesSet, $disabledDayCountByMember[$member->id] ?? 0);
            $fixedShare = $daysInMonth > 0
                ? round($totalFixed * ($activeDays / $daysInMonth), 2)
                : 0.0;

            $bill = round($mealCost + $fixedShare + $guestTotal, 2);

            $billPayments = $paymentsByMember[$member->id]['bill_payments'] ?? 0.0;
            // NOTE (CR-03): despite its name, `advance_applied` does NOT consume
            // advance deposits — it snapshots the bill-payment-type payments made
            // against this month's gross bill. Per the locked Phase-3 model
            // (D-07/D-08/D-10), advance deposits live only in `advance_balance`
            // and `due_balance`, tracked separately; they are NOT auto-applied
            // against the bill here. `net_bill` is therefore gross − bill payments.
            // The column name is retained for stability; a rename to
            // `bill_payments_applied` is tracked as a separate follow-up.
            $advanceApplied = $billPayments;
            $due = round($bill - $advanceApplied, 2);

            $advanceBalance = $advanceBalances[$member->id]['balance'] ?? 0.0;
            $dueBalance = $advanceBalances[$member->id]['due_balance'] ?? 0.0;

            $rows[] = [
                'member_id' => $member->id,
                'name' => $member->name,
                'meals' => $meals,
                'meal_cost' => $mealCost,
                'fixed_share' => $fixedShare,
                'guest_total' => $guestTotal,
                'bill' => $bill,
                'bill_payments' => $billPayments,
                'advance_payments' => $paymentsByMember[$member->id]['advance_payments'] ?? 0.0,
                'advance_applied' => $advanceApplied,
                'due' => $due,
                'advance_balance' => $advanceBalance,
                'due_balance' => $dueBalance,
                'active_days' => $activeDays,
                'status' => $member->status,
                'disabled_days' => $disabledDayCountByMember[$member->id] ?? 0,
            ];
        }

        return [
            'year' => $year,
            'month' => $month,
            'total_bazar' => $totalBazar,
            'total_meals' => $totalMeals,
            'meal_rate' => $mealRate,
            'total_fixed' => $totalFixed,
            'days_in_month' => $daysInMonth,
            'closed_days_count' => count($closedDates),
            'members' => $rows,
        ];
    }

    /**
     * @param  array<int, string>  $closedDatesSet  [date => true]
     * @param  array<int, array<int, string>>  $disabledDaysByMember  [member_id => [date, ...]]
     */
    private function mealTotals(array $memberIds, Carbon $start, Carbon $end, array $closedDatesSet = [], array $disabledDaysByMember = []): array
    {
        if (empty($memberIds)) {
            return [];
        }

        $entries = MealEntry::query()
            ->whereIn('member_id', $memberIds)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get(['member_id', 'date', 'breakfast', 'lunch', 'dinner']);

        $totals = array_fill_keys($memberIds, 0.0);
        foreach ($entries as $entry) {
            $dateStr = $entry->date->toDateString();
            // Skip entries on mess-closed dates
            if (isset($closedDatesSet[$dateStr])) {
                continue;
            }
            // Skip entries on member-disabled dates
            $memberDisabled = $disabledDaysByMember[$entry->member_id] ?? [];
            if (in_array($dateStr, $memberDisabled, true)) {
                continue;
            }

            $val = 0.0;
            if ($entry->breakfast) {
                $val += MealType::value(MealType::BREAKFAST);
            }
            if ($entry->lunch) {
                $val += MealType::value(MealType::LUNCH);
            }
            if ($entry->dinner) {
                $val += MealType::value(MealType::DINNER);
            }
            $totals[$entry->member_id] = ($totals[$entry->member_id] ?? 0.0) + $val;
        }

        return $totals;
    }

    private function guestTotals(array $memberIds, Carbon $start, Carbon $end): array
    {
        if (empty($memberIds)) {
            return [];
        }

        $rows = GuestMeal::query()
            ->whereIn('member_id', $memberIds)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get(['member_id', 'charge_amount']);

        $totals = array_fill_keys($memberIds, 0.0);
        foreach ($rows as $row) {
            $totals[$row->member_id] = ($totals[$row->member_id] ?? 0.0) + (float) $row->charge_amount;
        }

        return $totals;
    }

    private function paymentsByMember(array $memberIds, Carbon $start, Carbon $end): array
    {
        if (empty($memberIds)) {
            return [];
        }

        $rows = Payment::query()
            ->whereIn('member_id', $memberIds)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get(['member_id', 'type', 'amount']);

        $out = [];
        foreach ($memberIds as $id) {
            $out[$id] = ['bill_payments' => 0.0, 'advance_payments' => 0.0];
        }
        foreach ($rows as $row) {
            $id = $row->member_id;
            if ($row->type === PaymentType::ADVANCE_DEPOSIT) {
                $out[$id]['advance_payments'] += (float) $row->amount;
            } else {
                $out[$id]['bill_payments'] += (float) $row->amount;
            }
        }

        return $out;
    }

    private function advanceBalances(array $memberIds): array
    {
        if (empty($memberIds)) {
            return [];
        }

        $rows = AdvanceBalance::query()
            ->whereIn('member_id', $memberIds)
            ->get(['member_id', 'balance', 'due_balance']);

        $out = [];
        foreach ($memberIds as $id) {
            $out[$id] = ['balance' => 0.0, 'due_balance' => 0.0];
        }
        foreach ($rows as $row) {
            $out[$row->member_id] = [
                'balance' => (float) $row->balance,
                'due_balance' => (float) $row->due_balance,
            ];
        }

        return $out;
    }

    /**
     * @param  array<int, string>  $closedDatesSet  [date => true]
     */
    private function activeDaysForMember(Member $member, Carbon $start, Carbon $end, array $closedDatesSet = [], int $disabledDayCount = 0): int
    {
        $memberStart = $member->joining_date && $member->joining_date->gt($start)
            ? $member->joining_date->copy()
            : $start->copy();

        $memberEnd = $member->leaving_date && $member->leaving_date->lt($end)
            ? $member->leaving_date->copy()
            : $end->copy();

        if ($memberEnd->lt($memberStart)) {
            return 0;
        }

        $activeDays = (int) $memberStart->diffInDays($memberEnd) + 1;

        // Subtract mess-closed days that fall within this member's active period
        if (! empty($closedDatesSet)) {
            $cursor = $memberStart->copy();
            while ($cursor <= $memberEnd) {
                if (isset($closedDatesSet[$cursor->toDateString()])) {
                    $activeDays--;
                }
                $cursor->addDay();
            }
        }

        // Subtract member-disabled days
        return max(0, $activeDays - $disabledDayCount);
    }
}
