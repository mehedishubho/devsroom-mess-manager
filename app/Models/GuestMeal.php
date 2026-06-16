<?php

namespace App\Models;

use App\Models\Concerns\BelongsToActiveMess;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'mess_id', 'member_id', 'guest_name', 'date', 'meal_type',
    'quantity', 'meal_value', 'charge_amount', 'entered_by',
])]
class GuestMeal extends Model
{
    use BelongsToActiveMess, HasFactory;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'quantity' => 'decimal:2',
            'meal_value' => 'decimal:2',
            'charge_amount' => 'decimal:2',
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
}
