<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Global backup configuration singleton (table `backup_configs`, row id=1).
 *
 * Not scoped to an active mess — backups cover the whole DB (no mess_id column).
 *
 * `current()` reads the singleton row and memoizes it per process. It is
 * bootstrap-safe: any failure (missing table on a fresh clone, DB unreachable
 * during early boot) falls back to an in-memory default instance instead of
 * fataling, so callers in the scheduler / commands never break the app.
 *
 * NOTE: do NOT call Eloquent from config/*.php — Laravel resolves config
 * before the DB layer boots, so DB reads there always return the default.
 * Retention is enforced at command runtime by `backup:purge` instead.
 */
class BackupConfig extends Model
{
    protected $table = 'backup_configs';

    protected $fillable = [
        'frequency',
        'run_at',
        'keep_all_days',
        'max_mb',
        'notification_email',
        'encrypt_backups',
        'archive_password',
        'gdrive_backup',
        'gdrive_uploads',
        'r2_backup',
        'r2_uploads',
        // Cloud credentials (editable from the /dashboard/backups UI).
        // Secret fields are encrypted at rest via the casts below.
        'gdrive_client_id',
        'gdrive_client_secret',
        'gdrive_refresh_token',
        'gdrive_folder_id',
        'r2_key',
        'r2_secret',
        'r2_region',
        'r2_bucket',
        'r2_endpoint',
        'r2_use_path_style',
    ];

    protected function casts(): array
    {
        return [
            'keep_all_days' => 'integer',
            'max_mb' => 'integer',
            'encrypt_backups' => 'boolean',
            // Encrypted at rest for the same reason as the provider secrets:
            // a backup password must not leak into the archive it protects.
            'archive_password' => 'encrypted',
            'gdrive_backup' => 'boolean',
            'gdrive_uploads' => 'boolean',
            'r2_backup' => 'boolean',
            'r2_uploads' => 'boolean',
            // Encrypted at rest: backups include the DB dump, so plaintext
            // secrets would leak into every off-site backup. APP_KEY (in the
            // excluded .env) decrypts. Identifiers stay plaintext.
            'gdrive_client_secret' => 'encrypted',
            'gdrive_refresh_token' => 'encrypted',
            'r2_secret' => 'encrypted',
            'r2_use_path_style' => 'boolean',
        ];
    }

    /**
     * Keyed view-helper: which providers are enabled for which group.
     * Consumed by the Configure Backups UI to render the toggle matrix.
     *
     * @return array<string, array{backup: bool, uploads: bool}>
     */
    public function enabledProviders(): array
    {
        return [
            'gdrive' => [
                'backup' => (bool) $this->gdrive_backup,
                'uploads' => (bool) $this->gdrive_uploads,
            ],
            'r2' => [
                'backup' => (bool) $this->r2_backup,
                'uploads' => (bool) $this->r2_uploads,
            ],
        ];
    }

    /**
     * run_at is a TIME column (read back as 'HH:MM:SS'); expose a clean 'HH:MM'
     * for the schedule, the <input type="time"> value, and the config card.
     */
    public function runAtLabel(): string
    {
        try {
            return Carbon::parse($this->run_at)->format('H:i');
        } catch (\Throwable) {
            return '01:30';
        }
    }

    /** Human schedule label, e.g. "Daily at 01:30" / "Weekly at 01:30" / "Off". */
    public function scheduleLabel(): string
    {
        if ($this->frequency === 'off') {
            return 'Off';
        }

        return ucfirst($this->frequency).' at '.$this->runAtLabel();
    }

    protected static ?self $current = null;

    /**
     * The singleton row, memoized. Falls back to an in-memory default when the
     * row cannot be read (fresh clone, DB still coming up during early boot) —
     * but deliberately does NOT memoize that fallback.
     *
     * Memoizing the fallback was a real bug: AppServiceProvider::boot() reads
     * this to apply the runtime overrides, and when that early read missed, the
     * default instance was cached for the rest of the process. Every subsequent
     * reader — the Backups page, backup:purge, the schedule builder — then saw
     * default settings instead of the operator's saved configuration, and
     * writing through it INSERTED a second row instead of updating row 1.
     */
    public static function current(): self
    {
        if (static::$current instanceof self) {
            return static::$current;
        }

        try {
            $row = static::find(1);

            if ($row !== null) {
                return static::$current = $row;
            }
        } catch (\Throwable) {
            // Transient, or a fresh clone without the table yet — retry next call.
        }

        return static::default();
    }

    /** Forget the memoized row so the next `current()` re-reads the DB. */
    public static function flushCache(): void
    {
        static::$current = null;
    }

    protected static function default(): self
    {
        return new self([
            'frequency' => 'daily',
            'run_at' => '01:30',
            'keep_all_days' => 7,
            'max_mb' => 5000,
            'gdrive_backup' => false,
            'gdrive_uploads' => false,
            'r2_backup' => false,
            'r2_uploads' => false,
        ]);
    }
}
