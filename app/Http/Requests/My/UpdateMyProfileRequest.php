<?php

namespace App\Http\Requests\My;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateMyProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasRole('mess-member');
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:30', 'regex:/^(01)[3-9]\d{8}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'emergency_contact' => ['nullable', 'string', 'max:100'],
            'current_password' => ['nullable', 'required_with:new_password', 'current_password'],
            'new_password' => ['nullable', 'confirmed', Password::min(8)],
            'photo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.regex' => __('Mobile must be a valid BD number (e.g. 01700000000).'),
            'current_password.required_with' => __('Please enter your current password to set a new one.'),
            'current_password.current_password' => __('Current password is incorrect.'),
            'photo.max' => __('Photo must be 2 MB or smaller.'),
        ];
    }
}
