<?php

namespace App\Support;

final class NotificationType
{
    public const CLOSE_COMPLETE = 'close_complete';

    public const MEAL_OFF_DECISION = 'meal_off_decision';

    public const PAYMENT_RECORDED = 'payment_recorded';

    public const DUE_REMINDER = 'due_reminder';

    /**
     * Plan 06-02 D-05: spatie BackupHasFailed / UnhealthyBackupWasFound events
     * route to NotificationService::broadcastToManagers with this type.
     */
    public const BACKUP_FAILED = 'backup_failed';

    public const ALL = [
        self::CLOSE_COMPLETE,
        self::MEAL_OFF_DECISION,
        self::PAYMENT_RECORDED,
        self::DUE_REMINDER,
        self::BACKUP_FAILED,
    ];

    public const LABELS = [
        self::CLOSE_COMPLETE => 'Month closed',
        self::MEAL_OFF_DECISION => 'Meal off decision',
        self::PAYMENT_RECORDED => 'Payment recorded',
        self::DUE_REMINDER => 'Due reminder',
        self::BACKUP_FAILED => 'Backup failed',
    ];
}
