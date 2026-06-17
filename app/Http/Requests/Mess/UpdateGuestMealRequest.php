<?php

namespace App\Http\Requests\Mess;

use App\Support\MealType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGuestMealRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && ($user->hasRole('admin') || $user->hasRole('super-admin'));
    }

    public function rules(): array
    {
        return [
            'member_id' => ['required', 'integer', 'exists:members,id'],
            'guest_name' => ['required', 'string', 'max:100'],
            'date' => ['required', 'date'],
            'meal_type' => ['required', Rule::in(MealType::ALL)],
            'quantity' => ['required', 'numeric', 'min:1'],
        ];
    }
}
