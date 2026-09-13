<?php

namespace App\Jobs;

use App\Models\GoogleSheetsConfig;
use App\Services\GoogleSheets\GoogleSheetsSyncService;
use App\Services\NotificationService;
use App\Support\NotificationType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Flushes a mess's pending Google Sheets rows.
 *
 * Coalescing + serialisation:
 *  - `ShouldBeUnique` (per config) means a burst of model saves queues at most
 *    one flush, which then drains every pending row in batched API calls.
 *  - `WithoutOverlapping` guarantees two flushes never race on the same
 *    spreadsheet (row numbers would be read while another run mutates rows).
 *  - `ShouldQueueAfterCommit` means the job only runs once the rows it will
 *    read are actually committed.
 */
class SyncGoogleSheetsJob implements ShouldBeUnique, ShouldQueue, ShouldQueueAfterCommit
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60, 120, 300];

    public int $timeout = 120;

    public function __construct(public readonly int $configId) {}

    public function uniqueId(): string
    {
        return "google-sheets-sync:{$this->configId}";
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("google-sheets-sync:{$this->configId}"))
                ->releaseAfter(30)
                ->expireAfter(300),
        ];
    }

    public function handle(GoogleSheetsSyncService $service): void
    {
        $config = GoogleSheetsConfig::query()->find($this->configId);

        if ($config === null || ! $config->enabled || ! $config->isReady()) {
            return;
        }

        $service->flush($config);
    }

    /**
     * Runs after the retries are exhausted. Records the failure on the config
     * (so the config screen can show it) and raises an in-app notification for
     * the mess managers.
     */
    public function failed(Throwable $exception): void
    {
        try {
            $config = GoogleSheetsConfig::query()->find($this->configId);

            if ($config !== null) {
                $config->forceFill([
                    'last_error' => $exception->getMessage(),
                    'last_error_at' => now(),
                ])->save();

                GoogleSheetsConfig::flushCache((int) $config->mess_id);
            }

            app(NotificationService::class)->broadcastToManagers(NotificationType::SHEETS_SYNC_FAILED, [
                'message' => $exception->getMessage(),
            ]);
        } catch (Throwable $inner) {
            Log::error('Google Sheets failure handler failed', ['error' => $inner->getMessage()]);
        }
    }
}
