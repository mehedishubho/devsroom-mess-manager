---
phase: 06-backup-and-restore-system
reviewed: 2026-06-19T00:00:00Z
depth: standard
files_reviewed: 19
files_reviewed_list:
  - app/Console/Commands/RestoreTestRun.php
  - app/Http/Controllers/Backup/BackupController.php
  - app/Http/Controllers/Backup/RestoreController.php
  - app/Http/Requests/Backup/RestoreRequest.php
  - app/Jobs/CloseMonthJob.php
  - app/Listeners/NotifyOnBackupFailure.php
  - app/Models/RestoreTest.php
  - app/Providers/AppServiceProvider.php
  - app/Services/BackupRestoreService.php
  - app/Services/RestoreTestService.php
  - app/Support/BackupPathResolver.php
  - app/Support/NotificationType.php
  - config/backup.php
  - config/database.php
  - config/filesystems.php
  - database/factories/RestoreTestFactory.php
  - database/migrations/2026_06_19_000001_create_restore_tests_table.php
  - routes/console.php
  - routes/web.php
findings:
  critical: 3
  warning: 7
  info: 5
  total: 15
status: resolved
resolved: 2026-06-19T00:00:00Z
resolution: "All 3 criticals (CR-01/02/03) + 5 warnings (WR-01/03/05/06/07) fixed inline with non-mocked tests; see 06-VERIFICATION.md 'Re-Verification After Gap Closure'. WR-02 accepted (inherent to restore), WR-04 deferred (functional)."
---

# Phase 6: Code Review Report

**Reviewed:** 2026-06-19
**Depth:** standard
**Files Reviewed:** 19
**Status:** resolved — 3 criticals + 5 warnings fixed inline (see 06-VERIFICATION.md "Re-Verification After Gap Closure"). Historical findings preserved below.

## Summary

The Phase 6 backup-and-restore system lands the spatie/laravel-backup engine + bespoke destructive `BackupRestoreService` + super-admin UI + nightly schedule + post-close backup hook. The threat-model invariants that tests can verify are mostly honored: the `down` -> `queue:restart` -> `try { restore }` -> `finally { up }` ordering is correct; `restoreDatabase()` uses Symfony Process with ARRAY args (no shell injection); `BackupPathResolver` correctly uses Symfony Finder with throw-on-ambiguous; `RestoreTestService` uses `SELECT COUNT(*)`; `.env` is excluded; retention numbers and the destination disk match the locked decisions; routes are role-gated with throttle on the destructive POST.

However, the review found **three Critical issues** in the highest-risk surface (the restore path + the post-close backup), all of which silently break the feature without tripping the 278-green suite because the tests mock exactly the seams these bugs hide behind:

1. **File restore is silently broken** — spatie strips the `relative_path` prefix from files when zipping, so the backed-up files live at the zip root, not under `storage/app/public/`. `restoreFiles()` hardcodes the wrong source directory; `copyDirectory` becomes a silent no-op; the restore "succeeds" while uploaded files (photos, receipts) are NEVER restored. The code comment even acknowledges the no-op and calls it "harmless" — it is not.

2. **`CloseMonthJob::after()` is never invoked by Laravel** — Laravel's queue has no `after()` job lifecycle hook (only `failed()` and middleware). D-05 ("post-close backup fires on successful close") is therefore silently unimplemented; the feature does not exist at runtime.

3. **Audit `new_values` is double-JSON-encoded** — the `Audit` model casts `new_values` to `json`, but both controllers pass `json_encode($payload)` (a pre-encoded string). The result is JSON-of-JSON; when `AuditController` reads it back via the owen-it `Audit` trait, `$this->new_values` is a string and downstream iteration breaks. Repudiation mitigation (T-06-03-07) is technically still writing rows but the payload is corrupted.

The 7 Warnings cover edge cases in the restore sequence (memory blowup on `$disk->get()`, partial-restore state after the SQL import succeeds but `restoreFiles`/`verifyRestore` throw, scratch-DB restore using `SOURCE` with an absolute path containing spaces, missing `Storage::fake` guards in download tests), plus a missing schedule guard and a couple of idiom nits. The 5 Info items are minor.

