<?php

namespace App\Support;

final class MealOffStatus
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const ALL = [self::PENDING, self::APPROVED, self::REJECTED];
}
