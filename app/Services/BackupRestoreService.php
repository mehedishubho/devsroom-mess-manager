<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\BackupPathResolver;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * D-06 BackupRestoreService — bespoke full-restore orchestration.
 *
 * spatie/laravel-backup is backup-only by design; the restore side is custom
 * application code. The destructive sequence is MANDATORY (research Pattern 4):
 *
 *   1. Artisan::call('down')        FIRST  — web requests now 503
 *   2. Artisan::call('queue:restart')     — no CloseMonthJob mid-restore
 *   3. try { restore }
 *   4. finally { Artisan::call('up'); cleanup } — ALWAYS return the app to live
 *
 * D-08: every Process + Artisan call is mockable via protected seams plus a
 * public buildMysqlProcess() test seam. The suite NEVER shells out.
 *
 * CR-01: restoreFiles copies the backed-up files from the EXTRACTED ROOT into
 * storage_path('app/public'). spatie zips storage/app/public/* with
 * relative_path = storage/app/public, so that prefix is STRIPPED and the files
 * extract to the work-dir root (alongside db-dumps/), NOT under a nested
 * storage/app/public/ tree. verifyFilesRestored() asserts every extracted file
 * entry was copied — a regression net against the original silent-no-op bug.
 *
 * Pitfall 4: restoreFiles writes into storage_path('app/public') (the real
 * directory), NEVER public_path('storage') (the symlink target).
 *
 * T-06-02-01: the down-first + always-up-in-finally guarantee is verified
 * by BackupRestoreServiceTest::test_up_is_called_in_finally_even_on_exception.
 */
