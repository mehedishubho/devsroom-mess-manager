<?php

namespace App\Support;

final class PaymentType
{
    public const BILL_PAYMENT = 'bill_payment';

    public const ADVANCE_DEPOSIT = 'advance_deposit';

    public const ALL = [self::BILL_PAYMENT, self::ADVANCE_DEPOSIT];

    public const LABELS = [
        self::BILL_PAYMENT => 'Bill payment',
        self::ADVANCE_DEPOSIT => 'Advance deposit',
    ];
}
