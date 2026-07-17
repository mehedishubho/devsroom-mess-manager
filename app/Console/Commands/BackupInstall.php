<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

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

        // Schedule cron
        $this->newLine();
        $this->info('Scheduler cron (required for automatic backups):');
        $this->line('  * * * * * cd '.base_path().' && php artisan schedule:run >> /dev/null 2>&1');

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
