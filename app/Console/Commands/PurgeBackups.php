<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BackupLog;
use App\Services\BackupRetention;
use App\Support\BackupDestinations;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Enforces the admin-configured backup retention (rotation):
 *   1. Delete any backup older than `keep_all_days` days.
 *   2. If total backup storage still exceeds `max_mb`, delete oldest-first
 *      until under the cap.
 *
 * Runs against every active destination disk (Local always; Google Drive /
 * Cloudflare R2 when the operator has enabled + configured them) so the
 * mirrors stay in sync.
 *
 * This deliberately replaces spatie's `backup:clean` for the rotation knobs,
 * because spatie's cleanup reads its config — and Laravel resolves config
 * before the DB boots, so DB-backed retention can't live in config/backup.php.
 * Reading BackupConfig here (at command runtime) is safe.
 *
 * Every run writes a BackupLog row (`purge`/success or `purge`/failure) so the
 * Backups page Activity log shows rotation actually happening. spatie's
 * Cleanup* events never fire here — they belong to `backup:clean`, which is
 * not scheduled — so without this the purge action was invisible.
 */
class PurgeBackups extends Command
{
    protected $signature = 'backup:purge';

    protected $description = 'Delete backups older than the configured retention and enforce the storage cap';

    public function __construct(private readonly BackupRetention $retention)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $deletedPaths = [];
        $failures = [];

        try {
            $config = $this->retention->config();

            foreach (BackupDestinations::all() as $diskName) {
                try {
                    $disk = Storage::disk($diskName);
                } catch (\Throwable $e) {
                    $failures[] = $diskName.': '.$e->getMessage();

                    continue; // disk unusable (credentials removed mid-flight) — skip
                }

                $files = collect($disk->allFiles())
                    ->filter(fn ($p) => str_ends_with($p, '.zip'))
                    ->map(fn ($p) => [
                        'path' => $p,
                        'size' => (int) $disk->size($p),
                        'ts' => (int) $disk->lastModified($p),
                    ])
                    ->values()
                    ->all();

                // Same rule the Backups page previews — one implementation.
                $plan = $this->retention->plan($files, $config['keepDays'], $config['maxBytes']);

                foreach ($plan['delete'] as $file) {
                    $disk->delete($file['path']);
                    $deletedPaths[] = $diskName.':'.$file['path'];
                }
            }
        } catch (\Throwable $e) {
            $this->recordLog('failure', $e->getMessage());

            $this->error('Purge failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $count = count($deletedPaths);
        $message = $count === 0
            ? 'Nothing to purge — all backups are within the retention window.'
            : "Purged {$count} backup(s):\n".implode("\n", $deletedPaths);

        if ($failures !== []) {
            $message .= "\n\nDisk problems:\n".implode("\n", $failures);
        }

        $this->recordLog($failures === [] ? 'success' : 'failure', $message);

        $this->info("Purged {$count} backup(s).");

        return self::SUCCESS;
    }

    /**
     * Persist the outcome to the Activity log. Tolerates a missing
     * backup_logs table (a fresh deploy that hasn't migrated yet) — logging
     * must never break the command it logs.
     */
    private function recordLog(string $status, string $message): void
    {
        try {
            BackupLog::create([
                'action' => 'purge',
                'status' => $status,
                'message' => $message,
            ]);
        } catch (\Throwable $e) {
            Log::warning('backup_logs write failed: '.$e->getMessage());
        }
    }
}
