<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Mess;
use App\Models\MonthlyClosing;
use App\Models\MonthlyCorrection;
use App\Support\NotificationType;
use Illuminate\Support\Facades\DB;

class MonthlyCorrectionService
{
    public function __construct(private readonly AdvanceBalanceService $balances) {}

    /**
     * Record a signed correction against a closed month and apply it immediately
     * to the member's advance/due balance (D-24). The closed snapshot is never
     * rewritten (CLOSE-09).
     *
     * @param  array<string, mixed>  $notificationData  Optional extra payload.
     */
    public function create(
        MonthlyClosing $closing,
        int $memberId,
        float $amount,
        string $reason,
        int $appliedToYear,
        int $appliedToMonth,
        int $enteredBy,
    ): MonthlyCorrection {
        return DB::transaction(function () use ($closing, $memberId, $amount, $reason, $appliedToYear, $appliedToMonth, $enteredBy) {
            $correction = MonthlyCorrection::create([
                'mess_id' => Mess::activeId(),
                'monthly_closing_id' => $closing->id,
                'member_id' => $memberId,
                'applied_to_year' => $appliedToYear,
                'applied_to_month' => $appliedToMonth,
                'amount' => $amount,
                'reason' => $reason,
                'entered_by' => $enteredBy,
            ]);

            // Apply to advance (amount > 0) or due (amount < 0) immediately (D-24).
            $this->balances->carryForward($memberId, $amount);

            // Invalidate the preview cache for the applied-to month so future previews
            // pick up the balance change (D-26).
            app(BillPreviewService::class)->invalidate($appliedToYear, $appliedToMonth);

            // Notify the affected member's linked user (NOTIF-04 general-purpose channel).
            $member = Member::find($memberId);
            if ($member?->user_id) {
                app(NotificationService::class)->send($member->user, NotificationType::DUE_REMINDER, [
                    'year' => $appliedToYear,
                    'month' => $appliedToMonth,
                    'amount' => $amount,
                    'reason' => $reason,
                    'closing_id' => $closing->id,
                ]);
            }

            return $correction;
        });
    }
}
