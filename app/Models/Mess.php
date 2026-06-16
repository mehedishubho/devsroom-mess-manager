<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

#[Fillable(['name', 'address', 'monthly_rent', 'manager_contact', 'status'])]
class Mess extends Model implements AuditableContract
{
    use Auditable, HasFactory;

    private static ?int $activeIdCache = null;

    protected function casts(): array
    {
        return [
            'monthly_rent' => 'decimal:2',
        ];
    }

    /**
     * Resolve the active mess id at runtime.
     *
     * Priority: env override (mess.active_mess_id) if a Mess actually exists
     * at that id, otherwise the first Mess row. Returns null when no mess
     * has been created yet (pre-onboarding state).
     */
    public static function activeId(): ?int
    {
        if (self::$activeIdCache !== null) {
            return self::$activeIdCache;
        }

        $override = config('mess.active_mess_id');
        if (is_int($override) || (is_string($override) && ctype_digit($override))) {
            $id = (int) $override;
            if (static::query()->whereKey($id)->exists()) {
                return self::$activeIdCache = $id;
            }
        }

        $id = static::query()->orderBy('id')->value('id');

        return self::$activeIdCache = $id !== null ? (int) $id : null;
    }

    public static function forgetActiveIdCache(): void
    {
        self::$activeIdCache = null;
    }
}
