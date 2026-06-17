<?php

namespace App\Support;

final class NotificationType
{
    public const CLOSE_COMPLETE = 'close_complete';

    public const MEAL_OFF_DECISION = 'meal_off_decision';

    public const PAYMENT_RECORDED = 'payment_recorded';

    public const DUE_REMINDER = 'due_reminder';

    public const ALL = [
        self::CLOSE_COMPLETE,
        self::MEAL_OFF_DECISION,
        self::PAYMENT_RECORDED,
        self::DUE_REMINDER,
    ];

    public const LABELS = [
        self::CLOSE_COMPLETE => 'Month closed',
        self::MEAL_OFF_DECISION => 'Meal off decision',
        self::PAYMENT_RECORDED => 'Payment recorded',
        self::DUE_REMINDER => 'Due reminder',
    ];
}
