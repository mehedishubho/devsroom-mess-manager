<?php

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateMessRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && $user->hasRole('super-admin');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'monthly_rent' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'manager_contact' => ['nullable', 'string', 'max:255'],
            'meal_breakfast' => ['required', 'numeric', 'min:0', 'max:99.99'],
            'meal_lunch' => ['required', 'numeric', 'min:0', 'max:99.99'],
            'meal_dinner' => ['required', 'numeric', 'min:0', 'max:99.99'],
            'currency' => ['required', 'string', 'size:3'],
            'date_format' => ['required', Rule::in(['DD-MM-YYYY', 'MM-DD-YYYY', 'YYYY-MM-DD'])],
        ];
    }
}
