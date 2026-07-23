<?php

namespace App\Models;

use App\Models\Concerns\BelongsToActiveMess;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['mess_id', 'member_id', 'balance', 'due_balance', 'last_updated_at'])]
class AdvanceBalance extends Model
{
    use BelongsToActiveMess, HasFactory;

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'due_balance' => 'decimal:2',
            'last_updated_at' => 'datetime',
        ];
    }

    public function mess(): BelongsTo
    {
        return $this->belongsTo(Mess::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Net balance for DISPLAY: balance (credit) − due_balance (debt).
     * Positive = the member has credit; negative = the member owes. The two
     * columns are kept separate for the month-close settlement math, but every
     * user-facing surface shows this single net figure so a member is never
     * displayed as simultaneously owing and being owed.
     */
    public function netBalance(): float
    {
        return (float) bcsub((string) $this->balance, (string) $this->due_balance, 2);
    }
}
