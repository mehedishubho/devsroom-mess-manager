<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

/**
 * One row per backup-surface action (backup / purge / monitor / download /
 * delete / restore / configure). Drives the "Backup activity log" section on
 * the Backups page so a failed `backup:run` (e.g. mysqldump missing) is
 * visible instead of silently swallowed.
 *
 * NOTE: NO BelongsToActiveMess trait — backups are cross-mess infrastructure
 * (no mess_id column).
 */
#[Fillable(['action', 'status', 'path', 'message', 'user_id', 'duration_ms', 'size_bytes'])]
class BackupLog extends Model
{
    protected $table = 'backup_logs';

    protected function casts(): array
    {
        return [
            'duration_ms' => 'integer',
            'size_bytes' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Write an activity row, tolerating a missing table and returning null on
     * failure. Logging must never break the operation it is recording — e.g. a
     * fresh deploy whose `php artisan migrate` hasn't created backup_logs yet.
     */
    public static function record(string $action, string $status, ?string $message = null, ?string $path = null, ?int $userId = null, ?int $durationMs = null, ?int $sizeBytes = null): ?self
    {
        try {
            return static::create([
                'action' => $action,
                'status' => $status,
                'message' => $message,
                'path' => $path,
                'user_id' => $userId,
                'duration_ms' => $durationMs,
                'size_bytes' => $sizeBytes,
            ]);
        } catch (\Throwable $e) {
            Log::warning('backup_logs write failed: '.$e->getMessage());

            return null;
        }
    }
}