class BackupRestoreService
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly BackupPathResolver $resolver,
    ) {}

    /**
     * Restore the given backup zip into the live DB + storage/app/public.
     *
     * @param  string  $backupPath  Disk-relative path on the backups disk.
     *
     * @throws RuntimeException On any restore failure; the app is always
     *                          returned to live via the finally block.
     */
    public function restoreFromDisk(string $backupPath): void
    {
        // 1. MAINTENANCE MODE FIRST. Nothing else runs until the restore is done.
        Artisan::call('down', ['--render' => 'errors.maintenance-backup-restore']);

        // 2. STOP THE QUEUE WORKER so a CloseMonthJob cannot write mid-restore.
        //    queue:restart signals supervisor-managed workers to die after the
        //    current job; supervisor restarts them but the new workers find the
        //    app in 'down' mode and refuse to pick up jobs.
        Artisan::call('queue:restart');

        $workDir = null;
        try {
            $workDir = $this->downloadAndExtract($backupPath);
            $sqlPath = $this->locateSqlDump($workDir);
            $this->restoreDatabase($sqlPath);
            $filesRestored = $this->restoreFiles($workDir);
            $this->verifyRestore($workDir, $filesRestored);
        } finally {
            // ALWAYS bring the app back up, even on exception. T-06-02-01.
            Artisan::call('up');
            if ($workDir !== null) {
                $this->cleanup($workDir);
            }
        }
    }

    /**
     * PUBLIC TEST SEAM (D-08). Build the Symfony Process that runs the mysql
     * CLI with array args. The suite inspects this without ever running it.
     *
     * Uses ARRAY args (NOT escapeshellarg string concat) per research
     * Pattern 4a — Process handles cross-platform escaping cleanly.
     *
     * WR-03: the dump is piped via STDIN (mysql reads SQL from stdin when no
     * -e flag and no positional file is given). This avoids the `SOURCE <path>`
     * form, which breaks on paths containing spaces, and streams the file
     * without reading it all into memory.
     */
    public function buildMysqlProcess(string $sqlPath): Process
    {
        $cfg = config('database.connections.mysql');

        $process = new Process([
            'mysql',
            '--host='.$cfg['host'],
            '--port='.$cfg['port'],
            '--user='.$cfg['username'],
            '--password='.$cfg['password'],
            $cfg['database'],
        ]);

        $process->setInput($this->openDumpStream($sqlPath));

        return $process;
    }

    /**
     * Resolve the .sql dump inside the extracted backup tree. Protected seam
     * (D-08) — delegates to BackupPathResolver. The suite stubs this so the
     * resolver's filesystem walk is bypassed when the other protected seams
     * are mocked.
     */
    protected function locateSqlDump(string $workDir): string
    {
        return $this->resolver->locateSqlDump($workDir);
    }

    /**
     * Restore the dump into the live DB via the mysql CLI.
     * Protected seam (D-08) — mocked in the test suite.
     */
    protected function restoreDatabase(string $sqlPath): void
    {
        $process = $this->buildMysqlProcess($sqlPath);
        $process->setTimeout(600);
        $process->mustRun();
    }

    /**
     * Pitfall 4 + CR-01: copy the backed-up files back to storage_path('app/public'),
     * the REAL directory. NEVER write to public_path('storage') (the symlink).
     *
     * spatie zips storage/app/public/* with relative_path = storage/app/public
     * (config/backup.php), so that prefix is STRIPPED and the backed-up files
     * extract to the work-dir ROOT (e.g. <workDir>/profiles/foo.jpg), alongside
     * the db-dumps/ folder. Copy every top-level entry EXCEPT db-dumps back into
     * storage/app/public.
     *
     * Returns the count of entries copied (consumed by verifyFilesRestored).
     *
     * Protected seam (D-08) — mocked in the test suite (except the dedicated
     * non-mocked integration test that proves the path math).
     */
    protected function restoreFiles(string $workDir): int
    {
        $destDir = storage_path('app/public');
        $this->files->ensureDirectoryExists($destDir, 0775, true);

        $copied = 0;

        foreach ($this->files->directories($workDir) as $dir) {
            if (basename($dir) === 'db-dumps') {
                continue;
            }
            $this->files->copyDirectory($dir, $destDir.DIRECTORY_SEPARATOR.basename($dir));
            $copied++;
        }

        foreach ($this->files->files($workDir) as $file) {
            $this->files->copy($file->getPathname(), $destDir.DIRECTORY_SEPARATOR.$file->getFilename());
            $copied++;
        }

        return $copied;
    }

    /**
     * Post-restore verification: the DB must have rows AND every backed-up file
     * entry must have been copied (CR-01 silent-no-op guard).
     *
     * Protected seam (D-08) — mocked in the test suite.
     */
    protected function verifyRestore(string $workDir, int $filesRestored): void
    {
        $this->verifyDatabaseRestored();
        $this->verifyFilesRestored($workDir, $filesRestored);
    }

    /**
     * Lightweight DB spot-check: COUNT(*) on a few high-value tables must be > 0.
     * The full per-table parity check lives in RestoreTestService.
     */
    protected function verifyDatabaseRestored(): void
    {
        foreach (['members', 'monthly_closings', 'audits'] as $table) {
            $count = (int) DB::table($table)->count();
            if ($count === 0) {
                throw new RuntimeException(
                    "Post-restore verification failed: {$table} has 0 rows."
                );
            }
        }
    }

    /**
     * CR-01 regression net: the count of file entries restoreFiles reports
     * having copied MUST equal the count present in the extracted tree. If
     * restoreFiles ever silently no-ops again (e.g. wrong source path), this
     * diverges and the restore fails loudly instead of reporting success while
     * losing every uploaded file.
     */
    protected function verifyFilesRestored(string $workDir, int $filesRestored): void
    {
        $expected = $this->countExtractedFileEntries($workDir);

        if ($filesRestored !== $expected) {
            throw new RuntimeException(
                "Post-restore verification failed: restored {$filesRestored} file entries "
                ."but the extracted backup contained {$expected} (silent file-restore guard, CR-01)."
            );
        }
    }

    /**
     * Count the top-level file entries in the extracted tree, excluding the
     * db-dumps folder (which is handled by restoreDatabase, not restoreFiles).
     */
    protected function countExtractedFileEntries(string $workDir): int
    {
        $count = 0;
        foreach ($this->files->directories($workDir) as $dir) {
            if (basename($dir) === 'db-dumps') {
                continue;
            }
            $count++;
        }
        foreach ($this->files->files($workDir) as $file) {
            $count++;
        }

        return $count;
    }

    /**
     * Download the backup zip from the configured backups disk and extract it
     * to a fresh temp dir. Protected seam (D-08).
     *
     * WR-01: the zip is STREAMED to disk (readStream + stream_copy_to_stream)
     * rather than $disk->get(), which materializes the whole file in memory
     * and would OOM on a real prod backup.
     *
     * @return string The extracted work directory absolute path.
     */
    protected function downloadAndExtract(string $backupPath): string
    {
        $diskName = config('backup.backup.destination.disks.0', 'backups');
        $disk = Storage::disk($diskName);
        abort_unless($disk->exists($backupPath), 404, "Backup not found: {$backupPath}");

        $workDir = storage_path('app/backup-temp/restore-'.time());
        $this->files->ensureDirectoryExists($workDir, 0775, true);

        $localZip = $workDir.'/'.basename($backupPath);
        $this->streamToLocal($disk, $backupPath, $localZip);

        $zip = new \ZipArchive;
        $opened = $zip->open($localZip);
        if ($opened !== true) {
            throw new RuntimeException("Failed to open backup zip (code {$opened}).");
        }
        $zip->extractTo($workDir);
        $zip->close();

        return $workDir;
    }

    /**
     * Open a readable stream over the SQL dump (WR-03 — piped to mysql stdin).
     *
     * @return resource
     */
    protected function openDumpStream(string $sqlPath)
    {
        $resource = fopen($sqlPath, 'r');

        if ($resource === false) {
            throw new RuntimeException("Could not open SQL dump for restore: {$sqlPath}");
        }

        return $resource;
    }

    /**
     * Stream a file from a filesystem disk to a local path without buffering
     * the whole file in memory (WR-01).
     */
    protected function streamToLocal($disk, string $diskPath, string $destFile): void
    {
        $stream = $disk->readStream($diskPath);

        try {
            $dest = fopen($destFile, 'w+b');
            if ($dest === false) {
                throw new RuntimeException("Could not open {$destFile} for writing.");
            }
            stream_copy_to_stream($stream, $dest);
            fclose($dest);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    protected function cleanup(string $workDir): void
    {
        $this->files->deleteDirectory($workDir);
    }
}
