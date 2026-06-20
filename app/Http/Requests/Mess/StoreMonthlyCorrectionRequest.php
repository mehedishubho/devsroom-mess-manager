<?php

namespace App\Http\Requests\Mess;

use Illuminate\Foundation\Http\FormRequest;

class StoreMonthlyCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && $user->canManageMess();
    }

    public function rules(): array
    {
        return [
            'member_id' => ['required', 'integer', 'exists:members,id'],
            'amount' => ['required', 'numeric', 'not_in:0', 'min:-9999999.99', 'max:9999999.99'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'applied_to_year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'applied_to_month' => ['required', 'integer', 'min:1', 'max:12'],
        ];
    }
}
