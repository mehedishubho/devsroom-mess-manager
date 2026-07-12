<?php

namespace App\Http\Requests\Mess;

use App\Services\MessNotificationSettings;
use App\Support\NotificationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMessNotificationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->canManageMess();
    }

    public function rules(): array
    {
        $channelKeys = MessNotificationSettings::CHANNELS;

        return [
            'channels' => ['required', 'array'],
            'channels.email.enabled' => ['boolean'],

            'channels.telegram.enabled' => ['boolean'],
            'channels.telegram.bot_token' => ['nullable', 'string', 'max:255'],
            'channels.telegram.default_chat_id' => ['nullable', 'string', 'max:64'],

            'channels.whatsapp.enabled' => ['boolean'],
            'channels.whatsapp.provider' => ['nullable', Rule::in(['twilio', 'meta'])],
            'channels.whatsapp.from' => ['nullable', 'string', 'max:32'],
            'channels.whatsapp.sid' => ['nullable', 'string', 'max:64'],
            'channels.whatsapp.token' => ['nullable', 'string', 'max:128'],

            'channels.sms.enabled' => ['boolean'],
            'channels.sms.provider' => ['nullable', Rule::in(['vonage', 'twilio'])],
            'channels.sms.from' => ['nullable', 'string', 'max:32'],
            'channels.sms.key' => ['nullable', 'string', 'max:64'],
            'channels.sms.secret' => ['nullable', 'string', 'max:128'],
            'channels.sms.twilio_sid' => ['nullable', 'string', 'max:64'],
            'channels.sms.twilio_token' => ['nullable', 'string', 'max:128'],

            'routing' => ['sometimes', 'array'],
            'routing.*' => ['sometimes', 'array'],
            'routing.*.*' => ['sometimes', 'string', Rule::in($channelKeys)],
        ];
    }

    /**
     * Normalize the submitted form into the config shape MessNotificationSettings
     * stores: checkboxes become booleans, missing channel blocks get defaults,
     * and routing entries outside the known types are dropped.
     */
    public function normalizedConfig(): array
    {
        $input = $this->validated();
        $defaults = (new MessNotificationSettings)->defaults();

        $channels = array_merge(
            $defaults['channels'],
            array_map(
                fn ($block) => array_merge(['enabled' => false], is_array($block) ? $block : []),
                $input['channels'] ?? [],
            ),
        );

        $routing = $defaults['routing'];
        foreach (NotificationType::ALL as $type) {
            $routing[$type] = array_values(array_filter(
                $input['routing'][$type] ?? [],
                fn ($c) => in_array($c, MessNotificationSettings::CHANNELS, true),
            ));
        }

        return ['channels' => $channels, 'routing' => $routing];
    }
}
