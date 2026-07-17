<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per backup-surface action (backup / restore-test / download /
 * delete / restore / configure). Drives the "Backup activity log" section on
 * the Backups page so a failed `backup:run` (e.g. mysqldump missing) is
 * visible instead of silently swallowed.
 *
 * NOTE: NO BelongsToActiveMess trait — backups are cross-mess infrastructure
 * (no mess_id column), like RestoreTest.
 */
#[Fillable(['action', 'status', 'path', 'message', 'user_id'])]
class BackupLog extends Model
{
    protected $table = 'backup_logs';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
