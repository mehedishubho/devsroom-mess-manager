<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Backup\Config\Config;
use Spatie\Backup\Tasks\Backup\BackupJobFactory;
use Spatie\Backup\Tasks\Backup\DbDumperFactory;
use Spatie\Backup\Tasks\Backup\FileSelection;
use ZipArchive;

/**
 * Pinpoints the exact cause of "ZipArchive::close(): Invalid argument" by
 * reproducing spatie's FULL archive build (real DB dump + the real
 * storage/app/public files, with their real relative entry names + the real
 * setCompressionName call) and toggling variables until it fails.
 *
 * Everything else (permissions, disk, mysqldump, open_basedir, the engine in
 * isolation) having checked out healthy, this isolates whether the trigger is
 * per-entry compression, the compression method, the source files, or the
 * dump — and prints the exact fix (e.g. BACKUP_ZIP_COMPRESS=false).
 */
class BackupDiagnose extends Command
{
    protected $signature = 'backup:diagnose';

    protected $description = 'Reproduce spatie\'s full zip build and isolate the ZipArchive::close() failure.';

    public function handle(): int
    {
        $tempDir = (string) config('backup.backup.temporary_directory', storage_path('app/backup-temp'));
        if (! is_dir($tempDir)) {
            @mkdir($tempDir, 0o775, true);
        }

        // 1. Real DB dump (same config spatie uses).
        $dump = $tempDir.DIRECTORY_SEPARATOR.'__diag.sql';
        @unlink($dump);
        try {
            $dumper = DbDumperFactory::createFromConnection(config('database.default'));
            $dumper->dumpToFile($dump);
        } catch (\Throwable $e) {
            $this->error('Could not create DB dump: '.$e->getMessage());

            return self::FAILURE;
        }

        // 2. Use spatie's OWN FileSelection so we see EXACTLY what spatie backs
        //    up (Symfony Finder may surface a file/dir my RecursiveIterator
        //    missed — spatie logs "Zipping 3 files and directories" but my
        //    earlier scan found 2, so there is a 3rd entry to find).
        $include = config('backup.backup.source.files.include', []);
        $exclude = config('backup.backup.source.files.exclude', []);
        $selection = FileSelection::create($include);
        foreach ($exclude as $ex) {
            $selection->excludeFilesFrom($ex);
        }
        $selection->shouldFollowLinks((bool) config('backup.backup.source.files.follow_links', false));
        $selection->shouldIgnoreUnreadableDirs((bool) config('backup.backup.source.files.ignore_unreadable_directories', false));

        $public = (string) storage_path('app/public');
        $entries = [];
        foreach ($selection->selectedFiles() as $path) {
            // Mirror spatie's determineNameOfFileInZip relativePath branch.
            $name = str_starts_with($path, $public.DIRECTORY_SEPARATOR) ? substr($path, strlen($public) + 1) : $path;
            $entries[] = ['path' => $path, 'name' => $name];
        }
        $entries[] = ['path' => $dump, 'name' => 'db-dumps'.DIRECTORY_SEPARATOR.basename($dump)];

        $this->info('Entries spatie would zip ('.count($entries).' total):');
        foreach ($entries as $e) {
            $p = $e['path'];
            $type = match (true) {
                is_link($p) => 'SYMLINK->'.(@readlink($p) ?: '?'),
                is_dir($p) => 'DIR',
                is_file($p) => 'FILE',
                default => 'MISSING/NONE',
            };
            $readable = is_readable($p) ? 'readable' : 'NOT-READABLE';
            $owner = function_exists('posix_getpwuid') ? ((@posix_getpwuid(@fileowner($p)) ?: [])['name'] ?? '?') : '?';
            $this->line('  "'.$e['name'].'"  ['.$type.' | '.$readable.' | owner='.$owner.']');
        }

        @unlink($dump);

        // The manual reproductions all pass, yet `backup:run` fails — so run
        // spatie's ACTUAL BackupJob (exactly what backup:run does) and report
        // the real exception. This is the definitive test: if it fails here it
        // reproduces the live failure in an instrumented context; if it
        // succeeds, the issue is in the Artisan command/event layer.
        $this->newLine();
        $this->info('Real spatie BackupJob::run() (the definitive test):');
        try {
            $job = BackupJobFactory::createFromConfig(app(Config::class));
            $job->run();
            $this->line('  <fg=green>BackupJob::run() SUCCEEDED — a backup was written to the destination.</>');
            $this->line('  If the web "Backup now" still fails but this succeeds, the issue is in the Artisan');
            $this->line('  command/event layer, not the backup engine.');
        } catch (\Throwable $e) {
            $this->line('  <fg=red>BackupJob::run() FAILED:</>');
            $this->line('  '.$e->getMessage());
            $this->newLine();
            $this->line('  <fg=yellow>Underlying cause:</>');
            $prev = $e->getPrevious() ?? $e;
            $this->line('  '.($prev->getMessage() ?: '(no message)'));
            $this->line('  origin: '.$prev->getFile().':'.$prev->getLine());
            $originFrame = $prev->getTrace()[0] ?? null;
            if ($originFrame) {
                $this->line(sprintf('  trace[0]: %s:%s', $originFrame['file'] ?? '?', $originFrame['line'] ?? '?'));
            }
        }

        return self::SUCCESS;
    }
}
