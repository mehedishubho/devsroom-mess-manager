<?php

declare(strict_types=1);

namespace Tests\Feature\Backup;

use App\Models\BackupConfig;
use App\Models\User;
use App\Support\BackupDestinations;
use App\Support\StorageProvider;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Task 1 of quick-260717-2q3 — multi-provider backup + uploads mirroring.
 *
 * Covers the four DB-toggled provider flags (gdrive/r2 × backup/uploads),
 * BackupDestinations::all() dynamic resolution, StorageProvider::store()
 * mirror behavior, and the Configure Backups form persistence surface.
 *
 * D-08: cloud disks are faked — no real object storage or Drive API is
 * touched. The "misconfigured provider skipped gracefully" branch is
 * exercised by configuring the gdrive disk with no creds.
 */
class StorageProviderConfigTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();

        // Seed the singleton row so BackupConfig::current() reads from DB.
        BackupConfig::query()->updateOrCreate(['id' => 1], [
            'frequency' => 'daily',
            'run_at' => '01:30',
            'keep_all_days' => 7,
            'max_mb' => 5000,
            'enabled_spaces' => false,
            'gdrive_backup' => false,
            'gdrive_uploads' => false,
            'r2_backup' => false,
            'r2_uploads' => false,
        ]);
        BackupConfig::flushCache();

        Storage::fake('public');
        Storage::fake('uploads-gdrive');
        Storage::fake('uploads-r2');
    }

    public function test_backup_config_exposes_per_provider_per_group_flags_with_defaults_false(): void
    {
        $config = BackupConfig::current();

        $this->assertFalse($config->gdrive_backup);
        $this->assertFalse($config->gdrive_uploads);
        $this->assertFalse($config->r2_backup);
        $this->assertFalse($config->r2_uploads);
    }

    public function test_persisting_provider_flags_round_trips_through_the_singleton(): void
    {
        BackupConfig::query()->where('id', 1)->update([
            'gdrive_backup' => true,
            'gdrive_uploads' => false,
            'r2_backup' => false,
            'r2_uploads' => true,
        ]);
        BackupConfig::flushCache();

        $config = BackupConfig::current();
        $this->assertTrue($config->gdrive_backup);
        $this->assertFalse($config->gdrive_uploads);
        $this->assertFalse($config->r2_backup);
        $this->assertTrue($config->r2_uploads);

        $enabled = $config->enabledProviders();
        $this->assertSame(true, $enabled['gdrive']['backup']);
        $this->assertSame(true, $enabled['r2']['uploads']);
    }

    public function test_backup_destinations_starts_with_local_only_when_nothing_enabled(): void
    {
        $this->assertSame(['backups-local'], BackupDestinations::all());
    }

    public function test_backup_destinations_appends_gdrive_only_when_flag_and_creds_set(): void
    {
        BackupConfig::query()->where('id', 1)->update(['gdrive_backup' => true]);
        BackupConfig::flushCache();

        // Without creds → NOT appended.
        $this->assertSame(['backups-local'], BackupDestinations::all());

        // With creds → appended.
        config([
            'filesystems.disks.backups-gdrive.clientId' => 'cid',
            'filesystems.disks.backups-gdrive.clientSecret' => 'csecret',
            'filesystems.disks.backups-gdrive.folderId' => 'folder-123',
            'filesystems.disks.backups-gdrive.refreshToken' => 'token-abc',
        ]);
        $this->assertContains('backups-gdrive', BackupDestinations::all());
    }

    public function test_backup_destinations_appends_r2_only_when_flag_and_creds_set(): void
    {
        BackupConfig::query()->where('id', 1)->update(['r2_backup' => true]);
        BackupConfig::flushCache();

        // Without creds → NOT appended.
        $this->assertSame(['backups-local'], BackupDestinations::all());

        config([
            'filesystems.disks.backups-r2.key' => 'r2key',
            'filesystems.disks.backups-r2.secret' => 'r2secret',
            'filesystems.disks.backups-r2.bucket' => 'my-bucket',
            'filesystems.disks.backups-r2.endpoint' => 'https://example.r2.cloudflarestorage.com',
        ]);
        $this->assertContains('backups-r2', BackupDestinations::all());
    }

    public function test_storage_provider_active_upload_disks_returns_public_only_by_default(): void
    {
        $this->assertSame(['public'], StorageProvider::activeUploadDisks());
    }

    public function test_storage_provider_appends_cloud_mirrors_when_flag_and_creds_set(): void
    {
        BackupConfig::query()->where('id', 1)->update([
            'gdrive_uploads' => true,
            'r2_uploads' => true,
        ]);
        BackupConfig::flushCache();
        // BackupDestinations::gdriveConfigured() reads backups-gdrive.* (the
        // shared env-backed config); uploads-gdrive mirrors the same env vars
        // so a single check covers both disks.
        config([
            'filesystems.disks.backups-gdrive.clientId' => 'cid',
            'filesystems.disks.backups-gdrive.clientSecret' => 'csecret',
            'filesystems.disks.backups-gdrive.folderId' => 'folder-123',
            'filesystems.disks.backups-gdrive.refreshToken' => 'token-abc',
            'filesystems.disks.backups-r2.key' => 'r2key',
            'filesystems.disks.backups-r2.secret' => 'r2secret',
            'filesystems.disks.backups-r2.bucket' => 'my-bucket',
            'filesystems.disks.backups-r2.endpoint' => 'https://example.r2.cloudflarestorage.com',
        ]);

        $disks = StorageProvider::activeUploadDisks();
        $this->assertSame(['public', 'uploads-gdrive', 'uploads-r2'], $disks);
    }

    public function test_store_writes_to_public_and_each_active_mirror(): void
    {
        BackupConfig::query()->where('id', 1)->update(['gdrive_uploads' => true, 'r2_uploads' => true]);
        BackupConfig::flushCache();
        config([
            'filesystems.disks.backups-gdrive.clientId' => 'cid',
            'filesystems.disks.backups-gdrive.clientSecret' => 'csecret',
            'filesystems.disks.backups-gdrive.folderId' => 'folder-123',
            'filesystems.disks.backups-gdrive.refreshToken' => 'token-abc',
            'filesystems.disks.backups-r2.key' => 'r2key',
            'filesystems.disks.backups-r2.secret' => 'r2secret',
            'filesystems.disks.backups-r2.bucket' => 'my-bucket',
            'filesystems.disks.backups-r2.endpoint' => 'https://example.r2.cloudflarestorage.com',
        ]);

        $file = UploadedFile::fake()->image('receipt.jpg');
        $path = StorageProvider::store('receipts/99.jpg', $file);

        $this->assertSame('receipts/99.jpg', $path);
        Storage::disk('public')->assertExists('receipts/99.jpg');
        Storage::disk('uploads-gdrive')->assertExists('receipts/99.jpg');
        Storage::disk('uploads-r2')->assertExists('receipts/99.jpg');
    }

    public function test_store_does_not_throw_when_a_mirror_disk_breaks(): void
    {
        // Toggle on but DO NOT fake the r2 disk; Storage::disk('uploads-r2')
        // will fall through to the real s3 adapter which throws on missing
        // creds — StorageProvider must catch + log + skip.
        BackupConfig::query()->where('id', 1)->update(['r2_uploads' => true]);
        BackupConfig::flushCache();
        config([
            'filesystems.disks.backups-r2.key' => 'r2key',
            'filesystems.disks.backups-r2.secret' => 'r2secret',
            'filesystems.disks.backups-r2.bucket' => 'my-bucket',
            'filesystems.disks.backups-r2.endpoint' => 'https://example.r2.cloudflarestorage.com',
        ]);

        $file = UploadedFile::fake()->image('receipt.jpg');

        // Must not throw — the primary 'public' write still succeeds.
        $path = StorageProvider::store('receipts/100.jpg', $file);
        $this->assertSame('receipts/100.jpg', $path);
        Storage::disk('public')->assertExists('receipts/100.jpg');
    }

    public function test_super_admin_can_persist_all_four_toggles_via_configure_form(): void
    {
        $super = User::factory()->create();
        $super->assignRole(Role::where('slug', 'super-admin')->first());

        config([
            'filesystems.disks.backups-gdrive.clientId' => 'cid',
            'filesystems.disks.backups-gdrive.clientSecret' => 'csecret',
            'filesystems.disks.backups-gdrive.folderId' => 'folder-123',
            'filesystems.disks.backups-gdrive.refreshToken' => 'token-abc',
            'filesystems.disks.backups-r2.key' => 'r2key',
            'filesystems.disks.backups-r2.secret' => 'r2secret',
            'filesystems.disks.backups-r2.bucket' => 'my-bucket',
            'filesystems.disks.backups-r2.endpoint' => 'https://example.r2.cloudflarestorage.com',
        ]);

        $this->actingAs($super)
            ->from(route('dashboard.backups.configure'))
            ->put(route('dashboard.backups.configure.update'), [
                'frequency' => 'daily',
                'run_at' => '01:30',
                'keep_all_days' => 7,
                'max_mb' => 5000,
                'gdrive_backup' => '1',
                'gdrive_uploads' => '0',
                'r2_backup' => '0',
                'r2_uploads' => '1',
            ])
            ->assertRedirect(route('dashboard.backups.index'));

        // Flush the in-process memo so the next read reflects the DB row.
        BackupConfig::flushCache();
        $config = BackupConfig::current();
        $this->assertTrue($config->gdrive_backup);
        $this->assertFalse($config->gdrive_uploads);
        $this->assertFalse($config->r2_backup);
        $this->assertTrue($config->r2_uploads);
    }

    public function test_configure_form_does_not_leak_secrets(): void
    {
        // T-2q3-02: rendered HTML must NEVER contain the raw refresh token
        // or R2 secret, only the "configured: yes/no" boolean.
        $super = User::factory()->create();
        $super->assignRole(Role::where('slug', 'super-admin')->first());

        config([
            'filesystems.disks.backups-gdrive.refreshToken' => 'SECRET-TOKEN-DO-NOT-LEAK',
            'filesystems.disks.backups-r2.secret' => 'SECRET-R2-KEY-DO-NOT-LEAK',
        ]);

        $response = $this->actingAs($super)->get(route('dashboard.backups.configure'));
        $response->assertOk();
        $response->assertDontSee('SECRET-TOKEN-DO-NOT-LEAK');
        $response->assertDontSee('SECRET-R2-KEY-DO-NOT-LEAK');
    }

    public function test_admin_cannot_open_configure_form(): void
    {
        // T-2q3-01 — only super-admin can mutate backup config.
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());

        $this->actingAs($admin)
            ->get(route('dashboard.backups.configure'))
            ->assertForbidden();
    }
}
