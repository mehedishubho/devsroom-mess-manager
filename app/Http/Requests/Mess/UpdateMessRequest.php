<?php

namespace App\Http\Requests\Mess;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMessRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && $user->canManageMess();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'monthly_rent' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'manager_contact' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            // Per-meal weights (full = 1, half = 0.5). Optional — controller
            // falls back to defaults when omitted, so existing callers keep working.
            'meal_breakfast' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'meal_lunch' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'meal_dinner' => ['nullable', 'numeric', 'min:0', 'max:10'],
        ];
    }
}
