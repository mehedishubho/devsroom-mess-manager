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

        return $user && ($user->hasRole('admin') || $user->hasRole('super-admin'));
    }

    public function rules(): array
    {
        $memberId = $this->route('member')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:30', 'regex:/^(01)[3-9]\d{8}$/'],
            'email' => [
                'nullable', 'email', 'max:255',
                Rule::unique('members', 'email')
                    ->ignore($memberId)
                    ->where('mess_id', Mess::activeId()),
            ],
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
            'leaving_date.required_if' => __('Leaving date is required when status is former.'),
            'photo.max' => __('Photo must be 2 MB or smaller.'),
        ];
    }
}
