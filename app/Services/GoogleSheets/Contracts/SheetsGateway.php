<?php

namespace App\Services\GoogleSheets\Contracts;

use App\Models\GoogleSheetsConfig;

/**
 * Thin seam over the Google Sheets API.
 *
 * The sync engine only ever talks to this interface, so the whole feature can
 * be exercised (and drift is prevented) without network access: bind a fake
 * implementation in tests/dev and assert on the calls it receives.
 *
 * Every method takes the `GoogleSheetsConfig` (not a bare spreadsheet id) so
 * the implementation can build an authenticated client from the same config
 * that selected the target spreadsheet.
 *
 * Implementations MUST throw on failure — the caller (a queued job) is the
 * layer that decides how to handle and report an error.
 */
interface SheetsGateway
{
    /**
     * Ensure each given tab exists with a header row.
     *
     * @param  array<int, array{title: string, headers: array<int, string>}>  $tabs
     */
    public function ensureTabs(GoogleSheetsConfig $config, array $tabs): void;

    /**
     * Read the raw values of a column, excluding the header row.
     *
     * @return array<int, mixed>
     */
    public function readColumn(GoogleSheetsConfig $config, string $tab, string $column): array;

    /**
     * Write several ranges in one request.
     *
     * @param  array<int, array{range: string, values: array<int, array<int, mixed>>}>  $data
     */
    public function batchUpdateValues(GoogleSheetsConfig $config, array $data): void;

    /**
     * Append rows to the first empty line of a tab.
     *
     * @param  array<int, array<int, mixed>>  $rows
     * @return int 1-based sheet row index of the first appended row (0 when nothing was appended)
     */
    public function appendRows(GoogleSheetsConfig $config, string $tab, array $rows): int;

    /**
     * Delete whole rows.
     *
     * @param  array<int, int>  $rowNumbers  1-based sheet row numbers
     */
    public function deleteRows(GoogleSheetsConfig $config, string $tab, array $rowNumbers): void;

    /**
     * Erase every data row of a tab, keeping the header row (used by backfill
     * so a re-run rewrites the tab instead of duplicating it).
     */
    public function clearTab(GoogleSheetsConfig $config, string $tab): void;

    /**
     * Probe credentials + access to the configured spreadsheet.
     *
     * @return array{ok: bool, title: string}
     */
    public function testConnection(GoogleSheetsConfig $config): array;

    /**
     * Create a new spreadsheet owned by the configured account.
     *
     * @return array{id: string, url: string}
     */
    public function createSpreadsheet(GoogleSheetsConfig $config, string $title): array;
}
