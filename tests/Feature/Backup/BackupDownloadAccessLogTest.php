<?php

declare(strict_types=1);

namespace Tests\Feature\Backup;

use App\Models\Mess;
use App\Models\User;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Models\Audit;
use Tests\TestCase;

/**
 * Plan 06-03 Task 3 — BackupDownloadAccessLogTest (T-06-03-05 — PII leak
 * prevention via the access-logged download).
 *
 * Verifies:
 *   Test 12 — super-admin GET /dashboard/backups/<path>/download streams the
 *             file AND writes an audit row with event='backup.download'
 *   Test 13 — super-admin GET the download for a NON-existent path returns 404
 *             AND writes no audit row (abort fires before the audit write)
 *   Test 14 — admin GET the download returns 403 (super-admin-only surface)
 *
 * D-08 enforcement: Storage::fake('backups') so we do NOT hit DO Spaces; a
 * stub zip is put on the faked disk.
 */
class BackupDownloadAccessLogTest extends TestCase
{
    use RefreshDatabase;

    private const PATH = 'test-backup.zip';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();

        Mess::factory()->create(['name' => 'Test Mess']);
        config(['mess.active_mess_id' => Mess::first()->id]);
        Mess::forgetActiveIdCache();

        Storage::fake('backups');
        Storage::disk('backups')->put(self::PATH, 'fake-zip-content');
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'super-admin')->first());

        return $user;
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'admin')->first());

        return $user;
    }

    /**
     * Test 12: super-admin GET download streams the file + writes an audit
     * row with event='backup.download'. T-06-03-05 — every download leaves
     * a tamper-evident trail.
     *
     * The streamed-download body is sent via a closure + readStream(); the
     * core T-06-03-05 requirement is the access-log row + the 200, not the
     * bytes themselves, so we assert the status + the audit row (not the
     * streamed content — which is empty when the closure has not been
     * executed by the testing client).
     */
    public function test_super_admin_download_streams_file_and_writes_audit(): void
    {
        $this->actingAs($this->superAdmin())
            ->get('/dashboard/backups/'.self::PATH.'/download')
            ->assertOk()
            ->assertHeader('content-disposition');

        $audit = Audit::where('event', 'backup.download')->latest('id')->first();
        $this->assertNotNull($audit, 'Expected an audit row with event=backup.download.');
        // Audit::new_values is json-cast (array). Assert array access, not a
        // substring on the serialized blob (CR-03 — the cast must encode once).
        $this->assertSame(self::PATH, $audit->new_values['path'] ?? null);
    }

    /**
     * Test 13: super-admin GET download for a NON-existent path returns 404
     * and writes NO audit row (abort_unless fires before writeAudit).
     */
    public function test_super_admin_download_missing_path_returns_404(): void
    {
        $this->actingAs($this->superAdmin())
            ->get('/dashboard/backups/does-not-exist.zip/download')
            ->assertNotFound();

        $this->assertSame(0, Audit::where('event', 'backup.download')->count());
    }

    /**
     * Test 14: admin GET download returns 403 (super-admin-only download).
     */
    public function test_admin_gets_403_on_download(): void
    {
        $this->actingAs($this->admin())
            ->get('/dashboard/backups/'.self::PATH.'/download')
            ->assertForbidden();
    }
}
