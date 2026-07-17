<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Backup\Tasks\Backup\DbDumperFactory;
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

        // 2. Real source files with their REAL relative entry names (this is
        //    what my earlier self-test skipped — it used 'file0' and no
        //    setCompressionName).
        $public = storage_path('app/public');
        $entries = [];
        if (is_dir($public)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($public, \FilesystemIterator::SKIP_DOTS)) as $f) {
                if ($f->isFile()) {
                    $entries[] = ['path' => $f->getPathname(), 'name' => substr($f->getPathname(), strlen($public) + 1)];
                }
            }
        }
        $entries[] = ['path' => $dump, 'name' => 'db-dumps'.DIRECTORY_SEPARATOR.basename($dump)];

        $this->info('Entries spatie would zip:');
        foreach ($entries as $e) {
            $this->line('  "'.$e['name'].'"  <-  '.$e['path']);
        }

        // 3. Variants — auto-isolate the trigger.
        $variants = [
            'V1 full reproduction (addFile + setCompressionName CM_DEFAULT/9)' => ['compress' => true, 'method' => ZipArchive::CM_DEFAULT, 'level' => 9],
            'V2 no setCompressionName at all' => ['compress' => false, 'method' => 0, 'level' => 0],
            'V3 setCompressionName CM_STORE (BACKUP_ZIP_COMPRESS=false)' => ['compress' => true, 'method' => ZipArchive::CM_STORE, 'level' => 0],
            'V4 source files only, with setCompressionName' => ['compress' => true, 'method' => ZipArchive::CM_DEFAULT, 'level' => 9, 'skipDump' => true],
            'V5 dump only, with setCompressionName' => ['compress' => true, 'method' => ZipArchive::CM_DEFAULT, 'level' => 9, 'skipFiles' => true],
        ];

        $results = [];
        foreach ($variants as $label => $cfg) {
            $zipFile = $tempDir.DIRECTORY_SEPARATOR.'__diag_'.uniqid('v', true).'.zip';
            $z = new ZipArchive;
            $opened = @$z->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            if ($opened !== true) {
                $this->line("  <fg=red>$label: open() failed ($opened)</>");
                @unlink($zipFile);

                continue;
            }
            foreach ($entries as $e) {
                if (($cfg['skipDump'] ?? false) && str_contains($e['name'], 'db-dumps')) {
                    continue;
                }
                if (($cfg['skipFiles'] ?? false) && ! str_contains($e['name'], 'db-dumps')) {
                    continue;
                }
                @$z->addFile($e['path'], $e['name']);
                if ($cfg['compress']) {
                    try {
                        @$z->setCompressionName($e['name'], $cfg['method'], $cfg['level']);
                    } catch (\Throwable) {
                    }
                }
            }
            $ok = false;
            try {
                $ok = (bool) @$z->close();
            } catch (\Throwable) {
                $ok = false;
            }
            $results[$label] = $ok;
            $this->line($ok ? "  <fg=green>$label: close() OK</>" : "  <fg=red>$label: close() FAILED</>");
            @unlink($zipFile);
        }
        @unlink($dump);

        $this->newLine();
        $this->info('Interpretation:');
        if (($results['V1 full reproduction (addFile + setCompressionName CM_DEFAULT/9)'] ?? true) === true) {
            $this->line('  V1 succeeded here but fails in spatie — the difference is likely the entry NAME spatie');
            $this->line('  computes (absolute path / empty). Paste the "Entries spatie would zip" list above.');
        } elseif (($results['V3 setCompressionName CM_STORE (BACKUP_ZIP_COMPRESS=false)'] ?? false) === true) {
            $this->line('  <fg=green>FIX: add BACKUP_ZIP_COMPRESS=false to .env (CM_STORE works, CM_DEFAULT does not).</>');
        } elseif (($results['V2 no setCompressionName at all'] ?? false) === true) {
            $this->line('  setCompressionName itself (any method) breaks close() on this server. Avoid it by');
            $this->line('  setting BACKUP_ZIP_COMPRESS=false AND the code path still calls it — report this so');
            $this->line('  we can patch the Zip override.');
        } elseif (($results['V4 source files only, with setCompressionName'] ?? true) === false) {
            $this->line('  The source files break it. Look at the entry names above for a bad path/name.');
        } elseif (($results['V5 dump only, with setCompressionName'] ?? true) === false) {
            $this->line('  The dump entry breaks it (size/name).');
        }

        return self::SUCCESS;
    }
}
