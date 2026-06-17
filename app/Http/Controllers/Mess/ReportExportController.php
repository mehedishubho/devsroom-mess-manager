<?php

namespace App\Http\Controllers\Mess;

use App\Exports\ExpenseReportExport;
use App\Exports\MemberStatementExport;
use App\Exports\MonthlyReportExport;
use App\Exports\PaymentReportExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Report\ExpenseReportRequest;
use App\Http\Requests\Report\MemberStatementRequest;
use App\Http\Requests\Report\MonthNavigationRequest;
use App\Http\Requests\Report\PaymentReportRequest;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Member;
use App\Models\Mess;
use App\Models\Payment;
use App\Services\MemberStatementService;
use App\Services\ReportService;
use App\Support\MemberStatus;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * ReportExportController — PDF + Excel exports for all 4 manager reports.
 *
 * 8 methods: monthlyPdf/monthlyExcel, memberStatementPdf/memberStatementExcel,
 * expensesPdf/expensesExcel, paymentsPdf/paymentsExcel.
 *
 * Security:
 *  - All routes live inside ['auth', 'role:admin', EnsureMessExists] (T-04-03-07).
 *  - member-statement export uses ?member_id=X query param; Member::firstOrFail()
 *    triggers MessScope → 404 for cross-mess members (T-04-03-08).
 *  - All filenames derived from user-controlled input pass through safeFilename()
 *    which strips /, \, .., control chars (T-04-03-04 — path traversal /
 *    header injection prevention).
 *  - PDF render always sets isRemoteEnabled=false (T-04-03-10 — no SSRF).
 */
class ReportExportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly MemberStatementService $statements,
    ) {}

    public function monthlyPdf(MonthNavigationRequest $request): Response
    {
        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        $data = $this->reports->monthlyReport($year, $month);
        $mess = Mess::findOrFail(Mess::activeId());
        $period = Carbon::create($year, $month, 1)->translatedFormat('F Y');

        $pdf = Pdf::loadView('mess.reports.pdf.monthly', [
            'data' => $data,
            'mess' => $mess,
            'period' => $period,
            'reportTitle' => __('Monthly Report'),
            'generatedAt' => now()->format('d-m-Y H:i'),
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', false);

        $monthStr = str_pad((string) $month, 2, '0', STR_PAD_LEFT);

        return $pdf->download($this->safeFilename("monthly-report-{$year}-{$monthStr}").'.pdf');
    }

    public function monthlyExcel(MonthNavigationRequest $request): BinaryFileResponse
    {
        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        $data = $this->reports->monthlyReport($year, $month);
        $monthStr = str_pad((string) $month, 2, '0', STR_PAD_LEFT);

        return Excel::download(
            new MonthlyReportExport($data),
            $this->safeFilename("monthly-report-{$year}-{$monthStr}").'.xlsx'
        );
    }

    public function memberStatementPdf(MemberStatementRequest $request): Response
    {
        // Cross-mess protection (T-04-03-08): MessScope auto-filters → 404.
        $member = Member::where('id', $request->integer('member_id'))->firstOrFail();
        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        $statement = $this->statements->forMember($member->id, $year, $month);
        $mess = Mess::findOrFail(Mess::activeId());

        $pdf = Pdf::loadView('mess.reports.pdf.member-statement', [
            'statement' => $statement,
            'member' => $member,
            'mess' => $mess,
            'reportTitle' => __('Member Statement'),
            'generatedAt' => now()->format('d-m-Y H:i'),
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', false);

        return $pdf->download($this->statementFilename($member, $year, $month, 'pdf'));
    }

    public function memberStatementExcel(MemberStatementRequest $request): BinaryFileResponse
    {
        $member = Member::where('id', $request->integer('member_id'))->firstOrFail();
        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        $statement = $this->statements->forMember($member->id, $year, $month);

        return Excel::download(
            new MemberStatementExport($statement),
            $this->statementFilename($member, $year, $month, 'xlsx')
        );
    }

    public function expensesPdf(ExpenseReportRequest $request): Response
    {
        $filters = $request->validated();
        $messId = Mess::activeId();
        $mess = Mess::findOrFail($messId);

        // Re-query without pagination for the full export
        $rows = $this->expenseRowsForExport($messId, $filters);
        $categories = ExpenseCategory::query()->orderBy('name')->get(['id', 'name']);

        $pdf = Pdf::loadView('mess.reports.pdf.expenses', [
            'rows' => $rows,
            'filters' => $filters,
            'categories' => $categories,
            'mess' => $mess,
            'reportTitle' => __('Expense Report'),
            'generatedAt' => now()->format('d-m-Y H:i'),
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', false);

        return $pdf->download($this->filterFilename('expenses', $filters).'.pdf');
    }

    public function expensesExcel(ExpenseReportRequest $request): BinaryFileResponse
    {
        $filters = $request->validated();
        $messId = Mess::activeId();

        return Excel::download(
            new ExpenseReportExport(
                $messId,
                $filters['from'] ?? null,
                $filters['to'] ?? null,
                $filters['category_id'] ?? null,
                $filters['month'] ?? null,
            ),
            $this->filterFilename('expenses', $filters).'.xlsx'
        );
    }

    public function paymentsPdf(PaymentReportRequest $request): Response
    {
        $filters = $request->validated();
        $messId = Mess::activeId();
        $mess = Mess::findOrFail($messId);

        $rows = $this->paymentRowsForExport($messId, $filters);
        $members = Member::query()->whereIn('status', [MemberStatus::ACTIVE, MemberStatus::FORMER])
            ->orderBy('name')->get(['id', 'name']);

        $pdf = Pdf::loadView('mess.reports.pdf.payments', [
            'rows' => $rows,
            'filters' => $filters,
            'members' => $members,
            'mess' => $mess,
            'reportTitle' => __('Payment Report'),
            'generatedAt' => now()->format('d-m-Y H:i'),
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', false);

        return $pdf->download($this->filterFilename('payments', $filters).'.pdf');
    }

    public function paymentsExcel(PaymentReportRequest $request): BinaryFileResponse
    {
        $filters = $request->validated();
        $messId = Mess::activeId();

        return Excel::download(
            new PaymentReportExport(
                $messId,
                $filters['from'] ?? null,
                $filters['to'] ?? null,
                $filters['member_id'] ?? null,
                $filters['method'] ?? null,
            ),
            $this->filterFilename('payments', $filters).'.xlsx'
        );
    }

    /**
     * Fetch all expense rows matching filters (no pagination) for PDF export.
     *
     * @param  array<string,mixed>  $filters
     * @return Collection<int,Expense>
     */
    private function expenseRowsForExport(int $messId, array $filters)
    {
        return Expense::query()
            ->where('mess_id', $messId)
            ->with(['category:id,name,kind', 'purchasedByMember:id,name'])
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('date', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->where('date', '<=', $d))
            ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('expense_category_id', $id))
            ->when($filters['month'] ?? null, function ($q, $m) {
                [$y, $mo] = array_map('intval', explode('-', (string) $m));
                $q->whereYear('date', $y)->whereMonth('date', $mo);
            })
            ->orderBy('date')
            ->get();
    }

    /**
     * Fetch all payment rows matching filters (no pagination) for PDF export.
     *
     * @param  array<string,mixed>  $filters
     * @return Collection<int,Payment>
     */
    private function paymentRowsForExport(int $messId, array $filters)
    {
        return Payment::query()
            ->where('mess_id', $messId)
            ->with(['member:id,name'])
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('date', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->where('date', '<=', $d))
            ->when($filters['member_id'] ?? null, fn ($q, $id) => $q->where('member_id', $id))
            ->when($filters['method'] ?? null, fn ($q, $m) => $q->where('method', $m))
            ->orderBy('date')
            ->get();
    }

    /**
     * Build a sanitized filename for the member-statement export.
     */
    private function statementFilename(Member $member, int $year, int $month, string $ext): string
    {
        $monthStr = str_pad((string) $month, 2, '0', STR_PAD_LEFT);
        $slug = Str::slug($member->name) ?: 'member';
        $slug = $this->safeFilename($slug);

        return "member-statement-{$slug}-{$year}-{$monthStr}.{$ext}";
    }

    /**
     * Build a sanitized filename for the expenses/payments exports.
     *
     * @param  array<string,mixed>  $filters
     */
    private function filterFilename(string $base, array $filters): string
    {
        $from = $filters['from'] ?? 'all';
        $to = $filters['to'] ?? 'now';

        return $this->safeFilename("{$base}-{$from}-to-{$to}");
    }

    /**
     * Sanitize a filename base — strip path separators, parent-dir refs,
     * and control chars (T-04-03-04). Replaces any run of non letter/digit/
     * dash/underscore chars with a single dash. Empty result falls back to
     * 'export'.
     *
     * Uses '~' as the regex delimiter (NOT '/') because the rule explicitly
     * matches forward-slash path separators — using '/' as delimiter would
     * force escaping and risk parser ambiguity.
     */
    private function safeFilename(string $base): string
    {
        // Strip parent-dir sequences + path separators (forward + back).
        $cleaned = preg_replace('~\.\.+|[\\/]+~u', '-', $base);
        // Replace any run of chars outside letters/digits/dash/underscore.
        $cleaned = preg_replace('~[^\p{L}\p{N}\-_]+~u', '-', (string) $cleaned);
        $cleaned = trim((string) $cleaned, '-');
        if ($cleaned === '') {
            return 'export';
        }

        return $cleaned;
    }
}
