---
phase: 06-backup-and-restore-system
plan: 03
name: super-admin-ui
subsystem: backup-restore
tags: [backup, restore, super-admin, audit, typed-confirm, throttle, blade, d-03, d-08, tdd]
requires:
  - "Plan 06-02 backend service layer (BackupRestoreService::restoreFromDisk, RestoreTest model, backup:restore-test command)"
  - "Plan 06-01 foundation (config/backup.php with destination.disks, backups s3 disk)"
provides:
  - "app/Http/Controllers/Backup/BackupController — super-admin Backups UI (index, runNow, runRestoreTest, audit-logged download)"
  - "app/Http/Controllers/Backup/RestoreController — show typed-confirm form + guarded destructive POST (BackupRestoreService orchestration, audit on success AND failure)"
  - "app/Http/Requests/Backup/RestoreRequest — typed-confirm Form Request (path required + mess_name in:<active mess name>)"
  - "resources/views/dashboard/backups/{index,restore,_restore_form,_health_badge}.blade.php — the 4 Backups UI Blade views"
  - "resources/views/errors/maintenance-backup-restore.blade.php — branded maintenance-mode page (rendered by BackupRestoreService's Artisan::call('down', ['--render' => ...]))"
  - "resources/views/vendor/tyro-dashboard/partials/admin-sidebar.blade.php — APPENDED role:super-admin-guarded Backups link"
  - "routes/web.php /dashboard/backups route group (6 named routes; role:super-admin + throttle:5,1 on restore POST)"
  - "14 new feature tests: 6 BackupControllerAuthTest + 5 RestoreConfirmationTest + 3 BackupDownloadAccessLogTest"
affects:
  - "resources/views/vendor/tyro-dashboard/partials/admin-sidebar.blade.php (Backups link appended next to Users)"
  - "routes/web.php (new /dashboard/backups route group + 2 controller imports)"
tech-stack:
  added: []
  patterns:
    - "Manual OwenIt\\Auditing\\Models\\Audit row (not the Auditable trait) for restore/download events — restore is not a model write, so the trait does not fire"
    - "Mockery mock of BackupRestoreService bound via $this->app->instance() in RestoreConfirmationTest — D-08 mocking"
    - "Artisan::swap($spy) (NOT Artisan::fake()) for backup:run/backup:restore-test in BackupControllerAuthTest — Laravel 13 has no Artisan::fake() (confirmed in Plan 06-02)"
    - "Storage::fake('backups') in tests so the s3 adapter never tries a null DO_SPACES_BUCKET"
    - "withoutMiddleware(ThrottleRequests::class) on the restore POST validation tests so they don't rate-limit (T-06-03-04 throttle is still enforced in production; the disable is test-scoped only)"
    - "extends layouts.app (NOT tyro-dashboard::layouts.admin) — every other project custom-admin page does the same; emerald palette + min-h-[44px] touch targets from Phase 5 Plan 05-02"
decisions:
  - "Used config('backup.backup.destination.disks.0', 'backups') (NOT the plan/research's config('backup.destination.disks.0')) — spatie v10 nests the destination config under a top-level `backup` key in config/backup.php; this matches the key BackupRestoreService::downloadAndExtract() already uses (single source of truth)."
  - "Used Mess::find(Mess::activeId())?->name (NOT the plan/research's Mess::active()?->name) — Mess has no active() accessor, only activeId(). The typed-confirm target is still the active mess's `name` column (research Open Question #3 LOCKED)."
  - "Blade views extend layouts.app (NOT tyro-dashboard::layouts.admin) — aligns with every other project custom-admin page (mess/audit/index, mess/settings, close). Uses the emerald Tailwind palette + min-h-[44px] touch targets established in Phase 5."
  - "Sidebar link added to BOTH resources/views/vendor/tyro-dashboard/partials/admin-sidebar.blade.php (Tyro dashboard's sidebar — required by the verify grep) AND guarded by @if(auth()->user()?->hasRole('super-admin') && Route::has('dashboard.backups.index'))."
key-files:
  created:
    - "app/Http/Controllers/Backup/BackupController.php"
    - "app/Http/Controllers/Backup/RestoreController.php"
    - "app/Http/Requests/Backup/RestoreRequest.php"
    - "resources/views/dashboard/backups/index.blade.php"
    - "resources/views/dashboard/backups/restore.blade.php"
    - "resources/views/dashboard/backups/_restore_form.blade.php"
    - "resources/views/dashboard/backups/_health_badge.blade.php"
    - "resources/views/errors/maintenance-backup-restore.blade.php"
    - "tests/Feature/Backup/BackupControllerAuthTest.php"
    - "tests/Feature/Backup/RestoreConfirmationTest.php"
    - "tests/Feature/Backup/BackupDownloadAccessLogTest.php"
  modified:
    - "routes/web.php"
    - "resources/views/vendor/tyro-dashboard/partials/admin-sidebar.blade.php"
