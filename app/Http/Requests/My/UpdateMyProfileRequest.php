<?php

namespace App\Http\Requests\My;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMyProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasRole('user');
    }

    public function rules(): array
    {
        return [
            'emergency_contact' => ['nullable', 'string', 'max:100'],
            'photo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'photo.max' => __('Photo must be 2 MB or smaller.'),
        ];
    }
}
