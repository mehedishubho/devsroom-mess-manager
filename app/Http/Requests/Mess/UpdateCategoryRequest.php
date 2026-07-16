<?php

namespace App\Http\Requests\Mess;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the Edit Category form (Task 2 — quick-260717-2q3).
 * Route-level `roles:admin,super-admin,manager` middleware already authorizes
 * access; the authorize() gate is a belt-and-braces check via canManageMess().
 */
class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->canManageMess();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
