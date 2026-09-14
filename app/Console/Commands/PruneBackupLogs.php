<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BackupLog;
use Illuminate\Console\Command;

/**
 * Prunes the backup activity log so `backup_logs` cannot grow without bound.
 *
 * Backup archives are rotated by `backup:purge`, but the log table that
 * records every backup/purge/monitor/download/restore has no natural cap —
 * left alone it grows forever on a long-lived install. This drops rows older
 * than the configured window (config/backup.php → activity_log.keep_days,
 * fed by BACKUP_LOG_KEEP_DAYS, default 90 days).
 */
class PruneBackupLogs extends Command
{
    protected $signature = 'backup:prune-logs {--days= : Override the retention window in days}';

    protected $description = 'Delete backup activity-log rows older than the retention window';

    public function handle(): int
    {
        $days = max(1, (int) ($this->option('days') ?: config('backup.activity_log.keep_days', 90)));

        try {
            $deleted = BackupLog::query()
                ->where('created_at', '<', now()->subDays($days))
                ->delete();
        } catch (\Throwable $e) {
            $this->error('Log prune failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Pruned {$deleted} log row(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
