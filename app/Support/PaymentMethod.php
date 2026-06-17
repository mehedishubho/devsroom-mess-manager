<?php

namespace App\Support;

final class PaymentMethod
{
    public const CASH = 'cash';

    public const BKASH = 'bkash';

    public const NAGAD = 'nagad';

    public const ROCKET = 'rocket';

    public const BANK = 'bank';

    public const ALL = [self::CASH, self::BKASH, self::NAGAD, self::ROCKET, self::BANK];

    public const LABELS = [
        self::CASH => 'Cash',
        self::BKASH => 'bKash',
        self::NAGAD => 'Nagad',
        self::ROCKET => 'Rocket',
        self::BANK => 'Bank',
    ];

    public const COLORS = [
        self::CASH => 'emerald',
        self::BKASH => 'pink',
        self::NAGAD => 'orange',
        self::ROCKET => 'purple',
        self::BANK => 'sky',
    ];

    public static function isCash(string $method): bool
    {
        return $method === self::CASH;
    }
}
