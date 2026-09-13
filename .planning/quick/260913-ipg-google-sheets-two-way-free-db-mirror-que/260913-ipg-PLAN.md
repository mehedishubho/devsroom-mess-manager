---
must_haves:
  truths:
    - "A DB insert/update/delete on any synced domain model never fails because of Google Sheets — no Sheets API call happens in the request path."
    - "Every saved/deleted synced row is recorded in a pending table and mirrored to its spreadsheet tab by a queued job (near-real-time)."
    - "Bursts coalesce: a meal-grid save that touches N rows results in a bounded number of Sheets API calls, not N."
    - "Credentials (service-account JSON, OAuth client secret, refresh token) are encrypted at rest and never re-rendered into the form."
    - "A manager can configure, test, and backfill the connection from /mess/google-sheets; members cannot."
  artifacts:
    - "database/migrations/*_create_google_sheets_configs_table.php"
    - "database/migrations/*_create_google_sheets_pending_table.php"
    - "app/Models/GoogleSheetsConfig.php"
    - "app/Models/GoogleSheetsPending.php"
    - "app/Services/GoogleSheets/Contracts/SheetsGateway.php"
    - "app/Services/GoogleSheets/GoogleSheetsClientFactory.php"
    - "app/Services/GoogleSheets/GoogleApiclientSheetsGateway.php"
    - "app/Services/GoogleSheets/SheetSchema.php"
    - "app/Services/GoogleSheets/GoogleSheetsSyncService.php"
    - "app/Jobs/SyncGoogleSheetsJob.php"
    - "app/Jobs/BackfillGoogleSheetsJob.php"
    - "app/Http/Controllers/Mess/GoogleSheetsController.php"
    - "app/Http/Requests/Mess/UpdateGoogleSheetsSettingsRequest.php"
    - "resources/views/mess/google-sheets/edit.blade.php"
  key_links:
    - "AppServiceProvider::registerGoogleSheetsSync() listens on eloquent.saved/deleted for the 10 synced models and calls GoogleSheetsSyncService::enqueue()"
    - "routes/web.php manager group gains mess.google-sheets.* routes (no month.open)"
    - "resources/views/components/sidebar.blade.php Settings group gains the Google Sheets link"
    - "App\\Support\\NotificationType::SHEETS_SYNC_FAILED added to ALL + LABELS"
---

# Quick Task 260913-ipg — Plan

**Created:** 2026-09-13
**Description:** Google Sheets mirror — queued, fail-open DB→Sheets sync of all domain tables, dual auth (service account or OAuth refresh token), encrypted per-mess credentials, manager config page at `/mess/google-sheets`, plus backfill.

**Canonical design:** `C:\Users\USER\.commandcode\plans\google-sheets-sync.md` (user-approved). This file is the executor contract; the approved plan is the detail reference.

## Confirmed decisions
- Auth: **both**, user-selectable (`service_account` | `oauth`).
- Scope: **all four groups** — meals+guest meals, expenses+categories, payments+advances, members+closings (10 models).
- Mode: **queued async** (`ShouldQueue` + `ShouldQueueAfterCommit`).
- UI: **manager area** `/mess/google-sheets`.
- Direction: **one-way DB → Sheets**; DB is source of truth.

## Environment facts (verified — do not re-litigate)
- Laravel 13.15, PHP 8.4, MySQL (`devsroom_mess_management`, root/125524 per `.env`), `QUEUE_CONNECTION=database`, `CACHE_STORE=database`.
- `google/apiclient` v2.19.4 + `google/auth` v1.52.0 are **already installed** (transitive via `masbug/flysystem-google-drive-ext`). `Google\Service\Sheets` is available — **do NOT add a Composer package**.
- Existing Google auth pattern: `AppServiceProvider::registerGoogleDriveDriver()` (`Google\Client` + clientId/clientSecret/refreshToken).
- Existing encrypted-credential pattern: `app/Models/BackupConfig.php` (`'encrypted'` casts) + `BackupController::testConnection()/testResult()` (JSON-or-redirect).
- Existing model-event pattern: `AppServiceProvider::registerBillPreviewInvalidation()` → `Event::listen("eloquent.saved: {FQCN}", ...)`.
- **There is NO PHPUnit/Pest runner in this repo** (`tests/` absent, no `phpunit.xml`, `phpunit/phpunit` not in require-dev — removed in quick-260724-pm2). Verification is `php -l` + `php artisan` + `vendor/bin/pint` + tinker smoke (see Task 4). Do NOT add a test framework.

