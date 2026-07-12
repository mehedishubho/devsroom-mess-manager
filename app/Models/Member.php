<?php

namespace App\Models;

use App\Models\Concerns\BelongsToActiveMess;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

#[Fillable([
    'mess_id', 'user_id', 'name', 'slug', 'mobile', 'email', 'nid', 'profession',
    'room_or_seat', 'joining_date', 'leaving_date', 'status',
    'emergency_contact', 'photo_path',
])]
class Member extends Model implements AuditableContract
{
    use Auditable, BelongsToActiveMess, HasFactory, SoftDeletes;

    /**
     * Member URLs use the human-readable slug (/mess/members/john-doe) instead of
     * the opaque primary key. The slug is per-mess unique — see generateUniqueSlug.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected function casts(): array
    {
        return [
            'joining_date' => 'date',
            'leaving_date' => 'date',
        ];
    }

    /**
     * Boot: auto-assign (or refresh) a per-mess-unique slug whenever a member is
     * created or their name changes. Keeps URLs readable without manual upkeep.
     */
    protected static function booted(): void
    {
        static::creating(function (Member $member) {
            $member->slug ??= $member->generateUniqueSlug($member->name);
        });

        static::updating(function (Member $member) {
            if ($member->isDirty('name')) {
                $member->slug = $member->generateUniqueSlug($member->name, $member->id);
            }
        });
    }

    /**
     * Build a per-mess-unique slug for a display name. Same-name members are
     * disambiguated as john-doe, john-doe-2, john-doe-3, … — and if the suffix
     * range is exhausted, a short random tail is appended (the "unique system"
     * fallback). Soft-deleted rows count toward uniqueness so a tombstoned slug
     * can't shadow a re-added member.
     */
    public function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $messId = $this->mess_id ?? Mess::activeId();
        $base = Str::slug($name) ?: 'member';
        $ignoreId ??= $this->id;

        $candidate = $base;
        $suffix = 2;

        while (static::withTrashed()
            ->where('mess_id', $messId)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('slug', $candidate)
            ->exists()
        ) {
            $candidate = $base . '-' . $suffix++;

            // Beyond the numeric-suffix range, fall back to a short random tail
            // to guarantee uniqueness for pathological collision sets.
            if ($suffix > 1000) {
                $candidate = $base . '-' . Str::random(5);
                break;
            }
        }

        return $candidate;
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

    public function advanceBalance(): HasOne
    {
        return $this->hasOne(AdvanceBalance::class);
    }
}
