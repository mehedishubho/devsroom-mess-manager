<?php

declare(strict_types=1);

namespace Tests\Feature\Backup;

use App\Listeners\NotifyOnBackupFailure;
use App\Services\NotificationService;
use App\Support\NotificationType;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Mockery;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\UnhealthyBackupWasFound;
use Spatie\Backup\Notifications\EventHandler;
use Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification;
use Tests\TestCase;

/**
 * Plan 06-02 Task 2 — spatie failure-event → NotificationService wiring (D-05).
 *
 * spatie dispatches BackupHasFailed when the backup itself fails and
 * UnhealthyBackupWasFound when backup:monitor finds a constraint violation.
 * Both route to NotifyOnBackupFailure → NotificationService::broadcastToManagers()
 * so a backup failure surfaces in the in-app bell (the reliable channel,
 * MAIL_MAILER=log by default).
 *
 * spatie's OWN mail-notification EventHandler is also registered (via its
 * service provider). The test calls Notification::send overrides on the two
 * relevant spatie notifications so that path becomes a no-op (avoids trying
 * to resolve the unconfigured `backups` s3 disk in the test env).
 */
class SpatieFailureNotificationListenerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // Re-enable spatie's EventHandler so disabling it here does not leak
        // into other tests in the same process.
        EventHandler::enable();
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Build a NotificationService mock that captures the broadcastToManagers()
     * call. The return type is Database\Eloquent\Collection per the real
     * service's signature, so return an empty one.
     *
     * @param  array{type: string, data: array<string, mixed>}|null  $captured
     */
    private function captureNotificationService(?array &$captured): NotificationService
    {
        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldReceive('broadcastToManagers')
            ->andReturnUsing(function (string $type, array $data) use (&$captured) {
                $captured = ['type' => $type, 'data' => $data];

                return new EloquentCollection;
            });

        return $notifications;
    }

    /**
     * Test 4: dispatching BackupHasFailed causes NotificationService::broadcastToManagers()
     * to be invoked with type=backup_failed.
     */
    public function test_backup_has_failed_event_triggers_manager_notification(): void
    {
        // Silence spatie's own mail/notification path (needs the backups disk).
        EventHandler::disable();

        $this->app->instance(NotificationService::class, $this->captureNotificationService($captured));

        Event::dispatch(new BackupHasFailed(
            new \RuntimeException('mysqldump failed'),
            'backups',
        ));

        $this->assertNotNull($captured, 'NotificationService::broadcastToManagers was not invoked.');
        $this->assertSame(NotificationType::BACKUP_FAILED, $captured['type']);
        $this->assertStringContainsString('BackupHasFailed', $captured['data']['event']);
        $this->assertNotEmpty($captured['data']['message']); // disk name or 'unknown'
    }

    /**
     * Test 5: dispatching UnhealthyBackupWasFound does the same.
     *
     * Spatie's own EventHandler is also wired to this event; it tries to send
     * the UnhealthyBackupWasFoundNotification mail, which needs the backups
     * disk. We silence spatie's EventHandler via its built-in disable() static
     * toggle so only OUR listener (NotifyOnBackupFailure) runs.
     */
    public function test_unhealthy_backup_was_found_event_triggers_manager_notification(): void
    {
        // Silence spatie's own mail/notification path.
        EventHandler::disable();

        $this->app->instance(NotificationService::class, $this->captureNotificationService($captured));

        Event::dispatch(new UnhealthyBackupWasFound(
            'backups',
            'devsroom-mess/2026-06-19-01-30.zip',
            new Collection([['check' => 'MaximumAgeInDays', 'message' => 'stale']]),
        ));

        $this->assertNotNull($captured, 'NotificationService::broadcastToManagers was not invoked.');
        $this->assertSame(NotificationType::BACKUP_FAILED, $captured['type']);
        $this->assertStringContainsString('UnhealthyBackupWasFound', $captured['data']['event']);
    }
}
