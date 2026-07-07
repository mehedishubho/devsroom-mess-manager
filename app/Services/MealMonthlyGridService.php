<?php

namespace App\Services;

use App\Models\MealEntry;
use App\Models\MealOffRequest;
use App\Models\Member;
use App\Models\Mess;
use App\Support\MealOffStatus;
use App\Support\MemberStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * MealMonthlyGridService — builds the monthly matrix grid data (members × dates)
 * and handles bulk saves.
 *
 * This is the monthly counterpart to MealGridService (which handles single-day).
 * It builds the full month's data in one query batch, respecting:
 *   - Approved MealOffRequests (read-only cells)
 *   - MessClosedDay (read-only cells for entire mess)
 *   - MemberDisabledDay (read-only cells for specific member)
 */
class MealMonthlyGridService
{
    /**
     * @return array{
     *     members: \Illuminate\Support\Collection<int, object>,
     *     month: Carbon,
     *     days_in_month: int,
     *     closed_dates: array<int, string>,
     *     meal_off_dates_by_member: array<int, array<int, string>>,
     *     disabled_dates_by_member: array<int, array<int, string>>,
     * }
     */
    public function buildMonthlyGridData(Carbon $month): array
    {
        $start = $month->copy()->startOfMonth()->startOfDay();
        $end = $month->copy()->endOfMonth()->endOfDay();
        $messId = Mess::activeId();

        // All active members for this mess
        $activeMembers = Member::query()
            ->where('mess_id', $messId)
            ->where('status', MemberStatus::ACTIVE)
            ->orderBy('name')
            ->get();

        // All MealEntry records for this month, keyed by member_id + date
        $entries = MealEntry::query()
            ->where('mess_id', $messId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->groupBy('member_id');

        // Approved meal-off requests overlapping this month
        $mealOffs = MealOffRequest::query()
            ->where('mess_id', $messId)
            ->where('status', MealOffStatus::APPROVED)
            ->where('from_date', '<=', $end->toDateString())
            ->where('to_date', '>=', $start->toDateString())
            ->get()
            ->groupBy('member_id');

        // Mess closed days for this month
        $closedDates = DB::table('mess_closed_days')
            ->where('mess_id', $messId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->pluck('date')
            ->map(fn ($d) => $d instanceof \Carbon\Carbon ? $d->toDateString() : (string) $d)
            ->values()
            ->all();

        // Member disabled days for this month
        $disabledDays = DB::table('member_disabled_days')
            ->where('mess_id', $messId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->groupBy('member_id')
            ->map(fn ($rows) => $rows->pluck('date')->map(fn ($d) => $d instanceof \Carbon\Carbon ? $d->toDateString() : (string) $d)->values()->all())
            ->all();

        // Build per-member date ranges for meal-off dates and closed dates
        $mealOffDatesByMember = [];
        foreach ($mealOffs as $memberId => $offs) {
            $dates = [];
            foreach ($offs as $off) {
                $from = $off->from_date instanceof Carbon ? $off->from_date : Carbon::parse($off->from_date);
                $to = $off->to_date instanceof Carbon ? $off->to_date : Carbon::parse($off->to_date);
                $cursor = $from->copy()->startOfDay();
                while ($cursor <= $to) {
                    $dateStr = $cursor->toDateString();
                    if ($dateStr >= $start->toDateString() && $dateStr <= $end->toDateString()) {
                        $dates[$dateStr] = $dateStr;
                    }
                    $cursor->addDay();
                }
            }
            $mealOffDatesByMember[$memberId] = array_values($dates);
        }

        $closedDatesSet = array_flip($closedDates);

        $rows = $activeMembers->map(function (Member $member) use ($entries, $mealOffDatesByMember, $disabledDays, $closedDatesSet, $month, $start, $end, $messId) {
            $memberEntries = $entries->get($member->id, collect());
            $memberMealOff = $mealOffDatesByMember[$member->id] ?? [];
            $memberDisabled = $disabledDays[$member->id] ?? [];
            $memberMealOffSet = array_flip($memberMealOff);
            $memberDisabledSet = array_flip($memberDisabled);

            $daysInMonth = $month->daysInMonth;

            $dayData = [];
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $date = $month->copy()->day($d);
                $dateStr = $date->toDateString();

                $entry = $memberEntries->firstWhere('date', $dateStr);
                $isClosed = isset($closedDatesSet[$dateStr]);
                $isMealOff = isset($memberMealOffSet[$dateStr]);
                $isDisabled = isset($memberDisabledSet[$dateStr]);

                $dayData[] = (object) [
                    'date' => $dateStr,
                    'day' => $d,
                    'day_of_week' => $date->dayOfWeek,
                    'breakfast' => $entry?->breakfast ?? false,
                    'lunch' => $entry?->lunch ?? false,
                    'dinner' => $entry?->dinner ?? false,
                    'entry_id' => $entry?->id,
                    'editable' => !$isClosed && !$isMealOff && !$isDisabled,
                    'reason' => $isClosed ? __('Mess closed') : ($isMealOff ? __('Meal off') : ($isDisabled ? __('Disabled') : null)),
                ];
            }

            return (object) [
                'member' => $member,
                'days' => $dayData,
            ];
        });

        return [
            'members' => $rows,
            'month' => $month,
            'days_in_month' => $month->daysInMonth,
            'closed_dates' => $closedDates,
            'meal_off_dates_by_member' => $mealOffDatesByMember,
            'disabled_dates_by_member' => $disabledDays,
        ];
    }

    /**
     * @param  array<int, array{member_id:int,date:string,breakfast?:bool,lunch?:bool,dinner?:bool}>  $entries
     */
    public function bulkSaveMonthly(Carbon $month, array $entries): void
    {
        $messId = Mess::activeId();
        $userId = auth()->id();
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        // Pre-load closed dates, meal-offs, and disabled days to filter
        $closedDates = DB::table('mess_closed_days')
            ->where('mess_id', $messId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->pluck('date')
            ->map(fn ($d) => $d instanceof \Carbon\Carbon ? $d->toDateString() : (string) $d)
            ->values()
            ->all();

        $closedDatesSet = array_flip($closedDates);

        $memberIds = collect($entries)->pluck('member_id')->unique()->values()->all();

        $mealOffs = MealOffRequest::query()
            ->where('mess_id', $messId)
            ->where('status', MealOffStatus::APPROVED)
            ->whereIn('member_id', $memberIds)
            ->where('from_date', '<=', $end->toDateString())
            ->where('to_date', '>=', $start->toDateString())
            ->get();

        $disabledDays = DB::table('member_disabled_days')
            ->where('mess_id', $messId)
            ->whereIn('member_id', $memberIds)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get();

        DB::transaction(function () use ($entries, $messId, $userId, $closedDatesSet, $mealOffs, $disabledDays, $start, $end) {
            // Build lookup: member_id => [date => true] for meal-offs
            $mealOffLookup = [];
            foreach ($mealOffs as $off) {
                $from = $off->from_date instanceof Carbon ? $off->from_date : Carbon::parse($off->from_date);
                $to = $off->to_date instanceof Carbon ? $off->to_date : Carbon::parse($off->to_date);
                $cursor = $from->copy()->startOfDay();
                while ($cursor <= $to) {
                    $ds = $cursor->toDateString();
                    if ($ds >= $start->toDateString() && $ds <= $end->toDateString()) {
                        $mealOffLookup[$off->member_id][$ds] = true;
                    }
                    $cursor->addDay();
                }
            }

            // Build lookup: member_id => [date => true] for disabled days
            $disabledLookup = [];
            foreach ($disabledDays as $dd) {
                $ds = $dd->date instanceof Carbon ? $dd->date->toDateString() : (string) $dd->date;
                $disabledLookup[$dd->member_id][$ds] = true;
            }

            foreach ($entries as $entry) {
                $memberId = (int) ($entry['member_id'] ?? 0);
                $dateStr = $entry['date'] ?? '';

                // Skip if date is mess-closed, member has meal-off, or member is disabled
                if (isset($closedDatesSet[$dateStr])) {
                    continue;
                }
                if (isset($mealOffLookup[$memberId][$dateStr])) {
                    continue;
                }
                if (isset($disabledLookup[$memberId][$dateStr])) {
                    continue;
                }

                MealEntry::updateOrCreate(
                    [
                        'mess_id' => $messId,
                        'member_id' => $memberId,
                        'date' => $dateStr,
                    ],
                    [
                        'breakfast' => (bool) ($entry['breakfast'] ?? false),
                        'lunch' => (bool) ($entry['lunch'] ?? false),
                        'dinner' => (bool) ($entry['dinner'] ?? false),
                        'entered_by' => $userId,
                    ]
                );
            }
        });
    }
}
