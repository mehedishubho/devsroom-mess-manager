<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\BackupConfig;

/**
 * Resolves the spatie backup destination disk list.
 *
 * Backups ALWAYS write to the local folder (`backups-local`). When a provider
 * is enabled in the DB-backed BackupConfig AND its env credentials are set,
 * the corresponding cloud disk is appended. This keeps Local as the always-on
 * default and lets a super-admin toggle Google Drive / Cloudflare R2 /
 * DigitalOcean Spaces on or off without a redeploy.
 *
 * Uses only env() + the (bootstrap-safe) BackupConfig::current() memoized
 * singleton, so it is safe to call from config/backup.php.
 */
class BackupDestinations
{
    /** @return list<string> */
    public static function all(): array
    {
        $disks = ['backups-local'];

        if (static::spacesConfigured()) {
            $disks[] = 'backups';
        }

        // DB-toggled providers — wrap in try/catch so the spatie boot path
        // never fatals on a fresh clone (missing table / unreachable DB).
        try {
            $config = BackupConfig::current();

            if ($config->gdrive_backup && static::gdriveConfigured()) {
                $disks[] = 'backups-gdrive';
            }

            if ($config->r2_backup && static::r2Configured()) {
                $disks[] = 'backups-r2';
            }
        } catch (\Throwable) {
            // Bootstrap-safe: degrade to the always-on Local disk only.
        }

        return $disks;
    }

    public static function spacesConfigured(): bool
    {
        return filled(env('DO_SPACES_KEY'))
            && filled(env('DO_SPACES_SECRET'))
            && filled(env('DO_SPACES_BUCKET'));
    }

    public static function gdriveConfigured(): bool
    {
        // Read via config() so tests can override with config([...]) and
        // runtime changes after a config:clear take effect. config/filesystems.php
        // resolves env() at boot, then hands the values here.
        return filled(config('filesystems.disks.backups-gdrive.clientId'))
            && filled(config('filesystems.disks.backups-gdrive.clientSecret'))
            && filled(config('filesystems.disks.backups-gdrive.refreshToken'))
            && filled(config('filesystems.disks.backups-gdrive.folderId'));
    }

    public static function r2Configured(): bool
    {
        return filled(config('filesystems.disks.backups-r2.key'))
            && filled(config('filesystems.disks.backups-r2.secret'))
            && filled(config('filesystems.disks.backups-r2.bucket'))
            && filled(config('filesystems.disks.backups-r2.endpoint'));
    }
}
