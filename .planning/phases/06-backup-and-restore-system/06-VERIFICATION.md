---
phase: 06-backup-and-restore-system
verified: 2026-06-19T00:00:00Z
status: passed
score: 8/8 must-haves verified
overrides_applied: 0
re_verification:
  previous_status: gaps_found
  previous_score: 4/8
  reverified: 2026-06-19T00:00:00Z
  gaps_closed: [CR-01, CR-02, CR-03]
  gaps_remaining: []
  regressions: []
  fix_commits: [65cf87a, 26826fb, 5a370bd]
gaps:
  - truth: "BackupRestoreService can unzip a spatie backup, locate the SQL dump via glob, restore it via the mysql CLI under maintenance mode, and restore files to storage/app/public/"
    status: failed
    severity: critical
    reason: "CR-01: file restore is silently broken. spatie v10 (vendor/spatie/laravel-backup/src/Tasks/Backup/Zip.php lines 36-66) strips the relative_path prefix (storage/app/public) when zipping, so backed-up files extract to the zip ROOT, not under storage/app/public/. BackupRestoreService::restoreFiles() hardcodes the wrong source path ($workDir.'/storage/app/public' — nonexistent), Filesystem::copyDirectory() on a missing source is a silent no-op, and verifyRestore() only checks DB row counts (members/monthly_closings/audits). Net effect: every 'successful' restore overwrites the live DB + writes an audit row + brings the app up, but SILENTLY LOSES EVERY uploaded file (profile photos + bazar receipts). Directly defeats the phase goal 'never loses the mess's financial history' and decision D-07."
    artifacts:
      - path: "app/Services/BackupRestoreService.php"
        issue: "Lines 137-138: `$sourceDir = $workDir.'/storage/app/public'` — this directory does not exist after extraction. Lines 156-166 verifyRestore() only checks DB tables, not files. Line 136 comment ('copyDirectory is a no-op — harmless') is wrong: it is the entire file-restore surface failing silently."
    evidence:
      - "vendor/spatie/laravel-backup/src/Tasks/Backup/Zip.php:36-66 (relative_path prefix stripped on zipping)"
      - "config/backup.php:68 ('relative_path' => storage_path('app/public'))"
      - "app/Services/BackupRestoreService.php:131-147 (restoreFiles hardcoded source dir)"
      - "app/Services/BackupRestoreService.php:156-166 (verifyRestore DB-only spot-check)"
      - "tests/Feature/Backup/BackupRestoreServiceTest.php (restoreFiles is mocked — never exercises the real path; the suite is green while the bug is present)"
    missing:
      - "Locate the backed-up file tree by content (mirror spatie's relative-path strip — files live at $workDir/, not $workDir/storage/app/public/) OR fall back across both layouts. Pseudocode in 06-REVIEW.md CR-01 fix."
      - "Add a real (non-mocked) integration test: create a tiny zip with spatie's actual layout, extract, assert files land in storage_path('app/public')."
      - "Extend verifyRestore() to assert file count > 0 in storage/app/public after restore (currently DB-only)."

  - truth: "Post-close listener fires backup:run on successful CloseMonthJob completion and does NOT fire on failure"
    status: failed
    severity: critical
    reason: "CR-02: post-close backup is dead code. Laravel's queue runtime invokes only handle() + failed() (the failed() hook on exception); there is NO `after()` job lifecycle hook. CloseMonthJob::after() (app/Jobs/CloseMonthJob.php:47-54) is therefore never invoked by the worker. The D-05 post-close backup (capture the highest-value immutable snapshot immediately after a successful close) does not fire at runtime. The green test (PostCloseBackupListenerTest) calls $job->after() DIRECTLY (lines 80, 128), bypassing the queue runtime entirely — classic 'tests pass, goal unmet' defect."
    artifacts:
      - path: "app/Jobs/CloseMonthJob.php"
        issue: "Lines 47-54: public function after() — dead code. The queue runtime (vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php + Jobs/Job.php) never invokes after(); only handle() and failed()."
      - path: "tests/Feature/Backup/PostCloseBackupListenerTest.php"
        issue: "Lines 80, 95, 128: invokes $job->after() / $job->failed() directly. Does not dispatch the job through the queue runtime — the test green result proves nothing about runtime behavior."
    evidence:
      - "app/Jobs/CloseMonthJob.php:47-54 (the after() method)"
      - "grep of vendor/laravel/framework/src/Illuminate/Queue/* shows no `after()` invocation (only failed() and middleware())"
      - "tests/Feature/Backup/PostCloseBackupListenerTest.php:80, 128 (direct method call, not runtime dispatch)"
      - "06-REVIEW.md CR-02 (the orchestrator independently confirmed this)"
    missing:
      - "Move the post-close backup invocation into handle() (after $service->close() returns without throwing) OR register an Eloquent event listener on eloquent.created: App\\Models\\MonthlyClosing (research Pattern 6b, the rejected alternative that actually works)."
      - "Remove the dead after() method either way."
      - "Add a real test: dispatch CloseMonthJob through Bus::dispatchSync (or Queue::fake) and assert Artisan::assertCalled('backup:run')."

  - truth: "Every restore (success OR failure) writes a readable audit log row with event='backup.restore'"
    status: failed
    severity: critical
    reason: "CR-03: audit new_values is double-JSON-encoded, corrupting the payload. OwenIt\\Auditing\\Models\\Audit casts new_values to 'json' (vendor/owen-it/laravel-auditing/src/Models/Audit.php:39-43), but both BackupController::writeAudit() (line 104) and RestoreController::writeAudit() (line 96) pass `json_encode($payload)` — a pre-encoded string. The cast re-encodes on save, producing JSON-of-JSON. On read-back, the cast json_decodes once and returns the inner JSON STRING; owen-it's Audit::resolveData() iterates the string char-by-char. The audit row exists (so T-06-03-07 'tamper-evident trail' is technically satisfied — the test only asserts the row exists), but the payload is corrupt/unreadable from the Audit UI. Defeats the audit's forensic value."
    artifacts:
      - path: "app/Http/Controllers/Backup/BackupController.php"
        issue: "Line 104: `'new_values' => json_encode($payload) ?: null` — double-encodes against the Audit model's 'json' cast."
      - path: "app/Http/Controllers/Backup/RestoreController.php"
        issue: "Line 96: same double-encoding bug."
    evidence:
      - "vendor/owen-it/laravel-auditing/src/Models/Audit.php:39-43 (`protected $casts = ['old_values' => 'json', 'new_values' => 'json']`)"
      - "app/Http/Controllers/Backup/BackupController.php:104 (json_encode($payload) passed to a json-cast attribute)"
      - "app/Http/Controllers/Backup/RestoreController.php:96 (same bug)"
      - "tests/Feature/Backup/RestoreConfirmationTest.php + BackupDownloadAccessLogTest.php (assert only Audit row existence, not that new_values is a readable array — assertion at line ~'assertNotNull($audit)' + 'assertStringContainsString($path, $audit->new_values)' works only because the string-cast JSON contains the path as a substring)"
    missing:
      - "Pass the raw $payload array (NOT json_encode($payload)) into Audit->fill(['new_values' => $payload, ...]). The cast handles encoding."
      - "Add an assertion in the existing restore/download tests: `$audit->new_values['path'] === $validPath` (array index access — currently impossible because the value is a JSON string)."
    affected_decisions: ["T-06-03-07 (repudiation mitigation)", "T-06-03-05 (PII-leak-prevention audit trail)"]

