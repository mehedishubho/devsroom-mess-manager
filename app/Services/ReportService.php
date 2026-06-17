<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Mess;
use App\Models\MonthlyClosing;
use App\Models\MonthlyMemberSummary;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * ReportService — manager-side read-only reports.
 *
 * - monthlyReport(): D-26 switch between live compute (BillPreviewService)
 *   for open/never-closed months and the immutable MonthlyMemberSummary
 *   snapshot for closed months. Returns the SAME top-level shape as
 *   BillPreviewService::preview() so views can treat them interchangeably,
 *   plus a `source` key ('live' | 'snapshot') for the closed-month badge.
 *
 * - expenseReport() / paymentReport(): paginated direct queries with
 *   validated GET filters (D-18 sticky querystring).
 *
 * NOTE (CR-03 / Pitfall 3): the snapshot's `advance_applied` column is
 * misnamed — it holds bill-payment-type payments, not advance deposits
 * consumed. We never expose `advance_applied` to the view; we surface it
 * as `bill_payments` to mirror BillPreviewService's live shape.
 */
class ReportService
{
    public function __construct(
        private readonly BillPreviewService $preview,
    ) {}

    /**
     * @return array{year:int,month:int,total_bazar:float,total_meals:float,meal_rate:float,total_fixed:float,days_in_month:int,members:array<int,array<string,mixed>>,source:string}
     */
    public function monthlyReport(int $year, int $month): array
    {
        $messId = Mess::activeId();

        $closing = MonthlyClosing::query()
            ->where('mess_id', $messId)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        if ($closing) {
            return $this->monthlyFromSnapshot($closing, $year, $month);
        }

        $live = $this->preview->preview($year, $month);
        $live['source'] = 'live';

        return $live;
    }

    /**
     * Assemble the same shape BillPreviewService::preview() returns, but
     * read from the immutable MonthlyMemberSummary snapshot rows.
     *
     * @return array{year:int,month:int,total_bazar:float,total_meals:float,meal_rate:float,total_fixed:float,days_in_month:int,members:array<int,array<string,mixed>>,source:string}
     */
    private function monthlyFromSnapshot(MonthlyClosing $closing, int $year, int $month): array
    {
        $rows = MonthlyMemberSummary::query()
            ->where('monthly_closing_id', $closing->id)
            ->with('member:id,name,status')
            ->orderBy('member_id')
            ->get();

        $members = [];
        foreach ($rows as $row) {
            // Map snapshot columns to the BillPreviewService live-row keys
            // verbatim. `advance_applied` is renamed to `bill_payments` on
            // the way out (Pitfall 3) — they hold the same value.
            $members[] = [
                'member_id' => $row->member_id,
                'name' => $row->member?->name ?? (string) $row->member_id,
                'meals' => (float) $row->total_meals,
                'meal_cost' => (float) $row->meal_cost,
                'fixed_share' => (float) $row->fixed_cost_share,
                'guest_total' => (float) $row->guest_meal_charge,
                'bill' => (float) $row->gross_bill,
                'bill_payments' => (float) $row->advance_applied,
                'advance_payments' => 0.0, // not tracked on the snapshot
                'advance_applied' => (float) $row->advance_applied, // kept for parity; views MUST NOT display this
                'due' => (float) $row->balance_due,
                'advance_balance' => 0.0, // snapshot does not carry carried-forward balance
                'due_balance' => (float) $row->balance_due,
                'active_days' => 0,
                'status' => $row->member?->status ?? 'active',
            ];
        }

        return [
            'year' => $year,
            'month' => $month,
            'total_bazar' => (float) $closing->total_bazar,
            'total_meals' => (float) $closing->total_meals,
            'meal_rate' => (float) $closing->meal_rate,
            'total_fixed' => (float) $closing->total_fixed_expense,
            'days_in_month' => Carbon::create($year, $month, 1)->daysInMonth,
            'members' => $members,
            'source' => 'snapshot',
        ];
    }

    /**
     * @param  array<string,mixed>  $filters
     * @return array{rows:LengthAwarePaginator<mixed>,totals:array{amount:float}}
     */
    public function expenseReport(array $filters): array
    {
        $query = Expense::query()
            ->with(['category:id,name,kind', 'purchasedByMember:id,name', 'enteredBy:id,name'])
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('date', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->where('date', '<=', $d))
            ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('expense_category_id', $id))
            ->when($filters['month'] ?? null, function ($q, $m) {
                [$y, $mo] = array_map('intval', explode('-', (string) $m));
                $q->whereYear('date', $y)->whereMonth('date', $mo);
            })
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc');

        $rows = $query->paginate(50)->appends(request()->query());
        $totals = ['amount' => (float) (clone $query)->sum('amount')];

        return compact('rows', 'totals');
    }

    /**
     * @param  array<string,mixed>  $filters
     * @return array{rows:LengthAwarePaginator<mixed>,totals:array{amount:float}}
     */
    public function paymentReport(array $filters): array
    {
        $query = Payment::query()
            ->with(['member:id,name', 'enteredBy:id,name'])
            ->when($filters['member_id'] ?? null, fn ($q, $id) => $q->where('member_id', $id))
            ->when($filters['method'] ?? null, fn ($q, $m) => $q->where('method', $m))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('date', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->where('date', '<=', $d))
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc');

        $rows = $query->paginate(50)->appends(request()->query());
        $totals = ['amount' => (float) (clone $query)->sum('amount')];

        return compact('rows', 'totals');
    }
}
