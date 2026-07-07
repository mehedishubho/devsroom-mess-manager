<?php

namespace App\Http\Requests\Mess;

use Illuminate\Foundation\Http\FormRequest;

class BulkSaveMonthlyMealsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && $user->canManageMess();
    }

    public function rules(): array
    {
        return [
            'month' => ['required', 'date_format:Y-m'],
            'entries' => ['nullable', 'array'],
            'entries.*.member_id' => ['required', 'integer', 'exists:members,id'],
            'entries.*.date' => ['required', 'date_format:Y-m-d'],
            'entries.*.breakfast' => ['nullable', 'boolean'],
            'entries.*.lunch' => ['nullable', 'boolean'],
            'entries.*.dinner' => ['nullable', 'boolean'],
        ];
    }
}
