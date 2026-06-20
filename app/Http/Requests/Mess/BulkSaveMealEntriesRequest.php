<?php

namespace App\Http\Requests\Mess;

use Illuminate\Foundation\Http\FormRequest;

class BulkSaveMealEntriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && $user->canManageMess();
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'entries' => ['required', 'array'],
            'entries.*.member_id' => ['required', 'integer', 'exists:members,id'],
            'entries.*.breakfast' => ['boolean'],
            'entries.*.lunch' => ['boolean'],
            'entries.*.dinner' => ['boolean'],
        ];
    }
}
