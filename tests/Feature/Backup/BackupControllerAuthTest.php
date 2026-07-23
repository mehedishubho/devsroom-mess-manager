<?php

declare(strict_types=1);

namespace Tests\Feature\Backup;

use App\Models\Mess;
use App\Models\User;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

/**
 * Plan 06-03 Task 3 — BackupControllerAuthTest (D-08b — UI auth gating).
 *
 * Verifies the role:super-admin gate on /dashboard/backups/* (T-06-03-01):
 *   - super-admin GET 200 + sees the heading
 *   - admin    GET 403
 *   - user     GET 403
 *   - guest    GET redirects to /login
 *
 * Plus the two trigger POSTs (Test 5 + Test 6): super-admin restore-test
 * dispatches the artisan command; admin run POST is 403.
 *
 * D-08 enforcement: every Artisan::call('backup:*') is mocked via the
 * Artisan::swap($spy) pattern established in Plan 06-02 (Laravel 13 has no
 * Artisan::fake()). The swap replaces BOTH the container binding AND the
 * facade's resolvedInstance cache.
 */
class BackupControllerAuthTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    private array $artisanCalls = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();

        // Seed an active mess so the gate + view renders cleanly. Set the
        // env override so Mess::activeId() is deterministic.
        Mess::factory()->create(['name' => 'Test Mess']);
        config(['mess.active_mess_id' => Mess::first()->id]);
        Mess::forgetActiveIdCache();

        // The index() controller resolves the configured `backups` s3 disk
        // (DO Spaces). In the test env the DO_SPACES_* env vars are empty, so
        // we fake the disk — the s3 adapter would otherwise crash on a null
        // bucket. Per D-08, no real object storage is touched in the suite.
        Storage::fake('backups');

        $this->artisanCalls = [];
        $spy = Mockery::mock(Kernel::class);
        $spy->shouldReceive('call')
            ->andReturnUsing(function (string $command) {
                $this->artisanCalls[] = $command;

                return 0;
            });
        $spy->shouldReceive('handle', 'terminate', 'bootstrap', 'renderForConsole')->andReturn(0);
        Artisan::swap($spy);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
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
        $user->assignRole(Role::where('slug', 'manager')->first());

        return $user;
    }

    private function memberUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'mess-member')->first());

        return $user;
    }

    /**
     * Test 1: super-admin GET /dashboard/backups returns 200 + sees the heading.
     */
    public function test_super_admin_can_view_backups_index(): void
    {
        $this->actingAs($this->superAdmin())
            ->get('/dashboard/backups')
            ->assertOk()
            ->assertSee(__('Backups'));
    }

    /**
     * Test 2: admin GET /dashboard/backups returns 403.
     */
    public function test_admin_gets_403_on_backups_index(): void
    {
        $this->actingAs($this->admin())
            ->get('/dashboard/backups')
            ->assertForbidden();
    }

    /**
     * Test 3: user (member role) GET /dashboard/backups returns 403.
     */
    public function test_member_user_gets_403_on_backups_index(): void
    {
        $this->actingAs($this->memberUser())
            ->get('/dashboard/backups')
            ->assertForbidden();
    }

    /**
     * Test 4: guest (not authenticated) GET /dashboard/backups redirects to /login.
     */
    public function test_guest_is_redirected_to_login_on_backups_index(): void
    {
        $this->get('/dashboard/backups')->assertRedirect('/login');
    }

    /**
     * Test 5: super-admin POST /dashboard/backups/restore-test dispatches the
     * backup:restore-test artisan command (mocked). T-06-03-01 confirms
     * super-admin is allowed through the gate; this also confirms the action
     * actually fires the underlying artisan command.
     */
    public function test_super_admin_can_run_restore_test(): void
    {
        $this->actingAs($this->superAdmin())
            ->post('/dashboard/backups/restore-test')
            ->assertRedirect();

        $this->assertContains('backup:restore-test', $this->artisanCalls);
    }

    /**
     * Test 6: admin POST /dashboard/backups/run returns 403 (no backup:run
     * for non-super-admins).
     */
    public function test_admin_gets_403_on_backup_run(): void
    {
        $this->actingAs($this->admin())
            ->post('/dashboard/backups/run')
            ->assertForbidden();
    }
}
