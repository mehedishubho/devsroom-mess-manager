<?php

namespace App\Services;

use App\Models\GuestMeal;
use App\Models\MealEntry;
use App\Models\Member;
use App\Models\MemberDisabledDay;
use App\Models\Mess;
use App\Models\MessClosedDay;
use App\Models\MonthlyClosing;
use App\Models\MonthlyMemberSummary;
use App\Models\Payment;
use App\Support\MealType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

/**
 * MemberStatementService — full per-member ledger for a given month.
 *
 * Wraps BillPreviewService::forMember() for the live/open-month path and
 * the MonthlyMemberSummary snapshot for the closed-month path (D-26).
 * Adds the daily meal breakdown (D-23), guest-meal rows, payment rows,
 * the open-vs-closed period label (D-24), and a `source` flag.
 *
 * NOTE (Pitfall 3): the returned `row` keeps an `advance_applied` key
 * for shape-parity with BillPreviewService, but the view MUST NOT
 * display it — it equals `bill_payments`. Views surface `bill_payments`,
 * `advance_payments`, `advance_balance`, `due_balance` only.
 */
class MemberStatementService
{
    public function __construct(
        private readonly BillPreviewService $preview,
    ) {}

    /**
     * @return array{
     *     row:array<string,mixed>|null,
     *     daily:array<int,array{date:string,breakfast:bool,lunch:bool,dinner:bool,meal_value:float}>,
     *     guests:Collection<int,GuestMeal>,
     *     payments:Collection<int,Payment>,
     *     is_closed:bool,
     *     period_label:string,
     *     source:string,
     * }
     */
    public function forMember(int $memberId, int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth()->endOfDay();
        $messId = Mess::activeId();

        $closing = MonthlyClosing::query()
            ->where('mess_id', $messId)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        $isClosed = (bool) $closing;

        $row = $isClosed
            ? $this->rowFromSnapshot($memberId, $closing->id, $year, $month)
            : $this->preview->forMember($memberId, $year, $month);

        $daily = $this->dailyBreakdown($memberId, $start, $end);

        $guests = GuestMeal::query()
            ->where('member_id', $memberId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('date')
            ->get();

        $payments = Payment::query()
            ->where('member_id', $memberId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('date')
            ->get();

        $periodLabel = $isClosed
            ? Carbon::create($year, $month, 1)->translatedFormat('F Y')
            : __('As of today, :date', ['date' => now()->translatedFormat('j F Y')]);

        return [
            'row' => $row,
            'daily' => $daily,
            'guests' => $guests,
            'payments' => $payments,
            'is_closed' => $isClosed,
            'period_label' => $periodLabel,
            'source' => $isClosed ? 'snapshot' : 'live',
        ];
    }

    /**
     * Read one member's row from the closed-month snapshot, mapped to the
     * same shape BillPreviewService returns.
     *
     * @return array<string,mixed>|null
     */
    private function rowFromSnapshot(int $memberId, int $closingId, int $year, int $month): ?array
    {
        $row = MonthlyMemberSummary::query()
            ->where('monthly_closing_id', $closingId)
            ->where('member_id', $memberId)
            ->with('member:id,name,status')
            ->first();

        if (! $row) {
            return null;
        }

        // Recover the running credit/debt from the frozen closing_balance (signed
        // net). Pre-existing snapshots (column null) fall back to the old shape:
        // credit hidden, running due = the month residual.
        $closingBalance = $row->closing_balance !== null ? (float) $row->closing_balance : null;
        if ($closingBalance === null) {
            $advanceBalance = 0.0;
            $runningDue = (float) $row->balance_due;
        } else {
            $advanceBalance = $closingBalance >= 0 ? $closingBalance : 0.0;
            $runningDue = $closingBalance < 0 ? abs($closingBalance) : 0.0;
        }

        return [
            'member_id' => $row->member_id,
            'name' => $row->member?->name ?? (string) $row->member_id,
            'meals' => (float) $row->total_meals,
            'meal_cost' => (float) $row->meal_cost,
            'fixed_share' => (float) $row->fixed_cost_share,
            'guest_total' => (float) $row->guest_meal_charge,
            'bill' => (float) $row->gross_bill,
            'bill_payments' => (float) $row->advance_applied, // CR-03: snapshot's `advance_applied` is bill payments
            'advance_payments' => 0.0,
            'advance_applied' => (float) $row->advance_applied, // kept for shape parity; views MUST NOT display
            'due' => (float) $row->balance_due,
            'advance_balance' => $advanceBalance,
            'due_balance' => $runningDue,
            'active_days' => 0,
            'status' => $row->member?->status ?? 'active',
        ];
    }

    /**
     * Daily meal breakdown (D-23): each date with B/L/D booleans + the
     * configured meal value sum for that day.
     * Excludes mess-closed dates and member-disabled dates.
     *
     * @return array<int,array{date:string,breakfast:bool,lunch:bool,dinner:bool,meal_value:float}>
     */
    private function dailyBreakdown(int $memberId, Carbon $start, Carbon $end): array
    {
        $messId = Mess::activeId();

        // Load mess-closed dates
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

        // Load member-disabled dates
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
            ->orderBy('date')
            ->get(['date', 'breakfast', 'lunch', 'dinner']);

        $out = [];
        $seen = [];

        foreach ($entries as $entry) {
            $dateStr = $entry->date->toDateString();

            // Skip closed/disabled dates
            if (isset($closedDatesSet[$dateStr]) || isset($disabledDatesSet[$dateStr])) {
                continue;
            }

            // Avoid duplicates (in case of multiple entries for same date)
            if (isset($seen[$dateStr])) {
                continue;
            }
            $seen[$dateStr] = true;

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

            $out[] = [
                'date' => $dateStr,
                'breakfast' => (bool) $entry->breakfast,
                'lunch' => (bool) $entry->lunch,
                'dinner' => (bool) $entry->dinner,
                'meal_value' => $val,
            ];
        }

        return $out;
    }

    /**
     * Helper exposed for the controller's member picker — confirms a member
     * exists within the active mess (MessScope auto-applies the filter).
     */
    public function memberExistsInActiveMess(int $memberId): bool
    {
        return Member::query()->where('id', $memberId)->exists();
    }
}
