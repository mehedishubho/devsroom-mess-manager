<?php

namespace App\Http\Requests\Mess;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class TriggerMonthCloseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && $user->canManageMess();
    }

    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $data = $validator->getData();

            $target = Carbon::create((int) ($data['year'] ?? 0), (int) ($data['month'] ?? 0), 1)->startOfMonth();
            if ($target->greaterThan(Carbon::now()->startOfMonth())) {
                $validator->errors()->add('month', __('You cannot close a future month.'));
            }
        });
    }
}