## Conventions to follow
- Per-mess credential storage mirrors `BackupConfig`: `'encrypted'` Eloquent casts; form renders secrets **blank** with a `•••• (saved)` badge; blank submit = keep stored value.
- Manager routes live in the existing group `['auth','roles:super-admin,manager', EnsureMessExists::class]` in `routes/web.php`. **Do NOT add `month.open`.**
- Manager page = route + controller (`edit`/`update`) + FormRequest with `authorize()` = `$this->user() && $this->user()->canManageMess()` + Blade view `@extends('layouts.app')` using `.input` / `btn btn-primary`.
- All user-facing strings use `__()`.
- Flattening rules: person FKs → `.name`; category → `category.name`; dates → `Y-m-d`; money → numeric float; booleans → `1/0`. Column A of every tab = DB primary key (used to locate the row for update/delete).
- Queue worker resolves config by explicit `mess_id`, never `Mess::activeId()`.

---

## Task 1 — Data layer: config + pending tables, models, gateway, schema

**files**
- `database/migrations/<ts>_create_google_sheets_configs_table.php`
- `database/migrations/<ts>_create_google_sheets_pending_table.php`
- `app/Models/GoogleSheetsConfig.php`
- `app/Models/GoogleSheetsPending.php`
- `app/Services/GoogleSheets/Contracts/SheetsGateway.php`
- `app/Services/GoogleSheets/GoogleSheetsClientFactory.php`
- `app/Services/GoogleSheets/GoogleApiclientSheetsGateway.php`
- `app/Services/GoogleSheets/SheetSchema.php`

