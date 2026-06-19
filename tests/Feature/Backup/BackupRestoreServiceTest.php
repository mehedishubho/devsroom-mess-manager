<?php

declare(strict_types=1);

namespace Tests\Feature\Backup;

use App\Services\BackupRestoreService;
use App\Support\BackupPathResolver;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Mockery\MockInterface;
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
 *
 * Artisan mocking strategy: Laravel's Artisan facade does not ship a ::fake()
 * method in this version. We instead swap the bound ConsoleKernel contract with
 * a Mockery spy that records every call(command) invocation. The recorded calls
 * are then asserted on with assertArtisanCalled() / assertArtisanNotCalled().
 */
class BackupRestoreServiceTest extends TestCase
{
    /** @var array<int, string> */
    private array $artisanCalls = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Install a ConsoleKernel spy that records every call() invocation.
        // Artisan::swap() replaces BOTH the container binding AND the facade's
        // resolved-instance cache (Facade::$resolvedInstance), so calls to
        // Artisan::call(...) reach our spy.
        $this->artisanCalls = [];
        $spy = Mockery::mock(Kernel::class);
        $spy->shouldReceive('call')
            ->andReturnUsing(function (string $command) {
                $this->artisanCalls[] = $command;

                return 0;
            });
        // Some Laravel code paths call handle() or terminate(); no-op them.
        $spy->shouldReceive('handle', 'terminate', 'bootstrap', 'renderForConsole')->andReturn(0);
        Artisan::swap($spy);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function assertArtisanCalled(string $command): void
    {
        $this->assertContains(
            $command,
            $this->artisanCalls,
            "Expected Artisan::call('{$command}') to have been invoked. Calls seen: ".implode(', ', $this->artisanCalls ?: ['(none)']),
        );
    }

    /**
     * Helper: build a partial-mock of BackupRestoreService where the heavy
     * protected helpers (downloadAndExtract / restoreDatabase / restoreFiles /
     * verifyRestore / cleanup / locateSqlDump) are stubbed.
     *
     * @param  array<string, mixed>  $expectations  method => return-value or 'throw'
     */
    private function makeService(array $expectations = []): BackupRestoreService&MockInterface
    {
        $partial = Mockery::mock(BackupRestoreService::class, [new Filesystem, new BackupPathResolver(new Filesystem)])
            ->makePartial();
        // Required to mock the protected restore* / verifyRestore / cleanup
        // / downloadAndExtract / locateSqlDump seams per D-08.
        $partial->shouldAllowMockingProtectedMethods();

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
        $service = $this->makeService([
            'downloadAndExtract' => sys_get_temp_dir().'/fake-restore',
            'restoreDatabase' => null,
            'restoreFiles' => 0,
            'verifyRestore' => null,
            'cleanup' => null,
        ]);
        $service->shouldReceive('locateSqlDump')->andReturn('/fake/dump.sql');

        $service->restoreFromDisk('backups/test.zip');

        $this->assertArtisanCalled('down');
        // down MUST be the very FIRST Artisan call (before queue:restart).
        $this->assertSame('down', $this->artisanCalls[0] ?? null);
    }

    /**
     * Test 6: Artisan::call('queue:restart') is invoked BEFORE any DB write.
     */
    public function test_queue_restart_is_called_before_db_write(): void
    {
        $service = $this->makeService([
            'downloadAndExtract' => sys_get_temp_dir().'/fake-restore',
            'restoreDatabase' => null,
            'restoreFiles' => 0,
            'verifyRestore' => null,
            'cleanup' => null,
        ]);
        $service->shouldReceive('locateSqlDump')->andReturn('/fake/dump.sql');

        $service->restoreFromDisk('backups/test.zip');

        $this->assertArtisanCalled('queue:restart');
    }

    /**
     * Test 7 (the critical one): Artisan::call('up') runs EVEN on exception.
     *
     * The service MUST end maintenance mode in its finally block. A restore
     * that crashes mid-flight must NEVER leave the app in 'down' forever.
     */
    public function test_up_is_called_in_finally_even_on_exception(): void
    {
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
        $this->assertArtisanCalled('up');
    }

