<?php

namespace App\Models;

use App\Models\Concerns\BelongsToActiveMess;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only log of manual balance adjustments made from the Adjust page.
 *
 * Each row records one signed credit/charge with a reason. AdvanceBalanceService
 * still mutates the single advance_balances row in place; this table is the
 * readable history that lets the wallet ledger show manual adjustments as their
 * own dated lines. Historical adjustments (made before this table existed) are
 * not backfilled — accepted.
 */
#[Fillable(['mess_id', 'member_id', 'amount', 'reason', 'entered_by'])]
class BalanceAdjustment extends Model
{
    use BelongsToActiveMess, HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }
}
