<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

/**
 * Runs a spatie backup and reports whether it ACTUALLY produced an archive.
 *
 * `backup:run` does not throw when the dump fails (e.g. mysqldump missing on
 * the server) — Artisan::call() just returns a non-zero exit code, which the
 * original inline implementation ignored, producing a false "Backup
 * completed." flash. Failure is therefore detected three ways: exit code,
 * the captured output, and "no new zip actually appeared on disk".
 *
 * Extracted from the controller so the queued RunBackupJob and any future
 * caller share one implementation.
 */
class BackupRunner
{
    /**
     * @return array{ok:bool, message:string, output:string, archive:?array{path:string, size:int}}
     */
    public function run(): array
    {
        if ($preflight = $this->preflightWritable()) {
            return ['ok' => false, 'message' => $preflight, 'output' => '', 'archive' => null];
        }

        $disk = Storage::disk($this->backupDisk());
        $before = $this->countZips($disk);

        try {
            $exitCode = (int) Artisan::call('backup:run');
            $output = (string) Artisan::output();
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'output' => '', 'archive' => null];
        }

        $after = $this->countZips($disk);

        if ($after <= $before || $exitCode !== 0) {
            $reason = $this->extractFailureReason($output)
                ?: __('No backup file was produced (exit code :code). Usually mysqldump is missing on the server — install it and set DUMP_BINARY_PATH.', ['code' => $exitCode]);

            return ['ok' => false, 'message' => $reason, 'output' => $output, 'archive' => null];
        }

        return [
            'ok' => true,
            'message' => __('Backup completed.'),
            'output' => $output,
            'archive' => $this->newestArchive($disk),
        ];
    }

    /**
     * The archive the run just produced (newest .zip), with its size — so the
     * activity log can show how big the backup was, not just that it succeeded.
     *
     * @return array{path:string, size:int}|null
     */
    private function newestArchive($disk): ?array
    {
        $newest = collect($disk->allFiles())
            ->filter(fn ($p) => str_ends_with($p, '.zip'))
            ->map(fn ($p) => ['path' => $p, 'ts' => (int) $disk->lastModified($p)])
            ->sortByDesc('ts')
            ->first();

        if ($newest === null) {
            return null;
        }

        return ['path' => $newest['path'], 'size' => (int) $disk->size($newest['path'])];
    }

    /** The spatie destination disk (always backups-local after the Spaces removal). */
    public function backupDisk(): string
    {
        return (string) config('backup.backup.destination.disks.0', 'backups-local');
    }

    /**
     * Count .zip files on the backup disk — used to detect whether
     * `backup:run` actually produced a file (belt-and-suspenders alongside
     * the exit-code check).
     */
    private function countZips($disk): int
    {
        return collect($disk->allFiles())
            ->filter(fn ($p) => str_ends_with($p, '.zip'))
            ->count();
    }

    /**
     * Pre-flight check: ensure spatie's destination + temp + temp-archive
     * directories exist and are writable by the web/PHP user BEFORE handing
     * off to `backup:run`. The classic shared-host failure is
     * "ZipArchive::close(): Invalid argument" — spatie silently can't write
     * the final zip because storage/app/backups is missing or not writable.
     * This turns that opaque error into an actionable one.
     */
    private function preflightWritable(): ?string
    {
        // storage/app/backups        — final zip destination (backups-local disk root).
        // storage/app/backup-temp    — spatie's temporary_directory: THIS is where the
        //                              zip is actually staged, so a missing/root-owned
        //                              backup-temp makes close() fail even when the
        //                              destination looks writable.
        // storage/app/laravel-backup — spatie's DB-dump workdir.
        $paths = [
            'destination' => storage_path('app/backups'),
            'spatie-temp' => (string) config('backup.backup.temporary_directory', storage_path('app/backup-temp')),
            'dump-workdir' => storage_path('app/laravel-backup'),
        ];

        foreach ($paths as $label => $path) {
            if (! is_dir($path)) {
                @mkdir($path, 0o775, true);
            }
            if (! is_dir($path) || ! is_writable($path)) {
                return __('Backup :label directory (:path) is missing or not writable by the web server. Create it and fix ownership: chown -R <site-user>:<site-user> storage && chmod -R 775 storage.', ['label' => $label, 'path' => $path]);
            }
        }

        // Disk space / quota: open() can create a 0-byte file (success) but
        // close() fails when there is no room to write the actual content — a
        // classic shared-host "Invalid argument" on close().
        $free = @disk_free_space(storage_path('app'));
        if ($free !== false && $free < 50 * 1024 * 1024) {
            return __('Less than 50 MB free on the storage partition (:free MB). The backup needs room to stage the zip — free disk space or check the account quota.', ['free' => number_format($free / 1024 / 1024, 1)]);
        }

        // open_basedir / sys_temp_dir restriction: ZipArchive uses the system
        // temp dir internally; if PHP's open_basedir excludes it, close() can
        // fail even when the destination is writable.
        $openBasedir = ini_get('open_basedir');
        if ($openBasedir) {
            $systemTemp = sys_get_temp_dir();
            $allowed = array_map(fn ($p) => realpath(rtrim($p, DIRECTORY_SEPARATOR)), explode(PATH_SEPARATOR, $openBasedir));
            if (! in_array(realpath($systemTemp), $allowed, true)) {
                return __('PHP open_basedir excludes the system temp dir (:temp), which breaks ZipArchive. Set sys_temp_dir/upload_tmp_dir to a writable path inside the account (e.g. storage/app/tmp), or ask the host to widen open_basedir to include :temp.', ['temp' => $systemTemp]);
            }
        }

        return null;
    }

    /**
     * Pull the most informative failure line out of the captured artisan
     * output (mysqldump not found, connection refused, etc.). Returns null
     * when the output is empty / has no recognizable failure.
     */
    private function extractFailureReason(string $output): ?string
    {
        $output = trim($output);
        if ($output === '') {
            return null;
        }

        $lines = array_values(array_filter(array_map('trim', explode("\n", $output))));

        foreach ($lines as $line) {
            if (preg_match('/(not found|no such file|command not found|the dump failed|dumping database.*fail|connection refused|access denied|unknown database|backup failed|could not|denied|error)/i', $line)) {
                return mb_strlen($line) > 500 ? mb_substr($line, 0, 500).'…' : $line;
            }
        }

        return null;
    }
}