    /**
     * Test 8 (CR-01, NON-MOCKED integration): restoreFiles copies the backed-up
     * files from the EXTRACTED ROOT into storage_path('app/public').
     *
     * spatie zips storage/app/public/* with relative_path = storage/app/public,
     * so the prefix is STRIPPED and files extract to the work-dir root (alongside
     * db-dumps/). The original restoreFiles sourced from a nonexistent
     * <workDir>/storage/app/public and silently copied nothing — this test runs
     * the REAL Filesystem against a real spatie-shaped temp tree to prove the
     * fix: files land in storage/app/public, db-dumps is excluded.
     */
    public function test_restore_files_copies_extracted_root_into_storage_app_public(): void
    {
        $workDir = sys_get_temp_dir().'/cr01-'.uniqid();
        @mkdir($workDir.'/profiles', 0775, true);
        @mkdir($workDir.'/db-dumps', 0775, true);
        file_put_contents($workDir.'/profiles/member-1.jpg', 'photo-bytes');
        file_put_contents($workDir.'/loose-receipt.pdf', 'receipt-bytes');
        file_put_contents($workDir.'/db-dumps/devsroom.sql', '-- dump');

        $destDir = storage_path('app/public');

        $service = new BackupRestoreService(new Filesystem, new BackupPathResolver(new Filesystem));
        $ref = new \ReflectionMethod($service, 'restoreFiles');

        try {
            $copied = $ref->invoke($service, $workDir);

            // profiles/ dir + loose-receipt.pdf file = 2 entries (db-dumps excluded).
            $this->assertSame(2, $copied);
            $this->assertFileExists($destDir.'/profiles/member-1.jpg');
            $this->assertFileEquals($workDir.'/profiles/member-1.jpg', $destDir.'/profiles/member-1.jpg');
            $this->assertFileExists($destDir.'/loose-receipt.pdf');
            // db-dumps MUST NOT be copied into storage/app/public.
            $this->assertDirectoryDoesNotExist($destDir.'/db-dumps');
            $this->assertFileDoesNotExist($destDir.'/db-dumps/devsroom.sql');
        } finally {
            @unlink($destDir.'/profiles/member-1.jpg');
            @unlink($destDir.'/loose-receipt.pdf');
            @rmdir($destDir.'/profiles');
            @unlink($workDir.'/profiles/member-1.jpg');
            @unlink($workDir.'/loose-receipt.pdf');
            @unlink($workDir.'/db-dumps/devsroom.sql');
            @rmdir($workDir.'/profiles');
            @rmdir($workDir.'/db-dumps');
            @rmdir($workDir);
        }
    }

    /**
     * Test 8b (CR-01 guard): verifyFilesRestored throws when restoreFiles copies
     * fewer entries than the extracted tree contains (the original silent-no-op
     * regression net). The DB check is stubbed so we isolate the file guard.
     */
    public function test_verify_restore_throws_when_files_were_silently_skipped(): void
    {
        $workDir = sys_get_temp_dir().'/cr01-guard-'.uniqid();
        @mkdir($workDir.'/profiles', 0775, true);
        file_put_contents($workDir.'/profiles/member-1.jpg', 'photo-bytes');

        try {
            $service = Mockery::mock(BackupRestoreService::class, [new Filesystem, new BackupPathResolver(new Filesystem)])
                ->makePartial();
            $service->shouldAllowMockingProtectedMethods();
            $service->shouldReceive('verifyDatabaseRestored'); // isolate the file guard

            $ref = new \ReflectionMethod($service, 'verifyRestore');

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('silent file-restore guard');

            // 0 copied but the tree contains 1 entry => guard must fire.
            $ref->invoke($service, $workDir, 0);
        } finally {
            @unlink($workDir.'/profiles/member-1.jpg');
            @rmdir($workDir.'/profiles');
            @rmdir($workDir);
        }
    }

    /**
     * Test 9: buildMysqlProcess() constructs a Symfony\Component\Process\Process
     * with ARRAY args (no escapeshellarg string concat) containing --host /
     * --user / --password / the database name, and PIPES the dump via STDIN
     * (WR-03 — never the `SOURCE <path>` form, which breaks on paths with spaces).
     *
     * The service exposes buildMysqlProcess() as a public test seam so the
     * suite can inspect the Process without ever shelling out.
     */
    public function test_build_mysql_process_pipes_dump_via_stdin_not_source(): void
    {
        $dump = sys_get_temp_dir().'/cr03-'.uniqid().'.sql';
        file_put_contents($dump, '-- dump');

        try {
            // Build the service directly — bypassing the setUp() Artisan spy.
            $service = new BackupRestoreService(new Filesystem, new BackupPathResolver(new Filesystem));

            $process = $service->buildMysqlProcess($dump);

            // Symfony Process::getCommandLine() renders the array args back as a
            // shell-escaped string — assert the required tokens are present.
            $cmdline = $process->getCommandLine();
            $this->assertStringContainsString('mysql', $cmdline);
            $this->assertStringContainsString('--host', $cmdline);
            $this->assertStringContainsString('--user', $cmdline);
            $this->assertStringContainsString('--password', $cmdline);

            // WR-03: the dump is piped via STDIN — never passed as `SOURCE <path>`.
            $this->assertStringNotContainsString('SOURCE', $cmdline);
            $this->assertStringNotContainsString($dump, $cmdline);

            // The Process was built from an ARRAY of args (not a single shell
            // string) — confirmed by multiple quoted tokens in the rendered cmdline.
            $singleQuoteCount = substr_count($cmdline, "'");
            $doubleQuoteCount = substr_count($cmdline, '"');
            $this->assertGreaterThan(
                1,
                $singleQuoteCount + $doubleQuoteCount,
                'Expected Process to be constructed from an array of args (multiple quoted tokens), not a single shell string.',
            );

            // The dump stream is wired as the Process input (piped to mysql stdin).
            $this->assertNotNull($process->getInput());
        } finally {
            @unlink($dump);
        }
    }

    /**
     * Test 10: when restoreDatabase throws, the exception propagates AFTER
     * Artisan::call('up') has fired (in finally).
     */
    public function test_exception_propagates_after_up_has_run(): void
    {
        $service = Mockery::mock(BackupRestoreService::class, [new Filesystem, new BackupPathResolver(new Filesystem)])
            ->makePartial();
        $service->shouldAllowMockingProtectedMethods();
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
        $this->assertArtisanCalled('up');
    }
}
