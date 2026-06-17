<?php

namespace App\Http\Controllers\Mess;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mess\StoreMonthlyCorrectionRequest;
use App\Models\Member;
use App\Models\MonthlyClosing;
use App\Services\MonthlyCorrectionService;
use App\Support\MemberStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MonthlyCorrectionController extends Controller
{
    public function __construct(private readonly MonthlyCorrectionService $service) {}

    public function index(MonthlyClosing $closing): View
    {
        $closing->load(['corrections.member', 'corrections.enteredBy']);

        return view('mess.closings.corrections.index', compact('closing'));
    }

    public function create(MonthlyClosing $closing): View
    {
        $members = Member::query()
            ->where('status', MemberStatus::ACTIVE)
            ->orderBy('name')
            ->pluck('name', 'id');

        return view('mess.closings.corrections.create', compact('closing', 'members'));
    }

    public function store(StoreMonthlyCorrectionRequest $request, MonthlyClosing $closing): RedirectResponse
    {
        $this->service->create(
            $closing,
            (int) $request->validated('member_id'),
            (float) $request->validated('amount'),
            (string) $request->validated('reason'),
            (int) $request->validated('applied_to_year'),
            (int) $request->validated('applied_to_month'),
            (int) $request->user()->id,
        );

        return redirect()
            ->route('mess.closings.corrections.index', $closing)
            ->with('success', __('Correction recorded.'));
    }
}