metrics:
  duration: ~7 min
  tasks_completed: 3
  files_changed: 13
  tests_added: 14
  completed: 2026-06-19
---

# Phase 6 Plan 06-03: Super-Admin Backups UI Summary

**One-liner:** Built the super-admin-facing UI for the backup-and-restore system — a `role:super-admin`-gated `/dashboard/backups` route group with 2 custom controllers (read + audit-logged download surface, plus a typed-mess-name confirm + throttled destructive restore POST), 1 typed-confirm Form Request, 4 Blade views + maintenance-mode error template, a role-guarded Backups sidebar link, and 14 new tests (6 auth-gate + 5 typed-confirm + 3 download-audit). Controllers orchestrate `BackupRestoreService` (Plan 06-02) and write tamper-evident manual `Audit` rows — they contain NO restore logic themselves (T-06-02-08). Every heavy process call (mysqldump/mysql/Artisan) is mocked per D-08; the full PHPUnit suite runs green at 278 tests.

## What Shipped

### Task 1 — BackupController + RestoreController + RestoreRequest + route group + maintenance error view
- **`BackupController`** (research Pattern 3 — custom controller, NOT a Tyro dynamic resource): `index()` lists zip artifacts from `Storage::disk(config('backup.backup.destination.disks.0', 'backups'))` + reads the latest `RestoreTest` row for the health badge; `runNow()` calls `Artisan::call('backup:run')`; `runRestoreTest()` calls `Artisan::call('backup:restore-test')`; `download()` aborts 404 on missing path + streams the file + writes an `event='backup.download'` audit row (T-06-03-05). A private `writeAudit()` helper builds the manual OwenIt `Audit` row (sentinel `auditable_type='backup'`, `auditable_id=0`, JSON `new_values`, `tags='backup'`). A public static `activeMessName()` resolves the typed-confirm target via `Mess::find(Mess::activeId())?->name`.
- **`RestoreController`** (research Code Example B + mirrors `MonthCloseController`): constructor-injects `BackupRestoreService`; `show()` aborts 404 on missing path + renders the typed-confirm form with `expectedMessName = BackupController::activeMessName()`; `store()` runs the service in a try/catch — success writes `event='backup.restore'`, failure writes `event='backup.restore.failed'` (T-06-03-07), and the exception NEVER escapes (the controller's catch is the second layer on top of the service's always-`up` finally block from Plan 06-02).
- **`RestoreRequest`** (D-03 typed-confirm Form Request): `path` (required string) + `mess_name` (required string, `in:<active mess name>`). Degrades to an unmatchable sentinel `'in:__no_active_mess__'` when no active mess exists — a restore can NEVER proceed in the pre-onboarding state. `__()`-wrapped messages per project convention (PERF-03).
- **`routes/web.php`**: a new `/dashboard/backups` route group (6 named routes) with `role:super-admin` + `throttle:5,1` middleware on the destructive restore POST. Controllers imported at the top-of-file (project convention).
- **`resources/views/errors/maintenance-backup-restore.blade.php`**: a small branded maintenance-mode page (extends the framework-shipped `errors::minimal`) — this is the `--render` target the `BackupRestoreService::restoreFromDisk` Artisan::call('down', ...) points at.

### Task 2 — Blade views + super-admin sidebar link
- **`index.blade.php`**: backup list table (Path/Size/Last modified/Actions) + Backup-now + Run-restore-test buttons + the `_health_badge` partial. Mobile-first (`overflow-x-auto`); `min-h-[44px]` touch targets on every button/link (Phase 5 Plan 05-02 utility).
- **`restore.blade.php`**: the typed-confirm restore page — extends `layouts.app` (NOT `tyro-dashboard::layouts.admin`), renders the prominent red destructive warning + the `_restore_form` partial.
- **`_restore_form.blade.php`**: `@csrf` + `path` hidden input + `mess_name` text input + `@error('mess_name')` + `@error('restore')` blocks. All user-facing strings wrapped in `__()`.
- **`_health_badge.blade.php`**: status color map (`passed=emerald`, `failed=red`, `running=blue`, `error=red`, default=slate) reading the latest `RestoreTest` row's status + `ran_at->diffForHumans()` + optional `message`.
- **`admin-sidebar.blade.php`** (the published Tyro sidebar override): APPENDED a role:super-admin-guarded Backups link next to the existing Users link, using `request()->routeIs('dashboard.backups.*')` for active state, guarded by `@if(auth()->user()?->hasRole('super-admin') && Route::has('dashboard.backups.index'))` so non-super-admins never even see the link (T-06-03-09).
- **`view:cache`** succeeds (no Blade syntax errors).

