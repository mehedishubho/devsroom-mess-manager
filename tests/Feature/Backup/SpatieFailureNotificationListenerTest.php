<?php

declare(strict_types=1);

namespace Tests\Feature\Backup;

use App\Listeners\NotifyOnBackupFailure;
use App\Models\Notification;
use App\Services\NotificationService;
use App\Support\NotificationType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Mockery;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\UnhealthyBackupWasFound;
use Tests\TestCase;

/**
 * Plan 06-02 Task 2 — spatie failure-event → NotificationService wiring (D-05).
 *
 * spatie dispatches BackupHasFailed when the backup itself fails and
 * UnhealthyBackupWasFound when backup:monitor finds a constraint violation.
 * Both route to NotifyOnBackupFailure → NotificationService::broadcastToManagers()
 * so a backup failure surfaces in the in-app bell (the reliable channel,
 * MAIL_MAILER=log by default).
 */
class SpatieFailureNotificationListenerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test 4: dispatching BackupHasFailed causes NotificationService::broadcastToManagers()
     * to be invoked with type=backup_failed.
     */
    public function test_backup_has_failed_event_triggers_manager_notification(): void
    {
        $notifications = Mockery::mock(NotificationService::class);
        $captured = null;
        $notifications->shouldReceive('broadcastToManagers')
            ->once()
            ->andReturnUsing(function (string $type, array $data) use (&$captured) {
                $captured = ['type' => $type, 'data' => $data];

                return new Collection;
            });
        $this->app->instance(NotificationService::class, $notifications);

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
     */
    public function test_unhealthy_backup_was_found_event_triggers_manager_notification(): void
    {
        $notifications = Mockery::mock(NotificationService::class);
        $captured = null;
        $notifications->shouldReceive('broadcastToManagers')
            ->once()
            ->andReturnUsing(function (string $type, array $data) use (&$captured) {
                $captured = ['type' => $type, 'data' => $data];

                return new Collection;
            });
        $this->app->instance(NotificationService::class, $notifications);

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
