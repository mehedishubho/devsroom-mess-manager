<?php

namespace App\Http\Controllers\Mess;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mess\TriggerMonthCloseRequest;
use App\Jobs\CloseMonthJob;
use App\Models\MonthlyClosing;
use App\Services\BillPreviewService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MonthCloseController extends Controller
{
    public function __construct(private readonly BillPreviewService $service) {}

    public function index(): View
    {
        $now = Carbon::now();
        $year = $now->year;
        $month = $now->month;
        $preview = $this->service->preview($year, $month);
        $isClosed = MonthlyClosing::query()
            ->where('year', $year)
            ->where('month', $month)
            ->exists();

        return view('mess.close.index', compact('preview', 'year', 'month', 'isClosed'));
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
            ->route('mess.close.index')
            ->with('success', __('Closing dispatched. You will be notified when it completes.'));
    }
}
