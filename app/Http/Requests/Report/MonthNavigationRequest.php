<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the year/month query params on month-scoped reports
 * (Monthly Report + Member Statement). Authorization is enforced by
 * the route middleware (`role:admin` + `EnsureMessExists`).
 */
class MonthNavigationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'year' => ['sometimes', 'integer', 'min:2000', 'max:2100'],
            'month' => ['sometimes', 'integer', 'min:1', 'max:12'],
        ];
    }
}
