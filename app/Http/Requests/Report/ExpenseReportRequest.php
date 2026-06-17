<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the GET filter params on the Expense Report (D-18). Sticky
 * in URL. Mess-scoping is applied by the BelongsToActiveMess global
 * scope on the Expense model + ExpenseCategory.
 */
class ExpenseReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'category_id' => ['nullable', 'integer', Rule::exists('expense_categories', 'id')],
            'month' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'preset' => ['nullable', 'string', Rule::in(['this', 'last'])],
        ];
    }
}
