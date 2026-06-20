<?php

namespace App\Http\Requests\Mess;

use App\Support\ExpenseKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && $user->canManageMess();
    }

    public function rules(): array
    {
        $expectedKind = $this->routeIs('mess.expenses.fixed.store') || str_contains($this->path(), '/fixed') ? ExpenseKind::FIXED : ExpenseKind::BAZAR;

        return [
            'expense_category_id' => [
                'required', 'integer', 'exists:expense_categories,id',
                Rule::exists('expense_categories', 'id')->where('kind', $expectedKind),
            ],
            'date' => ['required', 'date'],
            'purchased_by' => [$expectedKind === ExpenseKind::BAZAR ? 'required' : 'nullable', 'integer', 'exists:members,id'],
            'vendor' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'amount' => ['required', 'numeric', 'min:0'],
            'receipt' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