deferred: []

human_verification:
  - test: "Provision a real DigitalOcean Spaces bucket + key/secret and run `php artisan backup:run --only-db` then `php artisan backup:run` (full) on a Linux VPS, confirming the zip lands in object storage."
    expected: "A zip appears in the DO Spaces bucket; `php artisan backup:list` lists it; `php artisan backup:monitor` health checks pass (max age 1d, max size 5000MB)."
    why_human: "Dev-on-Windows cannot run spatie v10 backup:run (incompatible), and dev has no real DO Spaces credentials. Plan 06-01 deferred this to operator steps."

  - test: "Run `php artisan backup:restore-test` against a live scratch MySQL DB (DB_RESTORE_TEST_DATABASE) and confirm a RestoreTest row with status='passed' is written."
    expected: "A restore_tests row with per_table_counts matching across all 17 domain tables; the UI health badge turns emerald ('Restore-test: Passed')."
    why_human: "Requires a real MySQL scratch DB + a real backup zip produced by spatie. Dev Windows cannot produce a real backup (spatie Windows-incompat). The test suite mocks countOnConnection() so the parity logic is exercised but the real DB-load + COUNT path is not."

  - test: "Configure SMTP (MAIL_MAILER=smtp + MAIL_HOST/PORT/USERNAME/PASSWORD) and trigger a backup failure (e.g. wrong DO_SPACES_SECRET) to confirm the failure email actually sends."
    expected: "spatie emits BackupHasFailed; the app's NotifyOnBackupFailure listener writes an in-app Notification row (manager bell); spatie's mail channel sends the failure email to BACKUP_NOTIFICATION_EMAIL."
    why_human: "Requires real SMTP + a forced failure. The test suite mocks the service layer; the real failure-notification delivery path is not exercised."

  - test: "Type-the-mess-name typed-confirm: log in as super-admin, click Restore on a real backup, type the active mess name EXACTLY, confirm the restore actually runs (app enters maintenance mode, DB is restored, app returns to live)."
    expected: "App shows the maintenance page during restore; on completion the app is back up; an audit row event='backup.restore' is written; the live DB matches the restored backup."
    why_human: "End-to-end destructive flow on real data — must NOT be exercised in CI. The test suite mocks BackupRestoreService::restoreFromDisk, so the actual destructive sequence is not exercised."

  - test: "Once CR-01 is fixed, perform a REAL end-to-end restore and confirm profile photos + bazar receipts land under storage/app/public/ after the restore."
    expected: "All backed-up files (storage/app/public/photos/*, storage/app/public/receipts/*) exist on disk post-restore with matching byte counts."
    why_human: "The file-restore path is mocked in the suite. Even after CR-01 fix, the spatie zip layout + extraction must be verified against a real backup on the prod VPS."

  - test: "Confirm the `down` -> `queue:restart` -> restore -> `up` ordering under live supervisor."
    expected: "During a restore: web requests 503 with the maintenance-backup-restore page; the queue worker dies + does NOT pick up CloseMonthJob; on completion the app returns 200."
    why_human: "Requires the supervisor-managed queue worker running on the prod VPS. The test suite mocks Artisan calls so the real queue-restart + supervisor behavior is not exercised."
