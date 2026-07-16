<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\BackupConfig;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Mirrors file uploads to every active cloud-storage disk.
 *
 * The 'public' local disk is ALWAYS the primary write — its returned path is
 * what goes into the DB (receipt_path, photo_path, …). Cloud disks (Google
 * Drive / Cloudflare R2) are best-effort mirrors: a misconfigured provider
 * logs a warning and is skipped so the primary upload never blocks on a
 * slow / broken cloud mirror (T-2q3-03 DoS mitigation).
 */
class StorageProvider
{
    /**
     * Active upload-mirror disks for the current request. Always starts with
     * 'public' (the canonical URL surface); appends cloud mirrors when their
     * BackupConfig toggle is on AND their env creds are filled.
     *
     * @return list<string>
     */
    public static function activeUploadDisks(): array
    {
        $disks = ['public'];

        try {
            $config = BackupConfig::current();

            if ($config->gdrive_uploads && BackupDestinations::gdriveConfigured()) {
                $disks[] = 'uploads-gdrive';
            }

            if ($config->r2_uploads && BackupDestinations::r2Configured()) {
                $disks[] = 'uploads-r2';
            }
        } catch (\Throwable) {
            // Bootstrap-safe: degrade to public-disk-only.
        }

        return $disks;
    }

    /**
     * Store $content (an UploadedFile or raw string) at $path on the 'public'
     * disk, then mirror to every active cloud disk. Returns the 'public'-disk
     * path (the canonical URL surface — never the cloud-mirror path).
     */
    public static function store(string $path, string|UploadedFile $content, ?string $visibility = 'public'): string
    {
        // Primary write — the canonical URL surface.
        if ($content instanceof UploadedFile) {
            Storage::disk('public')->putFileAs(dirname($path), $content, basename($path), $visibility);
        } else {
            Storage::disk('public')->put($path, $content, $visibility);
        }

        // Best-effort cloud mirrors. One misconfigured provider MUST NOT block
        // the primary upload or break sibling mirrors (T-2q3-03).
        foreach (array_slice(static::activeUploadDisks(), 1) as $disk) {
            try {
                if ($content instanceof UploadedFile) {
                    Storage::disk($disk)->putFileAs(dirname($path), $content, basename($path), $visibility);
                } else {
                    Storage::disk($disk)->put($path, $content, $visibility);
                }
            } catch (\Throwable $e) {
                Log::warning('storage_provider.misconfigured', [
                    'disk' => $disk,
                    'path' => $path,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        return $path;
    }

    /**
     * Delete $path from the 'public' disk AND every active upload mirror.
     * Best-effort per disk; never throws.
     */
    public static function delete(string $path): void
    {
        Storage::disk('public')->delete($path);

        foreach (array_slice(static::activeUploadDisks(), 1) as $disk) {
            try {
                Storage::disk($disk)->delete($path);
            } catch (\Throwable $e) {
                Log::warning('storage_provider.delete_failed', [
                    'disk' => $disk,
                    'path' => $path,
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }
}
