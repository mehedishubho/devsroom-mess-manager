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
                $netBill = (float) ($row['due'] ?? 0.0);
                $advanceApplied = (float) ($row['advance_applied'] ?? 0.0);

                $summaries->push(MonthlyMemberSummary::create([
                    'mess_id' => Mess::activeId(),
                    'monthly_closing_id' => $closing->id,
                    'member_id' => $row['member_id'],
                    'total_meals' => $row['meals'],
                    'meal_rate' => $preview['meal_rate'],
                    'meal_cost' => $row['meal_cost'],
                    'fixed_cost_share' => $row['fixed_share'],
                    'guest_meal_charge' => $row['guest_total'],
                    'gross_bill' => $row['bill'],
                    'advance_applied' => $advanceApplied,
                    'net_bill' => $netBill,
                    'payments_received' => $row['bill_payments'],
                    'balance_due' => $netBill,
                ]));
            }

            // Carry-forward (D-09): positive net_bill → due; negative → advance.
            $balanceService = app(AdvanceBalanceService::class);
            foreach ($summaries as $summary) {
                $net = (float) $summary->net_bill;
                if ($net > 0) {
                    $balanceService->carryForward($summary->member_id, -1 * $net);
                } elseif ($net < 0) {
                    $balanceService->carryForward($summary->member_id, abs($net));
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
}
