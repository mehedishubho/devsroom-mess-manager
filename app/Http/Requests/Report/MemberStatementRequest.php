<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the manager Member Statement query params.
 *
 * Task 5 (quick-260717-2q3) — `member_id` is now OPTIONAL so the
 * sidebar link (which has no member_id) no longer 404s. When omitted,
 * the controller auto-picks the first active member of the active mess.
 *
 * Cross-mess access is enforced at the controller via the MessScope
 * global scope (a foreign member_id resolves to null and the controller
 * falls through to auto-pick).
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
            'member_id' => ['sometimes', 'integer', Rule::exists('members', 'id')],
            'year' => ['sometimes', 'integer', 'min:2000', 'max:2100'],
            'month' => ['sometimes', 'integer', 'min:1', 'max:12'],
        ];
    }
}
