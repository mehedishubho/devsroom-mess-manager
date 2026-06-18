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
            $this->restoreFiles($workDir);
            $this->verifyRestore();
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
     */
    public function buildMysqlProcess(string $sqlPath): Process
    {
        $cfg = config('database.connections.mysql');

        return new Process([
            'mysql',
            '--host='.$cfg['host'],
            '--port='.$cfg['port'],
            '--user='.$cfg['username'],
            '--password='.$cfg['password'],
            $cfg['database'],
            '-e', 'SOURCE '.$sqlPath,
        ]);
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
     * Pitfall 4: copy the backed-up files back to storage_path('app/public'),
     * the REAL directory. NEVER write to public_path('storage') (the symlink).
     *
     * The backup zip stores files under storage/app/public (relative_path is
     * set to that in config/backup.php). When extracted, that nested tree
     * exists under the work dir.
     *
     * Protected seam (D-08) — mocked in the test suite.
     */
    protected function restoreFiles(string $workDir): void
    {
        // The spatie backup zips storage/app/public/* with relative_path =
        // storage/app/public, so the backed-up files live under
        // <workDir>/storage/app/public/. (If the layout differs, the source
        // dir simply won't exist and copyDirectory is a no-op — harmless.)
        $sourceDir = $workDir.'/storage/app/public';
        $destDir = storage_path('app/public');

        $this->files->ensureDirectoryExists($destDir, 0775, true);

        // copyDirectory merges the source tree into the destination,
        // overwriting existing files. Do NOT delete the destination dir first
        // (the storage symlink would be followed in the wrong direction on
        // some setups — Pitfall 4 anti-pattern). Research Pattern 4.
        $this->files->copyDirectory($sourceDir, $destDir);
    }

    /**
     * Lightweight spot-check: COUNT(*) on a few high-value tables must be > 0.
     * This is the "did the restore actually do anything" guard. The full
     * per-table parity check lives in RestoreTestService.
     *
     * Protected seam (D-08) — mocked in the test suite.
     */
    protected function verifyRestore(): void
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
     * Download the backup zip from the configured backups disk and extract it
     * to a fresh temp dir. Protected seam (D-08).
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
        $this->files->put($localZip, $disk->get($backupPath));

        $zip = new \ZipArchive;
        $opened = $zip->open($localZip);
        if ($opened !== true) {
            throw new RuntimeException("Failed to open backup zip (code {$opened}).");
        }
        $zip->extractTo($workDir);
        $zip->close();

        return $workDir;
    }

    protected function cleanup(string $workDir): void
    {
        $this->files->deleteDirectory($workDir);
    }
}
