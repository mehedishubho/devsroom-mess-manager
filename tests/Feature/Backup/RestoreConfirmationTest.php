<?php

declare(strict_types=1);

namespace Tests\Feature\Backup;

use App\Models\Mess;
use App\Models\User;
use App\Services\BackupRestoreService;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use Mockery;
use OwenIt\Auditing\Models\Audit;
use Tests\TestCase;

/**
 * Plan 06-03 Task 3 — RestoreConfirmationTest (D-08c — the typed-confirm
 * + maintenance-mode flow).
 *
 * Verifies:
 *   Test 7  — super-admin GET the restore form sees the typed-confirm + the
 *             expected mess name
 *   Test 8  — POST restore WITHOUT mess_name: validation error, no service
 *             call, no audit row
 *   Test 9  — POST restore with a WRONG mess_name: validation error
 *   Test 10 — POST restore with the CORRECT mess_name: BackupRestoreService
 *             fires once, audit row written with event='backup.restore',
 *             redirect to dashboard.backups.index with a status flash
 *   Test 11 — When BackupRestoreService THROWS: audit row written with
 *             event='backup.restore.failed', redirect back with an error flash,
 *             and the exception does NOT escape the controller
 *
 * D-08 enforcement: BackupRestoreService is a Mockery mock bound via
 * $this->app->instance(); NO real mysqldump/mysql/Artisan runs.
 *
 * Throttle: the restore POST route carries throttle:5,1 (T-06-03-04). Tests
 * 8+9 each fire multiple POSTs in a single setUp'd session, so we disable
 * the ThrottleRequests middleware per-test via withoutMiddleware().
 */
class RestoreConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private const PATH = 'test-backup.zip';

    private string $activeMessName;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();

        // Active mess (its name is the typed-confirm target).
        $mess = Mess::factory()->create(['name' => 'Main Mess']);
        config(['mess.active_mess_id' => $mess->id]);
        Mess::forgetActiveIdCache();
        $this->activeMessName = $mess->name;

        // The show() + store() controllers read from destination disks.0,
        // which is always 'backups-local' (BackupDestinations prepends it; DO
        // Spaces creds are not set in tests). Fake that disk + seed a stub zip
        // so $disk->exists($path) passes in the show test.
        Storage::fake('backups-local');
        Storage::disk('backups-local')->put(self::PATH, 'fake-zip-content');
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

    /**
     * Bind a Mockery mock of BackupRestoreService in the container so the
     * controller's constructor injection resolves our mock instead of the
     * real service. NO real mysqldump/mysql/Artisan::call runs.
     */
    private function bindMockedService(callable $configure): void
    {
        $mock = Mockery::mock(BackupRestoreService::class);
        $configure($mock);
        $this->app->instance(BackupRestoreService::class, $mock);
    }

    /**
     * Test 7: super-admin GET /dashboard/backups/restore/<path> returns 200
     * + sees the typed-confirm form + sees the expected mess name.
     */
    public function test_super_admin_sees_typed_confirm_form(): void
    {
        $this->actingAs($this->superAdmin())
            ->get('/dashboard/backups/restore/'.self::PATH)
            ->assertOk()
            ->assertSee(__('WARNING: This is a destructive operation.'))
            ->assertSee(__('Restore this backup'))
            ->assertSee(e($this->activeMessName));
    }

    /**
     * Test 8: POST restore WITHOUT mess_name redirects back with a
     * validation error. The BackupRestoreService MUST NOT be invoked and NO
     * audit row is written (the Form Request fails before the controller).
     */
    public function test_restore_refuses_without_mess_name(): void
    {
        $this->bindMockedService(fn ($m) => $m->shouldNotReceive('restoreFromDisk'));

        $this->actingAs($this->superAdmin())
            ->withoutMiddleware(ThrottleRequests::class)
            ->post('/dashboard/backups/restore', ['path' => self::PATH])
            ->assertRedirect()
            ->assertSessionHasErrors('mess_name');

        $this->assertSame(0, Audit::where('event', 'backup.restore')->count());
        $this->assertSame(0, Audit::where('event', 'backup.restore.failed')->count());
    }

    /**
     * Test 9: POST restore with a WRONG mess_name redirects back with a
     * validation error (the typed-confirm second factor).
     */
    public function test_restore_refuses_with_wrong_mess_name(): void
    {
        $this->bindMockedService(fn ($m) => $m->shouldNotReceive('restoreFromDisk'));

        $this->actingAs($this->superAdmin())
            ->withoutMiddleware(ThrottleRequests::class)
            ->post('/dashboard/backups/restore', [
                'path' => self::PATH,
                'mess_name' => 'totally wrong name',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('mess_name');

        $this->assertSame(0, Audit::where('event', 'backup.restore')->count());
    }

    /**
     * Test 10: POST restore with the CORRECT mess_name:
     *   (a) BackupRestoreService::restoreFromDisk fires once with the path
     *   (b) an audit row is written with event='backup.restore'
     *   (c) redirect to dashboard.backups.index with a status flash
     */
    public function test_restore_with_correct_mess_name_runs_service_and_writes_audit(): void
    {
        $this->bindMockedService(function ($m) {
            $m->shouldReceive('restoreFromDisk')->once()->with(self::PATH);
        });

        $this->actingAs($this->superAdmin())
            ->withoutMiddleware(ThrottleRequests::class)
            ->post('/dashboard/backups/restore', [
                'path' => self::PATH,
                'mess_name' => $this->activeMessName,
            ])
            ->assertRedirect(route('dashboard.backups.index'));

        $audit = Audit::where('event', 'backup.restore')->latest('id')->first();
        $this->assertNotNull($audit, 'Expected an audit row with event=backup.restore.');
        $this->assertSame('backup', $audit->auditable_type);
        // Audit::new_values is json-cast (array). Assert array access (CR-03).
        $this->assertSame(self::PATH, $audit->new_values['path'] ?? null);
    }

    /**
     * Test 11: When BackupRestoreService THROWS, the controller writes an
     * audit row with event='backup.restore.failed', redirects back with an
     * error flash, and the exception does NOT escape. T-06-03-07 — failures
     * are auditable too.
     */
    public function test_restore_failure_writes_failed_audit_and_does_not_throw(): void
    {
        $this->bindMockedService(function ($m) {
            // The real BackupRestoreService::restoreFromDisk always calls
            // Artisan::call('up') in its finally — but the MOCK does not run
            // that code path, so the controller's try/catch is the only guard
            // here (which is what we are testing).
            $m->shouldReceive('restoreFromDisk')
                ->once()
                ->andThrow(new \RuntimeException('mid-restore explosion'));
        });

        $this->actingAs($this->superAdmin())
            ->withoutMiddleware(ThrottleRequests::class)
            ->post('/dashboard/backups/restore', [
                'path' => self::PATH,
                'mess_name' => $this->activeMessName,
            ])
            ->assertRedirect();

        $audit = Audit::where('event', 'backup.restore.failed')->latest('id')->first();
        $this->assertNotNull($audit, 'Expected an audit row with event=backup.restore.failed.');
        // Audit::new_values is json-cast (array). Assert array access (CR-03).
        $this->assertSame('mid-restore explosion', $audit->new_values['error'] ?? null);

        // No success row should have been written alongside the failure row.
        $this->assertSame(0, Audit::where('event', 'backup.restore')->count());
    }
}
