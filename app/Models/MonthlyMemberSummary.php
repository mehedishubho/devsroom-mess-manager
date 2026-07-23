<?php

namespace App\Models;

use App\Models\Concerns\BelongsToActiveMess;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Frozen per-member snapshot written once at month close (CLOSE-09: immutable).
 *
 * Money columns are DECIMAL(10,2); reads return 2-decimal strings via the casts
 * below. NOTE (CR-03): `advance_applied` is misnamed — it holds the
 * bill-payment-type payments applied against the gross bill this month, NOT
 * advance deposits consumed (per D-07/D-08/D-10, advance deposits are tracked
 * separately in `advance_balances` and are never auto-applied to the bill).
 * A rename to `bill_payments_applied` is tracked as a separate follow-up.
 */
#[Fillable([
    'mess_id', 'monthly_closing_id', 'member_id', 'total_meals', 'meal_rate',
    'meal_cost', 'fixed_cost_share', 'guest_meal_charge', 'gross_bill',
    'advance_applied', 'net_bill', 'payments_received', 'balance_due', 'closing_balance',
])]
class MonthlyMemberSummary extends Model implements AuditableContract
{
    use Auditable, BelongsToActiveMess, HasFactory;

    protected function casts(): array
    {
        return [
            'total_meals' => 'decimal:2',
            'meal_rate' => 'decimal:4',
            'meal_cost' => 'decimal:2',
            'fixed_cost_share' => 'decimal:2',
            'guest_meal_charge' => 'decimal:2',
            'gross_bill' => 'decimal:2',
            'advance_applied' => 'decimal:2',
            'net_bill' => 'decimal:2',
            'payments_received' => 'decimal:2',
            'balance_due' => 'decimal:2',
            'closing_balance' => 'decimal:2',
        ];
    }

    public function mess(): BelongsTo
    {
        return $this->belongsTo(Mess::class);
    }

    public function monthlyClosing(): BelongsTo
    {
        return $this->belongsTo(MonthlyClosing::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
