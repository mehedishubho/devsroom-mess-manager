<?php

namespace App\Http\Requests\Setup;

use App\Services\InstallationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(InstallationService::class)->shouldRunSetup();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
