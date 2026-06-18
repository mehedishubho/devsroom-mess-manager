<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Services\NotificationService;
use App\Support\NotificationType;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\UnhealthyBackupWasFound;

/**
 * D-05 NotifyOnBackupFailure — routes spatie's failure / unhealthy events to
 * the project's in-app notification surface.
 *
 * The in-app Notification row is the RELIABLE channel. With the project's
 * default MAIL_MAILER=log, spatie's own mail notifications go to the log file,
 * not a real mailbox; the Notification row is what managers see in the bell.
 *
 * Registered in AppServiceProvider::boot() with class_exists guards.
 */
class NotifyOnBackupFailure
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(BackupHasFailed|UnhealthyBackupWasFound $event): void
    {
        // diskName exists on both event classes (BackupHasFailed may be null).
        $diskName = $event->diskName ?? 'unknown disk';

        $this->notifications->broadcastToManagers(NotificationType::BACKUP_FAILED, [
            'event' => class_basename($event),
            'message' => $diskName,
            'exception' => $event instanceof BackupHasFailed ? (string) $event->exception : null,
        ]);
    }
}
