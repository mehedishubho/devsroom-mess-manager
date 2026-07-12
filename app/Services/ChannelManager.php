<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\Channels\EmailChannel;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\Channels\TelegramChannel;
use App\Notifications\Channels\WhatsappChannel;
use App\Notifications\Contracts\NotificationChannel;
use Illuminate\Support\Facades\Log;

/**
 * Resolves which external notification channels should fire for a given type
 * (via MessNotificationSettings) and dispatches a notification to each, always
 * failing open: any channel error is logged and reported in the result array,
 * never re-thrown — recording a payment must not roll back because Telegram
 * was down.
 */
class ChannelManager
{
    /** Channel key → concrete class. */
    private const CHANNEL_CLASSES = [
        MessNotificationSettings::CHANNEL_EMAIL => EmailChannel::class,
        MessNotificationSettings::CHANNEL_TELEGRAM => TelegramChannel::class,
        MessNotificationSettings::CHANNEL_WHATSAPP => WhatsappChannel::class,
        MessNotificationSettings::CHANNEL_SMS => SmsChannel::class,
    ];

    public function __construct(private readonly MessNotificationSettings $settings) {}

    /**
     * Fan out one notification across the mess's enabled channels for the type,
     * further filtered by the recipient's own preference. A user with no
     * preference set receives every admin-enabled channel (opt-out); a user who
     * picked specific channels receives only the intersection of their choice
     * and what the admin enabled for the type.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, array{ok: bool, detail: string}> Per-channel outcome.
     */
    public function dispatch(User $recipient, string $type, array $data): array
    {
        $keys = $this->settings->channelsForType($type);

        // Apply the recipient's personal preference (a subset of the admin's
        // enabled channels). Null preference = receive all enabled channels.
        $preferred = $recipient->preferredChannels();
        if (is_array($preferred)) {
            $keys = array_values(array_intersect($keys, $preferred));
        }

        $results = [];

        foreach ($keys as $key) {
            $channel = $this->resolveChannel($key);

            if (! $channel instanceof NotificationChannel) {
                continue;
            }

            try {
                $results[$key] = $channel->send($recipient, $type, $data);
            } catch (\Throwable $e) {
                // Defensive: channels already catch internally, but guard against
                // any transport-level throw so the caller is never broken.
                $results[$key] = ['ok' => false, 'detail' => 'dispatch error: '.$e->getMessage()];
                Log::warning('Notification channel dispatch threw', [
                    'channel' => $key, 'type' => $type, 'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Resolve a channel instance from the container. Each channel auto-resolves
     * its own MessNotificationSettings dependency.
     */
    private function resolveChannel(string $key): ?NotificationChannel
    {
        $class = self::CHANNEL_CLASSES[$key] ?? null;

        if (! $class) {
            return null;
        }

        try {
            $instance = app($class);

            return $instance instanceof NotificationChannel ? $instance : null;
        } catch (\Throwable $e) {
            Log::warning('Notification channel could not be resolved', [
                'channel' => $key, 'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
