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
    'mess_id', 'user_id', 'name', 'mobile', 'email', 'nid', 'profession',
    'room_or_seat', 'joining_date', 'leaving_date', 'status',
    'emergency_contact', 'photo_path',
])]
class Member extends Model implements AuditableContract
{
    use Auditable, BelongsToActiveMess, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'joining_date' => 'date',
            'leaving_date' => 'date',
        ];
    }

    public function mess(): BelongsTo
    {
        return $this->belongsTo(Mess::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mealEntries(): HasMany
    {
        return $this->hasMany(MealEntry::class);
    }

    public function mealOffRequests(): HasMany
    {
        return $this->hasMany(MealOffRequest::class);
    }

    public function guestMeals(): HasMany
    {
        return $this->hasMany(GuestMeal::class);
    }
}
