<?php

namespace App\Http\Requests\My;

use Illuminate\Foundation\Http\FormRequest;

class StoreMealOffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasRole('mess-member');
    }

    public function rules(): array
    {
        return [
            'from_date' => ['required', 'date', 'after_or_equal:today'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => __('Please provide a reason for the meal off request.'),
            'reason.min' => __('Reason must be at least 3 characters.'),
        ];
    }
}
