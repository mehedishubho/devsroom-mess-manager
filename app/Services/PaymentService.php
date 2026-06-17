<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Mess;
use App\Models\Payment;
use App\Support\NotificationType;
use App\Support\PaymentType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class PaymentService
{
    public function __construct(
        private readonly AdvanceBalanceService $balances,
        private readonly NotificationService $notifications,
    ) {}

    public function list(Request $request): LengthAwarePaginator
    {
        $query = Payment::query()
            ->with(['member', 'enteredBy'])
            ->latest('date')
            ->latest('id');

        if ($memberId = $request->query('member_id')) {
            $query->where('member_id', (int) $memberId);
        }

        if ($method = $request->query('method')) {
            $query->where('method', $method);
        }

        if ($from = $request->query('from')) {
            $query->whereDate('date', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('date', '<=', $to);
        }

        return $query->paginate(50)->withQueryString();
    }

    public function listForMember(int $memberId, int $perPage = 30): LengthAwarePaginator
    {
        return Payment::query()
            ->where('member_id', $memberId)
            ->with('enteredBy')
            ->latest('date')
            ->latest('id')
            ->paginate($perPage);
    }

    public function create(array $data): Payment
    {
        $payment = Payment::create([
            'mess_id' => Mess::activeId(),
            'member_id' => $data['member_id'],
            'date' => $data['date'],
            'amount' => $data['amount'],
            'method' => $data['method'],
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'type' => $data['type'] ?? PaymentType::BILL_PAYMENT,
            'entered_by' => auth()->id(),
        ]);

        $this->balances->applyPayment($payment);

        // NOTIF-03: notify the member that a payment was recorded for them.
        $member = Member::find($payment->member_id);
        if ($member?->user_id) {
            $this->notifications->send($member->user, NotificationType::PAYMENT_RECORDED, [
                'payment_id' => $payment->id,
                'type' => $payment->type,
                'amount' => (float) $payment->amount,
                'method' => $payment->method,
                'date' => $payment->date,
            ]);
        }

        return $payment;
    }

    public function update(Payment $payment, array $data): Payment
    {
        $payment->update([
            'member_id' => $data['member_id'],
            'date' => $data['date'],
            'amount' => $data['amount'],
            'method' => $data['method'],
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'type' => $data['type'] ?? $payment->type,
        ]);

        return $payment->refresh();
    }
}
