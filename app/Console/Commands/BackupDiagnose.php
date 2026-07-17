<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Backup\Tasks\Backup\DbDumperFactory;
use Spatie\Backup\Tasks\Backup\FileSelection;
use Spatie\Backup\Tasks\Backup\Manifest;
use Spatie\Backup\Tasks\Backup\Zip;

/**
 * Calls spatie's ACTUAL Zip::createForManifest() (the exact code path that
 * fails in backup:run) with a manifest built exactly like BackupJob — real
 * mysqldump + real storage/app/public files via spatie's FileSelection — and
 * prints every manifest entry path alongside the entry NAME spatie's
 * determineNameOfFileInZip computes for it. An empty / absolute / duplicate
 * name is the likely close() killer, and it's the one variable my earlier
 * manual reproductions approximated instead of replicated.
 */
class BackupDiagnose extends Command
{
    protected $signature = 'backup:diagnose';

    protected $description = 'Call spatie\'s real Zip::createForManifest and print each entry + computed name.';

    public function handle(): int
    {
        $tempRoot = (string) config('backup.backup.temporary_directory', storage_path('app/backup-temp'));
        $tempDir = $tempRoot.DIRECTORY_SEPARATOR.'diag';
        if (! is_dir($tempDir.DIRECTORY_SEPARATOR.'db-dumps')) {
            @mkdir($tempDir.DIRECTORY_SEPARATOR.'db-dumps', 0o775, true);
        }

        // 1. Real dump (BackupJob::dumpDatabases equivalent).
        $dump = $tempDir.DIRECTORY_SEPARATOR.'db-dumps'.DIRECTORY_SEPARATOR.'dump.sql';
        @unlink($dump);
        try {
            DbDumperFactory::createFromConnection(config('database.default'))->dumpToFile($dump);
        } catch (\Throwable $e) {
            $this->error('dump failed: '.$e->getMessage());

            return self::FAILURE;
        }

        // 2. Real file selection (BackupJob::filesToBeBackedUp equivalent).
        $selection = FileSelection::create(config('backup.backup.source.files.include', []));
        foreach (config('backup.backup.source.files.exclude', []) as $ex) {
            $selection->excludeFilesFrom($ex);
        }
        $selection->shouldFollowLinks((bool) config('backup.backup.source.files.follow_links', false));
        $selection->shouldIgnoreUnreadableDirs((bool) config('backup.backup.source.files.ignore_unreadable_directories', false));
        $files = [];
        foreach ($selection->selectedFiles() as $p) {
            $files[] = $p;
        }

        // 3. Build the manifest exactly like BackupJob (dumps first, then files).
        $manifestPath = $tempDir.DIRECTORY_SEPARATOR.'manifest.txt';
        @unlink($manifestPath);
        $manifest = Manifest::create($manifestPath)->addFiles([$dump])->addFiles($files);

        // 4. pathToZip + relativePath, exactly as spatie resolves them.
        $pathToZip = $tempDir.DIRECTORY_SEPARATOR.'test.zip';
        $relativePathRaw = (string) config('backup.backup.source.files.relative_path', '');
        $relativePath = $relativePathRaw !== '' ? rtrim($relativePathRaw, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR : '';

        // 5. Print each manifest entry + the NAME spatie's determineNameOfFileInZip
        //    will compute for it (replicated here only for display).
        $this->info('Manifest entries — "computed zip name" <- real path:');
        $names = [];
        foreach ($manifest->files() as $file) {
            $name = $this->nameInZip($file, $pathToZip, $relativePath);
            $names[] = $name;
            $flag = $name === '' ? '  <EMPTY NAME>' : (in_array($name, array_slice($names, 0, -1), true) ? '  <DUPLICATE NAME>' : '');
            $this->line('  "'.$name.'"  <-  '.$file.$flag);
        }

        // 6. Call spatie's ACTUAL Zip::createForManifest — the exact failing path.
        $this->newLine();
        $this->info("Calling spatie's Zip::createForManifest() directly…");
        @unlink($pathToZip);
        try {
            $zip = Zip::createForManifest($manifest, $pathToZip);
            $this->line('  <fg=green>SUCCEEDED — '.$zip->count().' entries, '.$zip->humanReadableSize().'. Zip at '.$zip->path().'</>');
            $this->line('  createForManifest works in isolation -> BackupJob must pass a different manifest/path.');
        } catch (\Throwable $e) {
            $this->line('  <fg=red>FAILED: '.$e->getMessage().'</>');
            $this->line('  origin: '.$e->getFile().':'.$e->getLine());
        }
        @unlink($pathToZip);

        return self::SUCCESS;
    }

    /**
     * Faithful replica of spatie's Zip::determineNameOfFileInZip, for display.
     */
    private function nameInZip(string $file, string $pathToZip, string $relativePath): string
    {
        $fileDirectory = pathinfo($file, PATHINFO_DIRNAME).DIRECTORY_SEPARATOR;
        $zipDirectory = pathinfo($pathToZip, PATHINFO_DIRNAME).DIRECTORY_SEPARATOR;

        if (str_starts_with($fileDirectory, $zipDirectory)) {
            return substr($file, strlen($zipDirectory));
        }

        if ($relativePath && $relativePath !== DIRECTORY_SEPARATOR && str_starts_with($fileDirectory, $relativePath)) {
            return substr($file, strlen($relativePath));
        }

        return $file;
    }
}
