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
 * Plan 06-02 Task 2 / CR-02 — post-CloseMonthJob backup (D-05).
 *
 * The post-close backup lives at the TAIL of CloseMonthJob::handle(), so it
 * runs only after a successful close() and is invoked by the REAL queue runtime.
 * Laravel has NO `after()` job lifecycle hook — the previous `after()` impl was
 * dead code; CR-02 inlined the call into handle(). These tests exercise handle()
 * directly (with a Mockery-mocked MonthCloseService so the close math is
 * bypassed) and assert purely about the backup wiring:
 *   - a successful close fires backup:run;
 *   - a failed close does not (and the exception propagates so the job fails);
 *   - a backup:run failure never breaks the close path (T-06-02-07).
 *
 * File name retains 'Listener' for stability (no listener class is involved).
 */
class PostCloseBackupListenerTest extends TestCase
{
    /** @var array<int, string> */
    private array $artisanCalls = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Spy on Artisan so we can assert which commands handle() invoked.
        $this->artisanCalls = [];
        $this->installArtisanSpy();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Install a ConsoleKernel spy that records every call() invocation into
     * $this->artisanCalls. Artisan::swap() replaces BOTH the container binding
     * AND the facade's resolved-instance cache.
     */
    private function installArtisanSpy(): void
    {
        $spy = Mockery::mock(Kernel::class);
        $spy->shouldReceive('call')
            ->andReturnUsing(function (string $command) {
                $this->artisanCalls[] = $command;

                return 0;
            });
        $spy->shouldReceive('handle', 'terminate', 'bootstrap', 'renderForConsole')->andReturn(0);
        Artisan::swap($spy);
    }

    private function assertArtisanCalled(string $command): void
    {
        $this->assertContains(
            $command,
            $this->artisanCalls,
            "Expected Artisan::call('{$command}'). Calls seen: ".implode(', ', $this->artisanCalls ?: ['(none)']),
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
     * Test 1: a successful close() (handle runs to completion) fires
     * Artisan::call('backup:run') with --only-db (D-05 — capture the
     * highest-value immutable snapshot immediately).
     */
    public function test_handle_fires_backup_run_on_successful_close(): void
    {
        $close = Mockery::mock(MonthCloseService::class);
        $close->shouldReceive('close')->once()->with(2026, 5, 1);
        $this->app->instance(MonthCloseService::class, $close);

        (new CloseMonthJob(2026, 5, 1))->handle(app(MonthCloseService::class));

        $this->assertArtisanCalled('backup:run');
    }

    /**
     * Test 2: a FAILED close (close() throws) does NOT fire backup:run — we
     * must not snapshot a half-closed state. The exception propagates so the
     * queue marks the job failed (D-05).
     */
    public function test_handle_does_not_fire_backup_run_when_close_throws(): void
    {
        $close = Mockery::mock(MonthCloseService::class);
        $close->shouldReceive('close')->andThrow(new \RuntimeException('close failed mid-transaction'));
        $this->app->instance(MonthCloseService::class, $close);

        $thrown = null;
        try {
            (new CloseMonthJob(2026, 5, 1))->handle(app(MonthCloseService::class));
        } catch (\Throwable $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown, 'A failed close must propagate so the job is marked failed.');
        $this->assertSame('close failed mid-transaction', $thrown->getMessage());
        $this->assertArtisanNotCalled('backup:run');
    }

    /**
     * Test 3: a backup:run failure must NEVER break the close path
     * (T-06-02-07). close() already succeeded; the backup is best-effort, so
     * the swallowed exception must not escape handle().
     */
    public function test_handle_swallows_backup_failures(): void
    {
        $close = Mockery::mock(MonthCloseService::class);
        $close->shouldReceive('close')->once();
        $this->app->instance(MonthCloseService::class, $close);

        // Re-swap Artisan to throw on 'backup:run' (the close itself is a no-op mock).
        $spy = Mockery::mock(Kernel::class);
        $spy->shouldReceive('call')
            ->andReturnUsing(function (string $command) {
                $this->artisanCalls[] = $command;
                if ($command === 'backup:run') {
                    throw new \RuntimeException('backup:run exploded');
                }

                return 0;
            });
        $spy->shouldReceive('handle', 'terminate', 'bootstrap', 'renderForConsole')->andReturn(0);
        Artisan::swap($spy);

        $exception = null;
        try {
            (new CloseMonthJob(2026, 5, 1))->handle(app(MonthCloseService::class));
        } catch (\Throwable $e) {
            $exception = $e;
        }

        $this->assertNull($exception, 'handle() must not propagate backup:run failures (T-06-02-07).');
        $this->assertArtisanCalled('backup:run');
    }
}
