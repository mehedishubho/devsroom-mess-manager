<?php

namespace App\Support;

final class ExpenseKind
{
    public const BAZAR = 'bazar';

    public const FIXED = 'fixed';

    public const OTHER = 'other';

    public const ALL = [self::BAZAR, self::FIXED, self::OTHER];
}
