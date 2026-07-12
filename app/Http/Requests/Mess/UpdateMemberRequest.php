<?php

namespace App\Http\Requests\Mess;

use App\Models\Mess;
use App\Support\MemberStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && $user->canManageMess();
    }

    public function rules(): array
    {
        $memberId = $this->route('member')?->id;

        // Duplicate prevention: unique per mess, ignoring the member being edited,
        // and excluding null rows so optional contacts don't collide.
        $perMessUnique = function (string $column) use ($memberId) {
            return Rule::unique('members', $column)
                ->ignore($memberId)
                ->where(fn ($q) => $q->where('mess_id', Mess::activeId())->whereNotNull($column));
        };

        return [
            'name' => ['required', 'string', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:30', 'regex:/^(01)[3-9]\d{8}$/', $perMessUnique('mobile')],
            'email' => ['nullable', 'email', 'max:255', $perMessUnique('email')],
            'nid' => ['nullable', 'string', 'max:50'],
            'profession' => ['nullable', 'string', 'max:100'],
            'room_or_seat' => ['nullable', 'string', 'max:50'],
            'joining_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(MemberStatus::ALL)],
            'leaving_date' => ['nullable', 'date', 'required_if:status,former'],
            'emergency_contact' => ['nullable', 'string', 'max:100'],
            'photo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.regex' => __('Mobile must be a valid BD number (e.g. 01700000000).'),
            'mobile.unique' => __('A member with this mobile number already exists in this mess.'),
            'email.unique' => __('A member with this email already exists in this mess.'),
            'leaving_date.required_if' => __('Leaving date is required when status is former.'),
            'photo.max' => __('Photo must be 2 MB or smaller.'),
        ];
    }
}
