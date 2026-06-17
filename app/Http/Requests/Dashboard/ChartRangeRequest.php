<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the chart-range GET query params on /home (D-03, DASH-06).
 *
 * Each chart has its own from/to pair (D-08 auto-bucketing is applied
 * server-side). Preset names are optional hints for the UI; the server
 * always reads the explicit from/to when present. Authorization is
 * enforced by the route middleware (role:admin + EnsureMessExists).
 */
class ChartRangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'meal_from' => ['nullable', 'date'],
            'meal_to' => ['nullable', 'date', 'after_or_equal:meal_from'],
            'expense_from' => ['nullable', 'date'],
            'expense_to' => ['nullable', 'date', 'after_or_equal:expense_from'],
            'payment_from' => ['nullable', 'date'],
            'payment_to' => ['nullable', 'date', 'after_or_equal:payment_from'],
            'meal_preset' => ['nullable', 'string', Rule::in(['30d', '6mo', '12mo', 'custom'])],
            'expense_preset' => ['nullable', 'string', Rule::in(['30d', '6mo', '12mo', 'custom'])],
            'payment_preset' => ['nullable', 'string', Rule::in(['30d', '6mo', '12mo', 'custom'])],
        ];
    }
}
