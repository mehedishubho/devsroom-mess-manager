<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Backup\Tasks\Backup\DbDumperFactory;

/**
 * One-shot setup + diagnostic for the backup system on shared hosting
 * (CloudPanel, cPanel, Plesk, etc.).
 *
 * Creates the directories spatie/ZipArchive need, then reports writability,
 * ownership, the mysqldump binary, PHP open_basedir / sys_temp_dir, and the
 * scheduler cron line — printing panel-specific, copy-pasteable fixes for
 * whatever it finds. Safe to re-run.
 */
class BackupInstall extends Command
{
    protected $signature = 'backup:install';

    protected $description = 'Set up + diagnose backup directories, permissions, mysqldump, and open_basedir for shared hosting (CloudPanel/cPanel/Plesk).';

    public function handle(): int
    {
        $this->info('Setting up backup directories…');

        $dirs = [
            'destination (zip output)' => storage_path('app/backups'),
            'spatie-temp (zip staging)' => (string) config('backup.backup.temporary_directory', storage_path('app/backup-temp')),
            'temp (spatie dump workdir)' => storage_path('app/laravel-backup'),
            'tmp (ZipArchive scratch)' => storage_path('app/tmp'),
        ];

        foreach ($dirs as $label => $path) {
            if (! is_dir($path)) {
                @mkdir($path, 0o775, true);
            }
        }

        $this->newLine();
        $this->info('Directory status:');
        $ownerProblem = false;
        foreach ($dirs as $label => $path) {
            $exists = is_dir($path);
            $writable = $exists && is_writable($path);
            $owner = $this->ownerOf($path);
            $status = $writable ? '<fg=green>WRITABLE</>' : '<fg=red>NOT WRITABLE</>';
            $this->line(sprintf('  %s — %s', $status, $label));
            $this->line(sprintf('    path:  %s', $path));
            $this->line(sprintf('    owner: %s', $owner ?? 'unknown'));
            if (! $writable) {
                $ownerProblem = true;
            }
        }

        if ($ownerProblem) {
            $this->newLine();
            $this->error('One or more backup directories are not writable by this PHP user.');
            $this->line('On shared hosting this almost always means a root-owned deploy. Fix:');
            $this->line('  # run as root (or sudo), replace <site-user> with the account PHP-FPM runs as:');
            $this->line('  chown -R <site-user>:<site-user> storage bootstrap/cache');
            $this->line('  find storage bootstrap/cache -type d -exec chmod 775 {} \\;');
            $this->line('  find storage bootstrap/cache -type f -exec chmod 664 {} \\;');
            $this->line('CloudPanel/cPanel: <site-user> is the account that owns /home/<user>/ .');
        }

        // Disk space / quota — open() can create a 0-byte zip (success) but
        // close() fails when there's no room to write the content.
        $this->newLine();
        $this->info('Disk space:');
        $free = @disk_free_space(storage_path('app'));
        $total = @disk_total_space(storage_path('app'));
        if ($free !== false && $total !== false) {
            $freeMb = number_format($free / 1024 / 1024, 1);
            $totalMb = number_format($total / 1024 / 1024, 1);
            $this->line(sprintf('  %s MB free of %s MB on the storage partition', $freeMb, $totalMb));
            if ($free < 50 * 1024 * 1024) {
                $this->line('  <fg=red>Low space — backup close() will fail. Free space or check the account quota.</>');
            }
        } else {
            $this->line('  <fg=yellow>could not read disk_free_space (disabled?)</>');
        }

        // mysqldump
        $this->newLine();
        $this->info('mysqldump:');
        $configured = config('database.connections.mysql.dump.dump_binary_path');
        $this->line(sprintf('  DUMP_BINARY_PATH config: %s', $configured ?: '(not set — default /usr/bin)'));
        $found = $this->whichMysqldump();
        if ($found !== null) {
            $this->line(sprintf('  found: <fg=green>%s</>', $found));
        } else {
            $this->line('  found: <fg=red>NOT FOUND</>');
            $this->line('  install it (the DB dump will fail without mysqldump):');
            $this->line('    Debian/Ubuntu: sudo apt install mysql-client');
            $this->line('    cPanel/CloudPanel: usually preinstalled; if not, ask the host or use a host that has it.');
        }

        // open_basedir / sys_temp_dir
        $this->newLine();
        $this->info('PHP temp / open_basedir:');
        $openBasedir = ini_get('open_basedir') ?: null;
        $sysTemp = sys_get_temp_dir();
        $this->line(sprintf('  sys_get_temp_dir(): %s', $sysTemp));
        $this->line(sprintf('  open_basedir: %s', $openBasedir ?: '(not set — fine)'));
        if ($openBasedir) {
            $allowed = array_map(fn ($p): string|false => realpath(rtrim($p, DIRECTORY_SEPARATOR)), explode(PATH_SEPARATOR, $openBasedir));
            $ok = in_array(realpath($sysTemp), $allowed, true);
            if (! $ok) {
                $this->line('  <fg=red>open_basedir excludes the system temp dir — ZipArchive will fail.</>');
                $this->line('  Set an in-account temp dir in your panel PHP settings (.user.ini / pool config):');
                $this->line('    sys_temp_dir = '.storage_path('app/tmp'));
                $this->line('    upload_tmp_dir = '.storage_path('app/tmp'));
            }
        }

        // ZipArchive self-test — reproduces the EXACT spatie sequence
        // (open → addFile → setCompressionName → close) that fails with
        // "ZipArchive::close(): Invalid argument" on some libzip builds.
        // Trying CM_DEFAULT vs CM_STORE tells us whether compression is it.
        $this->newLine();
        $this->info('ZipArchive self-test (reproduces the spatie close() call):');
        $this->line('  PHP zip extension: '.(phpversion('zip') ?: 'not loaded'));
        $staging = (string) config('backup.backup.temporary_directory', storage_path('app/backup-temp'));
        if (! is_dir($staging)) {
            @mkdir($staging, 0o775, true);
        }
        $src = $staging.DIRECTORY_SEPARATOR.'__ziptest_src.txt';
        @file_put_contents($src, 'backup zip self-test');
        $zipPath = $staging.DIRECTORY_SEPARATOR.'__ziptest.zip';
        $storeWorks = null;
        foreach (['CM_DEFAULT (compressed)' => [\ZipArchive::CM_DEFAULT, 9], 'CM_STORE (no compression)' => [\ZipArchive::CM_STORE, 0]] as $label => $cfg) {
            [$method, $level] = $cfg;
            @unlink($zipPath);
            $zip = new \ZipArchive;
            $opened = @$zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
            if ($opened !== true) {
                $this->line("  $label: open() failed ($opened)");

                continue;
            }
            @$zip->addFile($src, 'src.txt');
            try {
                @$zip->setCompressionName('src.txt', $method, $level);
            } catch (\Throwable) {
            }
            $ok = false;
            try {
                $ok = (bool) @$zip->close();
            } catch (\Throwable) {
                $ok = false;
            }
            if ($method === \ZipArchive::CM_STORE) {
                $storeWorks = $ok;
            }
            $this->line($ok
                ? "  <fg=green>$label: close() OK</>"
                : "  <fg=red>$label: close() FAILED — this method breaks on this server's libzip.</>");
        }
        @unlink($src);
        @unlink($zipPath);
        if ($storeWorks === true) {
            $this->line('  -> <fg=yellow>If CM_DEFAULT failed but CM_STORE worked, add BACKUP_ZIP_COMPRESS=false to .env.</>');
        }

        // Source-files self-test — the ZipArchive engine works in isolation
        // (above), so if the real backup still fails at close() the culprit is
        // a specific source file. spatie ignores addFile()'s return value, so a
        // rejected file (bad name / unreadable / symlink / missing dir for
        // relative_path) silently corrupts the archive and close() then fails.
        // This reproduces the addFile loop over the REAL storage/app/public.
        $this->newLine();
        $this->info('Source files self-test (storage/app/public):');
        $public = storage_path('app/public');
        if (! is_dir($public)) {
            $this->line('  <fg=red>storage/app/public does NOT exist. spatie\'s relative_path resolves to nothing and ZipArchive gets invalid entry names — the likely close() cause. Fix: mkdir -p storage/app/public && touch storage/app/public/.gitignore</>');
        } else {
            $files = [];
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($public, \FilesystemIterator::SKIP_DOTS)) as $f) {
                if ($f->isFile()) {
                    $files[] = $f->getPathname();
                }
            }
            $this->line(sprintf('  %d file(s) under storage/app/public', count($files)));
            $testZip = $staging.DIRECTORY_SEPARATOR.'__sourcetest.zip';
            $bad = [];
            foreach ($files as $i => $path) {
                @unlink($testZip);
                $z = new \ZipArchive;
                if (@$z->open($testZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                    continue;
                }
                $added = @$z->addFile($path, 'file'.$i);
                $ok = false;
                try {
                    $ok = (bool) @$z->close();
                } catch (\Throwable) {
                    $ok = false;
                }
                if (! $added || ! $ok) {
                    $bad[] = $path.' (addFile='.($added ? 'ok' : 'FAIL').', close='.($ok ? 'ok' : 'FAIL').')';
                }
            }
            @unlink($testZip);
            if ($bad === []) {
                $this->line('  <fg=green>All source files zipped cleanly — the file source is not the cause (it would be the DB dump).</>');
            } else {
                $this->line('  <fg=red>Problem source file(s):</>');
                foreach ($bad as $b) {
                    $this->line('    '.$b);
                }
            }
        }