---

# Phase 6: Backup and Restore System — Verification Report

**Phase Goal:** A working backup + restore capability for the single-mess VPS deployment, so that a server loss, bad migration, or accidental/corrupt month-close never loses the mess's financial history. Backs up the MySQL DB + uploaded files to off-server S3-compatible object storage on a schedule, exposes a super-admin UI for safe operations plus a guarded full restore, runs a periodic restore-test that proves backups actually restore, and ships a restore runbook in DEPLOYMENT.md.

**Verified:** 2026-06-19
**Status:** passed (re-verified 2026-06-19 after gap closure)
**Re-verification:** Yes — see "Re-Verification After Gap Closure" below

## Re-Verification After Gap Closure (2026-06-19)

The 3 Critical gaps from the initial verification (CR-01, CR-02, CR-03) + 5 of the warnings (WR-01/03/05/06/07) were fixed inline and verified by NEW non-mocked / non-vacuous tests — the original 278-green suite had mocked exactly the seams that hid these bugs, so the new tests deliberately exercise the real code paths. Full suite: **279 tests / 683 assertions green**.

| Gap | Fix | Verifying test (non-vacuous) | Commit |
|-----|-----|------------------------------|--------|
| CR-01 silent file-loss on restore | `BackupRestoreService::restoreFiles()` now copies from the extracted work-dir ROOT (skipping `db-dumps/`) into `storage/app/public`; `verifyFilesRestored()` asserts the copied count matches the extracted tree (silent-no-op guard). | `test_restore_files_copies_extracted_root_into_storage_app_public` (real Filesystem against a spatie-shaped tree; asserts files land + db-dumps excluded) + `test_verify_restore_throws_when_files_were_silently_skipped` (guard fires on a no-op). | 26826fb |
| CR-02 dead post-close backup | Backup call moved into `CloseMonthJob::handle()` after `close()` succeeds (best-effort try/catch); dead `after()`/`failed()` removed. | `test_handle_fires_backup_run_on_successful_close` + `test_handle_does_not_fire_backup_run_when_close_throws` + `test_handle_swallows_backup_failures` — exercise the REAL `handle()`, not a dead hook. | 65cf87a |
| CR-03 audit double-encode | Both `writeAudit()` methods pass the raw `$payload` array (the Audit `json` cast encodes exactly once). | `BackupDownloadAccessLogTest` + `RestoreConfirmationTest` now assert `$audit->new_values['path']` / `['error']` (array access) — would fail if double-encoded. | 5a370bd |

