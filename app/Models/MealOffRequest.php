<?php

namespace App\Models;

use App\Models\Concerns\BelongsToActiveMess;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

#[Fillable([
    'mess_id', 'member_id', 'from_date', 'to_date', 'reason', 'status',
    'requested_at', 'acted_at', 'acted_by',
])]
class MealOffRequest extends Model implements AuditableContract
{
    use Auditable, BelongsToActiveMess, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
            'requested_at' => 'datetime',
            'acted_at' => 'datetime',
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

    public function actedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
    }
}
