<?php

namespace App\Http\Requests\Mess;

use App\Models\Member;
use App\Support\PaymentMethod;
use App\Support\PaymentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && $user->canManageMess();
    }

    public function rules(): array
    {
        $method = $this->input('method', PaymentMethod::CASH);
        $isCash = $method === PaymentMethod::CASH;

        return [
            'member_id' => ['required', 'integer', Rule::exists((new Member)->getTable(), 'id')],
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999.99'],
            'method' => ['required', Rule::in(PaymentMethod::ALL)],
            'reference' => [$isCash ? 'nullable' : 'required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', Rule::in(PaymentType::ALL)],
        ];
    }

    public function messages(): array
    {
        return [
            'reference.required' => __('Reference is required for non-cash payments.'),
        ];
    }
}
