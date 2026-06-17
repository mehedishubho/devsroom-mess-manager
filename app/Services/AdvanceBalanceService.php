<?php

namespace App\Services;

use App\Models\AdvanceBalance;
use App\Models\Mess;
use App\Models\Payment;
use App\Support\PaymentType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AdvanceBalanceService
{
    /**
     * Apply the impact of a Payment to the member's advance/due balance.
     * Only `advance_deposit` touches `balance` (D-07). `bill_payment` is a no-op here.
     */
    public function applyPayment(Payment $payment): void
    {
        if ($payment->type !== PaymentType::ADVANCE_DEPOSIT) {
            return;
        }

        DB::transaction(function () use ($payment) {
            $row = AdvanceBalance::query()
                ->where('member_id', $payment->member_id)
                ->lockForUpdate()
                ->first();

            if (! $row) {
                $row = AdvanceBalance::create([
                    'mess_id' => Mess::activeId(),
                    'member_id' => $payment->member_id,
                    'balance' => 0,
                    'due_balance' => 0,
                    'last_updated_at' => now(),
                ]);
            }

            $row->balance = (float) $row->balance + (float) $payment->amount;
            $row->last_updated_at = now();
            $row->save();
        });
    }

    /**
     * Manager-side manual adjustment with a reason (D-07 / D-11).
     */
    public function adjust(int $memberId, float $amount, string $reason, int $enteredBy): AdvanceBalance
    {
        if ($amount === 0.0) {
            throw new RuntimeException('Adjustment amount cannot be zero.');
        }

        return DB::transaction(function () use ($memberId, $amount, $reason, $enteredBy) {
            $row = AdvanceBalance::query()
                ->where('member_id', $memberId)
                ->lockForUpdate()
                ->firstOrCreate(
                    ['mess_id' => Mess::activeId(), 'member_id' => $memberId],
                    ['balance' => 0, 'due_balance' => 0, 'last_updated_at' => now()]
                );

            if ($amount > 0) {
                $row->balance = (float) $row->balance + $amount;
            } else {
                $row->due_balance = (float) $row->due_balance + abs($amount);
            }
            $row->last_updated_at = now();
            $row->save();

            Log::info('manual_balance_adjustment', [
                'member_id' => $memberId,
                'amount' => $amount,
                'reason' => $reason,
                'entered_by' => $enteredBy,
                'new_balance' => $row->balance,
                'new_due_balance' => $row->due_balance,
            ]);

            return $row;
        });
    }

    /**
     * Used by the month-close service in Plan 3.4.
     */
    public function carryForward(int $memberId, float $amount): AdvanceBalance
    {
        return DB::transaction(function () use ($memberId, $amount) {
            $row = AdvanceBalance::query()
                ->where('member_id', $memberId)
                ->lockForUpdate()
                ->firstOrCreate(
                    ['mess_id' => Mess::activeId(), 'member_id' => $memberId],
                    ['balance' => 0, 'due_balance' => 0, 'last_updated_at' => now()]
                );

            if ($amount > 0) {
                $row->balance = (float) $row->balance + $amount;
            } elseif ($amount < 0) {
                $row->due_balance = (float) $row->due_balance + abs($amount);
            }
            $row->last_updated_at = now();
            $row->save();

            return $row;
        });
    }
}
