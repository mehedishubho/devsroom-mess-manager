<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\BackupConfig;
use App\Support\BackupDestinations;
use App\Support\CloudBackupCredentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * CloudBackupCredentials — the layer that merges DB-stored Google Drive + R2
 * credentials over the env-derived disk config at runtime.
 *
 * Covers the four guarantees the UI relies on:
 *   1. DB creds are pushed into config('filesystems.disks.*') on apply.
 *   2. An empty DB value leaves the env value in place (env fallback).
 *   3. Secret fields are encrypted at rest (raw DB row != plaintext).
 *   4. gdriveConfiguredFromDb / r2ConfiguredFromDb reflect the DB row.
 */
class CloudBackupCredentialsTest extends TestCase
{
    use RefreshDatabase;

    protected function baseConfig(): array
    {
        return [
            'id' => 1,
            'frequency' => 'daily',
            'run_at' => '01:30',
            'keep_all_days' => 7,
            'max_mb' => 5000,
        ];
    }

    public function test_apply_pushes_db_gdrive_credentials_into_runtime_config(): void
    {
        BackupConfig::updateOrCreate(['id' => 1], array_merge($this->baseConfig(), [
            'gdrive_backup' => true,
            'gdrive_client_id' => 'cid-123',
            'gdrive_client_secret' => 'secret-456',
            'gdrive_refresh_token' => 'refresh-789',
            'gdrive_folder_id' => 'folder-000',
        ]));
        BackupConfig::flushCache();

        // Start from an empty disk config to prove the override populates it.
        config([
            'filesystems.disks.backups-gdrive.clientId' => null,
            'filesystems.disks.backups-gdrive.clientSecret' => null,
            'filesystems.disks.backups-gdrive.refreshToken' => null,
            'filesystems.disks.backups-gdrive.folderId' => null,
        ]);

        CloudBackupCredentials::applyToRuntimeConfig();

        $this->assertSame('cid-123', config('filesystems.disks.backups-gdrive.clientId'));
        $this->assertSame('secret-456', config('filesystems.disks.backups-gdrive.clientSecret'));
        $this->assertSame('refresh-789', config('filesystems.disks.backups-gdrive.refreshToken'));
        $this->assertSame('folder-000', config('filesystems.disks.backups-gdrive.folderId'));
        // Uploads mirror disk shares the credentials.
        $this->assertSame('cid-123', config('filesystems.disks.uploads-gdrive.clientId'));

        // With toggle on + creds present, the disk is now targeted by spatie.
        $this->assertContains('backups-gdrive', BackupDestinations::all());
        $this->assertContains('backups-gdrive', config('backup.backup.destination.disks'));
    }

    public function test_env_fallback_when_db_value_empty(): void
    {
        config(['filesystems.disks.backups-r2.key' => 'env-key']);

        BackupConfig::updateOrCreate(['id' => 1], array_merge($this->baseConfig(), [
            'r2_key' => null, // DB empty -> env value must remain.
        ]));
        BackupConfig::flushCache();

        CloudBackupCredentials::applyToRuntimeConfig();

        $this->assertSame('env-key', config('filesystems.disks.backups-r2.key'));
    }

    public function test_secrets_are_encrypted_at_rest(): void
    {
        BackupConfig::updateOrCreate(['id' => 1], array_merge($this->baseConfig(), [
            'gdrive_client_secret' => 'plain-secret-value',
            'r2_secret' => 'plain-r2-secret',
        ]));

        $rawGdrive = DB::table('backup_configs')->where('id', 1)->value('gdrive_client_secret');
        $rawR2 = DB::table('backup_configs')->where('id', 1)->value('r2_secret');

        $this->assertNotEquals('plain-secret-value', $rawGdrive);
        $this->assertNotEmpty($rawGdrive);
        $this->assertNotEquals('plain-r2-secret', $rawR2);

        // The model decrypts transparently.
        $cfg = BackupConfig::find(1);
        $this->assertSame('plain-secret-value', $cfg->gdrive_client_secret);
        $this->assertSame('plain-r2-secret', $cfg->r2_secret);
    }

    public function test_configured_from_db_flags(): void
    {
        BackupConfig::updateOrCreate(['id' => 1], array_merge($this->baseConfig(), [
            'gdrive_client_id' => 'cid',
            'gdrive_client_secret' => 'cs',
            'gdrive_refresh_token' => 'rt',
            'gdrive_folder_id' => 'fid',
            'r2_key' => null,
        ]));
        BackupConfig::flushCache();

        $this->assertTrue(CloudBackupCredentials::gdriveConfiguredFromDb());
        $this->assertFalse(CloudBackupCredentials::r2ConfiguredFromDb());
    }
}
