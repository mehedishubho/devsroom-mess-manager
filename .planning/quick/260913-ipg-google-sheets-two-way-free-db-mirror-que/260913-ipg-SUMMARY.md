---
status: complete
quick_id: 260913-ipg
date: 2026-09-13
---

# Quick Task 260913-ipg — Summary

Queued, fail-open DB→Google Sheets mirror with dual auth, encrypted per-mess
credentials, a coalescing pending table, a manager config page and backfill.

## What was built

**Data layer**
- `google_sheets_configs` (one row per mess) + `google_sheets_pending` (dirty-row buffer).
- `App\Models\GoogleSheetsConfig` — `'encrypted'` casts on the service-account JSON,
  OAuth client secret and refresh token; `forMess()` memoized lookup, `authMode()`,
  `hasCredentials()`, `isReady()`, `resolvedSpreadsheetId()` (accepts a full URL),
  `serviceAccountEmail()`.
- `App\Models\GoogleSheetsPending` — explicit `$table = 'google_sheets_pending'`
  ("pending" is uncountable, so Eloquent's pluralisation had to be overridden).

**Gateway seam (testable without network)**
- `Contracts\SheetsGateway` (ensureTabs / readColumn / batchUpdateValues / appendRows /
  deleteRows / clearTab / testConnection / createSpreadsheet).
- `GoogleSheetsClientFactory` — service account (`setAuthConfig`) or OAuth
  (`fetchAccessTokenWithRefreshToken`). Note: the existing Drive backup code calls a
  non-existent `refreshTokenWithRefreshToken()`; this feature uses the real method.
- `GoogleApiclientSheetsGateway` — google/apiclient implementation. Google's generated
  models have a **parameterless constructor**, so every request object is built with setters.

**Sync engine**
- `SheetSchema` — 10 model→tab mappings with flattening (names not ids, `Y-m-d`,
  numeric money, 1/0 flags) and eager-load hints. Column A is always the DB id.
- `GoogleSheetsSyncService` — `enqueue()` (request path: pending row + job dispatch only,
  fully try/catch-guarded) and `flush()` (worker path: drain → batch → append → delete).
- `SyncGoogleSheetsJob` — `ShouldQueue` + `ShouldQueueAfterCommit` + `ShouldBeUnique`,
  `WithoutOverlapping` per config, 5 tries with backoff; `failed()` stamps the config and
  notifies managers via the new `NotificationType::SHEETS_SYNC_FAILED`.
- `BackfillGoogleSheetsJob` — clears each enabled tab (header kept) and rewrites it in
  chunks, so "Sync existing data" is idempotent.

**Wiring / UI**
- `AppServiceProvider::register()` binds `SheetsGateway`; `boot()` calls the new
  `registerGoogleSheetsSync()`, which listens on `eloquent.saved` / `eloquent.deleted`
  for all 10 models.
- `GoogleSheetsController` (`edit`/`update`/`testConnection`/`backfill`/`createSpreadsheet`),
  `UpdateGoogleSheetsSettingsRequest` (blank secret = keep stored, JSON key validated),
  `resources/views/mess/google-sheets/edit.blade.php` (secrets blank + "saved ✓" badge,
  auth-mode toggle, per-tab checkboxes, fetch()-driven action buttons).
- 5 routes in the manager group (no `month.open`); sidebar link added to the Settings group.

## Deviations from the plan

1. **GSD executor sub-agent is read-only in this harness** — it has no Write/Edit/Bash
   tools, so it could not execute. Implementation was done directly by the orchestrator.
2. **No PHPUnit runner exists** in this repo (no `tests/`, no `phpunit.xml`, no
   `phpunit/phpunit`), so the planned PHPUnit suite could not be written/run. Verification
   used `php -l`, `migrate`, `route:list`, `pint`, `view:cache`, and a temporary offline
   smoke script instead (see below).
3. `SheetsGateway` methods take `GoogleSheetsConfig` rather than a bare spreadsheet id,
   so the implementation can build its own authenticated client (and tests can fake it).
4. The `GuestMeals` tab omits "Entered By": `GuestMeal` has no `enteredBy` relation, so
   emitting it would have meant printing a raw user id.
5. Added `clearTab()` to the gateway (not in the original plan) so backfill is idempotent
   instead of appending duplicates on re-run.
6. Plan-checker and verifier sub-agents were not spawned — the design came from a plan the
   user had already reviewed and approved; verification was done inline.
7. **Nothing was committed.** The orchestrator did not create git commits.

## Verification (observed)

- `php -l` clean on all 16 new/changed PHP files.
- `php artisan migrate` → both migrations ran (batch 9).
- `php artisan route:list --path=mess/google-sheets` → all 5 routes registered with names.
- `php artisan view:cache` → all Blade templates compiled (then cleared).
- `vendor/bin/pint --dirty` → 3 style issues fixed; re-linted clean.
- Offline smoke script (real app boot, no network) — **33/33 checks passed**:
  - SheetSchema: 10 models, correct keys, header rows, first column `ID`.
  - Encryption: secret stored as ciphertext, decrypts back, `serviceAccountEmail()` derived,
    `isReady()` true/false per auth-mode completeness.
  - Request path is API-free: a `MealEntry` saved successfully with unverifiable
    credentials (the real gateway was bound), a pending upsert row was recorded, and
    `SyncGoogleSheetsJob` was pushed carrying the config id.
  - Flush engine (recording fake gateway): append for a new row (DB id in column A),
    batched update at `'MealEntries'!A2` for an existing row, `deleteRows([2])` for a
    removed row, pending drained, `last_synced_at` stamped.
  - Disabled config: no pending row on save or delete.
  - Cleanup: DB left with `configs=0 pending=0 jobs=0 failed=0`.

## Not verified (needs real credentials + a live worker)

- An actual round-trip against the Google Sheets API (both auth modes).
- Live HTTP as a manager on `/mess/google-sheets`, and the 403 for a member.
- End-to-end "save a meal → row appears in the sheet" with `queue:work` running.

These need a real service-account key / OAuth refresh token and a target spreadsheet.
