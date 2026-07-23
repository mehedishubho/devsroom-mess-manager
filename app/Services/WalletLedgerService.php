<?php

namespace App\Services;

use App\Models\AdvanceBalance;
use App\Models\BalanceAdjustment;
use App\Models\Member;
use App\Models\Mess;
use App\Models\MonthlyClosing;
use App\Models\MonthlyCorrection;
use App\Models\MonthlyMemberSummary;
use App\Models\Payment;
use App\Support\PaymentType;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * WalletLedgerService — one chronological activity list per member.
 *
 * Assembles every money movement (payments, closed-month bills, corrections,
 * manual adjustments, and the current open-month pending bill) into a single
 * debit/credit list, anchored to the authoritative current net balance
 * (AdvanceBalance::netBalance()). This is the readable view that replaces the
 * old split between Payments, Advance Balances, and Adjust.
 *
 * Design note: a per-row running balance is intentionally omitted. The carried
 * balance (advance_balances) is mutated by close-time settlement mechanics that
 * don't map 1:1 onto individual rows, so a synthesized running column would
 * imply false precision. The header shows the real settled balance instead.
 */
class WalletLedgerService
{
    public function __construct(
        private readonly BillPreviewService $preview,
    ) {}

    /**
     * @return array{
     *     member:Member,
     *     current_balance:float,
     *     pending_bill:float,
     *     rows:Collection<int,array<string,mixed>>,
     * }
     */
    public function forMember(Member $member): array
    {
        $rows = collect();

        // Payments (all-time) → credit (money IN).
        foreach (Payment::query()->where('member_id', $member->id)->orderBy('date')->orderBy('id')->get() as $p) {
            $label = __('Payment').' · '.__(ucfirst((string) $p->method));
            if ($p->type === PaymentType::ADVANCE_DEPOSIT) {
                $label .= ' ('.__('advance').')';
            }
            if ($p->reference) {
                $label .= ' — '.$p->reference;
            }
            $rows->push([
                'date' => $p->date,
                'description' => $label,
                'credit' => (float) $p->amount,
                'debit' => 0.0,
            ]);
        }

        // Closed-month bills → debit (gross bill for the month).
        foreach (MonthlyMemberSummary::query()->where('member_id', $member->id)->with('monthlyClosing')->get() as $s) {
            $closing = $s->monthlyClosing;
            $monthLabel = $closing
                ? Carbon::create($closing->year, $closing->month, 1)->translatedFormat('F Y')
                : __('Closed month');
            $rows->push([
                'date' => $closing?->closed_at ?? $s->created_at,
                'description' => __('Monthly bill · :month', ['month' => $monthLabel]),
                'credit' => 0.0,
                'debit' => (float) $s->gross_bill,
            ]);
        }

        // Corrections → signed.
        foreach (MonthlyCorrection::query()->where('member_id', $member->id)->orderBy('created_at')->get() as $c) {
            $amt = (float) $c->amount;
            $rows->push([
                'date' => $c->created_at,
                'description' => __('Correction — :reason', ['reason' => $c->reason]),
                'credit' => $amt > 0 ? abs($amt) : 0.0,
                'debit' => $amt < 0 ? abs($amt) : 0.0,
            ]);
        }

        // Manual adjustments → signed.
        foreach (BalanceAdjustment::query()->where('member_id', $member->id)->orderBy('created_at')->get() as $a) {
            $amt = (float) $a->amount;
            $rows->push([
                'date' => $a->created_at,
                'description' => __('Adjustment — :reason', ['reason' => $a->reason]),
                'credit' => $amt > 0 ? abs($amt) : 0.0,
                'debit' => $amt < 0 ? abs($amt) : 0.0,
            ]);
        }

        // Current open-month bill (pending — not yet deducted from the balance).
        $now = Carbon::now();
        $alreadyClosed = MonthlyClosing::query()
            ->where('mess_id', Mess::activeId())
            ->where('year', $now->year)
            ->where('month', $now->month)
            ->exists();
        $pendingBill = 0.0;
        if (! $alreadyClosed) {
            $billRow = $this->preview->forMember($member->id, $now->year, $now->month);
            $pendingBill = (float) ($billRow['bill'] ?? 0);
            if ($pendingBill > 0) {
                $rows->push([
                    'date' => $now->copy()->endOfMonth(),
                    'description' => __('Current month bill (pending)'),
                    'credit' => 0.0,
                    'debit' => $pendingBill,
                    'pending' => true,
                ]);
            }
        }

        $rows = $rows->sortBy(fn ($r) => optional($r['date'])->timestamp ?? 0)->values();

        $ab = AdvanceBalance::where('member_id', $member->id)->first();
        $currentBalance = $ab?->netBalance() ?? 0.0;

        return [
            'member' => $member,
            'current_balance' => $currentBalance,
            'pending_bill' => $pendingBill,
            'rows' => $rows,
        ];
    }
}