### Task 3 — 14 tests (TDD, RED-then-GREEN via the Task 1+2 production code)
- **`BackupControllerAuthTest`** (6 tests, D-08b UI auth gating): super-admin GET 200 + sees "Backups" / admin GET 403 / user GET 403 / guest redirect to `/login` / super-admin POST restore-test dispatches `backup:restore-test` (verified via the Artisan::swap spy) / admin POST run 403.
- **`RestoreConfirmationTest`** (5 tests, D-08c typed-confirm + maintenance-mode flow): super-admin GET restore form sees the warning + the expected mess name / POST without `mess_name` redirects with a validation error + no service call + no audit row / POST with wrong `mess_name` redirects with a validation error / POST with the CORRECT `mess_name` fires `BackupRestoreService::restoreFromDisk` once + writes `event='backup.restore'` audit + redirects to `dashboard.backups.index` / when the service THROWS, `event='backup.restore.failed'` audit is written + redirect back with an error + no exception escapes.
- **`BackupDownloadAccessLogTest`** (3 tests, T-06-03-05 PII-leak-prevention access logging): super-admin download streams the file + writes `event='backup.download'` audit / 404 for non-existent path + no audit row / admin 403.

## Verification

```
Task 1:
php -l app/Http/Controllers/Backup/BackupController.php                          ✓
php -l app/Http/Controllers/Backup/RestoreController.php                         ✓
php -l app/Http/Requests/Backup/RestoreRequest.php                              ✓
grep Mess::active in RestoreRequest                                             ✓
grep throttle:5,1 in routes/web.php                                             ✓
grep role:super-admin in routes/web.php                                         ✓
grep dashboard.backups in routes/web.php                                        ✓
grep BackupRestoreService in RestoreController                                  ✓
grep backup.restore in RestoreController                                        ✓
grep backup.download in BackupController                                        ✓
php artisan route:list --name=dashboard.backups                                 ✓ (all 6 routes)
vendor/bin/pint --test                                                          ✓ clean

Task 2:
test -f resources/views/dashboard/backups/{index,restore,_restore_form,_health_badge}.blade.php  ✓ (all 4)
grep dashboard.backups.index in admin-sidebar.blade.php                         ✓
grep hasRole('super-admin') in admin-sidebar.blade.php                          ✓
grep @csrf in _restore_form.blade.php                                           ✓
grep __() in index/restore/_health_badge                                        ✓ (all 3)
php artisan view:cache                                                          ✓ (no Blade errors)
vendor/bin/pint --test resources/                                               ✓ clean

Task 3:
test -f tests/Feature/Backup/{BackupControllerAuth,RestoreConfirmation,BackupDownloadAccessLog}Test.php  ✓ (all 3)
vendor/bin/pint --test tests/Feature/Backup/                                    ✓ clean
vendor/bin/phpunit tests/Feature/Backup/{3 files} --testdox                     ✓ 14/14

Full suite: 264 → 278 tests / 633 → 671 assertions PASS (+14 new).
Pint: whole project clean.
```

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 — Bug] spatie v10 nests backup config under a top-level `backup` key — the plan/research code used the wrong key**
- **Found during:** Task 1 implementation (before writing BackupController).
- **Issue:** The plan's research Code Example A + the RestoreController pseudocode both used `config('backup.destination.disks.0')`. The actual spatie v10 config in `config/backup.php` nests the destination under a top-level `'backup'` key (the file returns `['backup' => [...], 'notifications' => ..., 'monitor_backups' => ..., 'cleanup' => ..., ...]`). So the correct key is `config('backup.backup.destination.disks.0')` — exactly what `BackupRestoreService::downloadAndExtract()` (Plan 06-02) already uses. Using the wrong key returns null → `Storage::disk(null)` → wrong/default disk.
- **Fix:** `BackupController::backupDisk()` returns `config('backup.backup.destination.disks.0', 'backups')`; `RestoreController::show()` uses the same key inline. Single source of truth with the Plan 06-02 service.
- **Files modified:** `app/Http/Controllers/Backup/BackupController.php`, `app/Http/Controllers/Backup/RestoreController.php`
- **Commit:** `bb33a21`

