<?php

namespace App\Http\Requests\My;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ChangeMyPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $rules = [
            'password' => ['required', 'confirmed', Password::min(8)],
        ];

        // Only require current_password if the user already has one set.
        // On first login (password_changed_at === null), skip the current
        // password check — the user was created with an auto-generated password
        // and may not remember it.
        if ($this->user()?->password_changed_at !== null) {
            $rules['current_password'] = ['required', 'current_password'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'current_password.current_password' => __('Your current password is incorrect.'),
            'password.confirmed' => __('The password confirmation does not match.'),
        ];
    }
}
