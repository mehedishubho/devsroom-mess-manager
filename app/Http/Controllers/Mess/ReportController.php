<?php

namespace App\Http\Controllers\Mess;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\ExpenseReportRequest;
use App\Http\Requests\Report\MemberStatementRequest;
use App\Http\Requests\Report\MonthNavigationRequest;
use App\Http\Requests\Report\PaymentReportRequest;
use App\Models\ExpenseCategory;
use App\Models\Member;
use App\Models\Mess;
use App\Services\MemberStatementService;
use App\Services\ReportService;
use App\Support\MemberStatus;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly MemberStatementService $statements,
    ) {}

    public function monthly(MonthNavigationRequest $request): View
    {
        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        $data = $this->reports->monthlyReport($year, $month);
        $mess = Mess::findOrFail(Mess::activeId());

        $monthRange = $this->reports->availableMonthRange(Mess::activeId());

        return view('mess.reports.monthly', compact('data', 'year', 'month', 'mess', 'monthRange'));
    }

    public function memberStatement(MemberStatementRequest $request)
    {
        // Task 5 (quick-260717-2q3) — fix the 404 the manager hit when the
        // sidebar link had no ?member_id. Auto-pick the first active member
        // of the active mess; if none exists, render an empty-state 200.
        $member = null;
        $requestedId = $request->integer('member_id');

        if ($requestedId) {
            // Cross-mess protection: MessScope auto-filters Member queries by
            // the active mess_id; a foreign member_id resolves to null here
            // (no firstOrFail — we fall through to auto-pick so the manager
            // never gets a 404 from a stale bookmark).
            $member = Member::where('id', $requestedId)->first();
        }

        if (! $member) {
            $member = Member::query()
                ->where('status', MemberStatus::ACTIVE)
                ->orderBy('name')
                ->first();
        }

        if (! $member) {
            // Empty-state 200 — the mess has zero active members yet.
            $mess = Mess::findOrFail(Mess::activeId());

            return view('mess.reports.member-statement-empty', compact('mess'));
        }

        // If we auto-picked (no member_id in URL OR the requested one was
        // cross-mess / missing), redirect to the same route WITH member_id
        // so the URL is shareable and the month-nav builds correct links.
        if (! $request->has('member_id') || (int) $request->integer('member_id') !== (int) $member->id) {
            return redirect()->route('mess.reports.member-statement', array_merge(
                ['member_id' => $member->id],
                $request->only(['year', 'month']),
            ));
        }

        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        $statement = $this->statements->forMember($member->id, $year, $month);

        $members = Member::query()
            ->whereIn('status', [MemberStatus::ACTIVE, MemberStatus::FORMER])
            ->orderBy('name')
            ->get(['id', 'name']);

        $mess = Mess::findOrFail(Mess::activeId());

        $monthRange = $this->reports->availableMonthRange(Mess::activeId());

        return view('mess.reports.member-statement', compact('statement', 'member', 'members', 'year', 'month', 'mess', 'monthRange'));
    }

    public function expenses(ExpenseReportRequest $request): View
    {
        $filters = $request->validated();
        $report = $this->reports->expenseReport($filters);

        $categories = ExpenseCategory::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('mess.reports.expenses', compact('report', 'filters', 'categories'));
    }

    public function payments(PaymentReportRequest $request): View
    {
        $filters = $request->validated();
        $report = $this->reports->paymentReport($filters);

        $members = Member::query()
            ->whereIn('status', [MemberStatus::ACTIVE, MemberStatus::FORMER])
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('mess.reports.payments', compact('report', 'filters', 'members'));
    }
}