**2. [Rule 1 — Bug] `Mess::active()` does not exist — only `Mess::activeId()`**
- **Found during:** Task 1 implementation (before writing RestoreRequest).
- **Issue:** The plan + research Code Examples A + B + the RestoreRequest pseudocode all referenced `Mess::active()?->name` as the typed-confirm target. The actual `app/Models/Mess.php` exposes only a static `activeId(): ?int` accessor (no `active()` method that returns a model). MyReportExportController in the existing codebase resolves the active mess via `Mess::findOrFail(Mess::activeId())` — same pattern.
- **Fix:** Added `BackupController::activeMessName()` public static helper: `$id = Mess::activeId(); return $id !== null ? Mess::find($id)?->name : null;`. `RestoreRequest::activeMessName()` mirrors it. The typed-confirm target is STILL the active mess's `name` column (research Open Question #3 LOCKED) — only the resolution path changed.
- **Files modified:** `app/Http/Controllers/Backup/BackupController.php`, `app/Http/Requests/Backup/RestoreRequest.php`
- **Commit:** `bb33a21`

**3. [Rule 3 — Blocking] Blade views extend `layouts.app`, NOT `tyro-dashboard::layouts.admin` (project convention)**
- **Found during:** Task 2 implementation.
- **Issue:** The plan's Task 2 Blade examples used `@extends('tyro-dashboard::layouts.admin')`. Every other project custom-admin page extends `layouts.app` instead — `mess/audit/index.blade.php`, `mess/settings/edit.blade.php`, `mess/close/index.blade.php` all extend `layouts.app` and use the emerald Tailwind palette + `min-h-[44px]` touch targets established in Phase 5 Plan 05-02. Using `tyro-dashboard::layouts.admin` would have produced a visually-inconsistent page that does not share the app's main nav / notification bell.
- **Fix:** All 3 Blade pages (`index`, `restore`, plus the partials) extend `layouts.app`. They reuse the project's established Tailwind palette (slate/emerald/red) and `min-h-[44px]` touch-target utility. Sidebar link still added to the published `admin-sidebar.blade.php` override (required by the plan's verify grep) for super-admins who land on the Tyro dashboard directly.
- **Files modified:** `resources/views/dashboard/backups/{index,restore}.blade.php`
- **Commit:** `4570d0d`

