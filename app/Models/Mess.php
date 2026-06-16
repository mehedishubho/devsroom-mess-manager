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

    protected function casts(): array
    {
        return [
            'monthly_rent' => 'decimal:2',
        ];
    }
}
