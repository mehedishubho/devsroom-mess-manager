<?php

namespace App\Services;

use App\Models\MealEntry;
use App\Models\MealOffRequest;
use App\Models\Member;
use App\Models\Mess;
use App\Support\MealOffStatus;
use App\Support\MemberStatus;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MealGridService
{
    /**
     * @return array{members: Collection, date: Carbon, mealOffByMember: array}
     */
    public function buildGridData(Carbon $date): array
    {
        $activeMembers = Member::query()
            ->where('status', MemberStatus::ACTIVE)
            ->orderBy('name')
            ->get();

        $entries = MealEntry::query()
            ->where('date', $date->toDateString())
            ->whereIn('member_id', $activeMembers->pluck('id'))
            ->get()
            ->keyBy('member_id');

        $mealOffByMember = $this->approvedMealOffForDate($date, $activeMembers->pluck('id'));

        $rows = $activeMembers->map(function (Member $member) use ($entries, $mealOffByMember) {
            $entry = $entries->get($member->id);
            $off = $mealOffByMember[$member->id] ?? null;

            return (object) [
                'member' => $member,
                'breakfast' => $entry?->breakfast ?? false,
                'lunch' => $entry?->lunch ?? false,
                'dinner' => $entry?->dinner ?? false,
                'entry_id' => $entry?->id,
                'meal_off_until' => $off?->to_date,
                'editable' => $off === null,
            ];
        });

        return [
            'members' => $rows,
            'date' => $date,
            'mealOffByMember' => $mealOffByMember,
        ];
    }

    /**
     * @param  array<int, array{breakfast: bool, lunch: bool, dinner: bool, member_id: int}>  $entries
     */
    public function bulkSave(Carbon $date, array $entries): void
    {
        $userId = auth()->id();
        $messId = Mess::activeId();
        $editableMembers = $this->editableMemberIdsForDate($date);

        DB::transaction(function () use ($date, $entries, $userId, $messId, $editableMembers) {
            foreach ($entries as $entry) {
                $memberId = (int) ($entry['member_id'] ?? 0);

                if (! in_array($memberId, $editableMembers, true)) {
                    continue;
                }

                MealEntry::updateOrCreate(
                    [
                        'mess_id' => $messId,
                        'member_id' => $memberId,
                        'date' => $date->toDateString(),
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

    /**
     * @return array<int, MealOffRequest>
     */
    private function approvedMealOffForDate(Carbon $date, Collection $memberIds): array
    {
        return MealOffRequest::query()
            ->where('status', MealOffStatus::APPROVED)
            ->where('from_date', '<=', $date->toDateString())
            ->where('to_date', '>=', $date->toDateString())
            ->whereIn('member_id', $memberIds)
            ->get()
            ->keyBy('member_id')
            ->all();
    }

    /**
     * @return int[]
     */
    private function editableMemberIdsForDate(Carbon $date): array
    {
        return Member::query()
            ->where('status', MemberStatus::ACTIVE)
            ->whereNotIn('id', function ($q) use ($date) {
                $q->select('member_id')
                    ->from('meal_off_requests')
                    ->where('status', MealOffStatus::APPROVED)
                    ->where('from_date', '<=', $date->toDateString())
                    ->where('to_date', '>=', $date->toDateString());
            })
            ->pluck('id')
            ->all();
    }
}
