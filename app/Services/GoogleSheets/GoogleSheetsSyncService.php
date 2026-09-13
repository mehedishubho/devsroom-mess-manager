<?php

namespace App\Services\GoogleSheets;

use App\Jobs\SyncGoogleSheetsJob;
use App\Models\GoogleSheetsConfig;
use App\Models\GoogleSheetsPending;
use App\Models\Scopes\MessScope;
use App\Services\GoogleSheets\Contracts\SheetsGateway;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Records DB changes for mirroring and flushes them to Google Sheets.
 *
 * Two responsibilities, both fail-open:
 *  - `enqueue()` runs inside the request that saved the model. It only writes a
 *    row into `google_sheets_pending` and dispatches a job — no Sheets API call
 *    ever happens on this path, so a Sheets outage can never fail the save.
 *  - `flush()` runs in the queued worker and is the only place the API is called.
 */
class GoogleSheetsSyncService
{
    /** Safety cap on drain passes within one job run. */
    private const MAX_PASSES = 50;

    /** Rows drained per pass. */
    private const PASS_LIMIT = 1000;

    public function __construct(private readonly SheetsGateway $gateway) {}

    /**
     * Record that a model changed and schedule a flush. Never throws.
     */
    public function enqueue(Model $model, string $operation): void
    {
        try {
            if (! isset(SheetSchema::tables()[$model::class])) {
                return;
            }

            $messId = $model->getAttribute('mess_id');

            if (! is_numeric($messId)) {
                return;
            }

            $messId = (int) $messId;
            $config = GoogleSheetsConfig::forMess($messId);

            if ($config === null || ! $config->enabled || ! $config->isReady()) {
                return;
            }

            if (! in_array($model::class, $config->enabledModels(SheetSchema::models()), true)) {
                return;
            }

            GoogleSheetsPending::query()->updateOrCreate(
                [
                    'mess_id' => $messId,
                    'model' => $model::class,
                    'record_id' => (int) $model->getKey(),
                ],
                ['operation' => $operation],
            );

            SyncGoogleSheetsJob::dispatch($config->id);
        } catch (Throwable $e) {
            // The DB write must never be disturbed by a mirroring problem.
            Log::warning('Google Sheets enqueue failed', [
                'model' => $model::class,
                'key' => $model->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Drain every pending row for the mess to the spreadsheet.
     *
     * Pending rows are only deleted after their batch has been written, so a
     * thrown gateway error leaves them in place for the job's retry.
     */
    public function flush(GoogleSheetsConfig $config): void
    {
        $messId = (int) $config->mess_id;
        $enabled = $config->enabledModels(SheetSchema::models());

        if ($enabled === []) {
            return;
        }

        for ($pass = 0; $pass < self::MAX_PASSES; $pass++) {
            $pending = GoogleSheetsPending::query()
                ->where('mess_id', $messId)
                ->whereIn('model', $enabled)
                ->orderBy('id')
                ->limit(self::PASS_LIMIT)
                ->get();

            if ($pending->isEmpty()) {
                break;
            }

            foreach ($pending->groupBy('model') as $model => $rows) {
                $this->pushModel($config, (string) $model, $rows);
            }

            GoogleSheetsPending::query()->whereIn('id', $pending->pluck('id'))->delete();
        }

        $config->forceFill([
            'last_synced_at' => now(),
            'last_error' => null,
            'last_error_at' => null,
        ])->save();

        GoogleSheetsConfig::flushCache($messId);
    }

    /**
     * Apply all pending rows for one model in as few API calls as possible:
     * one batched value write for updates, one append for new rows, one
     * structural delete for removals.
     *
     * Order matters: updates address row numbers read before any deletion, so
     * updates run first, then appends, then deletes.
     */
    private function pushModel(GoogleSheetsConfig $config, string $model, Collection $rows): void
    {
        $tab = SheetSchema::tabFor($model);

        if ($tab === null || ! class_exists($model)) {
            return;
        }

        $this->gateway->ensureTabs($config, SheetSchema::tabDefinitions([$model]));

        $rowById = $this->mapIdsToRows($this->gateway->readColumn($config, $tab, 'A'));

        $upsertIds = $rows->where('operation', GoogleSheetsPending::OPERATION_UPSERT)
            ->pluck('record_id')->map(fn ($id) => (int) $id)->all();
        $deleteIds = $rows->where('operation', GoogleSheetsPending::OPERATION_DELETE)
            ->pluck('record_id')->map(fn ($id) => (int) $id)->all();

        $models = $this->loadModels($model, $upsertIds, $config);

        $updates = [];
        $appends = [];
        $staleDeletes = [];

        foreach ($upsertIds as $id) {
            $instance = $models->get($id);

            if ($instance === null) {
                // The row vanished without a delete event (e.g. cascade). Mirror
                // that by removing it if it is still present in the sheet.
                if (isset($rowById[$id])) {
                    $staleDeletes[] = $rowById[$id];
                }

                continue;
            }

            $values = SheetSchema::rowFor($instance);

            if (isset($rowById[$id])) {
                $updates[] = [
                    'range' => "'".str_replace("'", "''", $tab)."'!A{$rowById[$id]}",
                    'values' => [$values],
                ];
            } else {
                $appends[] = $values;
            }
        }

        if ($updates !== []) {
            $this->gateway->batchUpdateValues($config, $updates);
        }

        if ($appends !== []) {
            $this->gateway->appendRows($config, $tab, $appends);
        }

        $rowNumbers = array_values(array_filter(array_merge(
            $staleDeletes,
            array_intersect_key($rowById, array_flip($deleteIds)),
        )));

        if ($rowNumbers !== []) {
            $this->gateway->deleteRows($config, $tab, $rowNumbers);
        }
    }

    /**
     * Load the instances for a batch of ids, bypassing the active-mess scope
     * (the worker has no session) and eager-loading the relations the schema
     * flattens.
     *
     * @param  array<int, int>  $ids
     * @return Collection<int, Model>
     */
    private function loadModels(string $model, array $ids, GoogleSheetsConfig $config): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return $model::query()
            ->withoutGlobalScope(MessScope::class)
            ->where('mess_id', $config->mess_id)
            ->whereIn('id', $ids)
            ->with(SheetSchema::relationsFor($model))
            ->get()
            ->keyBy(fn (Model $instance) => (int) $instance->getKey());
    }

    /**
     * @param  array<int, mixed>  $column  raw column A values, header excluded
     * @return array<int, int> id => 1-based sheet row number
     */
    private function mapIdsToRows(array $column): array
    {
        $map = [];

        foreach ($column as $index => $value) {
            $id = is_numeric($value) ? (int) $value : 0;

            if ($id > 0) {
                // +1 for the header row, +1 to convert the 0-based offset.
                $map[$id] = $index + 2;
            }
        }

        return $map;
    }
}
