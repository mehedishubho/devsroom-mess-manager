<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\BackupLog;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\BackupWasSuccessful;
use Spatie\Backup\Events\CleanupHasFailed;
use Spatie\Backup\Events\CleanupWasSuccessful;
use Spatie\Backup\Events\HealthyBackupWasFound;
use Spatie\Backup\Events\UnhealthyBackupWasFound;

/**
 * Writes a BackupLog row for SCHEDULED backup outcomes so the nightly
 * backup:run / backup:monitor appear on the Backups page Activity log — not
 * just the manual "Backup now" button (which the controller logs itself).
 *
 * Logs ONLY when running in the console (`schedule:run` / artisan). A manual
 * "Backup now" calls `backup:run` during an HTTP request, which the controller
 * already logs; the runningInConsole() guard keeps a manual run from producing
 * a second row for the same event.
 *
 * NOISE CONTROL: `backup:monitor` runs nightly and succeeds almost every
 * night. Logging a healthy row each time buried real failures under a wall of
 * "Backups healthy." rows. A healthy monitor result is therefore logged ONLY
 * on a state CHANGE — i.e. when the previous monitor row was a failure (a
 * recovery is worth knowing about). Unhealthy results are always logged.
 */
class LogScheduledBackupActivity
{
    public function handle(
        BackupWasSuccessful|BackupHasFailed|CleanupWasSuccessful|CleanupHasFailed|HealthyBackupWasFound|UnhealthyBackupWasFound $event,
    ): void {
        if (! app()->runningInConsole()) {
            return;
        }

        $mapped = $this->map($event);

        if ($mapped === null) {
            return;
        }

        [$action, $status, $message] = $mapped;

        try {
            BackupLog::create([
                'action' => $action,
                'status' => $status,
                'message' => $message,
            ]);
        } catch (\Throwable) {
            // Logging must never break the backup pipeline (e.g. a fresh
            // deploy whose backup_logs table hasn't been migrated yet).
        }
    }

    /**
     * Map a spatie event to an (action, status, message) BackupLog triple, or
     * null when the event should not be logged at all.
     *
     * Uses generic messages (no event-property access) so a spatie version
     * bump that renames a property can't break the listener.
     *
     * @return array{0:string,1:string,2:string}|null
     */
    private function map(object $event): ?array
    {
        return match (true) {
            $event instanceof BackupWasSuccessful => ['backup', 'success', __('Scheduled backup completed.')],
            $event instanceof BackupHasFailed => ['backup', 'failure', __('Scheduled backup failed.')],
            $event instanceof CleanupWasSuccessful => ['purge', 'success', __('Retention purge completed.')],
            $event instanceof CleanupHasFailed => ['purge', 'failure', __('Retention purge failed.')],
            $event instanceof UnhealthyBackupWasFound => ['monitor', 'failure', __('Backups unhealthy.')],
            $event instanceof HealthyBackupWasFound => $this->healthyMonitorRow(),
            default => ['backup', 'failure', class_basename($event)],
        };
    }

    /**
     * A healthy monitor result is only worth a row when it REPLACES a failing
     * one (state change), so the log isn't spammed by a nightly "all good".
     *
     * @return array{0:string,1:string,2:string}|null
     */
    private function healthyMonitorRow(): ?array
    {
        try {
            $previous = BackupLog::query()
                ->where('action', 'monitor')
                ->latest('id')
                ->first();

            if ($previous && $previous->status === 'failure') {
                return ['monitor', 'success', __('Backups healthy again (previous check failed).')];
            }
        } catch (\Throwable) {
            // backup_logs missing — nothing to compare against; stay quiet.
        }

        return null;
    }
}
