<?php

namespace App\Services\GoogleSheets;

use App\Models\GoogleSheetsConfig;
use App\Services\GoogleSheets\Contracts\SheetsGateway;
use Google\Service\Sheets;
use Google\Service\Sheets\AddSheetRequest;
use Google\Service\Sheets\BatchUpdateSpreadsheetRequest;
use Google\Service\Sheets\BatchUpdateValuesRequest;
use Google\Service\Sheets\ClearValuesRequest;
use Google\Service\Sheets\DeleteDimensionRequest;
use Google\Service\Sheets\DimensionRange;
use Google\Service\Sheets\Request as SheetsRequest;
use Google\Service\Sheets\SheetProperties;
use Google\Service\Sheets\Spreadsheet;
use Google\Service\Sheets\SpreadsheetProperties;
use Google\Service\Sheets\ValueRange;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * The real Google Sheets gateway, backed by google/apiclient.
 *
 * Note: google's generated models have a parameterless constructor, so every
 * request object is assembled with setters rather than constructor arrays.
 */
class GoogleApiclientSheetsGateway implements SheetsGateway
{
    public function __construct(private readonly GoogleSheetsClientFactory $factory) {}

    public function ensureTabs(GoogleSheetsConfig $config, array $tabs): void
    {
        if ($tabs === []) {
            return;
        }

        $sheets = $this->factory->make($config);
        $spreadsheetId = $this->spreadsheetId($config);

        try {
            $spreadsheet = $sheets->spreadsheets->get($spreadsheetId, [
                'fields' => 'sheets.properties(sheetId,title)',
            ]);
        } catch (Throwable $e) {
            throw $this->fail('Could not read the spreadsheet', $e);
        }

        $existing = [];
        foreach ($spreadsheet->getSheets() ?? [] as $sheet) {
            $title = $sheet->getProperties()?->getTitle();
            if (is_string($title) && $title !== '') {
                $existing[$title] = true;
            }
        }

        $requests = [];
        foreach ($tabs as $tab) {
            if (isset($existing[$tab['title']])) {
                continue;
            }

            $properties = new SheetProperties;
            $properties->setTitle($tab['title']);

            $addSheet = new AddSheetRequest;
            $addSheet->setProperties($properties);

            $request = new SheetsRequest;
            $request->setAddSheet($addSheet);

            $requests[] = $request;
        }

        if ($requests !== []) {
            $body = new BatchUpdateSpreadsheetRequest;
            $body->setRequests($requests);

            try {
                $sheets->spreadsheets->batchUpdate($spreadsheetId, $body);
            } catch (Throwable $e) {
                throw $this->fail('Could not create the spreadsheet tabs', $e);
            }
        }

        $this->ensureHeaders($sheets, $spreadsheetId, $tabs);
    }

    public function readColumn(GoogleSheetsConfig $config, string $tab, string $column): array
    {
        $sheets = $this->factory->make($config);
        $spreadsheetId = $this->spreadsheetId($config);

        try {
            $range = $sheets->spreadsheets_values->get($spreadsheetId, $this->a1($tab, $column.'2:'.$column));
        } catch (Throwable $e) {
            throw $this->fail("Could not read column {$column} of {$tab}", $e);
        }

        $values = $range->getValues() ?? [];

        return array_map(fn ($row) => is_array($row) ? ($row[0] ?? null) : $row, $values);
    }

    public function batchUpdateValues(GoogleSheetsConfig $config, array $data): void
    {
        if ($data === []) {
            return;
        }

        $sheets = $this->factory->make($config);
        $spreadsheetId = $this->spreadsheetId($config);

        $ranges = [];
        foreach ($data as $item) {
            $valueRange = new ValueRange;
            $valueRange->setRange($item['range']);
            $valueRange->setValues($item['values']);
            $ranges[] = $valueRange;
        }

        $body = new BatchUpdateValuesRequest;
        $body->setValueInputOption('USER_ENTERED');
        $body->setData($ranges);

        try {
            $sheets->spreadsheets_values->batchUpdate($spreadsheetId, $body);
        } catch (Throwable $e) {
            throw $this->fail('Could not write rows to the spreadsheet', $e);
        }
    }

    public function appendRows(GoogleSheetsConfig $config, string $tab, array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $sheets = $this->factory->make($config);
        $spreadsheetId = $this->spreadsheetId($config);

        $body = new ValueRange;
        $body->setValues($rows);

        try {
            $response = $sheets->spreadsheets_values->append(
                $spreadsheetId,
                $this->a1($tab, 'A1'),
                $body,
                ['valueInputOption' => 'USER_ENTERED', 'insertDataOption' => 'INSERT_ROWS'],
            );
        } catch (Throwable $e) {
            throw $this->fail("Could not append rows to {$tab}", $e);
        }

        $updatedRange = $response->getUpdates()?->getUpdatedRange();

        if (! is_string($updatedRange) || ! preg_match('/![A-Z]+(\d+)/', $updatedRange, $matches)) {
            return 0;
        }

        return (int) $matches[1];
    }

