<?php

namespace App\Http\Controllers\Mess;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mess\StoreDisabledDayRequest;
use App\Models\Member;
use App\Models\MemberDisabledDay;
use App\Models\Mess;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MemberDisabledDayController extends Controller
{
    public function index(Request $request, Member $member): View
    {
        $month = $request->filled('month')
            ? Carbon::parse($request->query('month'))
            : Carbon::now();

        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $disabledDays = MemberDisabledDay::query()
            ->where('member_id', $member->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('date', 'desc')
            ->get();

        $messId = Mess::activeId();

        return view('mess.members.disabled-days.index', compact('member', 'disabledDays', 'month', 'messId'));
    }

    public function store(StoreDisabledDayRequest $request, Member $member): RedirectResponse
    {
        $messId = Mess::activeId();
        $date = $request->validated('date');

        MemberDisabledDay::firstOrCreate(
            [
                'mess_id' => $messId,
                'member_id' => $member->id,
                'date' => $date,
            ],
            [
                'reason' => $request->validated('reason'),
                'entered_by' => auth()->id(),
            ]
        );

        $month = Carbon::parse($date)->format('Y-m');

        return redirect()
            ->route('mess.members.disabled-days.index', ['member' => $member, 'month' => $month])
            ->with('success', __('Day disabled for :name.', ['name' => $member->name]));
    }

    public function destroy(Member $member, MemberDisabledDay $disabledDay): RedirectResponse
    {
        $month = $disabledDay->date->format('Y-m');
        $date = $disabledDay->date->format('Y-m-d');
        $disabledDay->delete();

        return redirect()
            ->route('mess.members.disabled-days.index', ['member' => $member, 'month' => $month])
            ->with('success', __('Day re-enabled for :name on :date.', ['name' => $member->name, 'date' => $date]));
    }
}
