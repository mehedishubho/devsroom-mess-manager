<?php

namespace App\Http\Controllers\Mess;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mess\StorePaymentRequest;
use App\Http\Requests\Mess\UpdatePaymentRequest;
use App\Models\Member;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Support\MemberStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $service) {}

    public function index(Request $request): View
    {
        $payments = $this->service->list($request);

        $members = Member::query()
            ->where('status', MemberStatus::ACTIVE)
            ->orderBy('name')
            ->pluck('name', 'id');

        $filters = $request->only(['member_id', 'method', 'from', 'to']);

        return view('mess.payments.index', compact('payments', 'members', 'filters'));
    }

    public function create(): View
    {
        $members = Member::query()
            ->where('status', MemberStatus::ACTIVE)
            ->orderBy('name')
            ->pluck('name', 'id');

        return view('mess.payments.create', compact('members'));
    }

    public function store(StorePaymentRequest $request): RedirectResponse
    {
        $this->service->create($request->validated());

        return redirect()
            ->route('mess.payments.index')
            ->with('success', __('Payment recorded.'));
    }

    public function show(Payment $payment): View
    {
        $payment->load(['member', 'enteredBy']);

        return view('mess.payments.show', compact('payment'));
    }

    public function edit(Payment $payment): View
    {
        $payment->load('member');
        $members = Member::query()
            ->where('status', MemberStatus::ACTIVE)
            ->orderBy('name')
            ->pluck('name', 'id');

        return view('mess.payments.edit', compact('payment', 'members'));
    }

    public function update(UpdatePaymentRequest $request, Payment $payment): RedirectResponse
    {
        $this->service->update($payment, $request->validated());

        return redirect()
            ->route('mess.payments.index')
            ->with('success', __('Payment updated.'));
    }

    public function destroy(Payment $payment): RedirectResponse
    {
        $payment->delete();

        return redirect()
            ->route('mess.payments.index')
            ->with('success', __('Payment removed.'));
    }
}
