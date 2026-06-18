<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * D-04 restore_tests row — the latest one drives the Backups UI health badge.
 *
 * NOTE: NO BelongsToActiveMess trait — restore-tests are cross-mess
 * infrastructure (no mess_id column on the table).
 */
#[Fillable(['status', 'per_table_counts', 'message', 'ran_at'])]
class RestoreTest extends Model
{
    use HasFactory;

    protected $table = 'restore_tests';

    protected function casts(): array
    {
        return [
            'per_table_counts' => 'array',
            'ran_at' => 'datetime',
        ];
    }
}
