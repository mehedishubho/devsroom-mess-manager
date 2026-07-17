<?php

declare(strict_types=1);

namespace Tests\Feature\Backup;

use App\Models\BackupConfig;
use App\Models\BackupLog;
use App\Models\User;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Covers the Local-backup fixes:
 *   1. `Backup now` no longer reports a false success when spatie's dump
 *      fails — Artisan::call()'s non-zero exit code (and "no new zip
 *      appeared") is detected and surfaced as an error.
 *   2. Every attempt is written to the backup_logs table so the failure
 *      reason (mysqldump missing, etc.) is visible on the Backups page.
 *
 * Artisan is mocked (Artisan::shouldReceive) so no real mysqldump runs.
 */
class BackupRunNowLoggingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();

        BackupConfig::query()->updateOrCreate(['id' => 1], [
            'frequency' => 'daily',
            'run_at' => '01:30',
            'keep_all_days' => 7,
            'max_mb' => 5000,
        ]);
        BackupConfig::flushCache();

        // The controller downloads/lists/counts on destination disks.0, which
        // is always 'backups-local' (BackupDestinations prepends it; no DO
        // Spaces creds in tests). Fake it so no real filesystem is touched.
        Storage::fake('backups-local');
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'super-admin')->first());

        return $user;
    }

    public function test_failed_backup_is_detected_and_logged_instead_of_a_false_success(): void
    {
        // spatie returns non-zero + a failure line, and no zip appears — the
        // old code showed "Backup completed." here. Now it must error + log.
        Artisan::shouldReceive('call')->with('backup:run')->andReturn(1);
        Artisan::shouldReceive('output')->andReturn("Dumping database...\nmysqldump: command not found");

        $this->actingAs($this->superAdmin())
            ->from(route('dashboard.backups.index'))
            ->post(route('dashboard.backups.run'))
            ->assertRedirect(route('dashboard.backups.index'))
            ->assertSessionHasErrors('backup');

        $log = BackupLog::where('action', 'backup')->latest('id')->first();
        $this->assertNotNull($log, 'Expected a backup_logs row for the failed attempt.');
        $this->assertSame('failure', $log->status);
        $this->assertStringContainsString('mysqldump', (string) $log->message);
    }

    public function test_successful_backup_logs_success_and_flashes_completion(): void
    {
        // Simulate spatie actually producing a zip during the (mocked) call.
        Artisan::shouldReceive('call')->with('backup:run')->andReturnUsing(function () {
            Storage::disk('backups-local')->put('mess-2026-07-17-01-30-00.zip', 'fake-zip');

            return 0;
        });
        Artisan::shouldReceive('output')->andReturn('Backup completed!');

        $this->actingAs($this->superAdmin())
            ->from(route('dashboard.backups.index'))
            ->post(route('dashboard.backups.run'))
            ->assertRedirect(route('dashboard.backups.index'))
            ->assertSessionHas('success');

        $log = BackupLog::where('action', 'backup')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('success', $log->status);
    }

    public function test_zero_exit_but_no_file_is_treated_as_failure(): void
    {
        // Belt-and-suspenders: even if the exit code lies (0), a run that
        // produces no zip is a failure.
        Artisan::shouldReceive('call')->with('backup:run')->andReturn(0);
        Artisan::shouldReceive('output')->andReturn('');

        $this->actingAs($this->superAdmin())
            ->post(route('dashboard.backups.run'))
            ->assertSessionHasErrors('backup');

        $this->assertSame('failure', BackupLog::where('action', 'backup')->latest('id')->first()->status);
    }

    public function test_activity_log_is_visible_on_the_backups_page(): void
    {
        BackupLog::create([
            'action' => 'backup',
            'status' => 'failure',
            'message' => 'mysqldump: command not found',
            'user_id' => $this->superAdmin()->id,
        ]);

        $this->actingAs($this->superAdmin())
            ->get(route('dashboard.backups.index'))
            ->assertOk()
            ->assertSee(__('Activity log'))
            ->assertSee('mysqldump: command not found');
    }

    public function test_configure_form_is_inlined_on_the_backups_page(): void
    {
        // Request 1 of the follow-up: Configure lives ON Dashboard > Backups,
        // not behind a separate screen.
        $this->actingAs($this->superAdmin())
            ->get(route('dashboard.backups.index'))
            ->assertOk()
            ->assertSee(__('Save configuration'))
            ->assertSee(__('Storage providers'))
            ->assertSee(__('Google Drive'))
            ->assertSee(__('Cloudflare R2'));

        // The /configure deep link still works (renders the same page).
        $this->actingAs($this->superAdmin())
            ->get(route('dashboard.backups.configure'))
            ->assertOk()
            ->assertSee(__('Save configuration'));
    }
}
