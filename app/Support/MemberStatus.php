<?php

namespace App\Support;

final class MemberStatus
{
    public const ACTIVE = 'active';
    public const INACTIVE = 'inactive';
    public const FORMER = 'former';

    public const ALL = [self::ACTIVE, self::INACTIVE, self::FORMER];
}
