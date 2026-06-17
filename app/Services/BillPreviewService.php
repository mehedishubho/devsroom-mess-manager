<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Member;
use App\Models\Mess;
use App\Models\Payment;
use App\Support\ExpenseKind;
use App\Support\MealType;
use App\Support\MemberStatus;
use App\Support\PaymentType;
use Carbon\Carbon;
use Illuminate\Support\Collection;
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
        throw new \RuntimeException('DBG: messId='.$messId.' catIds='.json_encode($bazarCategoryIds).' expenses='.Expense::query()->where('mess_id', $messId)->count().' sum='.$totalBazar.' date='.$start);
        \Illuminate\Support\Facades\Log::debug('BillPreview total_bazar', [
            'count' => Expense::query()->where('mess_id', $messId)->count(),
            'bazar_category_ids' => $bazarCategoryIds,
            'total' => $totalBazar,
        ]);

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

        $mealTotalsByMember = $this->mealTotals($memberIds, $start, $end);
        $guestTotalsByMember = $this->guestTotals($memberIds, $start, $end);
        $paymentsByMember = $this->paymentsByMember($memberIds, $start, $end);
        $advanceBalances = $this->advanceBalances($memberIds);

        $denominatorEligible = $members->filter(function (Member $member) use ($start, $end) {
            return $this->eligibleForDenominator($member, $start, $end);
        });

        $totalMeals = 0.0;
        foreach ($denominatorEligible as $member) {
            $totalMeals += $mealTotalsByMember[$member->id] ?? 0.0;
        }

        $mealRate = $totalMeals > 0 ? round($totalBazar / $totalMeals, 2) : 0.0;

        $rows = [];
        foreach ($members as $member) {
            $meals = $mealTotalsByMember[$member->id] ?? 0.0;
            $guestTotal = $guestTotalsByMember[$member->id] ?? 0.0;
            $mealCost = round($meals * $mealRate, 2);

            $activeDays = $this->activeDaysForMember($member, $start, $end);
            $fixedShare = $daysInMonth > 0
                ? round($totalFixed * ($activeDays / $daysInMonth), 2)
                : 0.0;

            $bill = round($mealCost + $fixedShare + $guestTotal, 2);

            $billPayments = $paymentsByMember[$member->id]['bill_payments'] ?? 0.0;
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
            'members' => $rows,
        ];
    }

    private function mealTotals(array $memberIds, Carbon $start, Carbon $end): array
    {
        if (empty($memberIds)) {
            return [];
        }

        $entries = \App\Models\MealEntry::query()
            ->whereIn('member_id', $memberIds)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get(['member_id', 'breakfast', 'lunch', 'dinner']);

        $totals = array_fill_keys($memberIds, 0.0);
        foreach ($entries as $entry) {
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

        $rows = \App\Models\GuestMeal::query()
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

        $rows = \App\Models\AdvanceBalance::query()
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

    private function eligibleForDenominator(Member $member, Carbon $start, Carbon $end): bool
    {
        if ($member->status === MemberStatus::INACTIVE) {
            return false;
        }

        if ($member->joining_date && $member->joining_date->gt($start)) {
            return false;
        }

        if ($member->leaving_date && $member->leaving_date->lt($end)) {
            return false;
        }

        return true;
    }

    private function activeDaysForMember(Member $member, Carbon $start, Carbon $end): int
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

        return (int) $memberStart->diffInDays($memberEnd) + 1;
    }
}erEnd) + 1;
    }
}