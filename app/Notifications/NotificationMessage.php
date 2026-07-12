<?php

namespace App\Notifications;

use App\Support\NotificationType;

/**
 * Builds a human-readable subject + body for a notification type, interpolating
 * the per-event data payload. Shared by every external channel so email,
 * Telegram, WhatsApp and SMS all send consistent wording.
 */
final class NotificationMessage
{
    public function __construct(
        public readonly string $subject,
        public readonly string $body,
    ) {}

    /**
     * Compose a message from the notification type + data. Falls back to a sane
     * generic line for any type we don't have specific copy for.
     *
     * @param  array<string, mixed>  $data
     */
    public static function for(string $type, array $data, ?string $messName = null): self
    {
        $prefix = $messName ? "[{$messName}] " : '';

        return match ($type) {
            NotificationType::DUE_REMINDER => new self(
                __('Due reminder'),
                $prefix . __('You have an outstanding due of :amount with the mess. Please clear it at your earliest.', ['amount' => static::money($data['due_balance'] ?? 0)]),
            ),
            NotificationType::CLOSE_COMPLETE => new self(
                __('Month closed'),
                $prefix . __('The mess month has been closed. Meal rate: :rate per meal.', ['rate' => static::money($data['meal_rate'] ?? 0)]),
            ),
            NotificationType::PAYMENT_RECORDED => new self(
                __('Payment recorded'),
                $prefix . __('A payment of :amount has been recorded against your mess account.', ['amount' => static::money($data['amount'] ?? 0)]),
            ),
            NotificationType::MEAL_OFF_DECISION => new self(
                __('Meal off request :status', ['status' => $data['status'] ?? '']),
                $prefix . __('Your meal-off request for :range was :status.', [
                    'range' => trim((string) ($data['range'] ?? '')),
                    'status' => $data['status'] ?? '',
                ]),
            ),
            NotificationType::BACKUP_FAILED => new self(
                __('Backup failed'),
                $prefix . __('A scheduled mess backup failed. Please review the backup configuration.'),
            ),
            default => new self(
                __('Mess notification'),
                $prefix . __('You have a new mess notification (:type).', ['type' => $type]),
            ),
        };
    }

    protected static function money(float|int|string $value): string
    {
        return '৳' . number_format((float) $value, 2);
    }
}