All Critical and most Warning findings are precisely the kind of issue that mocks-and-seams test design cannot catch — they were invisible to the suite by construction.

## Critical Issues

### CR-01: File restore is silently broken — `restoreFiles()` looks in the wrong directory

**File:** `app/Services/BackupRestoreService.php:131-147`
**Issue:** `restoreFiles()` computes the source as `$workDir.'/storage/app/public'`. This assumes the backed-up files live under `storage/app/public/` inside the zip. They do not. `config/backup.php` line 68 sets `'relative_path' => storage_path('app/public')`, and spatie's `Zip::determineNameOfFileInZip()` (vendor, verified) strips exactly that prefix when adding files to the zip:

```php
// vendor/spatie/laravel-backup/src/Tasks/Backup/Zip.php:36-66
$relativePath = $config->backup->source->files->relativePath
    ? rtrim(...).DIRECTORY_SEPARATOR : false;
// ...
if ($relativePath && str_starts_with($fileDirectory, $relativePath)) {
    return substr($pathToFile, strlen($relativePath));   // <-- prefix stripped
}
```

So a file at `storage/app/public/photos/alice.jpg` is stored in the zip as `photos/alice.jpg` (zip root, no `storage/app/public/` prefix). When extracted to `$workDir`, the tree is `$workDir/photos/alice.jpg` — there is NO `$workDir/storage/app/public/` directory. `Filesystem::copyDirectory()` on a non-existent source is a silent no-op. The restore "succeeds" (DB restored, verifyRestore passes its COUNT checks), the audit row is written, the app is brought back up — but every profile photo and bazar receipt is gone. The existing comment ("If the layout differs ... copyDirectory is a no-op — harmless") is wrong: in this case it is the entire file-restore surface failing silently. This is exactly the highest-risk crown-jewel surface the threat model calls out (T-06-02-03 / Pitfall 4).

The mock-based test (BackupRestoreServiceTest) stubs `restoreFiles()` so it never exercises this code path; that is why the suite is green while the bug is present.

**Fix:** Make `restoreFiles()` locate the file tree the same way `BackupPathResolver` locates the SQL dump — by content, not by a hardcoded path — and also fall back to the legacy nested layout. Use Symfony Finder to locate a known marker (any file/dir that backed-up user uploads contain) OR, more robustly, mirror spatie's relative-path strip and copy from the zip root. Pseudocode:

```php
protected function restoreFiles(string $workDir): void
{
    $destDir = storage_path('app/public');
    $this->files->ensureDirectoryExists($destDir, 0775, true);

    // spatie strips `relative_path` (storage/app/public) when zipping, so
    // backed-up files live at the zip ROOT, not under storage/app/public/.
    // Try the root layout first (the actual v8+/v10 layout), then fall back
    // to the legacy nested layout for older backup zips.
    $candidates = [$workDir, $workDir.'/storage/app/public'];

    $sourceDir = null;
    foreach ($candidates as $candidate) {
        if (is_dir($candidate) && $this->directoryHasFiles($candidate)) {
            $sourceDir = $candidate;
            break;
        }
    }
    if ($sourceDir === null) {
        throw new RuntimeException(
            'Could not locate the backed-up file tree in the extracted backup.'
        );
    }

    $this->files->copyDirectory($sourceDir, $destDir);
}

private function directoryHasFiles(string $dir): bool
{
    // true if the dir contains anything other than the db-dumps folder.
    foreach ((new \FilesystemIterator($dir)) as $entry) {
        if (! $entry->isDir() || $entry->getFilename() !== 'db-dumps') {
            return true;
        }
    }
    return false;
}
```

And add a real (non-mocked) integration test that creates a tiny zip with the actual spatie layout and asserts the files land in `storage_path('app/public')` after `restoreFiles()`.

---

### CR-02: `CloseMonthJob::after()` is never invoked — the post-close backup (D-05) is silently absent

**File:** `app/Jobs/CloseMonthJob.php:47-54`
**Issue:** The plan's research and code claim that "research Pattern 6a (job lifecycle hook) is preferred over an Eloquent event listener file — no DispatchBackupAfterClose listener is created." But Laravel's queued-job runtime invokes only `handle()` (or the `Job` middleware pipeline) and the `failed(Throwable)` hook on exception. There is **no `after()` lifecycle hook** for jobs. Confirmed by greping `vendor/laravel/framework/src/Illuminate/Queue/` — the only `after` reference is the unrelated `afterCommit` transaction option.

