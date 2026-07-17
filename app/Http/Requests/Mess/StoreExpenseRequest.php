<?php

namespace App\Http\Requests\Mess;

use App\Models\ExpenseCategory;
use App\Models\Mess;
use App\Support\ExpenseKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates the unified Add Expense form (Task 6 — quick-260717-2q3).
 *
 * The expense's kind is derived from the chosen category's kind (not the
 * URL path). The category MUST belong to the active mess (MessScope
 * excludes a foreign-mess category id from the exists rule).
 *
 * Bazar-kind categories require a purchased_by member; fixed/other kinds
 * do not. The after() callback resolves the chosen category server-side
 * and applies the conditional rule — JS is for UX only.
 */
class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && $user->canManageMess();
    }

    public function rules(): array
    {
        return [
            'expense_category_id' => [
                'required',
                'integer',
                Rule::exists('expense_categories', 'id')->where(
                    fn ($q) => $q->where('mess_id', Mess::activeId())
                ),
            ],
            'date' => ['required', 'date'],
            'purchased_by' => ['nullable', 'integer', 'exists:members,id'],
            'vendor' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'amount' => ['required', 'numeric', 'min:0'],
            'receipt' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    /**
     * Bazar-kind categories require purchased_by. Server-side check is the
     * source of truth — the Alpine toggle in the view is for UX only.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $categoryId = (int) $this->input('expense_category_id');
                $category = ExpenseCategory::find($categoryId);

                if (! $category) {
                    return; // 'exists' rule above already handles a missing id.
                }

                if ($category->kind === ExpenseKind::BAZAR && ! $this->filled('purchased_by')) {
                    $validator->errors()->add(
                        'purchased_by',
                        __('The purchased by field is required for bazar-kind expenses.')
                    );
                }
            },
        ];
    }
}
