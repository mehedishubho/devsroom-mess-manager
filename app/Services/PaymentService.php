<?php

namespace App\Services;

use App\Models\Mess;
use App\Models\Payment;
use App\Support\PaymentType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class PaymentService
{
    /**
     * Manager-side paginated list with filters by member, method, and date range.
     */
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

    /**
     * Member-side paginated list of their own payments, latest first.
     */
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
        $payload = [
            'mess_id' => Mess::activeId(),
            'member_id' => $data['member_id'],
            'date' => $data['date'],
            'amount' => $data['amount'],
            'method' => $data['method'],
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'type' => $data['type'] ?? PaymentType::BILL_PAYMENT,
            'entered_by' => auth()->id(),
        ];

        return Payment::create($payload);
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