        // DB dump self-test — the source files zipped cleanly, so the remaining
        // suspect is the DB dump spatie adds to the same archive. This runs the
        // REAL mysqldump (via spatie's own DbDumperFactory) and then addFile +
        // close on it, exactly like BackupJob does.
        $this->newLine();
        $this->info('DB dump self-test (mysqldump -> addFile -> close):');
        $dumpFile = $staging.DIRECTORY_SEPARATOR.'__dumptest.sql';
        $dumpZip = $staging.DIRECTORY_SEPARATOR.'__dumptest.zip';
        @unlink($dumpFile);
        @unlink($dumpZip);
        try {
            $dumper = DbDumperFactory::createFromConnection(config('database.default'));
            $dumper->dumpToFile($dumpFile);
            $size = is_file($dumpFile) ? (int) filesize($dumpFile) : 0;
            $this->line(sprintf('  dump created: %s (%s bytes)', basename($dumpFile), number_format($size)));
            $z = new \ZipArchive;
            $opened = @$z->open($dumpZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
            if ($opened !== true) {
                $this->line("  <fg=red>zip open() failed ($opened)</>");
            } else {
                $entry = 'db-dumps'.DIRECTORY_SEPARATOR.basename($dumpFile);
                @$z->addFile($dumpFile, $entry);
                try {
                    @$z->setCompressionName($entry, \ZipArchive::CM_DEFAULT, 9);
                } catch (\Throwable) {
                }
                $ok = false;
                try {
                    $ok = (bool) @$z->close();
                } catch (\Throwable) {
                    $ok = false;
                }
                $this->line($ok
                    ? '  <fg=green>dump zipped cleanly (close() OK) — the dump is not the cause either.</>'
                    : '  <fg=red>dump addFile/close() FAILED — the DB dump is what breaks the zip.</>');
            }
        } catch (\Throwable $e) {
            $this->line('  <fg=red>dump test error: '.$e->getMessage().'</>');
        } finally {
            @unlink($dumpFile);
            @unlink($dumpZip);
        }

        // Scheduler cron. Use the full path to the PHP binary that is running
        // this command (PHP_BINARY) instead of a bare `php`: on shared hosting
        // (CloudPanel/cPanel) the cron user's PATH does not include php, so a
        // bare-`php` line silently fails to run the scheduler. That is what
        // drives operators to hand-write the cron with the wrong `cd` target
        // and a non-existent script.php. PHP_BINARY here resolves to the exact
        // interpreter the operator just used (e.g. /usr/bin/php8.4).
        $this->newLine();
        $this->info('Scheduler cron (required for automatic backups):');
        $cronLine = '* * * * * cd '.base_path().' && '.PHP_BINARY.' artisan schedule:run >> /dev/null 2>&1';
        $this->line('  '.$cronLine);
        $this->line('  <fg=yellow>Paste this exact line into your server crontab (crontab -e / CloudPanel cron job).</>');

        // Public storage symlink. Uploaded files (member photos, bazar
        // receipts, profile images) are written to storage/app/public and
        // served from /storage/* via the public/storage symlink created by
        // storage:link. If the link is missing the files upload fine but the
        // browser gets a 404 — the classic "image uploads but won't display".
        $this->newLine();
        $this->info('Public storage symlink (required for uploaded images to display):');
        $link = public_path('storage');
        if (is_link($link)) {
            $this->line('  <fg=green>LINKED</> — '.$link.' -> '.readlink($link));
        } else {
            $this->line('  <fg=red>MISSING</> — '.$link.' is not a symlink.');
            $this->line('  Uploaded files are stored but NOT visible in the browser.');
            $this->line('  Fix: '.PHP_BINARY.' '.base_path().'/artisan storage:link');
        }

        $this->newLine();
        $ownerProblem
            ? $this->error('Backup directories are not writable — fix ownership above, then run Backup now.')
            : $this->info('All backup directories are writable. You can now run "Backup now" from the Backups page.');

        return self::SUCCESS;
    }

    private function ownerOf(string $path): ?string
    {
        if (function_exists('posix_getpwuid')) {
            // fileowner() returns the numeric uid; posix_getpwuid resolves it
            // to a name (0 = root — the classic shared-host ownership bug).
            $info = @posix_getpwuid(@fileowner($path));

            return $info['name'] ?? null;
        }

        return null;
    }

    private function whichMysqldump(): ?string
    {
        if (! function_exists('exec')) {
            return null;
        }
        $binary = (string) (config('database.connections.mysql.dump.dump_binary_path') ?? '/usr/bin');
        $candidate = rtrim($binary, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'mysqldump';
        if (is_executable($candidate)) {
            return $candidate;
        }
        // Fall back to a PATH lookup.
        @exec('command -v mysqldump 2>/dev/null', $output, $code);
        if ($code === 0 && ! empty($output[0])) {
            return $output[0];
        }

        return null;
    }
}
