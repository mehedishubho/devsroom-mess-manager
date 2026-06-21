<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BackupConfig;
use App\Support\BackupDestinations;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Enforces the admin-configured backup retention (rotation):
 *   1. Delete any backup older than `keep_all_days` days.
 *   2. If total backup storage still exceeds `max_mb`, delete oldest-first
 *      until under the cap.
 *
 * Runs against every active destination disk (local always; Spaces when its
 * credentials are configured) so the mirror stays in sync.
 *
 * This deliberately replaces spatie's `backup:clean` for the rotation knobs,
 * because spatie's cleanup reads its config — and Laravel resolves config
 * before the DB boots, so DB-backed retention can't live in config/backup.php.
 * Reading BackupConfig here (at command runtime) is safe.
 */
class PurgeBackups extends Command
{
    protected $signature = 'backup:purge';

    protected $description = 'Delete backups older than the configured retention and enforce the storage cap';

    public function handle(): int
    {
        $cfg = BackupConfig::current();

        $keepDays = max(1, (int) $cfg->keep_all_days);
        $maxBytes = max(1, (int) $cfg->max_mb) * 1024 * 1024;
        $cutoff = now()->subDays($keepDays)->getTimestamp();

        $deleted = 0;

        foreach (BackupDestinations::all() as $diskName) {
            try {
                $disk = Storage::disk($diskName);
            } catch (\Throwable) {
                continue; // disk unusable (e.g. Spaces creds removed mid-flight) — skip
            }

            $files = collect($disk->allFiles())
                ->filter(fn ($p) => str_ends_with($p, '.zip'))
                ->map(fn ($p) => [
                    'path' => $p,
                    'size' => (int) $disk->size($p),
                    'ts' => (int) $disk->lastModified($p),
                ])
                ->values();

            // 1) Age purge: anything older than the keep window.
            foreach ($files->where('ts', '<', $cutoff) as $f) {
                $disk->delete($f['path']);
                $deleted++;
            }

            // 2) Size cap: delete oldest-first until under the cap.
            $remaining = $files->where('ts', '>=', $cutoff)->sortBy('ts')->values();
            $total = $remaining->sum('size');
            foreach ($remaining as $f) {
                if ($total <= $maxBytes) {
                    break;
                }
                $disk->delete($f['path']);
                $total -= $f['size'];
                $deleted++;
            }
        }

        $this->info("Purged {$deleted} backup(s).");

        return self::SUCCESS;
    }
}
