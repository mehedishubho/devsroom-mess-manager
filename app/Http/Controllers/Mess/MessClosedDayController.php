<?php

namespace App\Http\Controllers\Mess;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mess\StoreClosedDayRequest;
use App\Models\Mess;
use App\Models\MessClosedDay;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MessClosedDayController extends Controller
{
    public function index(Request $request): View
    {
        $month = $request->filled('month')
            ? Carbon::parse($request->query('month'))
            : Carbon::now();

        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $closedDays = MessClosedDay::query()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('date')
            ->get()
            ->keyBy(fn ($d) => $d->date->toDateString());

        $messId = Mess::activeId();

        return view('mess.closed-days.index', compact('closedDays', 'month', 'messId'));
    }

    public function store(StoreClosedDayRequest $request): RedirectResponse
    {
        $messId = Mess::activeId();
        $date = $request->validated('date');

        MessClosedDay::firstOrCreate(
            [
                'mess_id' => $messId,
                'date' => $date,
            ],
            [
                'reason' => $request->validated('reason'),
            ]
        );

        $month = Carbon::parse($date)->format('Y-m');

        return redirect()
            ->route('mess.closed-days.index', ['month' => $month])
            ->with('success', __(':date marked as a closed day.', ['date' => $date]));
    }

    public function destroy(MessClosedDay $closedDay): RedirectResponse
    {
        $date = $closedDay->date->format('Y-m-d');
        $month = $closedDay->date->format('Y-m');
        $closedDay->delete();

        return redirect()
            ->route('mess.closed-days.index', ['month' => $month])
            ->with('success', __(':date re-opened.', ['date' => $date]));
    }
}
