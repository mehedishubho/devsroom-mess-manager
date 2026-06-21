<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Resolves the spatie backup destination disk list.
 *
 * Backups ALWAYS write to the local folder (`backups-local`). When DigitalOcean
 * Spaces credentials are fully configured (DO_SPACES_KEY + DO_SPACES_SECRET +
 * DO_SPACES_BUCKET all non-empty), backups additionally mirror to the `backups`
 * S3 disk — giving the "local by default, also goes to the configured system"
 * behavior.
 *
 * Uses only env(), so it is safe to call from config/backup.php (config is
 * resolved before the DB layer boots, so no Eloquent here).
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

        return $disks;
    }

    public static function spacesConfigured(): bool
    {
        return filled(env('DO_SPACES_KEY'))
            && filled(env('DO_SPACES_SECRET'))
            && filled(env('DO_SPACES_BUCKET'));
    }
}
