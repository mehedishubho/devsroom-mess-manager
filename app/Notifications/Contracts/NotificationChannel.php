<?php

namespace App\Notifications\Contracts;

use App\Models\User;

/**
 * A delivery channel for mess notifications (email, Telegram, WhatsApp, SMS, …).
 *
 * Each implementation owns one transport and must FAIL OPEN: a thrown exception
 * or a misconfigured credential never aborts the caller's work (e.g. recording a
 * payment). The ChannelManager wraps every dispatch in a try/catch and logs the
 * result — channels just return a structured outcome.
 */
interface NotificationChannel
{
    /**
     * Stable identifier persisted in the mess notification settings
     * (e.g. "email", "telegram", "whatsapp", "sms").
     */
    public function key(): string;

    /** Human-readable name for the admin settings screen. */
    public function label(): string;

    /**
     * Deliver one notification to one recipient.
     *
     * @param  User  $recipient  The user to reach (email/mobile resolved inside).
     * @param  string  $type  A App\Support\NotificationType constant.
     * @param  array<string, mixed>  $data  Payload for the message body.
     * @return array{ok: bool, detail: string} Delivery outcome for logging.
     */
    public function send(User $recipient, string $type, array $data): array;
}
