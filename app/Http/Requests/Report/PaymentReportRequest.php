<?php

namespace App\Http\Requests\Report;

use App\Support\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the GET filter params on the Payment Report (D-18). Sticky
 * in URL. The `method` enum is sourced from App\Support\PaymentMethod::ALL
 * to match the actual stored values.
 */
class PaymentReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'member_id' => ['nullable', 'integer', Rule::exists('members', 'id')],
            'method' => ['nullable', 'string', Rule::in(PaymentMethod::ALL)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'preset' => ['nullable', 'string', Rule::in(['this', 'last'])],
        ];
    }
}
