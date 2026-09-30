<?php

namespace App\Http\Controllers\Mess;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mess\TriggerMonthCloseRequest;
use App\Jobs\CloseMonthJob;
use App\Models\MonthlyClosing;
use App\Services\BillPreviewService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MonthCloseController extends Controller
{
    public function __construct(private readonly BillPreviewService $service) {}

    public function index(Request $request): View
    {
        // The month a manager closes is the one that just ended — default to
        // the previous calendar month (opening /mess/close on Oct 1 targets
        // September). Any other month is reachable through the month picker.
        $default = Carbon::now()->subMonthNoOverflow();
        $year = (int) $request->query('year', $default->year);
        $month = (int) $request->query('month', $default->month);

        // Garbage input falls back to the default target (same pattern as
        // BillPreviewController::resolveYearMonth). Future months are never
        // closable — there is nothing to snapshot yet.
        if ($year < 2000 || $year > 2100) {
            $year = $default->year;
        }
        if ($month < 1 || $month > 12) {
            $month = $default->month;
        }
        if (Carbon::create($year, $month, 1)->startOfMonth()->greaterThan(Carbon::now()->startOfMonth())) {
            $year = $default->year;
            $month = $default->month;
        }

        $preview = $this->service->preview($year, $month);
        $isClosed = MonthlyClosing::query()
            ->where('year', $year)
            ->where('month', $month)
            ->exists();

        // A dispatched close still sitting in the jobs table means no queue
        // worker picked it up — surface that instead of looking "dead".
        $closeQueued = DB::table('jobs')
            ->where('payload', 'like', '%CloseMonthJob%')
            ->exists();

        return view('mess.close.index', compact('preview', 'year', 'month', 'isClosed', 'closeQueued'));
    }

    public function trigger(TriggerMonthCloseRequest $request): RedirectResponse
    {
        $year = (int) $request->validated('year');
        $month = (int) $request->validated('month');

        // Idempotency pre-check (D-18): if a closing already exists, do not dispatch.
        $existing = MonthlyClosing::query()
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        if ($existing) {
            return redirect()
                ->route('mess.closings.show', $existing)
                ->with('info', __('This month is already closed.'));
        }

        CloseMonthJob::dispatch($year, $month, (int) $request->user()->id);

        return redirect()
            ->route('mess.close.index', ['year' => $year, 'month' => $month])
            ->with('success', __('Closing dispatched. You will be notified when it completes.'));
    }
}