Warnings also addressed: **WR-01** (stream the zip to disk via `readStream` + `stream_copy_to_stream` — no whole-file OOM), **WR-03** (pipe the dump to mysql via STDIN instead of `SOURCE <path>` — handles paths with spaces), **WR-05** (explicit `..`/absolute path-traversal guard on download + restore), **WR-06** (`getMessage()` not `(string) $exception` — no stack-trace leak into the notification), **WR-07** (restore-test schedule guarded on spatie, not the always-present `RestoreTestRun` class). **WR-02** (no rollback mid-restore) accepted as inherent to a destructive restore — the nightly restore-test is the real safety net that catches a broken restore before a real disaster. **WR-04** (deprecated `getDoctrineSchemaManager`) left — functional, low priority.

**Pre-existing flakiness (NOT a Phase 6 regression):** the full suite intermittently crashes with "Premature end of PHP process" in the Phase 4 Dompdf / phpspreadsheet export tests under the default `memory_limit`. Those tests pass standalone and the full suite passes with `php -d memory_limit=1024M vendor/bin/phpunit`. No backup or report code was touched by the fix.

**Status change:** the 3 code-level Criticals that independently failed the phase goal are resolved and proven by non-mocked tests. The 6 human-verification items below still stand (disaster-recovery surfaces cannot be fully exercised on dev-Windows) — they confirm prod-runtime behavior, not code correctness.

## Goal Achievement

### Observable Truths (D-01..D-08 mapped to must-haves)

