<?php

namespace App\Services;

use App\Models\Mess;
use App\Models\Setting;
use App\Support\NotificationType;

/**
 * Reads and writes a mess's multi-channel notification configuration, persisted
 * as one JSON row (key `notifications.config`, group `notifications`) in the
 * per-mess `settings` table.
 *
 * Shape of the stored config:
 *  - channels.{key}.{enabled, ...credentials}   per-channel toggle + settings
 *  - routing.{type} = [channel keys]            restrict a type to channels;
 *                                               empty/missing = "all enabled"
 */
class MessNotificationSettings
{
    public const SETTING_KEY = 'notifications.config';

    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_TELEGRAM = 'telegram';
    public const CHANNEL_WHATSAPP = 'whatsapp';
    public const CHANNEL_SMS = 'sms';

    /** External channels that can be toggled on/off by the admin. */
    public const CHANNELS = [
        self::CHANNEL_EMAIL,
        self::CHANNEL_TELEGRAM,
        self::CHANNEL_WHATSAPP,
        self::CHANNEL_SMS,
    ];

    public function __construct(private ?int $messId = null) {}

    public function read(): array
    {
        $row = Setting::query()
            ->where('mess_id', $this->messId())
            ->where('key', self::SETTING_KEY)
            ->first();

        return array_replace_recursive($this->defaults(), $row?->value ?? []);
    }

    public function write(array $config): void
    {
        Setting::updateOrCreate(
            [
                'mess_id' => $this->messId(),
                'key' => self::SETTING_KEY,
            ],
            [
                'value' => array_replace_recursive($this->defaults(), $config),
                'type' => 'json',
                'group' => 'notifications',
                'description' => 'Multi-channel notification providers + routing',
            ],
        );
    }

    public function channelConfig(string $key): array
    {
        return $this->read()['channels'][$key] ?? [];
    }

    public function isChannelEnabled(string $key): bool
    {
        return (bool) ($this->channelConfig($key)['enabled'] ?? false);
    }

    /**
     * Resolve the external channels that should fire for a notification type:
     * the per-type routing list if it restricts, otherwise every enabled channel.
     *
     * @return list<string>
     */
    public function channelsForType(string $type): array
    {
        $config = $this->read();
        $routing = $config['routing'][$type] ?? [];
        $enabled = array_values(array_filter(
            self::CHANNELS,
            fn ($key) => (bool) ($config['channels'][$key]['enabled'] ?? false),
        ));

        if (empty($routing)) {
            return $enabled;
        }

        return array_values(array_intersect($enabled, $routing));
    }

    /**
     * Defaults for every field so the admin form always renders with stable
     * keys and new messes get a sane starting point.
     */
    public function defaults(): array
    {
        return [
            'channels' => [
                self::CHANNEL_EMAIL => ['enabled' => true],
                self::CHANNEL_TELEGRAM => [
                    'enabled' => false,
                    'bot_token' => '',
                    'default_chat_id' => '',
                ],
                self::CHANNEL_WHATSAPP => [
                    'enabled' => false,
                    'provider' => 'twilio',
                    'from' => '',
                    'sid' => '',
                    'token' => '',
                ],
                self::CHANNEL_SMS => [
                    'enabled' => false,
                    'provider' => 'vonage',
                    'from' => '',
                    'key' => '',
                    'secret' => '',
                    'twilio_sid' => '',
                    'twilio_token' => '',
                ],
            ],
            'routing' => array_fill_keys(NotificationType::ALL, []),
        ];
    }

    private function messId(): int
    {
        return $this->messId ??= Mess::activeId() ?? 0;
    }
}
