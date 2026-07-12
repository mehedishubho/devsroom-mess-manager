<?php

namespace App\Notifications\Channels;

use App\Models\Mess;
use App\Models\User;
use App\Notifications\Contracts\NotificationChannel;
use App\Services\MessNotificationSettings;

/**
 * Shared scaffolding for external notification channels: access to the mess
 * channel config, recipient contact resolution, and Bangladesh mobile-number
 * normalization (01XXXXXXXXX → +880XXXXXXXXX) for WhatsApp/SMS providers.
 */
abstract class Channel implements NotificationChannel
{
    public function __construct(protected MessNotificationSettings $settings) {}

    /** Channel credentials/toggle block for this channel's key. */
    protected function config(): array
    {
        return $this->settings->channelConfig($this->key());
    }

    protected function messName(): ?string
    {
        $mess = Mess::activeId() ? Mess::find(Mess::activeId()) : null;

        return $mess?->name;
    }

    /**
     * The recipient's mobile in international format for WhatsApp/SMS. Returns
     * null when the user has no member row or no mobile on file.
     */
    protected function recipientMobile(User $recipient): ?string
    {
        $mobile = $recipient->getMemberOrNull()?->mobile
            ?? $recipient->member?->mobile
            ?? null;

        if (! $mobile) {
            return null;
        }

        // Already international.
        if (str_starts_with($mobile, '+')) {
            return $mobile;
        }

        // Bangladesh domestic 01XXXXXXXXX → +880XXXXXXXXX.
        if (preg_match('/^01[3-9]\d{8}$/', $mobile)) {
            return '+880'.substr($mobile, 1);
        }

        return '+'.ltrim($mobile, '+0');
    }

    protected function recipientEmail(User $recipient): ?string
    {
        return $recipient->email ?: $recipient->getMemberOrNull()?->email;
    }
}
