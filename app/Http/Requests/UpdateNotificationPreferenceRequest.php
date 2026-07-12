<?php

namespace App\Http\Requests;

use App\Services\MessNotificationSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'channels' => ['present', 'array'],
            'channels.*' => ['string', Rule::in(MessNotificationSettings::CHANNELS)],
        ];
    }
}
