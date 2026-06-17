<?php

namespace App\Http\Controllers\My;

use App\Exports\MemberStatementExport;
use App\Exports\MonthlyReportExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\My\MyMonthNavigationRequest;
use App\Models\Member;
use App\Models\Mess;
use App\Services\MemberStatementService;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * MyReportExportController — member-side PDF + Excel exports (D-33, D-34).
 *
 * 4 methods: statementPdf/statementExcel (own statement), monthlyPdf/
 * monthlyExcel (aggregates-only monthly — D-19 structural enforcement:
 * the MonthlyReportExport is constructed with members=[] so peer rows
 * can never leave the server for a member request).
 *
 * SECURITY: Member identity is ALWAYS derived from $request->user()
 * ->getMemberOrNull() — there is NO {member} URL parameter (T-04-03-05).
 * A 403 is returned when the user has no Member record (no data to export).
 * All PDF renders set isRemoteEnabled=false (T-04-03-10).
 */
class MyReportExportController extends Controller
{
    public function __construct(
        private readonly MemberStatementService $statements,
        private readonly ReportService $reports,
    ) {}

    public function statementPdf(MyMonthNavigationRequest $request): Response
    {
        $member = $request->user()->getMemberOrNull();
        if (! $member) {
            abort(403, __('Your mess account is not set up.'));
        }

        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        $statement = $this->statements->forMember($member->id, $year, $month);
        $mess = Mess::findOrFail(Mess::activeId());

        $pdf = Pdf::loadView('my.reports.pdf.statement', [
            'statement' => $statement,
            'member' => $member,
            'mess' => $mess,
            'reportTitle' => __('My Statement'),
            'generatedAt' => now()->format('d-m-Y H:i'),
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', false);

        return $pdf->download($this->myStatementFilename($year, $month, 'pdf'));
    }

    public function statementExcel(MyMonthNavigationRequest $request): BinaryFileResponse
    {
        $member = $request->user()->getMemberOrNull();
        if (! $member) {
            abort(403, __('Your mess account is not set up.'));
        }

        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        $statement = $this->statements->forMember($member->id, $year, $month);

        return Excel::download(
            new MemberStatementExport($statement),
            $this->myStatementFilename($year, $month, 'xlsx')
        );
    }

    public function monthlyPdf(MyMonthNavigationRequest $request): Response
    {
        $member = $request->user()->getMemberOrNull();
        if (! $member) {
            abort(403, __('Your mess account is not set up.'));
        }

        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        // D-19: the PDF view OMITS the per-member table — only totals render.
        $data = $this->reports->monthlyReport($year, $month);
        $mess = Mess::findOrFail(Mess::activeId());
        $period = Carbon::create($year, $month, 1)->translatedFormat('F Y');

        $pdf = Pdf::loadView('my.reports.pdf.monthly', [
            'data' => $data,
            'mess' => $mess,
            'period' => $period,
            'reportTitle' => __('Monthly Report'),
            'generatedAt' => now()->format('d-m-Y H:i'),
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', false);

        return $pdf->download($this->myMonthlyFilename($year, $month, 'pdf'));
    }

    public function monthlyExcel(MyMonthNavigationRequest $request): BinaryFileResponse
    {
        $member = $request->user()->getMemberOrNull();
        if (! $member) {
            abort(403, __('Your mess account is not set up.'));
        }

        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        // T-04-03-06 / D-19 STRUCTURAL enforcement: members[] emptied so
        // peer rows can never leave the server for a member request.
        $data = $this->reports->monthlyReport($year, $month);
        $data['members'] = [];

        return Excel::download(
            new MonthlyReportExport($data),
            $this->myMonthlyFilename($year, $month, 'xlsx')
        );
    }

    private function myStatementFilename(int $year, int $month, string $ext): string
    {
        $monthStr = str_pad((string) $month, 2, '0', STR_PAD_LEFT);

        return "my-statement-{$year}-{$monthStr}.{$ext}";
    }

    private function myMonthlyFilename(int $year, int $month, string $ext): string
    {
        $monthStr = str_pad((string) $month, 2, '0', STR_PAD_LEFT);

        return "monthly-report-{$year}-{$monthStr}.{$ext}";
    }
}
