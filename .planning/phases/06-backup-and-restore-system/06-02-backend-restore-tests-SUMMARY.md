---
phase: 06-backup-and-restore-system
plan: 02
name: backend-restore-tests
subsystem: backup-restore
tags: [backup, restore, restore-test, schedule, listeners, tdd, d-08-mocking]
requires:
  - "Plan 06-01 foundation (config/backup.php, backups disk, mysql_restore_test connection)"
provides:
  - "app/Support/BackupPathResolver — Finder-based recursive db-dumps locator (Pitfall 1)"
  - "app/Services/BackupRestoreService — bespoke full-restore orchestration (down → queue:restart → restore → finally up)"
  - "app/Services/RestoreTestService — scratch-DB load + per-table COUNT(*) comparison"
  - "app/Models/RestoreTest + factory + migration — restore_tests row drives the UI health badge"
  - "app/Console/Commands/RestoreTestRun — backup:restore-test artisan command"
  - "app/Listeners/NotifyOnBackupFailure — spatie BackupHasFailed + UnhealthyBackupWasFound → NotificationService"
  - "CloseMonthJob after() + failed() lifecycle hooks (research Pattern 6a, post-close backup)"
  - "AppServiceProvider::registerBackupFailureListeners() — class_exists-guarded Event::listen wiring"
  - "routes/console.php — nightly backup:clean/run/monitor + backup:restore-test schedule"
  - "NotificationType::BACKUP_FAILED constant (free-string type system; no enum migration needed)"
affects:
  - "tests/Feature/Mess/NotificationTest.php (NotificationType::ALL assertion updated for new constant)"
tech-stack:
  added:
    - "Symfony Finder (already in Laravel core) for recursive db-dumps glob — replaces broken `**` glob()"
  patterns:
    - "Mockery partial-mock + shouldAllowMockingProtectedMethods() for D-08 service mocking"
    - "Artisan::swap() on the ConsoleKernel contract — bypasses facade caching so call() spies record"
    - "Spatie\\Backup\\Notifications\\EventHandler::disable()/enable() to silence spatie's own mail path during tests"
    - "Public test seam (buildMysqlProcess) so the suite can inspect Process args without shelling out"
    - "Protected seams (locateSqlDump / restoreDatabase / restoreFiles / downloadAndExtract / verifyRestore / cleanup) for partial-mock mocking"
    - "Symfony Process with ARRAY args (NOT escapeshellarg string concat) — research Pattern 4a"
decisions:
  - "Used research Pattern 6a (CloseMonthJob::after() lifecycle hook) — no DispatchBackupAfterClose listener file is created."
  - "NotificationType is a class-of-constants (NOT an enum). Added BACKUP_FAILED = 'backup_failed' as a new constant — no enum migration needed."
  - "Switched BackupPathResolver from PHP glob() to Symfony Finder because glob() does NOT walk `**` recursively (Issue #1389 fallback was a trap)."
  - "Added locateSqlDump() as a protected seam on BOTH services (delegating to BackupPathResolver) so the suite can mock the resolver's filesystem walk via shouldAllowMockingProtectedMethods()."
  - "Used Artisan::swap() (NOT Artisan::fake() which does not exist in this Laravel version) to install a Mockery ConsoleKernel spy that records call() invocations."
  - "spatie's own EventHandler mail path is silenced during SpatieFailureNotificationListenerTest via the package's built-in EventHandler::disable() / enable() toggle — cleaner than mocking the disk."
key-files:
  created:
    - "app/Support/BackupPathResolver.php"
    - "app/Services/BackupRestoreService.php"
    - "app/Services/RestoreTestService.php"
    - "app/Models/RestoreTest.php"
    - "database/factories/RestoreTestFactory.php"
    - "database/migrations/2026_06_19_000001_create_restore_tests_table.php"
    - "app/Console/Commands/RestoreTestRun.php"
    - "app/Listeners/NotifyOnBackupFailure.php"
    - "tests/Feature/Backup/.gitkeep"
    - "tests/Feature/Backup/BackupPathResolverTest.php"
    - "tests/Feature/Backup/BackupRestoreServiceTest.php"
    - "tests/Feature/Backup/RestoreTestServiceTest.php"
    - "tests/Feature/Backup/PostCloseBackupListenerTest.php"
    - "tests/Feature/Backup/SpatieFailureNotificationListenerTest.php"
    - "tests/Feature/Backup/ScheduledBackupCommandsTest.php"
  modified:
    - "app/Jobs/CloseMonthJob.php"
    - "app/Providers/AppServiceProvider.php"
    - "app/Support/NotificationType.php"
    - "routes/console.php"
    - "tests/Feature/Mess/NotificationTest.php"
