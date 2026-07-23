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
    public function __construct(
        private readonly BillPreviewService $billPreview,
    ) {}

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

            // A manual adjust changes the running credit/debt that the bill
            // preview now consumes (advance offsets the live bill), so drop the
            // current month's cached preview so the next read recomputes.
            $this->billPreview->invalidate(now()->year, now()->month);

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

    /**
     * Consume advance credit against a month's bill at close (D-09).
     *
     * Decrements `balance` by exactly `$amount` (a normalized 2-decimal string).
     * This is the write point that makes an advance deposit actually pay down a
     * bill — paired with BillPreviewService's `advance_applied`. BillPreviewService
     * caps advanceApplied at the available credit, so $amount <= balance by
     * construction; the defensive clamp below keeps balance >= 0 regardless.
     *
     * `$amount` MUST be a normalized 2-decimal string (e.g. `'61.75'`).
     */
    public function consumeAdvance(int $memberId, string $amount): AdvanceBalance
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

            // Defensive clamp: never let balance go negative even if a caller
            // somehow passes more than the available credit.
            if (bccomp($amountStr, (string) $row->balance, 2) > 0) {
                $amountStr = (string) $row->balance;
            }

            $row->balance = bcsub((string) $row->balance, $amountStr, 2);
            $row->last_updated_at = now();
            $row->save();

            return $row;
        });
    }

    /**
     * Net a member's credit (balance) against their debt (due_balance) so they
     * never simultaneously owe and are owed (D-09). Settles the smaller of the
     * two against the larger in BC math on the member's locked row.
     *
     * Called once per member at the end of MonthCloseService::close(), after the
     * month's advance has been consumed and any remaining bill carried to due.
     */
    public function settle(int $memberId): ?AdvanceBalance
    {
        return DB::transaction(function () use ($memberId) {
            $row = AdvanceBalance::query()
                ->where('member_id', $memberId)
                ->lockForUpdate()
                ->first();

            if (! $row) {
                return null;
            }

            $balance = (string) $row->balance;
            $due = (string) $row->due_balance;

            if (bccomp($balance, '0', 2) > 0 && bccomp($due, '0', 2) > 0) {
                $settle = bccomp($balance, $due, 2) <= 0 ? $balance : $due;
                $row->balance = bcsub($balance, $settle, 2);
                $row->due_balance = bcsub($due, $settle, 2);
                $row->last_updated_at = now();
                $row->save();
            }

            return $row;
        });
    }
}
