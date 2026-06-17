<?php

namespace App\Http\Requests\My;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the year/month query params on member-side month-scoped
 * reports (My Statement + My Monthly Report). Authorization is enforced
 * by the route middleware (`role:user`); the controller derives the
 * member from `$request->user()->getMemberOrNull()` — NEVER from the URL.
 */
class MyMonthNavigationRequest extends FormRequest
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
