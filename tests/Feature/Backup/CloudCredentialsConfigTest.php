<?php

declare(strict_types=1);

namespace Tests\Feature\Backup;

use App\Models\BackupConfig;
use App\Models\User;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * UI flow for DB-stored cloud credentials on /dashboard/backups:
 *   - super-admin PUT /configure persists GDrive creds (encrypted at rest)
 *   - a blank secret field keeps the existing stored value
 *   - GET /dashboard/backups shows the "Credentials configured" badge
 *   - POST /test/{provider} returns JSON ok=true against a faked disk
 *   - non-super-admin is forbidden from the test endpoint
 */
class CloudCredentialsConfigTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();

        Storage::fake('backups');

        // Use the local driver for the cloud disks so the Test-connection probe
        // works without the google-drive / s3 packages touching real storage.
        config([
            'filesystems.disks.backups-gdrive.driver' => 'local',
            'filesystems.disks.backups-r2.driver' => 'local',
        ]);
        Storage::fake('backups-gdrive');
        Storage::fake('backups-r2');
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'super-admin')->first());

        return $user;
    }

    private function baseForm(): array
    {
        return [
            'frequency' => 'daily',
            'run_at' => '01:30',
            'keep_all_days' => 7,
            'max_mb' => 5000,
        ];
    }

    public function test_super_admin_can_save_gdrive_credentials_encrypted(): void
    {
        $this->actingAs($this->superAdmin())
            ->put('/dashboard/backups/configure', array_merge($this->baseForm(), [
                'gdrive_client_id' => 'cid',
                'gdrive_client_secret' => 'secret-value',
                'gdrive_refresh_token' => 'rt',
                'gdrive_folder_id' => 'fid',
            ]))
            ->assertRedirect();

        $cfg = BackupConfig::find(1);
        $this->assertSame('cid', $cfg->gdrive_client_id);
        $this->assertSame('secret-value', $cfg->gdrive_client_secret); // decrypted

        // Raw DB row is ciphertext, not plaintext.
        $this->assertNotEquals(
            'secret-value',
            DB::table('backup_configs')->where('id', 1)->value('gdrive_client_secret')
        );
    }

    public function test_blank_secret_keeps_existing_stored_value(): void
    {
        BackupConfig::updateOrCreate(['id' => 1], array_merge([
            'frequency' => 'daily',
            'run_at' => '01:30',
            'keep_all_days' => 7,
            'max_mb' => 5000,
        ], ['gdrive_client_secret' => 'original-secret']));

        $this->actingAs($this->superAdmin())
            ->put('/dashboard/backups/configure', array_merge($this->baseForm(), [
                'gdrive_client_id' => 'new-cid',
                'gdrive_client_secret' => '', // blank -> keep existing
                'gdrive_refresh_token' => '',
            ]))
            ->assertRedirect();

        $cfg = BackupConfig::find(1);
        $this->assertSame('new-cid', $cfg->gdrive_client_id);
        $this->assertSame('original-secret', $cfg->gdrive_client_secret);
    }

    public function test_index_shows_configured_badge_after_credentials_saved(): void
    {
        BackupConfig::updateOrCreate(['id' => 1], array_merge([
            'frequency' => 'daily',
            'run_at' => '01:30',
            'keep_all_days' => 7,
            'max_mb' => 5000,
        ], [
            'gdrive_client_id' => 'cid',
            'gdrive_client_secret' => 'cs',
            'gdrive_refresh_token' => 'rt',
            'gdrive_folder_id' => 'fid',
        ]));
        BackupConfig::flushCache();

        $this->actingAs($this->superAdmin())
            ->get('/dashboard/backups')
            ->assertOk()
            ->assertSee(__('Credentials configured'));
    }

    public function test_test_connection_returns_json_ok_with_valid_disk(): void
    {
        BackupConfig::updateOrCreate(['id' => 1], array_merge([
            'frequency' => 'daily',
            'run_at' => '01:30',
            'keep_all_days' => 7,
            'max_mb' => 5000,
        ], [
            'gdrive_client_id' => 'cid',
            'gdrive_client_secret' => 'cs',
            'gdrive_refresh_token' => 'rt',
            'gdrive_folder_id' => 'fid',
        ]));

        $this->actingAs($this->superAdmin())
            ->withHeader('Accept', 'application/json')
            ->post('/dashboard/backups/test/gdrive')
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_test_connection_is_forbidden_for_non_super_admin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());

        $this->actingAs($admin)
            ->post('/dashboard/backups/test/r2')
            ->assertForbidden();
    }
}