| # | Truth (Decision) | Status | Evidence |
|---|------------------|--------|----------|
| 1 | D-01: spatie/laravel-backup is installed + config/backup.php declares source (DB + storage/app/public), DO Spaces destination, retention ladder, notifications | ✓ VERIFIED | `composer.json` requires `spatie/laravel-backup:^10.0` + `league/flysystem-aws-s3-v3:^3.0`; `config/backup.php` ships with keep_daily=14, keep_monthly=12, 5000MB cap, exclude `.env` (line 46), follow_links=false (line 57), AES-256 optional. Verified spatie 10.3.0 installed. |
| 2 | D-02: backups S3 disk (DO Spaces via custom endpoint) is wired; off-server destination | ✓ VERIFIED | `config/filesystems.php:66` declares `'backups' =>` s3 disk with DO_SPACES_* env keys + `throw=true`. `.env.example` has all 5 DO_SPACES_* keys. Operator provisioning is a human-verification item (dev Windows has no real creds). |
| 3 | D-03: super-admin Backups UI with role gate + typed-confirm + maintenance-mode + audit | ✓ VERIFIED (structure) | `routes/web.php:50-63` registers `/dashboard/backups` group with `role:super-admin` + `throttle:5,1` on restore POST. `BackupController` + `RestoreController` + `RestoreRequest` (mess_name `in:<active name>` rule) + 4 Blade views + role-guarded sidebar link all present. Auth-gate + typed-confirm + maintenance-mode tests pass. **CAVEAT: the audit payload written on restore/download is corrupt — see CR-03.** |
| 4 | D-04: periodic restore-test compares per-table COUNT(*) between live + scratch DB | ✓ VERIFIED (structure) | `RestoreTestService::compareCounts()` (line 66) uses `DB::table($t)->count()` on both connections (NO information_schema). 17-table DOMAIN_TABLES hardcoded. `restore_tests` migration + model + factory present. Nightly `backup:restore-test` (03:00) scheduled in `routes/console.php:33-36`. **Human-verification item: real restore-test against a live scratch DB.** |
| 5 | D-05: nightly schedule + post-close backup hook + spatie failure-event listener | ✗ FAILED (CR-02) | Schedule (01:00 clean / 01:30 run / 02:00 monitor / 03:00 restore-test) IS correctly wired in `routes/console.php:24-36` with class_exists guards + onOneServer + withoutOverlapping. `NotifyOnBackupFailure` listener IS wired in `AppServiceProvider::registerBackupFailureListeners()` (lines 68-82) for BackupHasFailed + UnhealthyBackupWasFound. **HOWEVER, the post-close backup is DEAD CODE: `CloseMonthJob::after()` (lines 47-54) is never invoked by Laravel's queue runtime — there is no `after()` lifecycle hook.** This breaks the 'fires only on successful close' half of D-05. |
| 6 | D-06: bespoke restore orchestration (maintenance mode + queue:restart + mysql restore + file copy + up) | ✗ FAILED (CR-01) | `BackupRestoreService::restoreFromDisk()` (line 50) correctly implements down → queue:restart → try { restore } finally { up + cleanup }. `buildMysqlProcess()` (line 84) uses Symfony Process with ARRAY args (no shell injection). `BackupPathResolver` correctly uses Symfony Finder for db-dumps. **HOWEVER, `restoreFiles()` (lines 131-147) hardcodes the wrong source directory ($workDir/storage/app/public — nonexistent after spatie's relative_path strip), and `copyDirectory()` on a missing source is a silent no-op. The restore "succeeds" but EVERY uploaded file is lost.** This is the highest-risk crown-jewel surface the threat model calls out. |
| 7 | D-07: backup coverage = mysqldump of all tables + storage/app/public; .env excluded | ✓ VERIFIED (backup side) | `config/backup.php:31-49` includes `storage_path('app/public')`, excludes `base_path('.env')`. The BACKUP coverage is correct. **The RESTORE side is broken (CR-01) — files are backed up correctly but never restored.** |
| 8 | D-08: tests mock heavy Process/Artisan calls; no real mysqldump/mysql in suite; >70% coverage | ✓ VERIFIED (tests pass) + ⚠️ WARNING (the mock design HID the CR-01/02/03 bugs) | 278 tests / 671 assertions green. Every Process/Artisan/DB-restore call mocked. **BUT the mock strategy mocked exactly the seams behind which CR-01/02/03 live: restoreFiles() is mocked (CR-01 invisible); after() is called directly not through the queue (CR-02 invisible); audit new_values is asserted via assertStringContainsString on the cast-serialized string not array access (CR-03 invisible). This is the class of "tests pass, goal unmet" the goal-backward verification exists to catch.** |

**Score:** 5/8 truths fully verified. (Truths 5, 6 fail on critical defects; truth 8 verifies structure but its design masked the criticals.)

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `composer.json` | spatie/laravel-backup + flysystem-aws-s3-v3 | ✓ VERIFIED | Both present (^10.0 + ^3.0); 10.3.0 installed. |
| `config/backup.php` | spatie config (source/destination/retention/notifications) | ✓ VERIFIED | keep_daily=14 / keep_monthly=12; .env excluded; follow_links=false; AES-256 optional; spatie v10 nested key structure. |
| `config/filesystems.php` | backups s3 disk (DO Spaces) | ✓ VERIFIED | Declared at line 66 with all 5 DO_SPACES_* env keys. |
| `config/database.php` | mysql.dump + mysql_restore_test connection | ✓ VERIFIED | dump block + mysql_restore_test connection present (per SUMMARY 06-01). |
| `.env.example` | All Phase 6 env keys documented | ✓ VERIFIED | DO_SPACES_*, BACKUP_*, DB_RESTORE_TEST_DATABASE, DUMP_BINARY_PATH present with explanatory comments. |
| `app/Services/BackupRestoreService.php` | Bespoke full-restore orchestration | ⚠️ PARTIAL (CR-01) | Structure correct (down → queue:restart → restore → up-in-finally, Symfony Process array args, BackupPathResolver via Finder), BUT restoreFiles() source path is wrong + verifyRestore() is DB-only → file restore silently fails. |
| `app/Services/RestoreTestService.php` | Scratch-DB load + per-table COUNT(*) | ✓ VERIFIED (structure) | compareCounts() uses COUNT(*) on both connections. Hardcoded 17-table list. **Human-verification: real scratch DB.** |
| `app/Support/BackupPathResolver.php` | Recursive db-dumps locator | ✓ VERIFIED | Symfony Finder with `->path('db-dumps')` catches both flat + nested layouts; throws on 0 or >1 matches. |
| `app/Models/RestoreTest.php` + migration + factory | restore_tests row drives UI badge | ✓ VERIFIED | #[Fillable], casts (array, datetime), HasFactory, no mess_id (cross-mess). |
| `app/Console/Commands/RestoreTestRun.php` | backup:restore-test artisan command | ✓ VERIFIED | Wraps RestoreTestService::runLatest(); correct exit codes. |
| `app/Listeners/NotifyOnBackupFailure.php` | spatie failure events → NotificationService | ✓ VERIFIED | Handles BackupHasFailed\|UnhealthyBackupWasFound union. Wired in AppServiceProvider. |
| `app/Jobs/CloseMonthJob.php` | after() + failed() lifecycle hooks | ✗ FAILED (CR-02) | after() exists but is dead code (Laravel queue runtime never invokes it). |
| `app/Http/Controllers/Backup/BackupController.php` | super-admin read + audit-logged download | ⚠️ PARTIAL (CR-03) | Structure correct; download is audit-logged; BUT writeAudit() double-JSON-encodes new_values. |
| `app/Http/Controllers/Backup/RestoreController.php` | show typed-confirm + guarded destructive POST | ⚠️ PARTIAL (CR-03) | Structure correct; typed-confirm via RestoreRequest; BUT writeAudit() double-JSON-encodes new_values. |
| `app/Http/Requests/Backup/RestoreRequest.php` | Validate path + typed mess_name | ✓ VERIFIED | `in:<active mess name>` rule; degrades to unmatchable sentinel when no active mess. **Caveat: if mess name contains a comma, the `in:` rule may mis-parse — edge case not exercised.** |
| `resources/views/dashboard/backups/*.blade.php` (4 views) | Backup list + restore form + health badge | ✓ VERIFIED | All 4 views + maintenance error template exist. view:cache succeeds. |
| `resources/views/vendor/tyro-dashboard/partials/admin-sidebar.blade.php` | Role-guarded Backups link | ✓ VERIFIED | `@if(auth()->user()?->hasRole('super-admin') && Route::has('dashboard.backups.index'))` guard present. |
| `routes/web.php` | /dashboard/backups route group | ✓ VERIFIED | role:super-admin + throttle:5,1 on restore POST; 6 named routes registered. |
| `routes/console.php` | Nightly schedule | ✓ VERIFIED | backup:clean (01:00) + backup:run (01:30, withoutOverlapping) + backup:monitor (02:00) + backup:restore-test (03:00). |
| `DEPLOYMENT.md` §11 | Backup & restore runbook | ✓ VERIFIED | §11.1-11.9 all present; §5 extended with 11 Phase 6 env keys. |

### Key Link Verification

| From | To | Via | Status | Details |
|------|-----|-----|--------|---------|
| `config/backup.php` destination | `config/filesystems.php` backups disk | `env('BACKUP_DISK', 'backups')` | ✓ WIRED | backup.php:126 + filesystems.php:66 both reference the backups disk. |
| `config/backup.php` source.databases | `config/database.php` mysql connection | `env('DB_CONNECTION', 'mysql')` | ✓ WIRED | Both reference the active mysql connection. |
| `BackupRestoreService::downloadAndExtract` | config backup destination disk | `config('backup.backup.destination.disks.0', 'backups')` | ✓ WIRED | Correct spatie v10 nested key (the SUMMARY correctly notes the deviation from the plan's flat key). |
| `BackupController::backupDisk` | config backup destination disk | `config('backup.backup.destination.disks.0', 'backups')` | ✓ WIRED | Same nested key as the service — single source of truth. |
| `RestoreController::store` | `BackupRestoreService::restoreFromDisk` | constructor injection | ✓ WIRED | Service is injected; called in try/catch with audit on success + failure. |
| `RestoreRequest::activeMessName` | `Mess::activeId()` | `Mess::find(Mess::activeId())?->name` | ✓ WIRED | Matches BackupController::activeMessName. (Plan/research wrongly assumed Mess::active() — corrected.) |
| `RestoreController::store` | `OwenIt\Auditing\Models\Audit` | `Audit->fill([..., 'new_values' => json_encode($payload), ...])->save()` | ✗ NOT-WIRED (CR-03) | The Audit model casts new_values to 'json', so json_encode($payload) double-encodes. Payload corrupt on read-back. |
| `CloseMonthJob::after` | `Artisan::call('backup:run')` | the after() lifecycle hook | ✗ NOT-WIRED (CR-02) | No such lifecycle hook exists in Laravel's queue runtime. Dead code. |
| `AppServiceProvider::registerBackupFailureListeners` | spatie BackupHasFailed event | `Event::listen(BackupHasFailed::class, NotifyOnBackupFailure::class)` | ✓ WIRED | class_exists-guarded; both events registered. |
| `routes/console.php` schedule | `backup:run` + `backup:restore-test` | `Schedule::command(...)` | ✓ WIRED | All 4 commands scheduled with onOneServer + withoutOverlapping. |

### Data-Flow Trace (Level 4)

| Artifact | Data Variable | Source | Produces Real Data | Status |
|----------|---------------|--------|---------------------|--------|
| `BackupController::index` | `$backups` | `Storage::disk(backups)->allFiles()` filtered to .zip | Yes (when DO Spaces provisioned) | ✓ FLOWING (in prod) |
| `BackupController::index` | `$latestRestoreTest` | `RestoreTest::latest('id')->first()` | Yes (after first restore-test run) | ✓ FLOWING (in prod) |
| `_health_badge.blade.php` | `$latestRestoreTest->status` | passed from controller | Yes | ✓ FLOWING |
| `RestoreController::store` audit row | `new_values` | `$payload` (array) → `json_encode($payload)` → Audit json-cast | No — double-encoded | ✗ HOLLOW (CR-03) |
| `BackupRestoreService::restoreFiles` | files in storage/app/public | `$workDir/storage/app/public` via copyDirectory | No — source path nonexistent, copyDirectory silent no-op | ✗ HOLLOW (CR-01) |
| `CloseMonthJob::after` | `Artisan::call('backup:run')` | the after() lifecycle hook | N/A — hook never fires | ✗ DISCONNECTED (CR-02) |
| `RestoreTestService::runLatest` | per-table COUNT comparison | scratch DB restore + COUNT(*) | Yes (when real backup + scratch DB exist) | ✓ FLOWING (in prod) |

### Behavioral Spot-Checks

| Behavior | Command | Result | Status |
|----------|---------|--------|--------|
| config/backup.php syntax | `php -l config/backup.php` (per SUMMARY) | clean | ✓ PASS |
| spatie/laravel-backup installed | `composer show spatie/laravel-backup` (per SUMMARY) | 10.3.0 | ✓ PASS |
| Schedule registered | `php artisan schedule:list` (per SUMMARY) | all 4 backup commands at 01:00/01:30/02:00/03:00 | ✓ PASS |
| Routes registered | `php artisan route:list --name=dashboard.backups` (per SUMMARY) | all 6 routes | ✓ PASS |
| view:cache | `php artisan view:cache` (per SUMMARY) | succeeds | ✓ PASS |
| Audit cast on new_values | Read `vendor/owen-it/laravel-auditing/src/Models/Audit.php:39-43` | `protected $casts = ['new_values' => 'json']` — double-encoding confirmed | ✗ FAIL (CR-03) |
| spatie relative_path strip | Read `vendor/spatie/laravel-backup/src/Tasks/Backup/Zip.php:36-66` | prefix stripped — source path mismatch confirmed | ✗ FAIL (CR-01) |
| Queue after() hook | Grep `vendor/laravel/framework/src/Illuminate/Queue/*` for `->after(` | no after() invocation — dead code confirmed | ✗ FAIL (CR-02) |

### Requirements Coverage

Phase 6 is a **post-v1 hardening phase** — it has **NO REQ-xxx IDs in `REQUIREMENTS.md`** (confirmed: all 154 requirements map to Phases 1-5). Success criteria derive from the CONTEXT.md decisions D-01..D-08. Requirement traceability against REQUIREMENTS.md is therefore **N/A** (skipped per the verification prompt).

The decision-level coverage is captured in the Observable Truths table above.

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| `app/Services/BackupRestoreService.php` | 137 | `$sourceDir = $workDir.'/storage/app/public'` — hardcoded wrong path; comment at 136 calls the no-op "harmless" | 🛑 Blocker (CR-01) | File restore silently fails; every uploaded file lost on restore. |
| `app/Services/BackupRestoreService.php` | 156-166 | `verifyRestore()` only checks DB tables — does not verify files restored | 🛑 Blocker (CR-01) | Hides the file-restore failure. |
| `app/Jobs/CloseMonthJob.php` | 47-54 | `public function after()` — dead code, no Laravel lifecycle hook | 🛑 Blocker (CR-02) | D-05 post-close backup never fires at runtime. |
| `app/Http/Controllers/Backup/BackupController.php` | 104 | `'new_values' => json_encode($payload)` — double-encoded against json cast | 🛑 Blocker (CR-03) | Audit payload corrupt/unreadable. |
| `app/Http/Controllers/Backup/RestoreController.php` | 96 | `'new_values' => json_encode($payload)` — same double-encoding | 🛑 Blocker (CR-03) | Audit payload corrupt/unreadable. |
| `app/Services/BackupRestoreService.php` | 184 | `$this->files->put($localZip, $disk->get($backupPath))` — loads entire zip into memory | ⚠️ Warning (WR-01) | memory_limit exceeded on large prod backups. |
| `app/Services/RestoreTestService.php` | 171 | Same `$disk->get($latestZip)` memory blowup | ⚠️ Warning (WR-01) | Same — nightly restore-test crashes on large messes. |
| `app/Services/BackupRestoreService.php` | 84-97 | `SOURCE <absPath>` via `-e` — mysql parses spaces in path incorrectly | ⚠️ Warning (WR-03) | Restore fails on Windows dev paths with spaces (e.g. `C:\Program Files\...`). |
| `app/Services/RestoreTestService.php` | 193 | `$conn->getDoctrineSchemaManager()->listTableNames()` — deprecated on Laravel 11+ | ⚠️ Warning (WR-04) | Future Laravel upgrade breaks the wipe. |
| `app/Services/BackupRestoreService.php` | 62-74 | No defensive pre-restore snapshot; partial-restore state on `restoreFiles`/`verifyRestore` throw | ⚠️ Warning (WR-02) | A failure mid-restore leaves the live DB overwritten but no rollback. |
| `app/Listeners/NotifyOnBackupFailure.php` | 34 | `(string) $event->exception` may leak stack traces into Notification row | ⚠️ Warning (WR-06) | PII/secrets in stack traces visible to managers. |
| `routes/console.php` | 33 | restore-test schedule guards on `class_exists(RestoreTestRun::class)` (always true) — not on spatie | ⚠️ Warning (WR-07) | If spatie is uninstalled, restore-test runs nightly + errors noisily. |
| `app/Services/RestoreTestService.php` | 34-52 | DOMAIN_TABLES hardcoded — drifts silently from schema | ℹ️ Info (IN-03) | Phase 7+ tables not in the parity set → false "passed". |
| `app/Services/BackupRestoreService.php` | 158 | `verifyRestore()` asserts count > 0 — fails on freshly-onboarded mess | ℹ️ Info (IN-01) | Restore of an empty mess false-fails. |

### Gaps Summary

The phase shipped ~30 files, a 278-test green suite, and a complete DEPLOYMENT.md runbook. Many invariants hold: the destructive down → queue:restart → restore → up-in-finally ordering is correct; Symfony Process uses array args; BackupPathResolver uses Symfony Finder correctly; RestoreTestService uses COUNT(*) (not information_schema); role:super-admin gate + throttle + typed-confirm are wired; .env is excluded from backups; the retention ladder is correct.

**However, three Critical defects break the phase goal at the highest-risk surface (restore + post-close). All three are invisible to the 278-green suite because Plan 06-02's D-08 mock strategy mocked exactly the seams behind which they hide — the textbook case for goal-backward verification.**

1. **CR-01 (file data-loss on restore — confirmed against vendor):** spatie v10 strips the `relative_path` prefix when zipping (vendor/spatie/laravel-backup/src/Tasks/Backup/Zip.php:36-66). Files backed up under `storage/app/public/` extract to the zip root, NOT under `storage/app/public/`. `BackupRestoreService::restoreFiles()` (line 137) hardcodes the wrong source path; `Filesystem::copyDirectory()` on a missing source is a silent no-op; `verifyRestore()` only checks DB row counts. A "successful" restore silently loses EVERY uploaded file (profile photos + bazar receipts). This directly defeats the phase goal "never loses the mess's financial history."

2. **CR-02 (post-close backup dead code — confirmed against vendor):** Laravel's queue runtime has no `after()` lifecycle hook. `CloseMonthJob::after()` (lines 47-54) is never invoked by the worker. D-05's "post-close backup fires only on successful close" is unimplemented at runtime. The PostCloseBackupListenerTest passes only because it calls `$job->after()` directly (line 80), bypassing the queue runtime.

3. **CR-03 (audit payload double-encoded — confirmed against vendor):** `OwenIt\Auditing\Models\Audit` casts `new_values` to `json` (vendor/owen-it/laravel-auditing/src/Models/Audit.php:39-43). Both controllers pass `json_encode($payload)` into a json-cast attribute, producing JSON-of-JSON. The audit row exists (so the repudiation-mitigation test passes) but the payload is corrupt/unreadable from the Audit UI.

These are goal-level defects. The phase goal — "never loses the mess's financial history" — is **NOT achieved** as long as CR-01 ships to prod (any restore loses all files), CR-02 ships (the highest-value monthly-close snapshot is not captured immediately), and CR-03 ships (the audit trail of every restore/download is unreadable). All three must be fixed + the gap-closure plan must add tests that exercise the real (non-mocked) code paths so this class of defect cannot recur.

### Human Verification Required

Disaster-recovery surfaces fundamentally cannot be fully verified on dev-Windows. The 6 human-verification items (frontmatter `human_verification:`) cover: real DO Spaces provisioning + first real `backup:run`, real restore-test against a live scratch DB, real SMTP failure email, the end-to-end typed-confirm destructive flow on the prod VPS, a real end-to-end file-restore verification (after CR-01 fix), and the down/queue:restart/up ordering under live supervisor.

These items do NOT change the status — the 3 Critical code-level gaps (CR-01/02/03) are independently sufficient to fail the phase goal regardless of human verification outcome.

---

_Verified: 2026-06-19_
_Verifier: Claude (gsd-verifier)_
