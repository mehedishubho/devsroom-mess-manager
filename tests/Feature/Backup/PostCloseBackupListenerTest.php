<?php

declare(strict_types=1);

namespace Tests\Feature\Backup;

use App\Jobs\CloseMonthJob;
use App\Services\MonthCloseService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Tests\TestCase;

/**
 * Plan 06-02 Task 2 — post-CloseMonthJob backup (D-05).
 *
 * The post-close backup fires via the CloseMonthJob::after() lifecycle hook
 * (research Pattern 6a — preferred over an Eloquent-event listener file).
 * after() runs ONLY on successful job completion; failed() runs on error and
 * MUST NOT trigger a backup (the close is incomplete).
 *
 * File name retains 'Listener' for stability even though no listener class
 * is created — research Pattern 6a (lifecycle hook) is preferred over 6b.
 */
class PostCloseBackupListenerTest extends TestCase
{
    /** @var array<int, string> */
    private array $artisanCalls = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Spy on Artisan so we can assert which commands CloseMonthJob::after()
        // invoked. See BackupRestoreServiceTest for the same pattern.
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

    private function assertArtisanCalled(string $command): void
    {
        $this->assertContains(
            $command,
            $this->artisanCalls,
            "Expected Artisan::call('{$command}') to have been invoked. Calls seen: ".implode(', ', $this->artisanCalls ?: ['(none)']),
        );
    }

    private function assertArtisanNotCalled(string $command): void
    {
        $this->assertNotContains(
            $command,
            $this->artisanCalls,
            "Did NOT expect Artisan::call('{$command}'). Calls seen: ".implode(', ', $this->artisanCalls ?: ['(none)']),
        );
    }

    /**
     * Test 1: after a successful CloseMonthJob::handle(), Artisan::call('backup:run')
     * IS invoked (D-05 — capture the highest-value immutable snapshot immediately).
     */
    public function test_after_hook_fires_backup_run_on_successful_close(): void
    {
        $job = new CloseMonthJob(2026, 5, 1);

        // Simulate a successful close — the lifecycle hook fires after handle().
        $job->after();

        $this->assertArtisanCalled('backup:run');
    }

    /**
     * Test 2: after a FAILED close (handle threw), Artisan::call('backup:run')
     * is NOT invoked. failed() is the failed() lifecycle hook; the after() hook
     * is what fires backup:run. We assert that failed() never touches backup:run.
     */
    public function test_failed_hook_does_not_fire_backup_run(): void
    {
        $job = new CloseMonthJob(2026, 5, 1);

        // failed() runs on close failure. It MUST NOT trigger a backup.
        $job->failed(new \RuntimeException('close failed mid-transaction'));

        $this->assertArtisanNotCalled('backup:run');
    }

    /**
     * Test 3: the post-close backup is non-blocking — a backup:run failure
     * does NOT propagate out of after() (T-06-02-07: a backup failure must
     * never break the close path; the close already succeeded).
     *
     * Implementation: after() wraps Artisan::call('backup:run') in try/catch.
     * Here we make the Kernel spy throw when 'backup:run' is called and
     * assert after() returns normally (no exception escapes).
     */
    public function test_after_hook_does_not_propagate_backup_failures(): void
    {
        $spy = Mockery::mock(Kernel::class);
        $spy->shouldReceive('call')
            ->andReturnUsing(function (string $command) {
                if ($command === 'backup:run') {
                    throw new \RuntimeException('backup:run exploded');
                }

                return 0;
            });
        $spy->shouldReceive('handle', 'terminate', 'bootstrap', 'renderForConsole')->andReturn(0);
        Artisan::swap($spy);

        $job = new CloseMonthJob(2026, 5, 1);

        // No exception should escape — after() must swallow backup failures.
        $exception = null;
        try {
            $job->after();
        } catch (\Throwable $e) {
            $exception = $e;
        }

        $this->assertNull($exception, 'after() must not propagate backup:run failures (T-06-02-07).');
    }
}
