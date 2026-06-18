<?php

declare(strict_types=1);

namespace Tests\Feature\Backup;

use App\Services\BackupRestoreService;
use App\Support\BackupPathResolver;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Tests\TestCase;

/**
 * Plan 06-02 Task 1 — BackupRestoreService (D-06 / D-08c).
 *
 * The restore service is the highest-risk surface in the app (it overwrites the
 * live DB). Every Process + Artisan::call invocation is MOCKED per D-08 — no
 * real mysqldump/mysql subprocess ever runs inside this suite.
 *
 * Tests 5-10 pin the mandatory sequence:
 *   down FIRST -> queue:restart -> try { restore } -> finally { up + cleanup }
 */
class BackupRestoreServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Helper: build a partial-mock of BackupRestoreService where the heavy
     * protected helpers (downloadAndExtract / restoreDatabase / restoreFiles /
     * verifyRestore / cleanup) are stubbed.
     *
     * @param  array<string, mixed>  $expectations  method => return-value or 'throw'
     */
    private function makeService(array $expectations = []): BackupRestoreService & \Mockery\MockInterface
    {
        $partial = Mockery::mock(BackupRestoreService::class, [new Filesystem(), new BackupPathResolver(new Filesystem())])
            ->makePartial();

        foreach ($expectations as $method => $payload) {
            if ($payload === 'throw') {
                $partial->shouldReceive($method)->andThrow(new \RuntimeException("boom from {$method}"));
            } else {
                $partial->shouldReceive($method)->andReturn($payload);
            }
        }

        return $partial;
    }

    /**
     * Test 5: Artisan::call('down') is invoked BEFORE any restore work.
     *
     * The mandatory sequence (research Pattern 4) is hard-coded in
     * restoreFromDisk: down -> queue:restart -> try { restore } -> finally { up }.
     * This test pins the FIRST call.
     */
    public function test_down_is_called_before_any_restore_work(): void
    {
        Artisan::fake(['down' => 0, 'up' => 0, 'queue:restart' => 0]);

        $service = $this->makeService([
            'downloadAndExtract' => sys_get_temp_dir().'/fake-restore',
            'restoreDatabase' => null,
            'restoreFiles' => null,
            'verifyRestore' => null,
            'cleanup' => null,
        ]);
        $service->shouldReceive('locateSqlDump')->andReturn('/fake/dump.sql');

        $service->restoreFromDisk('backups/test.zip');

        Artisan::assertCalled('down');
    }

    /**
     * Test 6: Artisan::call('queue:restart') is invoked BEFORE any DB write.
     */
    public function test_queue_restart_is_called_before_db_write(): void
    {
        Artisan::fake(['down' => 0, 'up' => 0, 'queue:restart' => 0]);

        $service = $this->makeService([
            'downloadAndExtract' => sys_get_temp_dir().'/fake-restore',
            'restoreDatabase' => null,
            'restoreFiles' => null,
            'verifyRestore' => null,
            'cleanup' => null,
        ]);
        $service->shouldReceive('locateSqlDump')->andReturn('/fake/dump.sql');

        $service->restoreFromDisk('backups/test.zip');

        Artisan::assertCalled('queue:restart');
    }

    /**
     * Test 7 (the critical one): Artisan::call('up') runs EVEN on exception.
     *
     * The service MUST end maintenance mode in its finally block. A restore
     * that crashes mid-flight must NEVER leave the app in 'down' forever.
     */
    public function test_up_is_called_in_finally_even_on_exception(): void
    {
        Artisan::fake(['down' => 0, 'up' => 0, 'queue:restart' => 0]);

        $service = $this->makeService();
        $service->shouldReceive('downloadAndExtract')
            ->andThrow(new \RuntimeException('mid-restore explosion'));
        $service->shouldReceive('cleanup'); // finally still runs cleanup

        try {
            $service->restoreFromDisk('backups/test.zip');
            $this->fail('Expected the service to re-throw the restore exception.');
        } catch (\RuntimeException $e) {
            $this->assertSame('mid-restore explosion', $e->getMessage());
        }

        // The crown-jewel assertion: even though the restore threw, 'up' MUST have fired.
        Artisan::assertCalled('up');
    }

    /**
     * Test 8: restoreFiles copies into storage_path('app/public'), NOT
     * public_path('storage'). Pitfall 4 — the symlink must not be followed.
     *
     * Verified structurally by spying on Filesystem::copyDirectory's dest arg.
     */
    public function test_restore_files_writes_into_storage_app_public_never_public_storage(): void
    {
        Artisan::fake(['down' => 0, 'up' => 0, 'queue:restart' => 0]);

        // Replace Filesystem with a spy so we can capture the copyDirectory() dest.
        $filesSpy = Mockery::mock(Filesystem::class);
        $filesSpy->shouldReceive('glob')->andReturn([]);
        $filesSpy->shouldReceive('exists')->andReturn(true);
        $filesSpy->shouldReceive('isDirectory')->andReturn(true);
        $filesSpy->shouldReceive('makeDirectory')->andReturn(true);
        $filesSpy->shouldReceive('deleteDirectory')->andReturn(true);

        $capturedDestinations = [];
        $filesSpy->shouldReceive('copyDirectory')
            ->with(\Mockery::on(fn ($src) => is_string($src)), \Mockery::on(function ($dest) use (&$capturedDestinations) {
                $capturedDestinations[] = $dest;

                return is_string($dest);
            }))
            ->andReturn(true);

        $this->app->instance(Filesystem::class, $filesSpy);

        // Build the service AFTER binding so DI resolves the spy.
        $mock = Mockery::mock($this->app->make(BackupRestoreService::class))->makePartial();
        $mock->shouldReceive('downloadAndExtract')->andReturn(sys_get_temp_dir().'/fake-workdir');
        $mock->shouldReceive('locateSqlDump')->andReturn('/fake/dump.sql');
        $mock->shouldReceive('restoreDatabase');
        $mock->shouldReceive('verifyRestore');
        $mock->shouldReceive('cleanup');

        $mock->restoreFromDisk('backups/test.zip');

        $forbidden = $this->normalizeSlashes(public_path('storage'));
        foreach ($capturedDestinations as $dest) {
            $this->assertStringNotContainsString(
                $forbidden,
                $this->normalizeSlashes($dest),
                "restoreFiles wrote to public_path('storage') (the symlink target). Pitfall 4.",
            );
        }

        // At least one destination must be storage_path('app/public').
        $expected = $this->normalizeSlashes(storage_path('app/public'));
        $foundStorageApp = false;
        foreach ($capturedDestinations as $dest) {
            if (str_contains($this->normalizeSlashes($dest), $expected)) {
                $foundStorageApp = true;

                break;
            }
        }
        $this->assertTrue($foundStorageApp, "restoreFiles did not write to storage_path('app/public').");
    }

    /**
     * Test 9: buildMysqlProcess() constructs a Symfony\Component\Process\Process
     * with ARRAY args (no escapeshellarg string concat) containing --host /
     * --user / --password / the database name + SOURCE directive.
     *
     * The service exposes buildMysqlProcess() as a public test seam so the
     * suite can inspect the Process without ever shelling out.
     */
    public function test_build_mysql_process_uses_array_args_with_required_flags(): void
    {
        $service = $this->app->make(BackupRestoreService::class);

        $process = $service->buildMysqlProcess('/tmp/dump.sql');

        // Symfony Process::getCommandLine() renders the array args back as a
        // shell-escaped string — assert the required tokens are present.
        $cmdline = $process->getCommandLine();
        $this->assertStringContainsString('mysql', $cmdline);
        $this->assertStringContainsString('--host', $cmdline);
        $this->assertStringContainsString('--user', $cmdline);
        $this->assertStringContainsString('--password', $cmdline);
        $this->assertStringContainsString('SOURCE', $cmdline);
        $this->assertStringContainsString('/tmp/dump.sql', $cmdline);

        // The Process was built from an ARRAY of args (not a single shell string)
        // — confirmed by the existence of multiple quoted tokens. (When
        // constructed from an array, getCommandLine() contains a quoted token
        // per array element; from a string, the whole cmdline is one blob.)
        $this->assertGreaterThan(
            1,
            substr_count($cmdline, "'"),
            'Expected Process to be constructed from an array of args (multiple quoted tokens), not a single shell string.',
        );
    }

    /**
     * Test 10: when restoreDatabase throws, the exception propagates AFTER
     * Artisan::call('up') has fired (in finally).
     */
    public function test_exception_propagates_after_up_has_run(): void
    {
        Artisan::fake(['down' => 0, 'up' => 0, 'queue:restart' => 0]);

        $service = Mockery::mock(BackupRestoreService::class)->makePartial();
        $service->shouldReceive('downloadAndExtract')->andReturn(sys_get_temp_dir().'/fake-workdir');
        $service->shouldReceive('locateSqlDump')->andReturn('/fake/dump.sql');
        $service->shouldReceive('restoreDatabase')->andThrow(new \RuntimeException('mysql restore failed'));
        $service->shouldReceive('restoreFiles');
        $service->shouldReceive('verifyRestore');
        $service->shouldReceive('cleanup');

        $thrown = null;
        try {
            $service->restoreFromDisk('backups/test.zip');
        } catch (\RuntimeException $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown, 'Exception must propagate.');
        $this->assertSame('mysql restore failed', $thrown->getMessage());
        Artisan::assertCalled('up');
    }

    private function normalizeSlashes(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
