<?php

namespace App\Http\Controllers\My;

use App\Http\Controllers\Controller;
use App\Http\Requests\My\MyMonthNavigationRequest;
use App\Models\Mess;
use App\Services\MemberStatementService;
use App\Services\ReportService;
use Illuminate\View\View;

/**
 * MyReportController — member-side read-only reports (RPT-05, RPT-06).
 *
 * SECURITY: Member identity is ALWAYS derived from $request->user()->getMemberOrNull().
 * There is NO `{member}` URL parameter on any role:user route — IDOR is
 * structurally impossible (T-04-02-01). Any `?member_id=` query param in the
 * URL is IGNORED by these controllers.
 *
 * Reuses Plan 4.1's MemberStatementService::forMember() verbatim for the
 * own-statement route and ReportService::monthlyReport() for the aggregates-
 * only monthly route (the member monthly view OMITS the per-member table — D-19).
 */
class MyReportController extends Controller
{
    public function __construct(
        private readonly MemberStatementService $statements,
        private readonly ReportService $reports,
    ) {}

    /**
     * Member's own statement (RPT-05, D-21, D-22). Shows the same 8-section
     * ledger as the manager view, scoped to the authenticated member.
     */
    public function statement(MyMonthNavigationRequest $request): View
    {
        $member = $request->user()->getMemberOrNull();

        if (! $member) {
            return view('my.no-member');
        }

        $year = (int) $request->integer('year', now()->year);
        $month = (int) $request->integer('month', now()->month);

        // SECURITY: NO member_id from URL. $member always comes from auth.
        $statement = $this->statements->forMember($member->id, $year, $month);
        $monthRange = $this->reports->availableMonthRange(Mess::activeId());

        return view('my.reports.statement', [
            'statement' => $statement,
            'member' => $member,
            'year' => $year,
            'month' => $month,
            'monthRange' => $monthRange,
        ]);
    }

    /**
     * Aggregates-only Monthly Report (RPT-06, D-19). The underlying
     * ReportService returns the full shape incl. members[], but the view
     * OMITS the per-member table — only aggregate totals are displayed.
     */
    public function monthly(MyMonthNavigationRequest $request): View
    {
        $member = $request->user()->getMemberOrNull();

        if (! $member) {
            return view('my.no-member');
        }

        $year = (int) $request->integer('year', now()->year);
        $month = (int) $request->integer('month', now()->month);

        $data = $this->reports->monthlyReport($year, $month);
        $monthRange = $this->reports->availableMonthRange(Mess::activeId());

        return view('my.reports.monthly', [
            'data' => $data,
            'year' => $year,
            'month' => $month,
            'monthRange' => $monthRange,
        ]);
    }
}
