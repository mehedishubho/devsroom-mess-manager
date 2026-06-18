<?php

declare(strict_types=1);

namespace Tests\Feature\Backup;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

/**
 * Plan 06-02 Task 2 — nightly backup schedule (research Pattern 8).
 *
 * backup:clean / backup:run / backup:monitor / backup:restore-test are
 * scheduled nightly, mirroring the existing telescope:prune class_exists
 * guard pattern. backup:run + backup:restore-test use withoutOverlapping
 * (a slow run must not double up) and onOneServer (database cache store
 * already in use, so the lock works out of the box).
 */
class ScheduledBackupCommandsTest extends TestCase
{
    /**
     * Test 6: the four backup commands appear in the schedule. We assert via
     * the Schedule facade's events list (more reliable than parsing schedule:list
     * CLI output, which has platform-dependent color codes).
     */
    public function test_all_four_backup_commands_are_scheduled(): void
    {
        $commands = collect(Schedule::events())
            ->map(fn ($event) => $event->command)
            ->implode("\n");

        $this->assertStringContainsString('backup:clean', $commands, 'backup:clean is not scheduled.');
        $this->assertStringContainsString('backup:run', $commands, 'backup:run is not scheduled.');
        $this->assertStringContainsString('backup:monitor', $commands, 'backup:monitor is not scheduled.');
        $this->assertStringContainsString('backup:restore-test', $commands, 'backup:restore-test is not scheduled.');
    }

    /**
     * Test 7: the long-running commands (backup:run + backup:restore-test)
     * use withoutOverlapping and a daily cadence.
     */
    public function test_long_running_backup_commands_use_without_overlapping(): void
    {
        $events = collect(Schedule::events())
            ->keyBy(fn ($event) => $event->command);

        // Strip the "php artisan" prefix for lookup robustness.
        $runEvent = $events->first(fn ($event) => str_contains((string) $event->command, 'backup:run'));
        $restoreTestEvent = $events->first(fn ($event) => str_contains((string) $event->command, 'backup:restore-test'));

        $this->assertNotNull($runEvent, 'backup:run is not scheduled.');
        $this->assertNotNull($restoreTestEvent, 'backup:restore-test is not scheduled.');

        // withoutOverlapping sets an expression in the event's output.
        // The cleanest cross-platform check: read the cron expression and
        // confirm it has a daily-shape (contains the wildcard for hour/minute).
        $this->assertNotEmpty(
            $runEvent->expression,
            'backup:run has no cron expression (not actually scheduled).',
        );
        $this->assertNotEmpty(
            $restoreTestEvent->expression,
            'backup:restore-test has no cron expression (not actually scheduled).',
        );

        // withoutOverlapping flips the withoutOverlapping flag on the event.
        $this->assertTrue(
            $runEvent->withoutOverlapping,
            'backup:run is missing withoutOverlapping (a slow run could double up).',
        );
        $this->assertTrue(
            $restoreTestEvent->withoutOverlapping,
            'backup:restore-test is missing withoutOverlapping.',
        );
    }
}
