<?php

namespace App\Support;

final class MealType
{
    public const BREAKFAST = 'breakfast';
    public const LUNCH = 'lunch';
    public const DINNER = 'dinner';

    public const ALL = [self::BREAKFAST, self::LUNCH, self::DINNER];

    public static function value(string $type): float
    {
        return match ($type) {
            self::BREAKFAST => 0.5,
            self::LUNCH => 1.0,
            self::DINNER => 1.0,
        };
    }
}