metrics:
  duration: ~45 min
  tasks_completed: 2
  files_changed: 20
  tests_added: 21
  completed: 2026-06-19
---

# Phase 6 Plan 06-02: Backend Restore Services + Restore-Test + Schedule + Listeners Summary

**One-liner:** Built the bespoke backend for Phase 6 — the destructive `BackupRestoreService` (down → queue:restart → Symfony-Process mysql restore → storage_path('app/public') file copy → always-up finally), the `RestoreTestService` (per-table COUNT(*) parity via `DB::connection('mysql_restore_test')` against 17 hard-coded domain tables), the `BackupPathResolver` (Finder-based Issue #1389 recursive `db-dumps/*.sql` locator), the `restore_tests` migration/model/factory (no mess_id — cross-mess infrastructure), the post-`CloseMonthJob::after()` backup hook (Pattern 6a — no listener file), the `NotifyOnBackupFailure` listener wired in `AppServiceProvider` (class_exists-guarded Event::listen), the `backup:restore-test` artisan command, and the nightly `routes/console.php` schedule (01:00 clean + 01:30 run + 02:00 monitor + 03:00 restore-test, all onOneServer, long-running ones withoutOverlapping). 21 new tests via strict TDD RED-then-GREEN; every Process/Artisan/DB-restore call mocked per D-08.

## What Shipped

### Task 1 — BackupRestoreService + RestoreTestService + BackupPathResolver + RestoreTest migration/model/factory (14 tests)
- **BackupPathResolver** (`app/Support/BackupPathResolver.php`): uses **Symfony Finder** (NOT `glob()`) because PHP's `glob()` does NOT walk `**` recursively — the Issue #1389 fallback glob would silently return `[]` on nested layouts. Finder with `->path('db-dumps')` catches both the flat legacy layout AND the nested v8+ layout. Throws `RuntimeException` on zero OR >1 matches (T-06-02-04).
- **BackupRestoreService** (`app/Services/BackupRestoreService.php`): exact research Pattern 4 sequence — `Artisan::call('down')` → `Artisan::call('queue:restart')` → `try { downloadAndExtract, locateSqlDump, restoreDatabase, restoreFiles, verifyRestore }` → `finally { Artisan::call('up'); cleanup }`. The crown-jewel guarantee (`test_up_is_called_in_finally_even_on_exception`) pins that the app ALWAYS returns to live even on mid-restore exception (T-06-02-01). `restoreDatabase` uses **Symfony Process with ARRAY args** (NOT `escapeshellarg` string concat) per Pattern 4a. `restoreFiles` copies into `storage_path('app/public')`, NEVER `public_path('storage')` (Pitfall 4 — symlink). **Public `buildMysqlProcess()` test seam** lets the suite inspect the Process without ever shelling out.
- **RestoreTestService** (`app/Services/RestoreTestService.php`): per-table `SELECT COUNT(*)` on the live `mysql` connection vs. `DB::connection('mysql_restore_test')`. 17 hard-coded domain table names (`users, messes, settings, members, meal_entries, meal_off_requests, guest_meals, expense_categories, expenses, payments, monthly_closings, monthly_member_summaries, monthly_corrections, advance_balances, notifications, member_invitations, audits`) — NOT `information_schema.TABLES.TABLE_ROWS` (InnoDB estimate — Pitfall). `runLatest()` persists a `RestoreTest` row with status='passed'/'failed'/'error' + per-table counts JSON + a divergence summary message.
- **restore_tests migration**: `status` (varchar32), `per_table_counts` (json), `message` (text nullable), `ran_at` (timestamp), indexes on `ran_at` + `status`. **NO mess_id** — restore-tests are cross-mess infrastructure.
- **RestoreTest model** + factory: `#[Fillable([...])]`, `casts()` for `per_table_counts → array` + `ran_at → datetime`, `HasFactory`. Mirror of `Notification.php` style but WITHOUT `BelongsToActiveMess`.
- **14 tests GREEN** (4 BackupPathResolver edge cases + 6 BackupRestoreService sequence guarantees + 4 RestoreTestService parity logic).

### Task 2 — Post-close hook + spatie failure listener + nightly schedule (7 tests)
- **CloseMonthJob** `after()` + `failed()` hooks (research Pattern 6a — preferred over a listener file; no `DispatchBackupAfterClose` listener is created). `after()` calls `Artisan::call('backup:run', ['--only-db' => true])` in `try/catch` (T-06-02-07 — a backup failure can NEVER break the close path because the close already succeeded). `failed()` is an explicit no-op (no backup of a half-closed state).
- **NotifyOnBackupFailure** listener: handles `BackupHasFailed|UnhealthyBackupWasFound` union → `NotificationService::broadcastToManagers(NotificationType::BACKUP_FAILED, [...])`. Uses the spatie v10 event signature (`$event->diskName` is a nullable string on both event classes — NOT a `BackupDestination` object as the research assumed; the listener was adapted to that real signature).
- **NotificationType::BACKUP_FAILED** constant added to the existing class-of-constants (no enum migration needed — `$type` is a free string on `broadcastToManagers()`). `ALL` + `LABELS` arrays extended; the existing `tests/Feature/Mess/NotificationTest::test_notification_type_constants_are_complete` was updated to assert 5 types instead of 4.
- **AppServiceProvider::boot()** registers the two `Event::listen` calls via a new `registerBackupFailureListeners()` private method, `class_exists`-guarded against missing spatie (mirrors the `telescope:prune` pattern in `routes/console.php`). NO second `boot()` method.
- **RestoreTestRun** artisan command (`backup:restore-test`): wraps `RestoreTestService::runLatest()`, prints PASSED/FAILED/errored with the right exit code.
- **routes/console.php** appended (telescope block untouched): `backup:clean` (01:00) + `backup:run` (01:30, withoutOverlapping) + `backup:monitor` (02:00) under `class_exists(BackupServiceProvider::class)`; `backup:restore-test` (03:00, withoutOverlapping) under `class_exists(RestoreTestRun::class)`. All onOneServer. `schedule:list` confirms all 4 commands scheduled at the right times.
- **7 tests GREEN** (3 PostClose + 2 SpatieFailure + 2 Schedule).

## Verification

```
Task 1:
php -l app/Support/BackupPathResolver.php                          ✓
php -l app/Services/BackupRestoreService.php                       ✓
php -l app/Services/RestoreTestService.php                         ✓
php -l app/Models/RestoreTest.php                                  ✓
grep restore_tests in migration                                    ✓
grep per_table_counts in migration                                 ✓
grep db-dumps in BackupPathResolver                                ✓
grep Artisan::call('down'/'queue:restart'/'up') in service         ✓ (all 3)
grep storage_path('app/public') in service                         ✓
grep DB::connection('mysql_restore_test') in RestoreTestService    ✓
grep compareCounts in RestoreTestService                           ✓
php artisan migrate --pretend | grep restore_tests                 ✓ (table + 2 indexes)

Task 2:
php -l app/Listeners/NotifyOnBackupFailure.php                     ✓
php -l app/Console/Commands/RestoreTestRun.php                     ✓
grep public function after/failed + backup:run in CloseMonthJob    ✓ (all 3)
grep BackupHasFailed + UnhealthyBackupWasFound in AppServiceProvider ✓ (both)
grep backup:clean/run/monitor/restore-test in routes/console.php   ✓ (all 4)
php artisan schedule:list | grep backup:run                        ✓ (01:30 daily)
php artisan schedule:list | grep backup:restore-test               ✓ (03:00 daily)

Full suite: 264 tests / 633 assertions PASS (was 243 → +21 new; +1 NotificationType::ALL assertion).
Pint: app/ + tests/ clean.
```

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 — Bug] PHP `glob()` does NOT walk `**` recursively**
- **Found during:** Task 1 RED phase (BackupPathResolver tests failed on nested layout).
- **Issue:** The plan's research Pattern (and the original resolver design) used `$this->files->glob($root.'/**/db-dumps/*.sql')` to match Issue #1389's nested layout. PHP's native `glob()` (and Laravel's `Filesystem::glob` which just calls it) does NOT support `**` recursion — it treats `**` as a single-level `*`, silently returning `[]` for any nested path. The fallback flat-layout glob would then return 1 match and the service would fail to find a nested dump.
- **Fix:** Switched the resolver to **Symfony Finder** (`Finder::create()->files()->name('*.sql')->in($root)->path('db-dumps')`) which actually walks subdirectories. The plan's verify grep (`grep -q "db-dumps" app/Support/BackupPathResolver.php`) still passes — the word "db-dumps" is present in both the source comment and the Finder path filter.
- **Files modified:** `app/Support/BackupPathResolver.php`
- **Commit:** `602cc1b`

**2. [Rule 1 — Bug] spatie v10 `BackupHasFailed` / `UnhealthyBackupWasFound` event signatures differ from the plan's research**
- **Found during:** Task 2 implementation.
- **Issue:** The research Pattern 7 listener used `$event->backupDestination?->diskName()` — a `BackupDestination` object. The actual spatie v10 events are flatter: `BackupHasFailed` has `$exception, ?string $diskName, ?string $backupName`; `UnhealthyBackupWasFound` has `string $diskName, string $backupName, Collection $failureMessages`. There is NO `backupDestination` object on either event.
- **Fix:** Listener uses `$event->diskName ?? 'unknown disk'` directly (works for both event classes since `$diskName` is a string on both — nullable on `BackupHasFailed`, required on `UnhealthyBackupWasFound`). Spatie v10 source was the source of truth.
- **Files modified:** `app/Listeners/NotifyOnBackupFailure.php`
- **Commit:** `ab6dd3b`

**3. [Rule 3 — Blocking] `Artisan::fake()` does not exist in this Laravel version**
- **Found during:** Task 1 RED phase (BackupRestoreService tests errored with `Call to undefined method Illuminate\Foundation\Console\Kernel::fake()`).
- **Issue:** The plan's Test 1/2 referred to `Artisan::fake(['backup:run' => 0])` — but Laravel 13's Artisan facade has no `::fake()` method.
- **Fix:** Replaced with `Artisan::swap($spy)` where `$spy` is a `Mockery::mock(Kernel::class)` whose `call()` is intercepted to record the command name. `Artisan::swap()` replaces BOTH the container binding AND the facade's `resolvedInstance` cache (the latter was the gotcha — `$this->app->instance()` alone did NOT intercept the cached facade root, as I verified in a tinker experiment). Documented in the test header comment so a future maintainer understands the pattern.
- **Files modified:** `tests/Feature/Backup/BackupRestoreServiceTest.php`, `tests/Feature/Backup/PostCloseBackupListenerTest.php`
- **Commit:** `602cc1b`, `ab6dd3b`

**4. [Rule 3 — Blocking] spatie's own `EventHandler` listener crashes the test env (needs the unconfigured `backups` s3 disk)**
- **Found during:** Task 2 RED phase (SpatieFailureNotificationListenerTest errored with `AwsS3V3Adapter: bucket must be string, null given`).
- **Issue:** When `Event::dispatch(new UnhealthyBackupWasFound(...))` fires, spatie's OWN `EventHandler` (registered via its service provider) ALSO catches the event and tries to send the `UnhealthyBackupWasFoundNotification` mail. That notification path constructs a `BackupDestination` from the configured disk — which fails in the test env because `DO_SPACES_*` env vars are empty.
- **Fix:** Discovered spatie ships a `EventHandler::disable()` static toggle (verified by reading `vendor/spatie/laravel-backup/src/Notifications/EventHandler.php`). The test calls `EventHandler::disable()` in setup and `EventHandler::enable()` in tearDown so the disabling doesn't leak across the suite. The point of the test (that OUR `NotifyOnBackupFailure` listener fires) is independent of spatie's mail path.
- **Files modified:** `tests/Feature/Backup/SpatieFailureNotificationListenerTest.php`
- **Commit:** `ab6dd3b`

**5. [Rule 1 — Bug] `Mockery::mock(Class::class)->makePartial()` does not initialize typed properties**
- **Found during:** Task 1 GREEN phase.
- **Issue:** `Mockery::mock(BackupRestoreService::class)->makePartial()` skips the constructor, leaving the typed `$resolver` property uninitialized → `Error: Typed property must not be accessed before initialization` when the service tried to call `$this->resolver->locateSqlDump(...)`.
- **Fix:** Two-pronged: (a) Test 10 passes the constructor args to the mock via `Mockery::mock(Class::class, [new Filesystem(), new BackupPathResolver(new Filesystem())])`; (b) added `locateSqlDump()` as a protected seam on BOTH services (delegating to `$this->resolver`) so the suite can `shouldReceive('locateSqlDump')` and bypass the resolver entirely.
- **Files modified:** `app/Services/BackupRestoreService.php`, `app/Services/RestoreTestService.php`, `tests/Feature/Backup/BackupRestoreServiceTest.php`, `tests/Feature/Backup/RestoreTestServiceTest.php`
- **Commit:** `602cc1b`

### Out-of-scope Discoveries

**None.** No new out-of-scope items were discovered. The pre-existing `config:cache` failure (tyro-login Closure, logged to `deferred-items.md` in Plan 06-01) was confirmed still pre-existing — `php artisan schedule:list` and `php artisan migrate --pretend` work fine without it.

## Authentication Gates

None. The plan never touched a system requiring authentication.

## Known Stubs

None. Plan 06-02 ships the full service layer + tests only — there is NO UI in this plan (no Blade views, no routes beyond the console schedule, no controllers). Plan 06-03 owns the super-admin controller + Blade view that surfaces this service layer; the plan's objective is explicit: "Plan 06-03 only adds the super-admin controller + Blade view that surfaces this service layer; it must not contain any restore logic itself."

The `verifyRestore()` method in `BackupRestoreService` is a lightweight 3-table spot-check (`members`, `monthly_closings`, `audits` — COUNT > 0) that is intentionally minimal; the FULL per-table parity check lives in `RestoreTestService::compareCounts()` and is not duplicated here. This is documented in the code comments.

## Threat Flags

None new beyond the plan's `<threat_model>` register. Every threat mitigation is verified by a test:

| Threat | Mitigation | Verified by |
|--------|------------|-------------|
| T-06-02-01 (mid-flight restore race / data loss) | `down` + `queue:restart` before any DB write; always-`up` finally | `test_down_is_called_before_any_restore_work`, `test_queue_restart_is_called_before_db_write`, `test_up_is_called_in_finally_even_on_exception`, `test_exception_propagates_after_up_has_run` |
| T-06-02-02 (real mysqldump in test suite) | Every Process + Artisan call mocked via Mockery + protected seams | All 21 tests run in ~3s, no subprocess |
| T-06-02-03 (file restore follows symlink) | `restoreFiles` writes to `storage_path('app/public')`, NEVER `public_path('storage')` | `test_restore_files_writes_into_storage_app_public_never_public_storage` |
| T-06-02-04 (path resolver picks wrong .sql) | BackupPathResolver throws on 0 OR >1 matches | `test_locate_sql_dump_throws_when_no_dump_present`, `test_locate_sql_dump_throws_when_multiple_dumps_present` |
| T-06-02-05 (scratch DB leaks PII) | `mysql_restore_test` is on the SAME MySQL host, wiped every run via `wipeScratchDb()` | Accepted per threat register; not test-asserted (no real scratch DB exists in the test env — counts are mocked) |
| T-06-02-07 (post-close backup fails → close breaks) | `CloseMonthJob::after()` wraps `Artisan::call('backup:run')` in try/catch | `test_after_hook_does_not_propagate_backup_failures` |
| T-06-02-08 (restore without audit trail) | DEFERRED to Plan 06-03 — the audit-log row is written by the RestoreController, not the service (the service trusts its caller) | Out of scope for 06-02 (no UI in this plan) |

No new network endpoints, auth paths, file access patterns, or trust-boundary schema changes were introduced by this plan beyond what the threat register anticipates.

## Self-Check: PASSED

- `app/Support/BackupPathResolver.php` — FOUND
- `app/Services/BackupRestoreService.php` — FOUND
- `app/Services/RestoreTestService.php` — FOUND
- `app/Models/RestoreTest.php` — FOUND
- `database/factories/RestoreTestFactory.php` — FOUND
- `database/migrations/2026_06_19_000001_create_restore_tests_table.php` — FOUND
- `app/Console/Commands/RestoreTestRun.php` — FOUND
- `app/Listeners/NotifyOnBackupFailure.php` — FOUND
- `tests/Feature/Backup/BackupPathResolverTest.php` — FOUND
- `tests/Feature/Backup/BackupRestoreServiceTest.php` — FOUND
- `tests/Feature/Backup/RestoreTestServiceTest.php` — FOUND
- `tests/Feature/Backup/PostCloseBackupListenerTest.php` — FOUND
- `tests/Feature/Backup/SpatieFailureNotificationListenerTest.php` — FOUND
- `tests/Feature/Backup/ScheduledBackupCommandsTest.php` — FOUND
- Commit `8f5f897` (RED Task 1) — FOUND
- Commit `602cc1b` (GREEN Task 1) — FOUND
- Commit `31f4a02` (RED Task 2) — FOUND
- Commit `ab6dd3b` (GREEN Task 2) — FOUND
- PHPUnit: 264 tests / 633 assertions PASS (was 243 → +21 new)
- Pint: app/ + tests/ clean
- schedule:list: 4 backup commands at 01:00/01:30/02:00/03:00
- migrate --pretend: restore_tests table + 2 indexes listed
