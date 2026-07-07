<?php

namespace App\Http\Requests\Mess;

use Illuminate\Foundation\Http\FormRequest;

class StoreDisabledDayRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && $user->canManageMess();
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date', 'date_format:Y-m-d'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.date_format' => __('Please provide a valid date in YYYY-MM-DD format.'),
        ];
    }
}
