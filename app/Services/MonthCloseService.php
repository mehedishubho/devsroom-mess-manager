<?php

namespace App\Services;

use App\Models\Mess;
use App\Models\MonthlyClosing;
use App\Models\MonthlyMemberSummary;
use App\Support\NotificationType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class MonthCloseService
{
    /**
     * Close a (year, month) for the active mess.
     *
     * Idempotent (D-18): firstOrCreate on (mess_id, year, month) backed by a
     * unique index. A second close attempt returns the existing closing and its
     * existing summaries without rewriting anything.
     *
     * Atomic: everything below is wrapped in DB::transaction.
     *
     * @return array{closing: MonthlyClosing, summaries: Collection<int, MonthlyMemberSummary>, was_recently_created: bool}
     */
    public function close(int $year, int $month, int $closedBy): array
    {
        return DB::transaction(function () use ($year, $month, $closedBy) {
            $closing = MonthlyClosing::firstOrCreate(
                [
                    'mess_id' => Mess::activeId(),
                    'year' => $year,
                    'month' => $month,
                ],
                [
                    'total_bazar' => 0,
                    'total_fixed_expense' => 0,
                    'total_meals' => 0,
                    'meal_rate' => 0,
                    'member_count' => 0,
                    'closed_at' => now(),
                    'closed_by' => $closedBy,
                    'status' => 'closed',
                ]
            );

            if (! $closing->wasRecentlyCreated) {
                // Idempotent path: second attempt is a no-op, returns existing snapshot.
                return [
                    'closing' => $closing,
                    'summaries' => $closing->memberSummaries()->get(),
                    'was_recently_created' => false,
                ];
            }

            $preview = app(BillPreviewService::class)->preview($year, $month);

            $closing->update([
                'total_bazar' => $preview['total_bazar'],
                'total_fixed_expense' => $preview['total_fixed'],
                'total_meals' => $preview['total_meals'],
                'meal_rate' => $preview['meal_rate'],
                'member_count' => count($preview['members']),
            ]);

            $summaries = collect();
            foreach ($preview['members'] as $row) {
                // CR-03: freeze money into the snapshot as normalized 2-decimal
                // strings so the carry-forward below never round-trips through
                // float. BillPreviewService returns rounded floats for display;
                // number_format() yields the canonical decimal string per the
                // project's "decimal money, never float" convention.
                $netBill = $this->money($row['due'] ?? 0);

                $summaries->push(MonthlyMemberSummary::create([
                    'mess_id' => Mess::activeId(),
                    'monthly_closing_id' => $closing->id,
                    'member_id' => $row['member_id'],
                    'total_meals' => $row['meals'],
                    'meal_rate' => $preview['meal_rate'],
                    'meal_cost' => $this->money($row['meal_cost'] ?? 0),
                    'fixed_cost_share' => $this->money($row['fixed_share'] ?? 0),
                    'guest_meal_charge' => $this->money($row['guest_total'] ?? 0),
                    'gross_bill' => $this->money($row['bill'] ?? 0),
                    'advance_applied' => $this->money($row['advance_applied'] ?? 0),
                    'net_bill' => $netBill,
                    'payments_received' => $this->money($row['bill_payments'] ?? 0),
                    'balance_due' => $netBill,
                ]));
            }

            // Carry-forward (D-09): positive net_bill → due; negative → advance.
            // BC math on the exact decimal string — sign via bcmul(), not float
            // negation (CR-03). carryForward() requires a 2-decimal string.
            $balanceService = app(AdvanceBalanceService::class);
            foreach ($summaries as $summary) {
                $net = (string) $summary->net_bill;
                if (bccomp($net, '0', 2) > 0) {
                    $balanceService->carryForward($summary->member_id, bcmul($net, '-1', 2));
                } elseif (bccomp($net, '0', 2) < 0) {
                    $balanceService->carryForward($summary->member_id, ltrim($net, '-'));
                }
            }

            // Invalidate the cached preview for the closed month.
            app(BillPreviewService::class)->invalidate($year, $month);

            // Broadcast close-complete notification to managers (NOTIF-01).
            app(NotificationService::class)->broadcastToManagers(NotificationType::CLOSE_COMPLETE, [
                'year' => $year,
                'month' => $month,
                'closing_id' => $closing->id,
                'total_bazar' => (float) $closing->total_bazar,
                'meal_rate' => (float) $closing->meal_rate,
                'member_count' => $closing->member_count,
                'closed_by' => $closedBy,
            ]);

            return [
                'closing' => $closing,
                'summaries' => $summaries,
                'was_recently_created' => true,
            ];
        });
    }

    /**
     * Normalize a money value to a canonical 2-decimal string (CR-03).
     *
     * BillPreviewService yields rounded floats; this freezes them into exact
     * decimal strings before they touch a DECIMAL column or the carry-forward
     * path, so money never round-trips through float.
     */
    private function money(float|int|string|null $value): string
    {
        return number_format((float) ($value ?? 0), 2, '.', '');
    }
}