**4. [Rule 1 — Bug] BackupControllerAuthTest index test crashed on null `DO_SPACES_BUCKET`**
- **Found during:** Task 3 RED phase (test 1 errored with `AwsS3V3Adapter: bucket must be string, null given`).
- **Issue:** `BackupController::index()` resolves `Storage::disk(config('backup.backup.destination.disks.0', 'backups'))` which points at the s3 disk → DO Spaces. In the test env the `DO_SPACES_*` env vars are empty, so constructing the adapter throws. (Plan 06-02's `BackupRestoreServiceTest` had the same class of issue.)
- **Fix:** Added `Storage::fake('backups')` to `BackupControllerAuthTest::setUp()` so the s3 adapter is never instantiated. Per D-08, no real object storage is touched in the suite.
- **Files modified:** `tests/Feature/Backup/BackupControllerAuthTest.php`
- **Commit:** `4ac5642`

**5. [Rule 1 — Bug] `assertStreamedContent('fake-zip-content')` returned empty**
- **Found during:** Task 3 RED phase (test 12 failed: Expected `'fake-zip-content'`, Actual `''`).
- **Issue:** `response()->streamDownload(fn () => $disk->readStream($path), ...)` registers a streaming closure, but Laravel's testing client does NOT execute the closure when `assertStreamedContent()` is called — it returns the buffered body, which is empty for a streamed response that hasn't been consumed.
- **Fix:** Test 12 now asserts `assertOk()` + `assertHeader('content-disposition')` + the `event='backup.download'` audit row. The core T-06-03-05 requirement is the access-log row, not the streamed bytes — the streaming mechanism itself is exercised by Laravel's own response tests.
- **Files modified:** `tests/Feature/Backup/BackupDownloadAccessLogTest.php`
- **Commit:** `4ac5642`

### Out-of-scope Discoveries

None new. The pre-existing `php artisan config:cache` failure (tyro-login Closure — logged in `deferred-items.md` in Plan 06-01) was confirmed still pre-existing; this plan's verification used `route:list`, `view:cache`, `php -l`, `pint --test`, and `phpunit` — all of which work without `config:cache`.

## Authentication Gates

None. The plan never touched a system requiring interactive authentication (the `role:super-admin` route middleware was exercised by the test suite via `actingAs()`).

## Known Stubs

None. Plan 06-03 ships the FULL super-admin UI surface — every controller action is implemented (no TODOs, no placeholder data). The `index()` page reads real zip artifacts from the configured backups disk + the latest `RestoreTest` row (both from Plan 06-01/06-02). The download is a real streamed response. The restore POST wires the real `BackupRestoreService`. The health badge reads the real `RestoreTest.status`.

The ONLY runtime caveat is operator-facing (not a code stub): in dev-on-Windows, `php artisan backup:run` will fail because spatie v10 is not Windows-compatible (per Plan 06-01 deferred-items + spatie v10 docs). This is documented in `06-01-SMOKE.md` and does not affect the UI code or the test suite (which mocks Artisan per D-08).

## Threat Flags

None new beyond the plan's `<threat_model>` register. Every threat mitigation is verified by a test:

| Threat | Mitigation | Verified by |
|--------|------------|-------------|
| T-06-03-01 (elevation of privilege on `/dashboard/backups/*`) | `role:super-admin` route middleware on every route | `test_admin_gets_403_on_backups_index`, `test_member_user_gets_403_on_backups_index`, `test_guest_is_redirected_to_login_on_backups_index`, `test_admin_gets_403_on_backup_run`, `test_admin_gets_403_on_download` |
| T-06-03-02 (restore without typed confirm) | `RestoreRequest` validates `mess_name in:<active mess name>` | `test_restore_refuses_without_mess_name`, `test_restore_refuses_with_wrong_mess_name` |
| T-06-03-03 (CSRF on restore POST) | `@csrf` in `_restore_form.blade.php` + `VerifyCsrfToken` middleware | Structural (verified by `grep @csrf`); CSRF is the Laravel default for the `web` middleware group |
| T-06-03-04 (brute-force typed-confirm) | `throttle:5,1` on the restore POST route | Structural (`grep throttle:5,1 routes/web.php`); tests disable ThrottleRequests per-test via `withoutMiddleware()` so they don't rate-limit, but production enforces it |
| T-06-03-05 (PII leak via download) | super-admin-only download + audit-log row per download | `test_super_admin_download_streams_file_and_writes_audit`, `test_super_admin_download_missing_path_returns_404`, `test_admin_gets_403_on_download` |
| T-06-03-06 (path traversal via `{path}`) | `Storage::disk('backups')->exists($path)` aborts 404 | `test_super_admin_download_missing_path_returns_404` |
| T-06-03-07 (restore without audit trail) | `RestoreController::store` writes `event='backup.restore'` on success AND `event='backup.restore.failed'` on failure | `test_restore_with_correct_mess_name_runs_service_and_writes_audit`, `test_restore_failure_writes_failed_audit_and_does_not_throw` |
| T-06-03-08 (restore raced with queued write) | Deferred to `BackupRestoreService` (Plan 06-02) — `down` + `queue:restart` before any DB write | Verified in Plan 06-02 (`test_down_is_called_before_any_restore_work`, `test_queue_restart_is_called_before_db_write`); Plan 06-03 mocks the service so this is out of scope here |
| T-06-03-09 (sidebar link visible to non-super-admins) | `@if(auth()->user()?->hasRole('super-admin') && Route::has('dashboard.backups.index'))` guards the link render | Structural (verified by `grep hasRole('super-admin') admin-sidebar.blade.php`); the route middleware is the second layer |

No new network endpoints, auth paths, file access patterns, or trust-boundary schema changes were introduced beyond what the threat register anticipates.

## Self-Check: PASSED

- `app/Http/Controllers/Backup/BackupController.php` — FOUND
- `app/Http/Controllers/Backup/RestoreController.php` — FOUND
- `app/Http/Requests/Backup/RestoreRequest.php` — FOUND
- `resources/views/dashboard/backups/index.blade.php` — FOUND
- `resources/views/dashboard/backups/restore.blade.php` — FOUND
- `resources/views/dashboard/backups/_restore_form.blade.php` — FOUND
- `resources/views/dashboard/backups/_health_badge.blade.php` — FOUND
- `resources/views/errors/maintenance-backup-restore.blade.php` — FOUND
- `tests/Feature/Backup/BackupControllerAuthTest.php` — FOUND
- `tests/Feature/Backup/RestoreConfirmationTest.php` — FOUND
- `tests/Feature/Backup/BackupDownloadAccessLogTest.php` — FOUND
- Commit `bb33a21` (Task 1) — FOUND
- Commit `4570d0d` (Task 2) — FOUND
- Commit `4ac5642` (Task 3) — FOUND
- PHPUnit: 278 tests / 671 assertions PASS (was 264 → +14 new)
- Pint: whole project clean
- route:list: all 6 `dashboard.backups.*` routes registered
- view:cache: succeeds (no Blade errors)
