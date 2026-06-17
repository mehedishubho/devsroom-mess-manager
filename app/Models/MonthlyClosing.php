<?php

namespace App\Models;

use App\Models\Concerns\BelongsToActiveMess;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

#[Fillable([
    'mess_id', 'year', 'month', 'total_bazar', 'total_fixed_expense',
    'total_meals', 'meal_rate', 'member_count', 'closed_at',
    'closed_by', 'status', 'notes',
])]
class MonthlyClosing extends Model implements AuditableContract
{
    use Auditable, BelongsToActiveMess, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'total_bazar' => 'decimal:2',
            'total_fixed_expense' => 'decimal:2',
            'total_meals' => 'decimal:2',
            'meal_rate' => 'decimal:4',
            'member_count' => 'integer',
            'closed_at' => 'datetime',
        ];
    }

    public function mess(): BelongsTo
    {
        return $this->belongsTo(Mess::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function memberSummaries(): HasMany
    {
        return $this->hasMany(MonthlyMemberSummary::class);
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(MonthlyCorrection::class);
    }
}
