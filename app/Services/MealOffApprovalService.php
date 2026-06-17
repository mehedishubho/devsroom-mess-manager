<?php

namespace App\Services;

use App\Models\MealOffRequest;
use App\Support\MealOffStatus;
use App\Support\NotificationType;

class MealOffApprovalService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function approve(MealOffRequest $request, int $actedBy): void
    {
        $request->update([
            'status' => MealOffStatus::APPROVED,
            'acted_at' => now(),
            'acted_by' => $actedBy,
        ]);

        $this->notifyMember($request, 'approved');
    }

    public function reject(MealOffRequest $request, int $actedBy, string $reason): void
    {
        $request->update([
            'status' => MealOffStatus::REJECTED,
            'rejection_reason' => $reason,
            'acted_at' => now(),
            'acted_by' => $actedBy,
        ]);

        $this->notifyMember($request, 'rejected', $reason);
    }

    /**
     * NOTIF-02: notify the member that their meal-off request was approved or rejected.
     */
    private function notifyMember(MealOffRequest $request, string $decision, ?string $reason = null): void
    {
        $member = $request->member;
        if (! $member?->user_id) {
            return;
        }

        $payload = [
            'meal_off_request_id' => $request->id,
            'decision' => $decision,
            'from_date' => $request->from_date?->format('Y-m-d'),
            'to_date' => $request->to_date?->format('Y-m-d'),
        ];
        if ($reason !== null) {
            $payload['reason'] = $reason;
        }

        $this->notifications->send($member->user, NotificationType::MEAL_OFF_DECISION, $payload);
    }
}
