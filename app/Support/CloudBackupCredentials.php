<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\BackupConfig;

/**
 * Merges DB-stored Google Drive + Cloudflare R2 credentials over the
 * env-derived filesystem disk config and pushes the result into Laravel's
 * runtime config repository.
 *
 * Why this exists: credentials used to live only in .env (read by
 * config/filesystems.php at boot). The /dashboard/backups UI now lets a
 * super-admin store them in the backup_configs row instead. DB values take
 * precedence when present; an empty DB value leaves the env value in place,
 * so .env keeps working as a fallback and nothing breaks before the operator
 * pastes creds into the UI.
 *
 * Called from AppServiceProvider::boot() (so every request/command sees DB
 * creds before any disk is resolved) and BackupController::testConnection()
 * (so a just-saved value is probeable without a new process).
 *
 * Bootstrap-safe, mirroring BackupDestinations: any failure (missing table on
 * a fresh clone, DB unreachable during early boot) returns silently.
 */
class CloudBackupCredentials
{
    /**
     * Override filesystems.disks.* from DB credentials, then recompute the
     * spatie destination/monitor lists so the cloud disks are actually used.
     */
    public static function applyToRuntimeConfig(): void
    {
        try {
            $config = BackupConfig::current();
        } catch (\Throwable) {
            return;
        }

        try {
            // Google Drive — map DB columns to the disk config keys the
            // google-drive driver reads (clientId/clientSecret/refreshToken/
            // folderId). Both the backup disk and the uploads mirror share creds.
            $gdrive = [
                'clientId' => $config->gdrive_client_id,
                'clientSecret' => $config->gdrive_client_secret, // decrypted via cast
                'refreshToken' => $config->gdrive_refresh_token,  // decrypted via cast
                'folderId' => $config->gdrive_folder_id,
            ];
            foreach ($gdrive as $key => $value) {
                if (filled($value)) {
                    static::overrideDisk('backups-gdrive', $key, $value);
                    static::overrideDisk('uploads-gdrive', $key, $value);
                }
            }

            // Cloudflare R2 — stock s3 driver reads key/secret/region/bucket/
            // endpoint/use_path_style_endpoint.
            foreach (['key', 'secret', 'region', 'bucket', 'endpoint'] as $key) {
                $value = match ($key) {
                    'key' => $config->r2_key,
                    'secret' => $config->r2_secret, // decrypted via cast
                    'region' => $config->r2_region,
                    'bucket' => $config->r2_bucket,
                    'endpoint' => $config->r2_endpoint,
                    default => null,
                };
                if (filled($value)) {
                    static::overrideDisk('backups-r2', $key, $value);
                    static::overrideDisk('uploads-r2', $key, $value);
                }
            }
            // Boolean toggle: apply unconditionally (its default false matches
            // the env default; only meaningful once R2 creds are present).
            static::overrideDisk('backups-r2', 'use_path_style_endpoint', (bool) $config->r2_use_path_style);
            static::overrideDisk('uploads-r2', 'use_path_style_endpoint', (bool) $config->r2_use_path_style);

            // spatie reads config('backup.backup.destination.disks') at
            // backup:run time. BackupDestinations::all() now reflects the
            // overridden creds, so re-key the spatie lists so the cloud disks
            // are actually targeted.
            $disks = BackupDestinations::all();
            config(['backup.backup.destination.disks' => $disks]);

            $monitor = config('backup.monitor_backups', []);
            foreach ($monitor as $i => $entry) {
                $monitor[$i]['disks'] = $disks;
            }
            config(['backup.monitor_backups' => $monitor]);
        } catch (\Throwable) {
            // Best-effort: never break the request/boot over a config override.
        }
    }

    /** True when Google Drive credentials are stored in the DB. */
    public static function gdriveConfiguredFromDb(): bool
    {
        try {
            $c = BackupConfig::current();

            return filled($c->gdrive_client_id)
                && filled($c->gdrive_client_secret)
                && filled($c->gdrive_refresh_token)
                && filled($c->gdrive_folder_id);
        } catch (\Throwable) {
            return false;
        }
    }

    /** True when Cloudflare R2 credentials are stored in the DB. */
    public static function r2ConfiguredFromDb(): bool
    {
        try {
            $c = BackupConfig::current();

            return filled($c->r2_key)
                && filled($c->r2_secret)
                && filled($c->r2_bucket)
                && filled($c->r2_endpoint);
        } catch (\Throwable) {
            return false;
        }
    }

    private static function overrideDisk(string $disk, string $key, mixed $value): void
    {
        config(["filesystems.disks.{$disk}.{$key}" => $value]);
    }
}
