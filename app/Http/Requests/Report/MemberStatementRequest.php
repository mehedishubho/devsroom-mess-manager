<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the manager Member Statement query params. Cross-mess access
 * is enforced at the controller via `Member::where('id', $id)->firstOrFail()`
 * which goes through the MessScope global scope (returns 404 for foreign
 * members). The `exists:members,id` rule is a pre-check only.
 */
class MemberStatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'member_id' => ['required', 'integer', Rule::exists('members', 'id')],
            'year' => ['sometimes', 'integer', 'min:2000', 'max:2100'],
            'month' => ['sometimes', 'integer', 'min:1', 'max:12'],
        ];
    }
}