Consequence: `CloseMonthJob::after()` is dead code. The post-close backup that D-05 / T-06-02-07 mandates — "fires only on successful close. Captures the highest-value immutable snapshot immediately rather than waiting for the nightly run" — never fires. A successful month-close produces no ad-hoc backup; the only protection is the 01:30 nightly run, which can be up to ~24h later. The success criterion "A successful close-month triggers a best-effort ad-hoc backup" is unmet.

The green suite cannot catch this because there is no test asserting the hook fires (PostCloseBackupListenerTest exercises the method directly, not through the queue runtime).

**Fix:** Use one of the two real Laravel mechanisms. The cleanest is an Eloquent event listener on `MonthlyClosing` creation (research Pattern 6b, the rejected alternative that actually works):

```php
// app/Providers/AppServiceProvider.php — extend registerBackupFailureListeners()
// or a new registerPostCloseBackup() method called from boot()
Event::listen('eloquent.created: '.\App\Models\MonthlyClosing::class, function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('backup:run', ['--only-db' => true]);
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::warning('Post-close backup failed', ['exception' => $e]);
    }
});
```

Alternatively, keep the logic in the job by calling it from inside `handle()` itself (after `$service->close(...)` returns without throwing):

```php
public function handle(MonthCloseService $service): void
{
    $service->close($this->year, $this->month, $this->closedBy);
    $this->runPostCloseBackup();   // fires only because close() did not throw
}

private function runPostCloseBackup(): void
{
    try {
        Artisan::call('backup:run', ['--only-db' => true]);
    } catch (Throwable $e) {
        Log::warning('Post-close backup failed', ['exception' => $e]);
    }
}
```

Then add a real test that dispatches `CloseMonthJob` (with `Queue::fake` or `Bus::dispatchNow`) and asserts `Artisan::assertCalled('backup:run')`, and another that asserts `assertNotCalled` when `MonthCloseService::close` throws.

Remove the `after()` method either way — it is misleading dead code.

---

### CR-03: Audit rows have double-encoded `new_values` (corrupted payload)

**File:** `app/Http/Controllers/Backup/BackupController.php:93-110`, `app/Http/Controllers/Backup/RestoreController.php:87-102`
**Issue:** Both controllers write `'new_values' => json_encode($payload)`. But `OwenIt\Auditing\Models\Audit` declares `protected $casts = ['old_values' => 'json', 'new_values' => 'json']` (vendor, verified at `vendor/owen-it/laravel-auditing/src/Models/Audit.php:39-43`). When a JSON-cast attribute receives an already-encoded string, Laravel encodes it again on save — the column ends up holding `'"{\"path\":\"foo.zip\"}"'` instead of `"{\"path\":\"foo.zip\"}"`.

Downstream, owen-it's `Audit::resolveData()` does `foreach ($this->new_values ?? [] as $key => $value)`. With the cast deserializing the double-encoded value, `$this->new_values` becomes the raw JSON string (the cast json_decodes once, returning the inner JSON string). Iterating a string in PHP yields its characters. The audit row exists (so T-06-03-07 "tamper-evident trail" is technically satisfied), but the payload is unreadable from the Audit UI — every restore/download payload is corrupted, defeating the audit's forensic value.

**Fix:** Pass the raw array and let the cast encode it. In both controllers:

```php
$audit->fill([
    'user_type'      => $request->user() ? get_class($request->user()) : null,
    'user_id'        => $request->user()?->id,
    'event'          => $event,
    'auditable_type' => 'backup',
    'auditable_id'   => 0,
    'new_values'     => $payload,           // <-- raw array; cast handles JSON
    'url'            => $request->fullUrl(),
    'ip_address'     => $request->ip(),
    'user_agent'     => $request->userAgent(),
    'tags'           => 'backup',
])->save();
```

Add an assertion in the existing restore/download tests that reads the audit row back through the model and asserts `$audit->new_values['path'] === $validPath` (a string-index access — currently impossible because the value is a string-of-JSON).

## Warnings

### WR-01: Large S3 backup zip is loaded into memory before extraction

