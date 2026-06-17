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

            $row->balance = bcadd((string) $row->balance, (string) $payment->amount, 2);
            $row->last_updated_at = now();
            $row->save();
        });
    }

    /**
     * Reverse the prior impact of a Payment on the member's advance/due balance
     * (WR-01). Used by PaymentService::update before re-applying the new values:
     * subtracts the original amount for ADVANCE_DEPOSIT (the mirror of applyPayment).
     * No-op for BILL_PAYMENT (matches applyPayment's no-op).
     */
    public function reversePayment(Payment $payment): void
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
                // Nothing to reverse — the member never had a balance row.
                return;
            }

            $row->balance = bcsub((string) $row->balance, (string) $payment->amount, 2);
            $row->last_updated_at = now();
            $row->save();
        });
    }

    /**
     * Manager-side manual adjustment with a reason (D-07 / D-11).
     */
    public function adjust(int $memberId, float $amount, string $reason, int $enteredBy): AdvanceBalance
    {
        $amountStr = number_format($amount, 2, '.', '');
        if (bccomp($amountStr, '0', 2) === 0) {
            throw new RuntimeException('Adjustment amount cannot be zero.');
        }

        return DB::transaction(function () use ($memberId, $amountStr, $reason, $enteredBy) {
            $row = AdvanceBalance::query()
                ->where('member_id', $memberId)
                ->lockForUpdate()
                ->firstOrCreate(
                    ['mess_id' => Mess::activeId(), 'member_id' => $memberId],
                    ['balance' => 0, 'due_balance' => 0, 'last_updated_at' => now()]
                );

            if (bccomp($amountStr, '0', 2) > 0) {
                $row->balance = bcadd((string) $row->balance, $amountStr, 2);
            } else {
                $row->due_balance = bcadd((string) $row->due_balance, ltrim($amountStr, '-'), 2);
            }
            $row->last_updated_at = now();
            $row->save();

            Log::info('manual_balance_adjustment', [
                'member_id' => $memberId,
                'amount' => $amountStr,
                'reason' => $reason,
                'entered_by' => $enteredBy,
                'new_balance' => $row->balance,
                'new_due_balance' => $row->due_balance,
            ]);

            return $row;
        });
    }

    /**
     * Carry a signed month-close net bill into the member's running balance (D-09).
     *
     * Positive `$amount` → increases `balance` (advance/credit); negative →
     * increases `due_balance` (debt). This is the single write point that
     * accumulates money across months, so it operates purely in BC math on a
     * 2-decimal string — never float (CR-03: "decimal money, never float").
     *
     * `$amount` MUST be a normalized 2-decimal string (e.g. `'6000.00'`,
     * `'-150.00'`). Callers normalize via `number_format($value, 2, '.', '')`
     * or `bcmul()` before calling.
     */
    public function carryForward(int $memberId, string $amount): AdvanceBalance
    {
        $amountStr = number_format((float) $amount, 2, '.', '');

        return DB::transaction(function () use ($memberId, $amountStr) {
            $row = AdvanceBalance::query()
                ->where('member_id', $memberId)
                ->lockForUpdate()
                ->firstOrCreate(
                    ['mess_id' => Mess::activeId(), 'member_id' => $memberId],
                    ['balance' => 0, 'due_balance' => 0, 'last_updated_at' => now()]
                );

            if (bccomp($amountStr, '0', 2) > 0) {
                $row->balance = bcadd((string) $row->balance, $amountStr, 2);
            } elseif (bccomp($amountStr, '0', 2) < 0) {
                $row->due_balance = bcadd((string) $row->due_balance, ltrim($amountStr, '-'), 2);
            }
            $row->last_updated_at = now();
            $row->save();

            return $row;
        });
    }
}
