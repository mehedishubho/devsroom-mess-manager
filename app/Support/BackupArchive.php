<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Helpers for the Backups page's archive list.
 *
 * Two things the page needs per archive that are too expensive or too
 * cross-cutting to inline in the controller:
 *
 *  - WHERE an archive actually lives. With Local + optional Drive/R2 mirrors,
 *    "the backup exists" is not a yes/no question. A missing tick is the
 *    fastest way to notice a broken mirror.
 *  - Its sha256, so a downloaded archive can be verified offline. Hashing a
 *    multi-hundred-MB zip is far too slow to do while rendering a list, so the
 *    digest is computed on demand (the "Verify archive" action) and remembered
 *    keyed by path + mtime — a changed file naturally gets a new key.
 */
class BackupArchive
{
    /** How long a computed checksum is remembered. */
    public const CHECKSUM_TTL_DAYS = 90;

    /**
     * Which active destination disks hold this archive.
     *
     * @return array<string, bool> disk name => present
     */
    public static function destinations(string $path): array
    {
        $result = [];

        foreach (BackupDestinations::all() as $diskName) {
            try {
                $result[$diskName] = Storage::disk($diskName)->exists($path);
            } catch (\Throwable) {
                // An unreachable/misconfigured mirror is "not present here",
                // not a reason to blow up the whole page.
                $result[$diskName] = false;
            }
        }

        return $result;
    }

    /**
     * A previously computed checksum, if one was ever recorded for this exact
     * file version. Never hashes — safe to call while rendering the list.
     */
    public static function cachedChecksum(string $diskName, string $path, int $modified): ?string
    {
        $cached = Cache::get(self::checksumKey($diskName, $path, $modified));

        return is_string($cached) ? $cached : null;
    }

    /**
     * Compute the sha256 of the archive, streaming it so a large file is never
     * loaded into memory, then remember the result.
     *
     * @throws \RuntimeException when the archive cannot be read.
     */
    public static function checksum(string $diskName, string $path, int $modified): string
    {
        $stream = Storage::disk($diskName)->readStream($path);

        if (! is_resource($stream)) {
            throw new \RuntimeException("Could not read {$path} from the {$diskName} disk.");
        }

        $context = hash_init('sha256');

        try {
            hash_update_stream($context, $stream);
        } finally {
            fclose($stream);
        }

        $hash = hash_final($context);

        Cache::put(self::checksumKey($diskName, $path, $modified), $hash, now()->addDays(self::CHECKSUM_TTL_DAYS));

        return $hash;
    }

    /**
     * Cache key scoped to the file's current mtime — a rewritten archive gets
     * a different key, so a stale digest can never be reported as current.
     */
    private static function checksumKey(string $diskName, string $path, int $modified): string
    {
        return 'backup-checksum:'.sha1($diskName.'|'.$path.'|'.$modified);
    }
}
