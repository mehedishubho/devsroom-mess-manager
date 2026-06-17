<?php

namespace App\Support;

final class Money
{
    public static function format(float|string|null $value, string $symbol = '৳'): string
    {
        $number = $value === null ? 0.0 : (float) $value;

        return $symbol.number_format($number, 2);
    }

    public static function taka(float|string|null $value): string
    {
        return self::format($value, '৳');
    }
}