**action**
- `google_sheets_configs`: `id`; `mess_id` FK→messes cascade **unique**; `enabled` boolean default false; `auth_mode` string(20) default `'service_account'`; `service_account_json` **text** nullable; `service_account_email` string nullable; `oauth_client_id` string nullable; `oauth_client_secret` **text** nullable; `oauth_refresh_token` **text** nullable; `spreadsheet_id` string nullable; `tabs` json nullable; `last_synced_at` timestamp nullable; `last_error` text nullable; `last_error_at` timestamp nullable; `timestamps()`.
- `google_sheets_pending`: `id`; `mess_id` FK→messes cascade; `model` string; `record_id` unsignedBigInteger; `operation` string(10); `timestamps()`; `unique(['mess_id','model','record_id'])`; `index(['mess_id'])`.
- `GoogleSheetsConfig`: `#[Fillable([...])]`; `casts()` → `enabled` bool, `tabs` array, `last_synced_at`/`last_error_at` datetime, and `'encrypted'` on `service_account_json`, `oauth_client_secret`, `oauth_refresh_token`. Add `forMess(int $messId): ?self` (memoized per id via a static array; `flushCache()`), `authMode(): string`, `isReady(): bool` (spreadsheet_id filled AND the selected mode's credential set filled), `serviceAccountEmail(): ?string` (parse `client_email` from the JSON, tolerate malformed).
- `GoogleSheetsPending`: `#[Fillable(['mess_id','model','record_id','operation'])]`; `casts()` `record_id` integer.
- `SheetsGateway` interface: `ensureTabs(string $spreadsheetId, array $tabs): void`; `readColumn(string $spreadsheetId, string $tab, string $column): array`; `batchUpdateValues(string $spreadsheetId, array $data): void`; `appendRows(string $spreadsheetId, string $tab, array $rows): int`; `deleteRows(string $spreadsheetId, string $tab, array $rowNumbers): void`; `testConnection(string $spreadsheetId): array` (returns `['ok'=>true,'title'=>string]`, throws on failure); `createSpreadsheet(string $title): array` (returns `['id'=>,'url'=>]`). All doc-commented.
- `GoogleSheetsClientFactory`: `make(GoogleSheetsConfig $c): \Google\Service\Sheets`. Service account → `$client->setAuthConfig(json_decode($c->service_account_json, true, 512, JSON_THROW_ON_ERROR))`; OAuth → `setClientId`/`setClientSecret`/`refreshTokenWithRefreshToken`. Both → `setScopes([\Google\Service\Sheets::SPREADSHEETS])`, `setApplicationName(config('app.name'))`. Throw `RuntimeException` with a readable message when unconfigured.
- `GoogleApiclientSheetsGateway implements SheetsGateway` using `Google\Service\Sheets`: tab creation via `spreadsheets->batchUpdate` addSheet requests (skip existing, from `spreadsheets->get` sheets titles); header row via `values->update('Tab!A1', ...)`; `readColumn` via `values->get('Tab!A2:A')`; `batchUpdateValues` via `values->batchUpdate` (ValueRange list, `USER_ENTERED`); `appendRows` via `values->append` (`INSERT_ROWS`, `USER_ENTERED`) returning the first appended row number parsed from `updates.updatedRange`; `deleteRows` via `spreadsheets->batchUpdate` `DeleteDimension` requests in **descending** row order; `testConnection` via `spreadsheets->get` (title); `createSpreadsheet` via `spreadsheets->create`. Convert `Google\Service\Exception` / any `Throwable` into `RuntimeException` with status + message.
- `SheetSchema`: `const TABLES = [ModelClass => ['tab' => '…', 'headers' => [...], 'row' => fn (Model $m): array => [...]]]` for the 10 models, exactly per the canonical mapping table:
  - `MealEntry`/`MealEntries`: ID, Date, Member, Breakfast, Lunch, Dinner, Guest Breakfast, Guest Lunch, Guest Dinner, Entered By, Updated At
  - `GuestMeal`/`GuestMeals`: ID, Date, Host Member, Guest Name, Meal Type, Quantity, Meal Value, Charge Amount, Entered By
  - `MealOffRequest`/`MealOffRequests`: ID, Member, From Date, To Date, Status, Reason, Rejection Reason, Acted By, Acted At
  - `ExpenseCategory`/`ExpenseCategories`: ID, Name, Kind, Default, Sort Order
  - `Expense`/`Expenses`: ID, Date, Category, Kind, Purchased By, Vendor, Description, Amount, Entered By
  - `Payment`/`Payments`: ID, Date, Member, Type, Method, Amount, Reference, Notes, Entered By
  - `AdvanceBalance`/`AdvanceBalances`: ID, Member, Balance, Due Balance, Net Balance, Last Updated
  - `Member`/`Members`: ID, Name, Slug, Mobile, Email, Profession, Room/Seat, Joining Date, Leaving Date, Status, Emergency Contact
  - `MonthlyClosing`/`MonthlyClosings`: ID, Year, Month, Total Bazar, Total Fixed, Total Meals, Meal Rate, Member Count, Closed At, Closed By, Status
  - `MonthlyMemberSummary`/`MonthlyMemberSummaries`: ID, Closing, Member, Total Meals, Meal Rate, Meal Cost, Fixed Share, Guest Charge, Gross Bill, Bill Payments, Net Bill, Payments Received, Balance Due, Brought Forward, Closing Balance
  - Header `"Bill Payments"` maps `MonthlyMemberSummary::advance_applied` (misnamed in DB — do not "fix" the column). `AdvanceBalance` Net uses `netBalance()`. Missing relations render `''` (never throw).
  - Methods: `tables(): array`, `tabFor(string $model): ?string`, `headersFor(string $model): array`, `rowFor(Model $m): array`, `models(): array`.

**verify**
- `php -l` clean on every new file.
- `php artisan migrate` succeeds against the dev MySQL DB (2 new tables; inspect with `php artisan db:table google_sheets_configs` and `... google_sheets_pending` if available, else `SHOW CREATE TABLE`).
- `php artisan tinker --execute` round-trip: create a `GoogleSheetsConfig` with a JSON secret → reload → assert the raw DB value is NOT the plaintext (encrypted) and the cast returns the original.

**done** Both tables migrated; `SheetSchema::models()` returns 10 entries; encrypted casts verified by the tinker round-trip.

---

## Task 2 — Sync engine: pending queue, sync service, jobs, event wiring

**files**
- `app/Services/GoogleSheets/GoogleSheetsSyncService.php`
- `app/Jobs/SyncGoogleSheetsJob.php`
- `app/Jobs/BackfillGoogleSheetsJob.php`
- `app/Providers/AppServiceProvider.php` (edit)
- `app/Support/NotificationType.php` (edit)

**action**
- `GoogleSheetsSyncService`:
  - `enqueue(Model $model, string $operation): void` — **fail-open**: resolve `GoogleSheetsConfig::forMess((int) $model->mess_id)`; return if missing/`!enabled`/`!isReady()`; `GoogleSheetsPending::updateOrCreate(['mess_id','model'=>$model::class,'record_id'=>$model->getKey()], ['operation'=>$operation])`; `SyncGoogleSheetsJob::dispatch($config->id)`; wrap the whole body in `try/catch (\Throwable) { Log::warning(...); }`.
  - `flush(GoogleSheetsConfig $config): void` — drain pending for the config's mess; group by `model`; for each model: `ensureTabs`, read column A once to build `id => rowNumber`, split into inserts/updates/deletes, apply `batchUpdateValues` (updates) + `appendRows` (inserts) + `deleteRows` (deletes), then delete the drained pending rows; stamp `last_synced_at`; clear `last_error`. Loop until no pending remain (cap ~50 passes) so rows added mid-run are picked up. Rebuild per-model state each pass (no cross-pass row-number assumptions).
- `SyncGoogleSheetsJob`: `implements ShouldQueue, ShouldQueueAfterCommit, ShouldBeUnique`; `uniqueId()` = `"google-sheets-sync:{$this->configId}"`; `middleware()` → `WithoutOverlapping("google-sheets-sync:{$this->configId}")->releaseAfter(30)->expireAfter(300)`; `public int $tries = 5; public array $backoff = [10,30,60,120,300]; public int $timeout = 120;` constructor takes `public readonly int $configId`; `handle(GoogleSheetsSyncService $svc)`; `failed(Throwable $e)` stamps `last_error`/`last_error_at` and calls `app(NotificationService::class)->broadcastToManagers(NotificationType::SHEETS_SYNC_FAILED, ['message' => $e->getMessage()])` inside try/catch.
- `BackfillGoogleSheetsJob`: `ShouldQueue`; `tries = 1`, `timeout = 600`; `handle(SheetsGateway, GoogleSheetsClientFactory)`: `ensureTabs`, then per model in `SheetSchema::models()` query with `->withoutGlobalScope(\App\Models\Scopes\MessScope::class)->where('mess_id', $messId)->with(<relations>)` `chunkById(500)`, map via `SheetSchema::rowFor()`, `appendRows` in chunks; stamp `last_synced_at`; failures → `Log::error` + notification.
- `NotificationType`: add `public const SHEETS_SYNC_FAILED = 'sheets_sync_failed';`, add to `ALL` and `LABELS` (`'Google Sheets sync failed'`).
- `AppServiceProvider`:
  - `register()`: `$this->app->bind(SheetsGateway::class, GoogleApiclientSheetsGateway::class);`
  - `boot()`: add `$this->registerGoogleSheetsSync();`
  - New `registerGoogleSheetsSync()`: resolve `GoogleSheetsSyncService` once; `foreach (SheetSchema::models() as $modelClass)` register `eloquent.saved: {$modelClass}` → enqueue `upsert`, `eloquent.deleted: {$modelClass}` → enqueue `delete`, each wrapped so any throw is logged and swallowed (never break the save).

**verify**
- `php -l` clean.
- `php artisan route:list | findstr google-sheets` unaffected by this task (Task 3 adds routes).
- `php artisan tinker --execute`: with NO config row, `SheetSchema::models()` save path is a no-op (create a MealEntry, assert no `GoogleSheetsPending` row). Then create an enabled-but-unready config and assert still no pending row. Then assert `SyncGoogleSheetsJob` is dispatchable: `SyncGoogleSheetsJob::dispatchSync` is NOT used; instead check `get_class(new SyncGoogleSheetsJob(1))` + `dispatch` is callable.
- `php artisan queue:failed` still empty/unchanged.

**done** Saving a model with an enabled+ready config creates exactly one pending row per record and dispatches one coalescing job; with no config it is a silent no-op; enqueue never throws.

---

## Task 3 — Manager UI: routes, controller, request, view, sidebar

**files**
- `app/Http/Controllers/Mess/GoogleSheetsController.php`
- `app/Http/Requests/Mess/UpdateGoogleSheetsSettingsRequest.php`
- `resources/views/mess/google-sheets/edit.blade.php`
- `routes/web.php` (edit)
- `resources/views/components/sidebar.blade.php` (edit)

**action**
- Routes (inside the existing manager group, after the `/mess/notifications` pair):
  `GET /mess/google-sheets` → `edit` (`mess.google-sheets.edit`);
  `PUT /mess/google-sheets` → `update` (`mess.google-sheets.update`);
  `POST /mess/google-sheets/test` → `testConnection` (`throttle:10,1`, `mess.google-sheets.test`);
  `POST /mess/google-sheets/backfill` → `backfill` (`throttle:5,1`, `mess.google-sheets.backfill`);
  `POST /mess/google-sheets/create-spreadsheet` → `createSpreadsheet` (`throttle:5,1`, `mess.google-sheets.create-spreadsheet`).
  Import `GoogleSheetsController` at the top of `web.php`.
- `UpdateGoogleSheetsSettingsRequest`: `authorize()` = `$this->user() && $this->user()->canManageMess()`; rules — `enabled` boolean; `auth_mode` `Rule::in(['service_account','oauth'])`; `service_account_json` nullable string; `oauth_client_id`/`oauth_client_secret`/`oauth_refresh_token` nullable string; `spreadsheet_id` nullable string; `tabs` nullable array; `tabs.*` string in `SheetSchema::models()` keys. `normalizedConfig()`: parse a full `docs.google.com/spreadsheets/d/{id}/...` URL down to `{id}`; drop blank secret keys so the controller keeps stored values.
- `GoogleSheetsController` (constructor-inject `GoogleSheetsClientFactory`, `SheetsGateway`):
  - `edit(): View` — `GoogleSheetsConfig::forMess(Mess::activeId())`, `SheetSchema::tables()`, pending count, ready/error status.
  - `update(...)`: `firstOrNew` the config for the active mess; `fill()` only non-blank secrets; save; `GoogleSheetsConfig::flushCache()`; redirect back with `success`.
  - `testConnection(Request)`: build client + `$gateway->testConnection($spreadsheetId)`; return `{ok,message}` JSON when `expectsJson()`, else redirect-with-flash (mirror `BackupController::testResult`).
  - `backfill()`: `BackfillGoogleSheetsJob::dispatch($messId)`; redirect back with success.
  - `createSpreadsheet()`: `$gateway->createSpreadsheet(...)`, store the new id, redirect back.
- View: `@extends('layouts.app')`; header `h1`; single `PUT` form (`@csrf`, `@method('PUT')`); sections in `rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:p-6`; `.input` fields; `old()` + `@error`; secret inputs `type="password"` rendered **blank** with a `•••• (saved)` badge when a value exists; auth-mode radio toggling the two credential blocks (plain JS, no build step); per-tab checkboxes driven by `SheetSchema::tables()`; buttons `Test connection`, `Sync existing data`, `Create spreadsheet` using native `fetch()` with `X-CSRF-TOKEN` + `Accept: application/json` (copy the pattern in `resources/views/dashboard/backups/_configure_form.blade.php`); status panel (last synced, pending count, last error).
- Sidebar: add to the manager **Settings** group in `resources/views/components/sidebar.blade.php`:
  `['route' => 'mess.google-sheets.edit', 'match' => 'mess.google-sheets.*', 'label' => __('Google Sheets'), 'icon' => '<svg …sheet/grid…></svg>']`.

**verify**
- `php -l` clean.
- `php artisan route:list --path=mess/google-sheets` lists all 5 routes with the expected names and middleware (`auth`, `EnsureAnyTyroRole`, `EnsureMessExists`, plus throttles).
- `php artisan view:clear` then load the page over a local `php artisan serve` as a manager → HTTP 200 and the form renders; as a member → 403. (If live login is impractical, assert the FormRequest `authorize()` returns false for a member user and true for a manager via tinker.)
- `vendor/bin/pint --dirty` clean.

**done** A manager reaches the page, saves config, sees secrets masked, and the three action buttons return JSON `{ok,message}`.

---

## Task 4 — Verification pass

**files** (none new; verification only)

**action**
- `vendor/bin/pint` (project style) — then re-check `git status`.
- `php artisan route:list --path=mess/google-sheets`.
- `php artisan migrate:status` — both new migrations ran.
- `php -l` over every new/modified PHP file.
- Behavioural smoke via `php artisan tinker --execute` (temporary script is fine — **clean it up after**, per project convention):
  1. No config → saving a `MealEntry` creates **no** `GoogleSheetsPending` row and dispatches nothing.
  2. Enabled+ready config (fake credentials) → saving a `MealEntry` creates a pending row with `operation='upsert'`; saving again updates it in place (still one row — coalescing); deleting creates `operation='delete'`.
  3. `GoogleSheetsConfig` secret round-trip is encrypted at rest.
  4. `SheetSchema::rowFor()` on a real `Expense`/`Payment`/`Member` returns the expected number of columns and a date in `Y-m-d`.
- Confirm the request path performs **no** outbound HTTP: with `QUEUE_CONNECTION=sync` temporarily NOT used, the save returns even when credentials are bogus (the failure lands in the pending table + job, not the response). Document the observed result.
- Report any deviation from this plan in the SUMMARY.

**verify** All commands above pass; the smoke assertions hold; `vendor/bin/pint` reports no changes.

**done** SUMMARY.md written with `status: complete`, listing the observed verification output and any deviations.

## Notes / risks
- Do NOT add `google/apiclient` to composer.json — it is already installed transitively. (Optional hardening for a follow-up: promote it to a direct require.)
- Do NOT add a test framework.
- Sheets API calls only ever happen inside queued jobs.
- `SheetSchema::rowFor()` must never throw on a missing relation — use null-safe access.
- `MonthlyMemberSummary.advance_applied` stays as-is in the DB; only the sheet header reads "Bill Payments".
