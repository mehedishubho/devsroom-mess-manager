<?php

namespace App\Http\Requests\Mess;

use App\Support\ExpenseKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && ($user->hasRole('admin') || $user->hasRole('super-admin'));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'kind' => ['required', Rule::in(ExpenseKind::ALL)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