**File:** `app/Services/BackupRestoreService.php:184`, `app/Services/RestoreTestService.php:171`
**Issue:** `$this->files->put($localZip, $disk->get($backupPath))` calls `Filesystem::get()` which reads the entire object into a string before writing. For a real prod backup (DB dump + receipts + photos — easily hundreds of MB, up to the 5000MB cap set in `MaximumStorageInMegabytes`), this will exceed `memory_limit` (typically 128-256M on a VPS) and crash the restore mid-flight. The restore-test has the same problem on the nightly schedule.

**Fix:** Stream the object to disk instead of materializing it as a string:

```php
$stream = $disk->readStream($backupPath);
$local = fopen($localZip, 'w+b');
while (! feof($stream)) {
    fwrite($local, fread($stream, 8192));
}
fclose($stream);
fclose($local);
```

Or use Laravel 11+ `Storage::copy` across disks if both are registered (won't work for s3->local transparently without a stream helper). Either way, never `get()` a binary blob of unknown size.

---

### WR-02: A failed `restoreFiles()` or `verifyRestore()` leaves the live DB already overwritten but no clean state

**File:** `app/Services/BackupRestoreService.php:62-74`
**Issue:** Inside the try, the order is `restoreDatabase` -> `restoreFiles` -> `verifyRestore`. If `restoreFiles` or `verifyRestore` throws (e.g. CR-01's source dir does not exist — though `copyDirectory` is silent, a permissions error could still throw; or `verifyRestore` throws because the dump had no rows in a checked table), the live DB has ALREADY been overwritten by `restoreDatabase`. The `finally` runs `up` + cleanup and the exception propagates to the controller, which writes a `backup.restore.failed` audit row. But the system is now in a half-restored state (new DB + old files). There is no rollback path.

This is structurally hard to fix (you cannot un-restore a DB without a pre-restore snapshot), but the current code does nothing to mitigate it. At minimum, the runbook must document that any restore failure requires operator intervention, and `verifyRestore()` failure should be a hard alarm.

**Fix (minimum):** Before `restoreDatabase`, take a defensive `mysqldump` of the current live DB into the backup-temp dir (and include it in the failed-restore audit row). On `verifyRestore` failure, log a CRITICAL-level error and emit a `NotificationType::BACKUP_FAILED` broadcast so managers see it. Document the half-restored state in the restore runbook (Plan 06-04 territory, but flag here).

---

### WR-03: `restoreDumpIntoScratch` and `buildMysqlProcess` pass an absolute `$sqlPath` to `mysql -e "SOURCE <path>"` without quoting

**File:** `app/Services/BackupRestoreService.php:84-97`, `app/Services/RestoreTestService.php:205-219`
**Issue:** The `SOURCE` command runs INSIDE the mysql client (it is a mysql-client meta-command, not SQL), and mysql parses `SOURCE /path/with spaces/foo.sql` by splitting on the first space — so a work-dir path containing a space (e.g. a Windows dev path under `C:\Program Files\...`) will silently fail to load the dump. The Symfony Process ARRAY form correctly handles the args list (no shell injection), but the string passed to `-e` is `SOURCE <path>` and mysql itself parses that string, not Symfony. This is not a security issue (the path is internally generated), it is a correctness issue on paths-with-spaces.

**Fix:** Either `cd` into the dump's directory first and pass only the basename, or prefer piping the file into mysql's stdin:

```php
$process = new Process([
    'mysql',
    '--host='.$cfg['host'],
    '--port='.$cfg['port'],
    '--user='.$cfg['username'],
    '--password='.$cfg['password'],
    $cfg['database'],
]);
$process->setInput(file_get_contents($sqlPath));
$process->setTimeout(600);
$process->mustRun();
```

Stdin avoids the `SOURCE`-parsing fragility entirely and works for arbitrarily-named dump files.

---

### WR-04: `RestoreTestService::wipeScratchDb` uses `getDoctrineSchemaManager()` which is deprecated on Laravel 11+/doctrine-dbal 4+

**File:** `app/Services/RestoreTestService.php:189-198`
**Issue:** `$conn->getDoctrineSchemaManager()->listTableNames()` relies on doctrine-dbal's schema manager. On Laravel 11+/PHP 8.4 with current doctrine-dbal, `getDoctrineSchemaManager()` is deprecated in favor of `Connection::getSchemaManager()` or the new native Laravel schema-builder introspection (`Schema::connection('mysql_restore_test')->getTables()`). If/when doctrine-dbal is removed (the trajectory in Laravel 12+), this throws. Also, if the scratch DB has zero tables yet (first run, fresh provision), `listTableNames()` returns `[]` and the loop is empty — that is correct but worth a comment.

**Fix:** Use the framework's schema introspection:

```php
$tables = Schema::connection('mysql_restore_test')->getTableListing();
foreach ($tables as $name) {
    $conn->statement("DROP TABLE IF EXISTS `{$name}`");
}
```

`Schema::getTableListing()` is the post-Laravel-9 stable API and avoids the deprecated doctrine dependency.

---

### WR-05: `BackupController::download` and `RestoreController::show` path-traversal mitigation depends on disk behavior, not an explicit allow-list

**File:** `app/Http/Controllers/Backup/BackupController.php:78-86`, `app/Http/Controllers/Backup/RestoreController.php:36-45`
**Issue:** The plan's threat model (T-06-03-06) claims `Storage::disk(...)->exists($path)` is the path-traversal guard. This is true ONLY if the disk adapter normalizes `../` sequences. The `local` driver (used as the dev fallback when DO Spaces keys are absent) DOES normalize, but the `s3` driver's behavior on leading `..`/absolute paths varies by SDK version — there have been cases where the SDK treats `../foo.zip` as a literal key. The current code is probably safe in prod (s3 with the DO Spaces adapter), but the mitigation is implicit. Also, since `$path` is concatenated into the URL in `route('dashboard.backups.download', ['path' => $backup['path']])`, a backup whose stored key happens to contain URL-special chars would break.

**Fix:** Add an explicit guard rejecting suspicious shapes before the disk call:

```php
abort_if(str_contains($path, '..') || str_starts_with($path, '/') || str_starts_with($path, '\\'), 404);
abort_unless($disk->exists($path), 404);
```

Defense-in-depth; the cost is one line.

---

### WR-06: `NotifyOnBackupFailure` accesses `BackupHasFailed->exception` without null-coalescing on the string cast

**File:** `app/Listeners/NotifyOnBackupFailure.php:34`
**Issue:** `(string) $event->exception` — `BackupHasFailed::__construct(public ?Throwable $exception = null, ...)` (verified in vendor). If spatie ever dispatches the event with a null exception (it currently doesn't, but the signature allows it), `(string) null` is the empty string, which is fine. The bigger concern: `(string) $exception` calls `Throwable::__toString()`, which for some throwable types can leak absolute filesystem paths or stack internals into the Notification row's `data` JSON — material that super-admins see in the bell but admins also see (broadcastToManagers targets both). PII/secret leakage risk is low but real for stack traces that include env-loaded values.

**Fix:** Truncate and sanitize:

```php
'exception' => $event instanceof BackupHasFailed && $event->exception
    ? mb_substr($event->exception->getMessage(), 0, 500)
    : null,
```

Log the full stack separately via `report()` if you want it preserved.

---

### WR-07: `routes/console.php` restore-test schedule is not guarded by `BackupServiceProvider` existence, so the schedule errors if spatie is uninstalled

**File:** `routes/console.php:31-36`
**Issue:** The restore-test schedule is wrapped in `class_exists(RestoreTestRun::class)`. But `RestoreTestService` (the command's dependency) shells out to `mysql`, and the schedule is meaningless without spatie having produced the backup the test loads. More importantly, `RestoreTestRun::class` is always present (it lives in `app/`), so the guard never fails — it provides no protection. The intent (mirror the telescope + spatie guards) is not realized. If `composer install --no-dev` ever excluded spatie but kept app code, `backup:restore-test` would run, fail to find any backup zip (spatie never wrote one), and write a `restore_tests` row with status=`error` every night — a noisy false alarm.

**Fix:** Guard on spatie, the actual external dependency:

```php
if (class_exists(\Spatie\Backup\BackupServiceProvider::class)
    && class_exists(RestoreTestRun::class)) {
    Schedule::command('backup:restore-test')->daily()->at('03:00')
        ->withoutOverlapping()->onOneServer();
}
```

Or merge the restore-test line into the existing spatie-guarded block above it.

## Info

### IN-01: `verifyRestore()` asserts `count > 0` which fails on a legitimately empty (freshly-onboarded) mess

**File:** `app/Services/BackupRestoreService.php:156-166`
**Issue:** A brand-new mess with 0 payments, 0 monthly_closings, and possibly 0 audit rows would fail `verifyRestore()` even though the restore was correct. `audits` in particular is empty on a fresh install until the first auditable write. The check is a heuristic; on small datasets it false-negatives.

**Fix:** Either remove the `audits` table from the spot-check, or change the assertion to `>= 0` for tables that can legitimately be empty and `> 0` only for tables the schema guarantees non-empty (e.g. `messes`). At minimum, document that a freshly-onboarded mess cannot be restore-tested.

---

### IN-02: `BackupController::runNow` and `runRestoreTest` run synchronously — HTTP timeout risk on a slow Spaces upload

**File:** `app/Http/Controllers/Backup/BackupController.php:49-72`
**Issue:** Both methods call `Artisan::call(...)` synchronously inside the request. For a small mess this is acceptable (the code comment acknowledges it), but a backup:run that uploads hundreds of MB to DO Spaces can exceed nginx/php-fpm's `max_execution_time` and the user sees a 504 with no flash. The restore-test is even longer (download + extract + mysql restore + COUNT loop).

**Fix:** Consider dispatching to the queue (`Artisan::call('backup:run')` -> `dispatch(new RunBackupJob())` or use `->onQueue()`), and surface status via the existing health badge / a flash that says "started" not "completed". This is a Phase 6.5 polish item, not a blocker.

---

### IN-03: `RestoreTestService::DOMAIN_TABLES` is a hardcoded list that will drift from the actual schema

**File:** `app/Services/RestoreTestService.php:34-52`
**Issue:** The comment says "hardcoded rather than read from information_schema so the test is deterministic." That trade-off is defensible, but the list will silently go stale as new tables are added in future phases (the table is already missing some Phase-4 tables like `expense_categories` — wait, it does have it; check `meal_off_requests` is there — yes). Still, any Phase 7+ migration adding a domain table will not be in this list, so the restore-test will pass even if the new table failed to restore. There is no CI guard that flags the drift.

**Fix:** Add a periodic check (or a unit test) that asserts `RestoreTestService::DOMAIN_TABLES` equals the union of non-infrastructure tables in `database/migrations/`. Failing that, add a doc-block note to update the constant when adding a domain migration.

---

### IN-04: `config/backup.php` notifications closure runs `env()` at config-cache time

**File:** `config/backup.php:186-240`
**Issue:** The `(function () { ... env() ... })()` closure executes once when `config/backup.php` is loaded. With `php artisan config:cache`, env vars are baked into the cached config — fine. Without caching (dev), env() runs on every config access. The comment at line 181-185 explains the env-vs-env_nullable choice; the chosen approach (manual `!== '' && !== null` checks) works but is verbose. Not a bug, just unusual.

**Fix:** Optional — wrap the env reads in `env_nullable()` (Laravel 11+ helper) if available, or leave as-is since the explicit check is correct.

---

### IN-05: `BackupPathResolver::locateSqlDump` returns `getRealPath()` which resolves symlinks — may break if the work dir is under a symlinked storage path

**File:** `app/Support/BackupPathResolver.php:45`
**Issue:** `$file->getRealPath() ?: $file->getPathname()`. On systems where `storage_path()` is a symlink (some Docker setups), `getRealPath()` returns the resolved path, which is then passed to `mysql -e "SOURCE <path>"` — fine. But if the symlink target is outside the work-dir tree (unlikely for backup-temp but possible), `SOURCE` could fail. The fallback to `getPathname()` only triggers when `getRealPath()` returns `false` (file does not exist), not when it returns a different valid path.

**Fix:** Use `$file->getPathname()` directly to preserve the work-dir-relative path; Symfony Finder already returns absolute paths when `->in($extractedRoot)` is given an absolute path. Drop the `getRealPath()` call entirely.

---

_Reviewed: 2026-06-19_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
