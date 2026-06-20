<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use HasinHayder\Tyro\Concerns\HasTyroRoles;
use HasinHayder\TyroLogin\Traits\HasTwoFactorAuth;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasApiTokens, HasTwoFactorAuth, HasTyroRoles;

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function member(): HasOne
    {
        return $this->hasOne(Member::class, 'user_id');
    }

    /**
     * All mess memberships for this user (WR-08). A user is generally a member
     * of at most one mess per row in `members`, but historically a user may have
     * rows in multiple messes (FORMER status etc.) — query membership of a
     * specific mess via ->members()->where('mess_id', $id).
     */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class, 'user_id');
    }

    public function getMemberOrNull(): ?Member
    {
        return $this->member()->first();
    }

    /**
     * Is this user a mess Manager? (distinct from the generic `admin` role,
     * but with the same day-to-day mess authority).
     */
    public function isManager(): bool
    {
        return $this->hasRole('manager');
    }

    /**
     * Can the user manage the mess day-to-day? True for admin, super-admin,
     * or manager. Centralizes the repeated
     * `hasRole('admin') || hasRole('super-admin')` gate used across the
     * mess FormRequests, routes, and views — and extends it to the manager role.
     */
    public function canManageMess(): bool
    {
        return $this->hasAnyRole(['admin', 'super-admin', 'manager']);
    }
}
