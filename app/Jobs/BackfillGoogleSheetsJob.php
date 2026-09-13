<?php

namespace App\Jobs;

use App\Models\GoogleSheetsConfig;
use App\Models\Scopes\MessScope;
use App\Services\GoogleSheets\Contracts\SheetsGateway;
use App\Services\GoogleSheets\SheetSchema;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Seeds every enabled tab from the existing DB rows ("Sync existing data").
 *
 * Each tab is cleared (header kept) and rewritten, so the action is idempotent
 * and also doubles as a full resync when the sheet has drifted. Best-effort:
 * a failure is logged and recorded on the config, it never bubbles up to the
 * request that dispatched it.
 */
class BackfillGoogleSheetsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    /** Rows buffered before each append call. */
    private const CHUNK = 500;

    public function __construct(public readonly int $messId) {}

    public function handle(SheetsGateway $gateway): void
    {
        $config = GoogleSheetsConfig::forMess($this->messId);

        if ($config === null || ! $config->isReady()) {
            return;
        }

        $models = $config->enabledModels(SheetSchema::models());

        if ($models === []) {
            return;
        }

        try {
            $gateway->ensureTabs($config, SheetSchema::tabDefinitions($models));

            foreach ($models as $model) {
                $this->backfillModel($gateway, $config, $model);
            }
        } catch (Throwable $e) {
            Log::error('Google Sheets backfill failed', [
                'mess_id' => $this->messId,
                'error' => $e->getMessage(),
            ]);

            $config->forceFill([
                'last_error' => $e->getMessage(),
                'last_error_at' => now(),
            ])->save();

            GoogleSheetsConfig::flushCache($this->messId);

            return;
        }

        $config->forceFill([
            'last_synced_at' => now(),
            'last_error' => null,
            'last_error_at' => null,
        ])->save();

        GoogleSheetsConfig::flushCache($this->messId);
    }

    private function backfillModel(SheetsGateway $gateway, GoogleSheetsConfig $config, string $model): void
    {
        $tab = SheetSchema::tabFor($model);

        if ($tab === null || ! class_exists($model)) {
            return;
        }

        $gateway->clearTab($config, $tab);

        $buffer = [];

        $model::query()
            ->withoutGlobalScope(MessScope::class)
            ->where('mess_id', $this->messId)
            ->with(SheetSchema::relationsFor($model))
            ->chunkById(self::CHUNK, function ($rows) use (&$buffer, $gateway, $config, $tab) {
                foreach ($rows as $row) {
                    $buffer[] = SheetSchema::rowFor($row);
                }

                if (count($buffer) >= self::CHUNK) {
                    $gateway->appendRows($config, $tab, $buffer);
                    $buffer = [];
                }
            });

        if ($buffer !== []) {
            $gateway->appendRows($config, $tab, $buffer);
        }
    }
}