    public function deleteRows(GoogleSheetsConfig $config, string $tab, array $rowNumbers): void
    {
        if ($rowNumbers === []) {
            return;
        }

        $sheets = $this->factory->make($config);
        $spreadsheetId = $this->spreadsheetId($config);

        try {
            $spreadsheet = $sheets->spreadsheets->get($spreadsheetId, [
                'fields' => 'sheets.properties(sheetId,title)',
            ]);
        } catch (Throwable $e) {
            throw $this->fail("Could not resolve the sheet id for {$tab}", $e);
        }

        $sheetId = null;
        foreach ($spreadsheet->getSheets() ?? [] as $sheet) {
            if ($sheet->getProperties()?->getTitle() === $tab) {
                $sheetId = $sheet->getProperties()->getSheetId();
                break;
            }
        }

        if ($sheetId === null) {
            return;
        }

        // Descending order so earlier deletions do not shift the later indices.
        $sorted = array_values(array_unique(array_map('intval', $rowNumbers)));
        rsort($sorted);

        $requests = [];
        foreach ($sorted as $rowNumber) {
            $dimension = new DimensionRange;
            $dimension->setSheetId($sheetId);
            $dimension->setDimension('ROWS');
            $dimension->setStartIndex($rowNumber - 1);
            $dimension->setEndIndex($rowNumber);

            $delete = new DeleteDimensionRequest;
            $delete->setRange($dimension);

            $request = new SheetsRequest;
            $request->setDeleteDimension($delete);

            $requests[] = $request;
        }

        $body = new BatchUpdateSpreadsheetRequest;
        $body->setRequests($requests);

        try {
            $sheets->spreadsheets->batchUpdate($spreadsheetId, $body);
        } catch (Throwable $e) {
            throw $this->fail("Could not delete rows from {$tab}", $e);
        }
    }

    public function clearTab(GoogleSheetsConfig $config, string $tab): void
    {
        $sheets = $this->factory->make($config);
        $spreadsheetId = $this->spreadsheetId($config);

        $body = new ClearValuesRequest;

        try {
            $sheets->spreadsheets_values->clear(
                $spreadsheetId,
                $this->a1($tab, 'A2:ZZ'),
                $body,
            );
        } catch (Throwable $e) {
            throw $this->fail("Could not clear {$tab}", $e);
        }
    }

    public function testConnection(GoogleSheetsConfig $config): array
    {
        $sheets = $this->factory->make($config);

        try {
            $spreadsheet = $sheets->spreadsheets->get($this->spreadsheetId($config), [
                'fields' => 'properties.title',
            ]);
        } catch (Throwable $e) {
            throw $this->fail('Could not open the spreadsheet', $e);
        }

        return [
            'ok' => true,
            'title' => (string) ($spreadsheet->getProperties()?->getTitle() ?? ''),
        ];
    }

    public function createSpreadsheet(GoogleSheetsConfig $config, string $title): array
    {
        $sheets = $this->factory->make($config);

        $properties = new SpreadsheetProperties;
        $properties->setTitle($title);

        $spreadsheet = new Spreadsheet;
        $spreadsheet->setProperties($properties);

        try {
            $created = $sheets->spreadsheets->create($spreadsheet);
        } catch (Throwable $e) {
            throw $this->fail('Could not create the spreadsheet', $e);
        }

        return [
            'id' => (string) $created->getSpreadsheetId(),
            'url' => (string) $created->getSpreadsheetUrl(),
        ];
    }

    /**
     * Write the header row into any requested tab whose A1 cell is empty, using
     * a single batch read to find them.
     *
     * @param  array<int, array{title: string, headers: array<int, string>}>  $tabs
     */
    private function ensureHeaders(Sheets $sheets, string $spreadsheetId, array $tabs): void
    {
        $ranges = array_map(fn (array $tab) => $this->a1($tab['title'], 'A1'), $tabs);

        try {
            $response = $sheets->spreadsheets_values->batchGet($spreadsheetId, ['ranges' => $ranges]);
            $valueRanges = $response->getValueRanges() ?? [];
        } catch (Throwable $e) {
            // Headers are cosmetic for the write path — never fail the sync over them.
            Log::warning('Google Sheets header probe failed', ['error' => $e->getMessage()]);

            return;
        }

        foreach ($tabs as $index => $tab) {
            $values = $valueRanges[$index]?->getValues() ?? [];
            $firstCell = $values[0][0] ?? null;

            if ($firstCell !== null && $firstCell !== '') {
                continue;
            }

            $body = new ValueRange;
            $body->setValues([$tab['headers']]);

            try {
                $sheets->spreadsheets_values->update(
                    $spreadsheetId,
                    $this->a1($tab['title'], 'A1'),
                    $body,
                    ['valueInputOption' => 'RAW'],
                );
            } catch (Throwable $e) {
                throw $this->fail("Could not write the header row for {$tab['title']}", $e);
            }
        }
    }

    private function spreadsheetId(GoogleSheetsConfig $config): string
    {
        $id = $config->resolvedSpreadsheetId();

        if ($id === null) {
            throw new RuntimeException(__('No spreadsheet is configured.'));
        }

        return $id;
    }

    private function a1(string $tab, string $range): string
    {
        return "'".str_replace("'", "''", $tab)."'!".$range;
    }

    private function fail(string $context, Throwable $e): RuntimeException
    {
        return new RuntimeException($context.': '.$e->getMessage(), 0, $e);
    }
}
