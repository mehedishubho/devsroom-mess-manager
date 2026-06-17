<?php

namespace App\Services;

use App\Models\MealOffRequest;
use App\Support\MealOffStatus;

class MealOffApprovalService
{
    public function approve(MealOffRequest $request, int $actedBy): void
    {
        $request->update([
            'status' => MealOffStatus::APPROVED,
            'acted_at' => now(),
            'acted_by' => $actedBy,
        ]);
    }

    public function reject(MealOffRequest $request, int $actedBy, string $reason): void
    {
        $request->update([
            'status' => MealOffStatus::REJECTED,
            'rejection_reason' => $reason,
            'acted_at' => now(),
            'acted_by' => $actedBy,
        ]);
    }
}
