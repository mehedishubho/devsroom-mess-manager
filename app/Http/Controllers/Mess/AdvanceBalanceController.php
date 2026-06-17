<?php

namespace App\Http\Controllers\Mess;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mess\AdjustAdvanceBalanceRequest;
use App\Models\Member;
use App\Services\AdvanceBalanceService;
use App\Support\MemberStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdvanceBalanceController extends Controller
{
    public function __construct(private readonly AdvanceBalanceService $service) {}

    public function index(): View
    {
        $members = Member::query()
            ->where('status', MemberStatus::ACTIVE)
            ->with('advanceBalance')
            ->orderBy('name')
            ->get();

        return view('mess.advance-balances.index', compact('members'));
    }

    public function adjust(Member $member): View
    {
        $member->load('advanceBalance');

        return view('mess.advance-balances.adjust', compact('member'));
    }

    public function storeAdjust(AdjustAdvanceBalanceRequest $request, Member $member): RedirectResponse
    {
        $this->service->adjust(
            (int) $member->id,
            (float) $request->validated('amount'),
            (string) $request->validated('reason'),
            (int) $request->user()->id,
        );

        return redirect()
            ->route('mess.advance-balances.index')
            ->with('success', __('Balance adjusted for :name.', ['name' => $member->name]));
    }
}
