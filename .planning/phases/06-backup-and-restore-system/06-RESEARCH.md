# Phase 6: Backup and Restore System - Research

**Researched:** 2026-06-19
**Domain:** Backup/restore for a single-mess Laravel 13 / PHP 8.4 / MySQL 8 VPS deploy
**Confidence:** HIGH (core engine + destination verified; restore orchestration verified via the official absence-of-feature + community companion package)

## Summary

Phase 6 adds off-server backup + guarded restore to a stack that already runs a persistent supervisor queue worker and the Laravel scheduler on a VPS (`DEPLOYMENT.md` §3.3 + §4.3). The decisions in `06-CONTEXT.md` are sound and the technical landscape is well-mapped: **`spatie/laravel-backup` v10.x** is the correct, current engine — it requires PHP 8.4 and Laravel 12+ (Laravel 13 is a non-breaking superset of 12), and produces a single zip containing the uploaded files plus a `mysqldump` of the DB. The destination is a Laravel **`s3` driver disk pointed at DigitalOcean Spaces** via a custom `endpoint` — only one composer dep (`league/flysystem-aws-s3-v3 ^3.0`) needs adding because Laravel deliberately keeps the AWS SDK optional.

The hard part — and the largest bespoke build in the phase — is **restore**. Confirmed: **spatie/laravel-backup ships NO restore command by design** (validates D-06). The full-restore UI (D-03) and the restore-test job (D-04) are custom application code: unzip the chosen backup, locate `db-dumps/<dbname>.sql`, restore via the `mysql` CLI wrapped in maintenance mode + queue-stop, then copy files back to `storage/app/public/` without clobbering the symlink. The restore-test loads the dump into a **separate scratch MySQL connection** and asserts per-table row counts. `stefanzweifel/laravel-backup-restore` (composer `wnx/laravel-backup-restore`) exists as a community companion that does DB-only restore with interactive confirmation + post-restore health checks — it is a **valid shortcut for the DB-restore half** but explicitly **does not restore files**, so the planner still owns the file-restore + UI + super-admin gate + maintenance-mode orchestration. The recommendation is to **build the custom restore in-house** (per D-06) and use the companion only as a reference implementation — Phase 6's spec calls for a tightly-controlled, tamper-resistant, audit-logged full restore flow that is easier to reason about as bespoke code than as a wrapper around an interactive Artisan command.

**Primary recommendation:** Install `spatie/laravel-backup` + `league/flysystem-aws-s3-v3`, configure a `backups` s3 disk → DO Spaces, schedule `backup:run --only-db --only-files` nightly with `withoutOverlapping()`, add a post-`CloseMonthJob` listener that dispatches an ad-hoc backup, build a `BackupRestoreService` + `RestoreTestService` behind a `role:super-admin` custom controller + Blade view under `/dashboard/backups`, wire spatie's `BackupHasFailed` / `UnhealthyBackupWasFound` events to the existing `NotificationService::broadcastToManagers()`, and ship a restore runbook as a new `DEPLOYMENT.md` section.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- **D-01:** `spatie/laravel-backup` is the backup engine (mysqldump + files zip, scheduled, off-site, cleanup, failure notification). Researcher confirms version compatible with Laravel 13 / PHP 8.4.
- **D-02:** S3-compatible object storage (DigitalOcean Spaces default) as a Laravel `s3` disk with custom `endpoint`. DB + uploaded files. Daily 14d + monthly 12mo retention. **Never local-only.**
- **D-03:** Super-admin "Backups" page in the Tyro dashboard (`role:super-admin` only) with a **guarded one-click FULL RESTORE**: type-the-mess-name confirm + super-admin-only + **auto maintenance mode** before clobbering live data.
- **D-04:** Periodic **restore-test job** that loads the latest backup into a **scratch MySQL DB** (separate connection), asserts **per-table row counts** match source, surfaces a **health badge**. Untested backup is not a backup.
- **D-05:** Schedule = **nightly** via existing Laravel scheduler + **on-demand** artisan/button + **queued listener after successful `CloseMonthJob`**. **Notify super-admin on backup failure / unhealthy state.**
- **D-06:** `spatie/laravel-backup` ships **NO restore command** — full-restore UI + restore-test are **custom application code** (unzip + mysql restore + file copy).
- **D-07:** Coverage = **mysqldump of all 26 domain tables** + everything under `storage/app/public/`. **EXCLUDE `.env`** (secrets) — document `APP_KEY` / credential regeneration in the restore runbook. Exclude spatie's temp dir + `storage/app/laravel-backup` working area.
- **D-08:** Test by **mocking the heavy process calls** (no real `mysqldump`/`mysql` in the suite). Test: (a) restore-test comparison logic; (b) Backups UI auth gating (super-admin allowed, admin/user/guest 403); (c) restore confirmation flow (typed mess name, super-admin gate, maintenance-mode flip); (d) post-close listener fires after success, no-op on failure.

### Claude's Discretion
- Exact S3-compatible provider + bucket name/region (DO Spaces default).
- `spatie/laravel-backup` version (researcher confirms — see Standard Stack).
- Whether the Backups UI is a **Tyro dynamic resource** or a **custom controller + Blade view** under `role:super-admin` (recommendation: **custom controller** — see Architecture Patterns §3).
- Restore-test scratch DB connection name + schedule (daily vs on-demand).
- Notification channel specifics (SMTP mail + log; Slack webhook if provided).
- Fine-tuning the exact retention numbers (14d daily / 12mo monthly are starting points).
- Whether to also keep a server-level snapshot (Forge/DO snapshot) — optional, not required.
- Test approach details for the custom restore orchestration (mock `Process`/`Artisan` vs tiny fixture dump).

### Deferred Ideas (OUT OF SCOPE)
- Multi-mess backup orchestration / per-mess restore (v2 MULTI-01..04).
- Continuous / streaming DB replication (MySQL binlog / read replica).
- Partial / point-in-time / per-table / per-row restore (user chose full-restore only).
- Cross-region replication of the object-storage backups.
- Backup encryption (client-side envelope encryption) — DO Spaces provides server-side encryption; revisit only if threat model demands it.
- Server-level snapshot as a second decoupled copy (Forge/DO snapshot) — optional, not required.
- Member-facing "export my data" (data portability, separate concern).
- CI job that runs the restore-test on every PR (no CI yet — CONCERNS #16).

</user_constraints>

<phase_requirements>
## Phase Requirements

**Phase 6 has NO REQ-IDs.** It is a post-v1 hardening phase, NOT in `REQUIREMENTS.md` (all 154 requirements map to Phases 1–5 = the v1 milestone; the `ROADMAP.md` Phase 6 entry is a stub `Goal: [To be planned]`). Success criteria are therefore derived from CONTEXT.md D-01..D-08, not from a requirement ID.

The `## Out of Scope` table in `REQUIREMENTS.md` does not list backup/restore — confirm at planning time that the success criteria below align with the project's v1→v2 boundary (they do: backup/restore is post-v1 hardening that protects the v1 crown jewels — `monthly_closings` + `monthly_member_summaries` + `audit_logs` — without expanding v1 scope).

**Derived success criteria (planner refines):**
1. Nightly off-server backup (DB + `storage/app/public/`) runs via the existing scheduler and is visible in `backup:list`. [D-01, D-02, D-05]
2. Backups land on DO Spaces (off-server); retention = daily 14d + monthly 12mo. [D-02]
3. Post-`CloseMonthJob` listener dispatches an ad-hoc backup on successful close; no-op on close failure. [D-05]
4. Super-admin Backups UI at `/dashboard/backups` (role-gated): list, download, trigger-now, restore-test health badge, guarded full-restore form. [D-03]
5. Full-restore is refused without the correct typed mess name, refused when not super-admin, and flips the app into maintenance mode before touching data. [D-03, D-06]
6. Restore-test job loads the latest backup into a scratch MySQL DB and asserts per-table row counts; result surfaced as a health badge. [D-04]
7. Backup failure / unhealthy state notifies super-admin(s) via the project's notification surface. [D-05]
8. `.env` is excluded from backups; restore runbook documents `APP_KEY` + credential regeneration. [D-07]
9. PHPUnit tests pass with heavy processes mocked; >70% coverage maintained. [D-08]
10. `DEPLOYMENT.md` ships a "Backup & restore runbook" section. [CONTEXT.md canonical_refs]

</phase_requirements>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| `spatie/laravel-backup` | `^9.9` or `^10.0` (see note) | Backup engine: mysqldump + files → zip → disk; cleanup; monitor; failure events | The Laravel ecosystem default; one well-maintained package that does exactly the locked spec. `[VERIFIED: spatie.be/docs/laravel-backup/v10 + Packagist + changelog]` |
| `league/flysystem-aws-s3-v3` | `^3.0` | S3 driver backend for Flysystem (Laravel keeps it optional) | Required by Laravel's `s3` disk driver; NOT bundled with `laravel/framework`. `[VERIFIED: laravel.com/docs/13.x/filesystem — "you will need to install the Flysystem S3 package"]` |
| `ext-zip` | bundled (PHP 8.4) | ZIP archive create/extract — required by spatie/laravel-backup AND by the custom restore unzip step | Already verified loaded in Plan 05-01 (Phase 4 Excel path). `[VERIFIED: codebase — phpunit.xml + Plan 05-01 Task 3 "Verified: ext-zip is loaded"]` |
| `mysql` CLI (`mysql` + `mysqldump`) | 8.x | Restore (`mysql < dump.sql`) + dump (spatie shells out to `mysqldump`) + restore-test | Installed on the VPS per `DEPLOYMENT.md` §2 (`sudo apt install -y mysql-server`). `[CITED: DEPLOYMENT.md §2]` |

### ⚠️ spatie/laravel-backup version — CONFIRMED FACTS
- **v10.x requires PHP 8.4 + Laravel 12+.** Laravel 13 (released 2026-03-17) is a non-breaking superset of 12, so v10 is fully compatible with this project (Laravel 13.15, PHP 8.4). `[VERIFIED: spatie.be/docs/laravel-backup/v10/requirements — "requires PHP 8.4, with the ZIP module and Laravel 12 or higher"]`
- **v10 is NOT compatible with Windows servers** (the *package itself* — for filesystem path assumptions). **This matters for dev-on-Windows**: `composer install` will succeed but `php artisan backup:run` will likely fail on the Windows dev box. This is fine — backups run in prod on Ubuntu; the dev workflow is to *exercise the config* but not run real backups locally. `[VERIFIED: spatie.be/docs/laravel-backup/v10/requirements — "It's not compatible with Windows servers"]`
- **Version pin recommendation:** `composer require spatie/laravel-backup:^10.0` (pulls latest v10.x). If composer resolution conflicts arise, fall back to `^9.9` (v9 supports older Laravel but we have no reason to prefer it). `[CITED: github.com/spatie/laravel-backup/blob/main/CHANGELOG.md]`

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `wnx/laravel-backup-restore` | — (optional, NOT recommended) | Companion package: DB-only restore from a spatie backup zip + post-restore health checks | **Reference implementation only.** Explicitly does NOT restore files, is an interactive Artisan command, and adds a third-party dep to a destructive code path. Build custom per D-06. `[VERIFIED: github.com/stefanzweifel/laravel-backup-restore — "does not support restoring files from backups"]` |
| `symfony/process` | bundled (Laravel dep) | Spawn `mysqldump` / `mysql` / `unzip` from PHP safely (escapes args, captures stderr) | Inside `BackupRestoreService` + `RestoreTestService` to invoke the binaries. `[CITED: symfony.com/components/Process + used elsewhere in Laravel core]` |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `spatie/laravel-backup` | Manual `mysqldump` cron + `aws s3 cp` | Rejected: D-01 locked spatie — re-implementing cleanup + monitor + notifications is a trap. |
| `wnx/laravel-backup-restore` (DB restore) | Custom `mysql < dump.sql` via `Process` | Custom is preferred: tighter control over the typed-confirm + role gate + maintenance-mode + audit-log + queue-stop, all of which the spec mandates. The package is an interactive CLI, not an HTTP-driven UI primitive. |
| `backup:run` full zip | Separate DB-only (`--only-db`) + files-only (`--only-files`) jobs | Decision for planner; one combined zip matches D-07 "single zip" mental model and simplifies the restore (one artifact). |
| DO Spaces | AWS S3 / Backblaze B2 / Cloudflare R2 | D-02 locked "S3-compatible, DO Spaces default". R2 (zero egress) is the strongest cost alternative; revisit at planner discretion. |

**Installation:**
```bash
composer require spatie/laravel-backup:^10.0 league/flysystem-aws-s3-v3:^3.0
php artisan vendor:publish --provider="Spatie\Backup\BackupServiceProvider" --tag="backup-config"
# config/backup.php is now published.
```

**Version verification (run before pinning):**
```bash
composer show spatie/laravel-backup | head -5   # confirm dev-resolvable to ^10
composer show league/flysystem-aws-s3-v3 | head -5
```

## Architecture Patterns

### Recommended Project Structure
```
app/
├── Console/Commands/
│   ├── BackupRunNow.php              # `backup:run-now` wrapper (optional — or just call spatie's backup:run from route)
│   └── RestoreTestRun.php            # `backup:restore-test` artisan command
├── Http/
│   ├── Controllers/
│   │   └── Backup/
│   │       ├── BackupController.php      # index (list), download, runNow
│   │       └── RestoreController.php     # restore form + guarded POST (typed-confirm + role gate)
│   ├── Middleware/
│   │   └── EnsureBackupRestorePrivilege.php  # OR rely on `role:super-admin` route middleware (preferred)
│   └── Requests/Backup/
│       └── RestoreRequest.php           # validates typed mess-name confirmation
├── Jobs/
│   └── RunRestoreTestJob.php            # queued restore-test (optional; can run sync in a command)
├── Listeners/
│   ├── DispatchBackupAfterClose.php     # hooks CloseMonthJob success (D-05)
│   └── NotifyOnBackupFailure.php        # hooks spatie BackupHasFailed / UnhealthyBackupWasFound (D-05)
├── Services/
│   ├── BackupRestoreService.php         # custom restore orchestration (unzip + mysql restore + file copy)
│   ├── RestoreTestService.php           # scratch-DB load + per-table count comparison (D-04)
│   └── BackupHealthService.php          # reads restore-test result + spatie monitor → health badge
├── Support/
│   └── BackupPathResolver.php           # finds db-dumps/<dbname>.sql inside the extracted zip
config/
├── backup.php                           # published by spatie, edited for this project
├── filesystems.php                      # populate the s3 disk → DO Spaces (D-02)
└── database.php                         # add dump.dump_binary_path + add `mysql_restore_test` connection (D-04)
database/migrations/
└── 2026_mm_dd_HHMMSS_create_restore_tests_table.php  # last_restore_test_at, status, per_table_counts_json, message
routes/
├── console.php                          # nightly backup:run + restore-test schedule
└── web.php                              # /dashboard/backups route group (role:super-admin)
resources/views/dashboard/backups/       # index, _restore_form, _health_badge partials
```

### Pattern 1: spatie `backup.php` config shape (the canonical structure)
**What:** The published `config/backup.php` is the single source of truth for what to back up, where, how long to keep, and who to notify.
**When to use:** Mandatory — Phase 6's first code task.
**Example (the keys that matter for this project):**
```php
// config/backup.php  — Source: github.com/spatie/laravel-backup/blob/main/config/backup.php [VERIFIED]
return [
    'name' => env('APP_NAME', 'laravel-backup'),

    'source' => [
        'files' => [
            // D-07: back up storage/app/public (profile photos + bazar receipts).
            // Include the project base so spatie can resolve paths, but EXCLUDE everything
            // we do NOT want off-server (vendor, node_modules, .env, framework cache,
            // and spatie's OWN working area to avoid recursion).
            'include' => [
                storage_path('app/public'),
            ],
            'exclude' => [
                storage_path('app/backup-temp'),        // spatie's own temp dir
                storage_path('app/laravel-backup'),     // legacy spatie working area
                storage_path('app/private'),            // D-07: NOT the private disk
                storage_path('framework'),
                base_path('.env'),                      // D-07: NEVER back up secrets
                base_path('vendor'),
                base_path('node_modules'),
            ],
            'follow_links' => false,                    // public/storage symlink — see Pitfall 4
        ],
        'databases' => [
            env('DB_CONNECTION', 'mysql'),              // the one MySQL connection (all 26 tables)
        ],
    ],

    'destination' => [
        'filename_prefix' => '',
        'disks' => [
            env('BACKUP_DISK', 'backups'),              // the s3 disk → DO Spaces (D-02)
        ],
    ],

    'cleanup' => [
        'strategy' => \Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy::class,
        'default_strategy' => [
            // D-02: daily 14d + monthly 12mo.
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 14,        // <-- D-02 daily retention
            'keep_weekly_backups_for_weeks' => 8,
            'keep_monthly_backups_for_months' => 12,    // <-- D-02 monthly retention
            'keep_yearly_backups_for_years' => 2,
            'delete_oldest_backups_when_using_more_megabytes_than' => env('BACKUP_MAX_MB', 5000),
        ],
    ],

    'monitor_backups' => [
        [
            'name' => env('APP_NAME', 'laravel-backup'),
            'disks' => [env('BACKUP_DISK', 'backups')],
            'health_checks' => [
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays::class => 1,
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes::class => 5000,
            ],
        ],
    ],

    'notifications' => [
        'notifications' => [
            \Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification::class => ['mail'],
            // D-05: also wire BackupHasFailed + UnhealthyBackupWasFound EVENTS to our
            // NotificationService::broadcastToManagers() via a Listener (see Pattern 5).
        ],
        'notifiable' => \Spatie\Backup\Notifications\Notifiable::class,
        'mail' => [
            'to' => env('BACKUP_NOTIFICATION_EMAIL', env('MAIL_FROM_ADDRESS')),
            'from' => ['address' => env('MAIL_FROM_ADDRESS'), 'name' => env('MAIL_FROM_NAME')],
        ],
        // Discord / Slack / generic webhook blocks ship built-in; enable if creds exist.
    ],
];
```

### Pattern 2: DigitalOcean Spaces as a Laravel `s3` disk
**What:** DO Spaces speaks the S3 API; the existing `config/filesystems.php` `s3` disk block already has the right keys (driver, key, secret, region, bucket, endpoint, `use_path_style_endpoint`). Just add a `backups` disk (so the spatie `local` default is untouched).
**Example:**
```php
// config/filesystems.php — add this disk alongside the existing s3 disk
// Source: joelennon.com/using-digitalocean-spaces-in-laravel + digitalocean.com/docs/spaces [VERIFIED]
'backups' => [
    'driver' => 's3',
    'key' => env('DO_SPACES_KEY'),
    'secret' => env('DO_SPACES_SECRET'),
    'region' => env('DO_SPACES_REGION', 'nyc3'),  // DO region, NOT an AWS region
    'bucket' => env('DO_SPACES_BUCKET'),
    'endpoint' => env('DO_SPACES_ENDPOINT', 'https://nyc3.digitaloceanspaces.com'),
    'use_path_style_endpoint' => env('DO_SPACES_USE_PATH_STYLE_ENDPOINT', false),
    'throw' => true,  // surface upload errors instead of silently swallowing them
],
```
**.env additions:**
```env
DO_SPACES_KEY=...           # Spaces access key (generated in the DO control panel)
DO_SPACES_SECRET=...
DO_SPACES_REGION=nyc3       # must match the endpoint's subdomain
DO_SPACES_BUCKET=devsroom-mess-backups
DO_SPACES_ENDPOINT=https://nyc3.digitaloceanspaces.com
BACKUP_DISK=backups
```
⚠️ **DO Spaces `region` must match the endpoint subdomain** — `nyc3` + `https://nyc3.digitaloceanspaces.com`, `sfo3` + `https://sfo3.digitaloceanspaces.com`, etc. A mismatch is the single most common misconfiguration. `[VERIFIED: digitalocean.com/products/spaces/details/limits + joelennon.com]`

### Pattern 3: Tyro Backups UI — custom controller, NOT a dynamic resource
**What:** Two options exist for the Backups UI. **Recommendation: custom controller + Blade view under `/dashboard/backups`, gated by `role:super-admin`.**
**Why NOT a Tyro dynamic resource:** Tyro's `config/tyro-dashboard.php` `resources` array (already used for `messes` + `settings`) is a CRUD scaffolder — index/create/store/show/edit/update/destroy against a single Eloquent model. A Backups UI is **not CRUD over one model**: it lists zip artifacts on an object-storage disk, exposes a one-click destructive action with a typed confirmation, surfaces a health badge from a separate restore-test result, and downloads binary blobs. Forcing this into a dynamic resource would mean fighting the scaffolder. A custom controller is the established alternative — `app/Http/Controllers/Mess/AuditController.php` and `OnboardingController` are both custom controllers in the project, so the pattern has precedent. `[CITED: config/tyro-dashboard.php:230-322 + routes/web.php:38-45 + .agents/skills/tyro-dashboard/SKILL.md "Add an app-level admin page" recipe]`
**Route shape (mirrors the existing `role:admin` group in `routes/web.php`):**
```php
// routes/web.php — append a new route group
Route::middleware(['auth', 'role:super-admin'])->prefix('dashboard/backups')->name('dashboard.backups.')->group(function () {
    Route::get('/', [BackupController::class, 'index'])->name('index');
    Route::post('/run', [BackupController::class, 'runNow'])->name('run');         // ad-hoc backup:run
    Route::get('/{path}/download', [BackupController::class, 'download'])
        ->where('path', '.*')->name('download');                                  // super-admin-only zip download
    Route::get('/restore/{path}', [RestoreController::class, 'show'])
        ->where('path', '.*')->name('restore.show');                              // the typed-confirm form
    Route::post('/restore', [RestoreController::class, 'store'])->name('restore.store'); // the guarded POST
    Route::post('/restore-test', [BackupController::class, 'runRestoreTest'])->name('restore-test.run');
});
```
**Sidebar link:** Follow the Tyro skill recipe — prefer a published sidebar partial override (`resources/views/vendor/tyro-dashboard/partials/admin-sidebar.blade.php`) OR extend the existing layout, with `role:super-admin` guarding the link render so non-super-admins don't even see it. `[CITED: .agents/skills/tyro-dashboard/SKILL.md "Add or change a sidebar item" recipe + common publish tags]`

### Pattern 4: Custom restore orchestration (`BackupRestoreService`)
**What:** The full-restore flow (D-03 + D-06) is bespoke code behind the super-admin gate. The service owns the destructive sequence; the controller owns auth + the typed confirm + the audit-log row.
**Pseudocode shape:**
```php
// app/Services/BackupRestoreService.php
class BackupRestoreService
{
    public function restoreFromDisk(string $backupPath, string $confirmMessName): void
    {
        // 1. AUTH + CONFIRM are enforced by the Form Request + middleware, NOT here.
        //    The service trusts its caller (defense in depth: assert(func_get_args())).

        // 2. Maintenance mode ON (D-03) — web requests now 503.
        Artisan::call('down', ['--render' => 'errors.maintenance-backup-restore']);

        // 3. Stop the queue worker so no CloseMonthJob runs mid-restore.
        //    `queue:restart` signals supervisor-managed workers to die after current job;
        //    supervisor restarts them — but in `down` mode the new workers won't pick up jobs.
        Artisan::call('queue:restart');

        try {
            $workDir = $this->downloadAndExtract($backupPath);   // Storage::disk('backups')->stream(...) → unzip
            $sqlPath = $this->locateSqlDump($workDir);            // db-dumps/<dbname>.sql (see Pitfall 1)
            $this->restoreDatabase($sqlPath);                     // mysql --force < dump.sql (see Pattern 4a)
            $this->restoreFiles($workDir);                        // rsync-equivalent into storage/app/public/
            $this->verifyRestore();                               // per-table COUNT spot-check
        } finally {
            Artisan::call('up');                                  // ALWAYS bring the app back up
            $this->cleanup($workDir);
        }
    }

    // Pattern 4a: restore via the mysql CLI (NOT PDO::exec)
    // Why CLI over PDO: mysqldump output contains multi-statement SQL, DELIMITER changes
    // (for stored procedures / triggers), and USE statements that PDO::exec chokes on without
    // PDO::MYSQL_ATTR_MULTI_STATEMENTS (which is off by default for good reason). The mysql
    // CLI handles all of this natively.
    private function restoreDatabase(string $sqlPath): void
    {
        $cmd = sprintf(
            'mysql --host=%s --port=%s --user=%s --password=%s %s < %s',
            escapeshellarg(config('database.connections.mysql.host')),
            escapeshellarg((string) config('database.connections.mysql.port')),
            escapeshellarg(config('database.connections.mysql.username')),
            escapeshellarg(config('database.connections.mysql.password')),
            escapeshellarg(config('database.connections.mysql.database')),
            escapeshellarg($sqlPath)
        );
        // PREFER symfony/Process over escapeshellarg() string concat:
        $process = new Process([
            'mysql',
            '--host='.config('database.connections.mysql.host'),
            '--user='.config('database.connections.mysql.username'),
            '--password='.config('database.connections.mysql.password'),
            config('database.connections.mysql.database'),
            '-e', 'SOURCE '.escapeshellarg($sqlPath),
        ]);
        $process->setTimeout(600);   // restoring a 5GB dump can take minutes
        $process->mustRun();
    }
}
```
**FK-constraint ordering:** `mysqldump`'s default output includes `SET FOREIGN_KEY_CHECKS=0;` at the top and `SET FOREIGN_KEY_CHECKS=1;` at the bottom, so restoring in dependency order is handled automatically — do NOT hand-order the tables. `[VERIFIED: dev.mysql.com/doc/refman/8.0/en/mysqldump.html + standard mysqldump output]`

### Pattern 5: Restore-test job (D-04) — scratch DB + per-table counts
**What:** A scheduled (and on-demand) job that proves a backup actually restores. Loads the latest backup into a **separate MySQL database** via a **separate connection**, then asserts each domain table's row count matches the source.
**Example:**
```php
// config/database.php — add a second mysql connection that points at a scratch DB
// (same host/creds; different database name). Same driver/options as the live connection.
'mysql_restore_test' => [
    'driver' => 'mysql',
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '3306'),
    'database' => env('DB_RESTORE_TEST_DATABASE', 'devsroom_mess_restore_test'),
    'username' => env('DB_USERNAME'),
    'password' => env('DB_PASSWORD'),
    'charset' => 'utf8mb4',
    'strict' => true,
    // ... mirror the live mysql connection block
],
```
```php
// app/Services/RestoreTestService.php
class RestoreTestService
{
    public function runLatest(): RestoreTestResult
    {
        // 1. Download the latest backup zip from the 'backups' disk.
        // 2. Wipe the scratch DB (Db::connection('mysql_restore_test')->statement('DROP ...'))
        //    + re-run all migrations against it (migrate --database=mysql_restore_test).
        // 3. Restore the dump INTO the scratch DB (mysql_restore_test connection).
        // 4. For each of the 26 domain tables:
        //    liveCount  = DB::table($t)->count();              // live mysql connection
        //    testCount  = DB::connection('mysql_restore_test')->table($t)->count();
        //    pass = (liveCount === testCount);
        // 5. Persist a RestoreTest row: status, per_table_counts_json, ran_at, message.
        // 6. Drop all tables in the scratch DB so it doesn't grow (or DROP DATABASE + recreate).
    }
}
```
**Per-table counts via `information_schema.TABLES.TABLE_ROWS` are NOT acceptable** — that value is an InnoDB statistical estimate, not exact. Use `SELECT COUNT(*)` per table. `[VERIFIED: dev.mysql.com/doc/refman/8.0/en/information-schema-tables-table.html — "TABLE_ROWS ... For InnoDB ... the row count is only a rough estimate"]`
**Scratch DB hygiene:** Either `DB::connection('mysql_restore_test')->statement('SET FOREIGN_KEY_CHECKS=0')` + loop `DROP TABLE` at the start of every run, OR `DROP DATABASE` + `CREATE DATABASE` once per run. Pick one and stick with it (consistency-first per the Laravel skill). `[CITED: .agents/skills/laravel-best-practices/SKILL.md §1 Consistency First]`

### Pattern 6: Post-close backup listener (D-05) — mirror Phase 3's notification pattern
**What:** A listener that fires an ad-hoc `backup:run` immediately after a successful `CloseMonthJob`. The close produces the highest-value immutable data of the month (`monthly_closings` + `monthly_member_summaries`); capture it now, not up to 24h later.
**Two implementation styles — pick one (D-05 says "queued listener"):**

**(a) Job `failed`/`after` hooks (preferred — no event needed):**
```php
// app/Jobs/CloseMonthJob.php — add the lifecycle hooks
class CloseMonthJob implements ShouldQueue
{
    public function handle(MonthCloseService $service): void { /* unchanged */ }

    // fires only on successful completion (no exception thrown)
    public function after(): void
    {
        // dispatch a backup asynchronously — do NOT block the close path
        Artisan::call('backup:run', ['--only-db' => true]);  // DB-only (files didn't change)
        // OR dispatch a queued job: dispatch(new RunAdHocBackupJob());
    }

    // fires only on failure
    public function failed(\Throwable $e): void { /* do NOT backup a half-closed state */ }
}
```
**(b) Eloquent event listener (alternative, mirrors the existing AppServiceProvider pattern):**
```php
// app/Providers/AppServiceProvider.php — register alongside registerBillPreviewInvalidation
Event::listen('eloquent.saved: '.MonthlyClosing::class, function (MonthlyClosing $closing) {
    if ($closing->wasRecentlyCreated) {   // only on the FIRST successful close of a (year, month)
        Artisan::call('backup:run', ['--only-db' => true]);
    }
});
```
**Dedup against the nightly run:** spatie's `backup:run` is naturally idempotent — each run produces a fresh timestamped zip; cleanup handles retention. There is no "skip if a recent backup exists" behavior by default, so a close + the nightly run will produce two zips. This is acceptable (the close-time zip is the point); the planner can add a `--filename-prefix=post-close-` if they want to distinguish them in the UI. `[VERIFIED: spatie config has no dedup option; the v10 changelog confirms timestamped zips]`

### Pattern 7: Failure notification via the project's `NotificationService` (D-05)
**What:** spatie dispatches events on failure. Wire them to `NotificationService::broadcastToManagers()` so a backup failure shows up in the in-app bell that super-admins already use, AND via spatie's built-in mail channel (which needs SMTP in prod).
**Event names (correct FQCNs):** `[VERIFIED: spatie.be/docs/laravel-backup/v10/taking-backups/events]`
- `Spatie\Backup\Events\BackupHasFailed` — fires when the backup itself fails.
- `Spatie\Backup\Events\UnhealthyBackupWasFound` — fires when `backup:monitor` finds a backup that violates a `health_checks` constraint (too old, too big).
- `Spatie\Backup\Events\CleanupHasFailed` — fires when cleanup fails.
- `Spatie\Backup\Events\BackupWasSuccessful` / `HealthyBackupWasFound` / `CleanupWasSuccessful` — the success counterparts (rarely need wiring; useful for the health badge).

**Listener registration:**
```php
// app/Providers/AppServiceProvider.php — extend boot()
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\UnhealthyBackupWasFound;
use App\Listeners\NotifyOnBackupFailure;

protected function boot(): void
{
    // ... existing boot() body ...
    Event::listen(BackupHasFailed::class, NotifyOnBackupFailure::class);
    Event::listen(UnhealthyBackupWasFound::class, NotifyOnBackupFailure::class);
}

// app/Listeners/NotifyOnBackupFailure.php
class NotifyOnBackupFailure
{
    public function __construct(private NotificationService $notifications) {}

    public function handle(BackupHasFailed|UnhealthyBackupWasFound $event): void
    {
        $this->notifications->broadcastToManagers('backup_failed', [
            'event'   => class_basename($event),
            'message' => $event->backupDestination?->name() ?? 'unknown disk',
            // include the exception property if present (BackupHasFailed has ->exception)
        ]);
    }
}
```
⚠️ **`MAIL_MAILER=log` default** means spatie's mail notifications go to the log file by default, NOT a real mailbox. The restore runbook MUST document setting `MAIL_MAILER=smtp` + SMTP creds in prod for the failure email to actually arrive. The in-app `Notification` row is the reliable channel; email is a bonus. `[CITED: .planning/codebase/INTEGRATIONS.md + DEPLOYMENT.md §5]`

### Pattern 8: Scheduling — mirror the existing `telescope:prune` pattern
**What:** Add the nightly backup + restore-test to `routes/console.php` using the same `class_exists` guard style already established there.
**Example:**
```php
// routes/console.php — append after the existing telescope:prune block
use Illuminate\Support\Facades\Schedule;

if (class_exists(\Spatie\Backup\BackupServiceProvider::class)) {
    // D-05: nightly backup. withoutOverlapping so a slow run doesn't double up.
    // onOneServer so a multi-server deploy (future) doesn't both run it.
    Schedule::command('backup:clean')->daily()->at('01:00')->onOneServer();
    Schedule::command('backup:run')->daily()->at('01:30')
        ->withoutOverlapping()->onOneServer();
    Schedule::command('backup:monitor')->daily()->at('02:00')->onOneServer();
}

// D-04: restore-test. Daily is the spec's default; on-demand button is also wired.
if (class_exists(\App\Console\Commands\RestoreTestRun::class)) {
    Schedule::command('backup:restore-test')->daily()->at('03:00')
        ->withoutOverlapping()->onOneServer();
}
```
`->onOneServer()` requires the `database`/Redis cache store — this project uses `database` for cache, so it works out of the box. `[VERIFIED: laravel.com/docs/13.x/scheduling + .planning/codebase/INTEGRATIONS.md cache=database]`

### Anti-Patterns to Avoid
- **`Storage::disk('public')->deleteDirectory('/')` during file restore.** This will follow the `public/storage → storage/app/public` symlink in the wrong direction on some setups and may delete real files. Instead: extract to a temp dir, then `File::copyDirectory($temp.'/storage/app/public', storage_path('app/public'))` with explicit source/dest paths.
- **Restoring via `PDO::exec(file_get_contents($dump))`.** mysqldump output uses `DELIMITER` (for routines/triggers) + multi-statement SQL that PDO refuses by default. Always shell out to the `mysql` CLI. (See Pattern 4a.)
- **Hard-coding the dump path.** spatie's zip layout changed between versions (Issue #1389): the `db-dumps/` folder may be nested under the configured source base path. Use `BackupPathResolver` to glob `**/db-dumps/*.sql` instead of guessing. (See Pitfall 1.)
- **Relying on `information_schema.TABLES.TABLE_ROWS` for the restore-test count.** That is an InnoDB estimate. Always `SELECT COUNT(*)`. (See Pattern 5.)
- **Skipping maintenance mode during restore.** A user writing to `payments` or `meal_entries` while the DB is being restored = data loss. `down` is mandatory, not optional (D-03).
- **Putting backup failures ONLY on email.** With `MAIL_MAILER=log`, the email goes nowhere. Always also write a `Notification` row. (See Pattern 7.)
- **Adding a third-party restore package (`wnx/laravel-backup-restore`) to the destructive path.** The locked spec (D-06) calls for custom code with a super-admin gate + typed confirm + audit log + maintenance mode — easier to reason about as bespoke than as a wrapper around an interactive Artisan command.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| mysqldump | Manual `SELECT INTO OUTFILE` per table | `spatie/laravel-backup` `backup:run` (shells out to `mysqldump`) | mysqldump handles DDL, FK ordering, charsets, `DELIMITER`, routines, views, triggers. Reinventing it is a multi-month bug factory. |
| Backup zip assembly | Custom ZipArchive loop | spatie's destination zip (CM_DEFAULT, level 9) | Spatie handles the file walker, exclusion, the db-dumps folder, and (v10) optional AES-256 encryption. |
| Retention / cleanup | Custom cron deleting old files | spatie's `DefaultStrategy` (`keep_all` / `keep_daily` / `keep_monthly` ladder) | The strategy never deletes the newest backup and handles the daily→weekly→monthly→yearly decay correctly. |
| Backup health monitoring | Custom "is there a recent backup?" check | spatie's `backup:monitor` + `MaximumAgeInDays` / `MaximumStorageInMegabytes` health checks | Emits `UnhealthyBackupWasFound` automatically when the backup is stale or growing too big. |
| S3 multipart upload | Custom chunked PUT | Flysystem s3 v3 adapter (auto-multipart for >5MB) | The adapter auto-uses multipart under the hood; DO Spaces supports 5 TB objects / 10,000 parts. |
| Sub-process arg escaping | `escapeshellarg()` string concat | `symfony/Process` with an array of args | Process handles platform differences (Windows-dev vs Linux-prod) and captures stderr cleanly. |
| Audit-log entries on restore | Custom `audit_logs` insert | `owen-it/laravel-auditing` `Auditable` trait (already in the project at v14.0.4) + a manual audit write for the restore action | The project standard; reuse so restores show up in the same audit surface as every other write. |

**Key insight:** This phase's only hand-rolled code should be (a) the restore orchestration (D-06 mandates it; spatie doesn't ship restore), (b) the restore-test comparison logic (D-04 mandates it; no package does row-count parity), and (c) the UI glue. Everything else — dumping, zipping, uploading, cleaning, monitoring, notifying — is spatie + Flysystem + Laravel scheduler.

## Runtime State Inventory

This is a **greenfield feature phase** (no rename/refactor/migration of existing strings). The categories below are answered explicitly to confirm nothing is missed.

| Category | Items Found | Action Required |
|----------|-------------|------------------|
| Stored data | None — no existing string/key/collection name is being renamed. New tables added: `restore_tests` (one row per restore-test run, latest = the health badge source). | New migration (create `restore_tests`); no data migration of existing tables. |
| Live service config | None — DO Spaces is brand new; the bucket + keys are created at provision time, not pre-existing. The Laravel scheduler + supervisor queue worker are already configured (`DEPLOYMENT.md` §3.3 + §4.3); this phase *adds* scheduled commands to them but does not rename anything. | Add scheduled commands to `routes/console.php`; add the `backups` disk to `config/filesystems.php`; add the `mysql_restore_test` connection to `config/database.php`. |
| OS-registered state | None — supervisor (`mess-worker.conf` per §4.3) + the `* * * * * schedule:run` cron (per §4.4) are unchanged. The restore flow's `Artisan::call('down')` uses Laravel's maintenance-mode file (`storage/framework/down`), not an OS-level registration. | None — verify the supervisor config's `stopwaitsecs=3600` survives a `queue:restart` mid-restore (it does — that's its purpose). |
| Secrets/env vars | New env keys added to `.env` + `.env.example`: `DO_SPACES_KEY`, `DO_SPACES_SECRET`, `DO_SPACES_REGION`, `DO_SPACES_BUCKET`, `DO_SPACES_ENDPOINT`, `BACKUP_DISK`, `BACKUP_MAX_MB`, `BACKUP_NOTIFICATION_EMAIL`, `DB_RESTORE_TEST_DATABASE`, optional `DUMP_BINARY_PATH` (dev-Windows path differs from prod-Linux path). **Existing secret to AVOID backing up**: `.env` itself (D-07) — explicitly listed in `backup.php` `exclude`. | Code edit only — no SOPS key rename, no existing secret rotation. Document the Spaces key provisioning in the restore runbook. |
| Build artifacts | None — no `pyproject.toml`-style rename, no compiled binaries. The new composer deps (`spatie/laravel-backup`, `league/flysystem-aws-s3-v3`) drop into `vendor/` on `composer install`. The published `config/backup.php` is committed to git (it is project config). | `composer install` after merge; `php artisan vendor:publish --tag=backup-config` once. |

**The canonical question — "After every file in the repo is updated, what runtime systems still have the old string cached, stored, or registered?"** — Answer: nothing. This phase is purely additive. The closest runtime-state concern is that a **restore in progress** must not race with the queue worker (`queue:restart` in Pattern 4 handles it) and must not race with web writes (`Artisan::call('down')` handles it).

## Common Pitfalls

### Pitfall 1: The dump file location inside the backup zip changed across versions
**What goes wrong:** A restore script assumes `db-dumps/<dbname>.sql` at the zip root; the actual zip has it nested under the configured source base path (`<source-base>/db-dumps/<dbname>.sql`). The restore crashes with "sql file not found."
**Why it happens:** spatie/laravel-backup Issue #1389 — the internal folder structure changed between versions; the `db-dumps` folder is now placed under the application source base path.
**How to avoid:** Use a `BackupPathResolver` that globs `**/db-dumps/*.sql` over the extracted tree rather than guessing a fixed path. Same for the file content (`**/storage/app/public/...`).
**Warning signs:** "SQL file not found" or "0-byte dump restored" in test runs.

### Pitfall 2: Windows-dev `mysqldump.exe` PATH vs Linux-prod
**What goes wrong:** `backup:run` works in prod but throws `Symfony\Component\Process\Exception\ProcessFailedException: The dump process failed` in dev because `mysqldump.exe` isn't on the Windows PATH.
**Why it happens:** spatie shells out to `mysqldump` (the binary name, no `.exe`). On Windows, PHP's `proc_open` won't find `mysqldump` unless the MySQL bin dir is on PATH.
**How to avoid:** Set `DUMP_BINARY_PATH` in dev `.env` to the MySQL bin dir (e.g. `C:\Program Files\MySQL\MySQL Server 8.0\bin`), and wire it into `config/database.php`:
```php
// config/database.php — inside the 'mysql' connection block
'dump' => [
    'dump_binary_path' => env('DUMP_BINARY_PATH', '/usr/bin'),  // dir only, NOT the executable
    'use_single_transaction' => true,   // avoids locking InnoDB tables during the dump
    'add_extra_option' => '--quick --single-transaction',  // belt + suspenders
],
```
Prod `.env` leaves `DUMP_BINARY_PATH` unset (or set to `/usr/bin`) — `mysqldump` is installed there by `apt install mysql-server` per `DEPLOYMENT.md` §2.
**Warning signs:** The dump fails ONLY in dev; prod is fine.
**Spec impact:** Per the spatie docs, the path is the **directory** containing the binary, not the binary itself. `[VERIFIED: spatie.be/docs/laravel-backup/v10/installation-and-setup]`

### Pitfall 3: The `database` cache/queue/session driver means a restore can trash live state
**What goes wrong:** Restoring the DB mid-flight clobbers the `cache`, `sessions`, `jobs`, `failed_jobs`, `job_batches` tables — logging out every user, dropping queued `CloseMonthJob`s, and invalidating caches mid-write.
**Why it happens:** Per `.planning/codebase/INTEGRATIONS.md` + `CONCERNS.md #3`, the project uses `database` for cache + queue + session. The `mysqldump` includes those tables; restoring them overwrites the live state.
**How to avoid:** Maintenance mode (D-03) **mitigates** this — `down` stops new web requests + the queue worker (`queue:restart`), so no new writes happen during the restore. After `up`, sessions are re-established on the next request. **The restore-test (D-04) is the proof that the backup includes enough to fully recover.** Do NOT try to exclude `cache`/`sessions`/`jobs` from the backup — they're small and a full restore should put them back too.
**Warning signs:** A post-restore user reports being logged out (expected + acceptable). A queued close job disappears mid-restore (avoided by `queue:restart` before the restore).

### Pitfall 4: The `public/storage` symlink during file restore
**What goes wrong:** The restore copies files into `storage/app/public/`, but the symlink `public/storage → storage/app/public` is either broken, points at the old directory, or gets clobbered.
**Why it happens:** `storage_path('app/public')` and `public_path('storage')` are different paths joined by a symlink created by `php artisan storage:link`. A naive `File::copyDirectory` that follows the symlink can recurse into itself.
**How to avoid:** (a) Set `follow_links => false` in `backup.php` `source.files` so the backup zip contains the real files under `storage/app/public/`, not the symlink. (b) During restore, copy into `storage_path('app/public')` (the real dir), never `public_path('storage')` (the symlink). (c) Re-run `php artisan storage:link` after restore as a belt-and-suspenders step.
**Warning signs:** Files visible in `ls storage/app/public/` but 404 on the web (broken symlink).

### Pitfall 5: DO Spaces region/endpoint mismatch
**What goes wrong:** Uploads to DO Spaces fail with `The authorization signature is invalid` or hang, because the `region` config value doesn't match the `endpoint` subdomain.
**Why it happens:** The AWS SDK validates the region against the endpoint. `region=nyc3` + `endpoint=https://sfo3.digitaloceanspaces.com` is rejected.
**How to avoid:** Pin both from the same source of truth: `region` and the subdomain of `endpoint` MUST match (`nyc3` + `https://nyc3.digitaloceanspaces.com`).
**Warning signs:** Local file backups work (`local` disk), Spaces backups fail with a signature/region error.

### Pitfall 6: Restoring into a `monthly_closings` row that already exists
**What goes wrong:** The restore succeeds but the unique index `(mess_id, year, month)` on `monthly_closings` (D-18 from Phase 3) is fine — the restore doesn't conflict because it replaces the whole table. The pitfall is more subtle: a half-finished restore can leave the DB in a state where a `CloseMonthJob` dispatched earlier has already started writing. `queue:restart` + `down` before the restore avoids this.
**Why it happens:** Close runs as a queued job; if one is in flight when the restore starts, both write to the same tables.
**How to avoid:** `Artisan::call('down')` + `Artisan::call('queue:restart')` as the FIRST two calls in `BackupRestoreService::restoreFromDisk()`, before any DB write. (Already in Pattern 4.)
**Warning signs:** A `monthly_closings` row with a timestamp DURING the restore window.

### Pitfall 7: Backup zip growth vs cleanup strategy
**What goes wrong:** Backups grow unbounded over 12 months because the monthly retention is too generous + the zip includes too much.
**Why it happens:** Photos + receipts accumulate; the zip includes the full `storage/app/public/` tree each time (not deltas).
**How to avoid:** (a) Set `MaximumStorageInMegabytes` in the spatie monitor health check (the spec's `BACKUP_MAX_MB=5000` starting point). (b) Periodically audit `storage/app/public/` for orphaned files (a member photo from a deleted member shouldn't live forever). (c) The 12-month monthly retention is correct per D-02 (immutable financial records), so DO NOT trim it to save $0.50/mo — the cleanup strategy's `delete_oldest_backups_when_using_more_megabytes_than` is the size guard, not the monthly retention.
**Warning signs:** `backup:monitor` reports `UnhealthyBackupWasFound` with `MaximumStorageInMegabytes` violated.

### Pitfall 8: Restoring the `.env` indirectly via a stale backup
**What goes wrong:** The `.env` accidentally ends up in the backup because the exclude rule was mis-scoped.
**Why it happens:** spatie's default `include` is `base_path()` — the whole project. If the planner blindly uses the default include, `.env`, `vendor/`, `node_modules/`, and framework cache all go into the zip and end up in object storage (secrets leak).
**How to avoid:** D-07 mandates excluding `.env`. The Pattern 1 config explicitly sets `include` to ONLY `storage_path('app/public')` + adds `base_path('.env')` to `exclude`. The `mysql` dump is added to the zip separately by spatie regardless of the file include list.
**Warning signs:** `unzip -l backup.zip | grep .env` returns a hit (run this in the restore-test as an assertion).

### Pitfall 9: Restore-test scratch DB growth / cross-contamination
**What goes wrong:** The scratch DB grows because the test keeps adding rows without cleaning up, OR the test reads from the scratch DB connection but the Laravel default connection is used somewhere, contaminating results.
**Why it happens:** Forgetting to wipe the scratch DB at the start of each run; typoing the connection name in one of the 26 COUNT queries.
**How to avoid:** Pattern 5 mandates wiping (DROP DATABASE + CREATE, or DROP TABLE loop with FK_CHECKS=0) at the START of every run. Run the restore-test in a `DB::connection('mysql_restore_test')->transaction(fn() => ...)` and never touch the default connection inside the test.
**Warning signs:** The scratch DB size grows monotonically across runs; restore-test COUNT mismatches against a perfectly good backup.

### Pitfall 10: GTID_PURGED error when restoring a DO-managed MySQL dump
**What goes wrong:** Restoring a dump taken from a DigitalOcean managed MySQL fails with `ERROR 3546 (HY000): @@GLOBAL.GTID_PURGED cannot be changed`.
**Why it happens:** DO managed databases enable GTID; `mysqldump` includes a `SET @@GLOBAL.GTID_PURGED` line that conflicts with the target server's GTID state.
**How to avoid:** Add `'add_extra_option' => '--set-gtid-purged=OFF --skip-disable-keys'` to the `dump` config. NOTE: this project uses a **self-managed MySQL on the VPS** (per `DEPLOYMENT.md` §2 `apt install mysql-server`), so GTID is off by default and this pitfall **does not apply** — but the planner should document it in the runbook in case the deploy later moves to a DO managed DB.
**Warning signs:** The exact `ERROR 3546` message during restore. `[CITED: github.com/stefanzweifel/laravel-backup-restore README "Troubleshooting"]`

## Code Examples

### A. BackupController — the index + run-now + download surface
```php
// Source: derived from existing app/Http/Controllers/Mess/AuditController.php pattern
// + .agents/skills/tyro-dashboard/SKILL.md "Add an app-level admin page" recipe
namespace App\Http\Controllers\Backup;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class BackupController extends Controller
{
    public function index()
    {
        // spatie's backup:list output is via Artisan; easier to glob the disk directly.
        $disk = Storage::disk(config('backup.destination.disks.0'));
        $backups = collect($disk->allFiles())
            ->filter(fn ($p) => str_ends_with($p, '.zip'))
            ->map(fn ($p) => [
                'path' => $p,
                'size' => $disk->size($p),
                'last_modified' => $disk->lastModified($p),
            ])
            ->sortByDesc('last_modified')
            ->values();

        $latestRestoreTest = \App\Models\RestoreTest::latest('id')->first();

        return view('dashboard.backups.index', compact('backups', 'latestRestoreTest'));
    }

    public function runNow()
    {
        // Dispatch async via the queue so the UI doesn't hang on a slow backup.
        Artisan::call('backup:run', []);
        return back()->with('status', __('Backup started.'));
    }

    public function download(string $path)
    {
        // Super-admin gate is on the route middleware. Add an audit-log row here.
        $disk = Storage::disk(config('backup.destination.disks.0'));
        abort_unless($disk->exists($path), 404);
        // Audit log: "super-admin downloaded backup <path>" (uses owen-itAuditable pattern)
        return response()->streamDownload(fn () => $disk->readStream($path), basename($path));
    }
}
```

### B. RestoreController — the typed-confirm + guarded POST
```php
namespace App\Http\Controllers\Backup;

use App\Http\Controllers\Controller;
use App\Http\Requests\Backup\RestoreRequest;
use App\Services\BackupRestoreService;
use Illuminate\Support\Facades\Log;

class RestoreController extends Controller
{
    public function show(string $path)
    {
        // Render the typed-confirm form. The super-admin gate is on the route.
        return view('dashboard.backups.restore', ['path' => $path]);
    }

    public function store(RestoreRequest $request, BackupRestoreService $service)
    {
        // RestoreRequest already validated: typed mess name === active mess name.
        try {
            $service->restoreFromDisk($request->validated('path'), $request->validated('mess_name'));
            // Audit log entry: the existing owen-it/laravel-auditing Auditable surface
            // OR a manual Audit::create(['action' => 'restore', ...]) row.
            return redirect()->route('dashboard.backups.index')
                ->with('status', __('Restore completed. The app is back online.'));
        } catch (\Throwable $e) {
            Log::error('Backup restore failed', ['exception' => $e]);
            // BackupRestoreService::restoreFromDisk ALWAYS calls Artisan::call('up') in its finally{}.
            return back()->withErrors(['restore' => __('Restore failed. App is back online; check logs.')]);
        }
    }
}
```
```php
// app/Http/Requests/Backup/RestoreRequest.php
class RestoreRequest extends FormRequest
{
    public function rules(): array
    {
        $activeMessName = \App\Models\Mess::active()?->name;  // the typed-confirm target
        return [
            'path' => ['required', 'string'],
            'mess_name' => ['required', 'string', "in:{$activeMessName}"],  // typed-confirm
        ];
    }
}
```

### C. Restoring via symfony/Process (the safe shell-out)
```php
// Source: symfony.com/components/Process [VERIFIED] + spatie/db-dumper pattern
use Symfony\Component\Process\Process;

private function restoreDatabase(string $sqlPath): void
{
    $cfg = config('database.connections.mysql');
    $process = new Process([
        'mysql',
        '--host='.$cfg['host'],
        '--port='.$cfg['port'],
        '--user='.$cfg['username'],
        '--password='.$cfg['password'],
        $cfg['database'],
        '-e', 'SOURCE '.$sqlPath,
    ]);
    $process->setTimeout(600);  // large dumps can take minutes
    $process->mustRun();
}
```

### D. The restore-test count comparison (mockable for D-08)
```php
// app/Services/RestoreTestService.php — the comparison logic is the unit-test target (D-08a)
public function compareCounts(): array
{
    $tables = [/* the 26 domain table names */];
    $results = [];
    foreach ($tables as $t) {
        $live = DB::table($t)->count();
        $test = DB::connection('mysql_restore_test')->table($t)->count();
        $results[$t] = ['live' => $live, 'test' => $test, 'pass' => $live === $test];
    }
    return $results;
    // The TEST (D-08a) feeds two known dumps and asserts pass=true when counts match,
    // pass=false when they diverge — without invoking real mysql. The restore part
    // of runLatest() is mocked; only compareCounts() runs for real.
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| spatie/laravel-backup v9 | v10 (PHP 8.4 + Laravel 12+) | March 2026 (v10 release) | v10 adds serializable events, resilient multi-destination backups, AES-256 archive encryption. Pin `^10.0`. |
| Hand-rolled mysqldump cron | `spatie/laravel-backup` `backup:run` on the scheduler | Standard for years | The dump + zip + upload + cleanup + monitor + notify loop is solved. Don't reinvent. |
| Restore via spatie (myth) | Custom restore (always was) | n/a — spatie never shipped restore | Confirms D-06. The community companion (`wnx/laravel-backup-restore`) does DB-only; we still build the file-restore + UI + gate. |
| `information_schema.TABLES.TABLE_ROWS` for counts | `SELECT COUNT(*)` for exact | Always (InnoDB estimate is statistical) | Restore-test assertions MUST use COUNT(*) or they'll false-pass. |
| AWS S3 as the only S3 endpoint | DO Spaces / Backblaze B2 / Cloudflare R2 (S3-compatible) | DO Spaces GA years ago; Laravel's `s3` disk takes an `endpoint` for all of them | D-02's "DO Spaces default" is the standard pattern; one composer dep + one disk block. |

**Deprecated/outdated:**
- `spatie/laravel-backup` v8 and earlier (PHP 8.2 era) — not compatible with PHP 8.4 / Laravel 13.
- `league/flysystem-aws-s3-v3:^1.0` (Flysystem v1 era) — Laravel 11+ requires Flysystem v3, so `^3.0` is the only valid pin.
- The myth that "spatie/laravel-backup restores" — confirmed false (D-06). The repo's own README + docs + Stefan Zweifel's companion-package announcement all confirm this.

## Validation Architecture

> `workflow.nyquist_validation` is **explicitly `false`** in `.planning/config.json`. This section is intentionally light — covering only the D-08 test discipline.

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit 12.5.30 (installed), Pint-clean standard |
| Config file | `phpunit.xml` (MySQL `devsroom_mess_management_testing`, `QUEUE_CONNECTION=sync`, `CACHE_STORE=array`) |
| Quick run command | `vendor/bin/phpunit tests/Feature/Backup --testdox` |
| Full suite command | `composer test` (= `php artisan config:clear --ansi && php artisan test`) |
| Coverage gate | >70% lines (pcov 1.0.12 verified in Plan 05-01) |

### D-08 Test Strategy (heavy processes MOCKED)
| Test | Target | Mocks | Asserts |
|------|--------|-------|---------|
| Restore-test comparison logic | `RestoreTestService::compareCounts()` | `DB::connection('mysql_restore_test')` (swap to a real test-DB connection or a Mockery mock that returns canned counts) | pass=true when counts match; pass=false when one table diverges |
| Backups UI auth gating | `BackupController@index` / `RestoreController@store` route middleware | none | super-admin 200, `admin`/`user`/guest 403 |
| Restore typed-confirm | `RestoreRequest` | none (form request test) | refuses without `mess_name`; refuses with wrong `mess_name`; passes with correct |
| Restore maintenance mode | `BackupRestoreService::restoreFromDisk` | `Artisan::call` (mock via `Artisan::fake()`), `Process` (Mockery) | `down` called before any DB write; `up` called in `finally` even on exception |
| Post-close listener | `CloseMonthJob::after()` or the Eloquent `saved: MonthlyClosing` listener | `Artisan::fake(['backup:run'])` | fires on successful close (wasRecentlyCreated); no-op on close failure |

**Wave 0 gaps to create before implementation:**
- `tests/Feature/Backup/.gitkeep` (Wave 0 scaffold like Plan 04-00 did for Reports)
- Optional: a tiny fixture `tests/Fixtures/backup-zips/sample.zip` (a real-but-tiny spatie zip with one trivial table dump + one trivial file) for the restore-test's parsing assertion — but per D-08, mocking `Process` + `Artisan` is preferred over fixture dumps.

## Security Domain

> `security_enforcement` is not explicitly set in `.planning/config.json`, so the standard ASVS L1 lens applies. Backup/restore is a destructive super-admin surface with PII (member data + financial snapshots) — this is the highest-stakes UI in the app.

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | yes | Tyro Login (already enforced); super-admin role gate is the second factor here |
| V3 Session Management | yes | `database` session driver (existing); maintenance mode invalidates UX but not sessions |
| V4 Access Control | **yes — critical** | `role:super-admin` route middleware + typed-confirm second factor + audit-log |
| V5 Input Validation | yes | `RestoreRequest` Form Request validates `path` + `mess_name`; route `->where('path', '.*')` constrains |
| V6 Cryptography | yes | DO Spaces server-side encryption at rest; `.env` excluded from backups (D-07); `APP_KEY` regeneration documented in the runbook |
| V7 Error Handling | yes | `BackupRestoreService::finally { Artisan::call('up'); }` — app always returns to live |
| V9 Communications | yes | HTTPS enforced (Forge/Let's Encrypt per `DEPLOYMENT.md` §6); download URLs are super-admin-only |
| V10 Malicious Code | n/a | no third-party code execution; restore runs the project's own SQL |
| V12 Files & Resources | yes | zip download is super-admin-only + access-logged; restore zip contains member PII |

### Known Threat Patterns for the backup/restore stack

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Unauthorized restore (insider or compromised `admin` account) | Elevation of Privilege | `role:super-admin` route middleware on every `/dashboard/backups/*` route + the typed-mess-name confirm as a second factor + audit log row per restore |
| CSRF on the restore POST | Tampering | `@csrf` in the restore form (Laravel default) + `VerifyCsrfToken` middleware (in the `web` group) |
| Path traversal via the `{path}` route param (download/restore a zip outside the backups disk) | Tampering / Information disclosure | `Storage::disk('backups')->exists($path)` aborts 404 if not found; route `->where('path', '.*')` is permissive but the disk boundary is the guard — never read from the local filesystem using the raw path |
| PII leak via backup zip download | Information disclosure | Super-admin-only download + an audit-log row per download (record user_id, path, IP, timestamp) |
| Restore raced with a queued write (CloseMonthJob mid-flight) | Tampering / Data loss | `Artisan::call('down')` + `Artisan::call('queue:restart')` BEFORE any DB write in `BackupRestoreService` |
| `.env` accidentally included in backup → secrets leak to object storage | Information disclosure | `backup.php` `exclude` explicitly lists `base_path('.env')`; restore-test asserts `unzip -l <zip> \| grep .env` returns nothing (Pitfall 8) |
| Backup zip tampering in object storage (man-in-the-cloud) | Tampering | DO Spaces server-side encryption + spatie v10's optional AES-256 archive encryption (Claude's discretion; deferred per CONTEXT.md but documented) |
| Brute-force the typed-confirm (try many mess names) | Elevation of Privilege | Laravel `throttle` middleware on the restore POST route (e.g. `throttle:5,1` — 5 attempts per minute); the typed target is one fixed string so this is low-risk but defense-in-depth |
| Restore-test scratch DB leaks live data | Information disclosure | Scratch DB is on the SAME host (no new attack surface); wiped every run; never exposed to the web |

**Audit-logging a restore (the locked spec mandates this implicitly via the project's `Auditable` standard):**
```php
// Every restore writes a manual audit entry alongside the existing owen-it surface.
// This is one of the few non-Eloquent-audit entries — restore is not a model write.
\App\Models\Audit::create([
    'user_id' => $request->user()->id,
    'action'  => 'backup.restore',
    'meta'    => ['path' => $path, 'mess_name_confirmed' => true, 'ip' => $request->ip()],
]);
```
(The exact audit surface — `owen-it/laravel-auditing\Auditable` vs the Tyro `audit_logs` table vs a domain `Audit` model — is a planner detail. The point is: every restore leaves a tamper-evident trail.)

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | `spatie/laravel-backup:^10.0` resolves cleanly under Laravel 13.15 + PHP 8.4 with the existing composer.json (no version conflict with `barryvdh/laravel-dompdf:^3.1`, `maatwebsite/excel:^3.1`, `owen-it/laravel-auditing:^14.0`). | Standard Stack | LOW — verified via docs that v10 needs only PHP 8.4 + Laravel 12+, but the actual `composer require` hasn't been run yet. If a conflict surfaces, fall back to `^9.9` or pin tighter. |
| A2 | `league/flysystem-aws-s3-v3:^3.0` is NOT already pulled in transitively by `spatie/laravel-backup` or any installed package, so it must be added explicitly. | Standard Stack | LOW — Laravel docs explicitly say to install it separately. If a `composer show` reveals it's already there, the explicit require is a no-op. |
| A3 | The existing MySQL install on the dev (Windows) box has `mysqldump.exe` available somewhere (MySQL Server bin dir). | Pitfall 2 | MEDIUM — if the dev box has MySQL via a non-standard installer (XAMPP's is at `C:\xampp\mysql\bin`), the path differs. The planner should verify with `where mysqldump` (Windows) and set `DUMP_BINARY_PATH` accordingly. |
| A4 | The Tyro dashboard has a published sidebar override OR supports the menu-injection extension point cleanly for a "Backups" link. | Architecture Patterns §3 | LOW — the Tyro skill explicitly documents both paths (publish sidebar partial, OR menu injection). If neither works, fall back to a route + a link rendered conditionally in the existing layout. |
| A5 | `Mess::active()->name` is the correct "typed-confirm" target string (i.e. the user must type the active mess's name to confirm a restore). | Code Examples B | MEDIUM — if the user expects to type the app name or the mess slug instead, the Form Request rule changes trivially. Confirm with the user at planning time. |
| A6 | DO Spaces server-side encryption is acceptable for the threat model (no client-side envelope encryption needed in v1). | Security Domain V6 | LOW — explicitly deferred in CONTEXT.md ("Claude's discretion to revisit if the threat model demands it"). |

## Open Questions

1. **Restore-test schedule: daily (default in CONTEXT.md) or on-demand only?**
   - What we know: D-04 says "periodic" + on-demand. Pattern 8 schedules it daily at 03:00.
   - What's unclear: a daily scratch-DB load is more thorough but adds DB load + 1 extra backup-restore cycle per day. For a single small mess, daily is fine; if the planner wants to minimize VPS load, weekly is defensible.
   - Recommendation: **daily** (the spec's default), surface the schedule in `routes/console.php` clearly so it's tunable.

2. **Audit-log surface for the restore action: `owen-it/laravel-auditing` Auditable, the Tyro `audit_logs` table, or a new domain `Audit` model?**
   - What we know: `owen-it/laravel-auditing` v14.0.4 is installed; the Tyro dashboard has its own audit surface; `AUDIT-05` says "Domain audit log is separate from Tyro's user/role audit log."
   - What's unclear: a restore is not a model write, so the `Auditable` trait doesn't fire automatically. The cleanest path is a manual `Audit::create()` against the existing domain audit surface — confirm the exact model/table name with the planner.
   - Recommendation: a manual write to the existing domain `audits` table (the `owen-it` surface), keyed by `action = 'backup.restore'`, so restores appear in the same audit view as every other sensitive write.

3. **`mess_name` (A5) vs `app_name` vs custom confirmation string for the typed restore confirm.**
   - What we know: D-03 says "type-the-mess-name."
   - Recommendation: the active mess's `name` column. If the user prefers a different confirmation string (app name, a custom phrase), trivial to swap.

4. **Is a server-level snapshot (Forge/DO droplet snapshot) part of this phase or strictly optional?**
   - What we know: CONTEXT.md `<deferred>` lists it as optional. `DEPLOYMENT.md` has no snapshot section.
   - Recommendation: leave out of Phase 6 (the spec is "spatie + runbook"); document in the runbook as "optional defense-in-depth" so the operator can enable it independently.

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP 8.4 + ext-zip | spatie/laravel-backup (create + extract zip) | ✓ | 8.4.15 + ext-zip (Plan 05-01 verified ext-zip loaded) | — |
| MySQL 8 (server + `mysql`/`mysqldump` CLI) | spatie dump + custom restore + restore-test | ✓ (dev Windows: see A3) | 8.x | — |
| `mysqldump` on PATH | spatie `backup:run` | ✓ prod (Ubuntu `/usr/bin/mysqldump`); ✗ likely dev-Windows unless `DUMP_BINARY_PATH` set | 8.x | set `DUMP_BINARY_PATH` in dev `.env` |
| DigitalOcean Spaces bucket + access key | `backups` s3 disk | ✗ (provisioned at deploy time, not yet) | n/a | Backblaze B2 / Cloudflare R2 / AWS S3 (all S3-compatible) |
| `league/flysystem-aws-s3-v3` | the `s3` disk driver | ✗ (not yet installed) | `^3.0` | — |
| `spatie/laravel-backup` | the backup engine | ✗ (not yet installed) | `^10.0` | — |
| Supervisor queue worker (existing) | `CloseMonthJob` + the post-close backup listener | ✓ (per DEPLOYMENT.md §4.3) | configured | — |
| Laravel scheduler cron (existing) | nightly `backup:run` + `backup:restore-test` | ✓ (per DEPLOYMENT.md §4.4) | configured | — |
| SMTP in prod | spatie failure emails | ✗ default `MAIL_MAILER=log` | — | in-app `Notification` row is the reliable channel; SMTP is a bonus |
| pcov / xdebug | coverage measurement (>70%) | ✓ pcov 1.0.12 (Plan 05-01 verified) | 1.0.12 | — |

**Missing dependencies with no fallback:**
- `spatie/laravel-backup` + `league/flysystem-aws-s3-v3` — first composer task of the phase; trivial install, no blocker.
- DO Spaces bucket + keys — provisioned by the operator (not a code blocker); the planner should make the `backups` disk env-driven so dev can run with the `local` disk as a stand-in (or skip `backup:run` entirely in dev per Pitfall 2 / the v10 Windows-incompat note).

**Missing dependencies with fallback:**
- SMTP for failure email — falls back to in-app `Notification` row (Pattern 7); the runbook documents enabling SMTP in prod.

## Sources

### Primary (HIGH confidence)
- **spatie/laravel-backup v10 docs** — https://spatie.be/docs/laravel-backup/v10/requirements (PHP 8.4 + Laravel 12+, NOT Windows-compatible), https://spatie.be/docs/laravel-backup/v10/installation-and-setup (`dump_binary_path` config location), https://spatie.be/docs/laravel-backup/v10/introduction (single zip = files + db dump), https://spatie.be/docs/laravel-backup/v10/taking-backups/events (event FQCNs)
- **spatie/laravel-backup config source** — https://github.com/spatie/laravel-backup/blob/main/config/backup.php (full structure: source.files.include/exclude, source.databases, destination.disks, cleanup.default_strategy retention ladder, monitor_backups health checks, notifications.notifications + channels)
- **Laravel 13.x Filesystem docs** — https://laravel.com/docs/13.x/filesystem ("Before using the S3 driver, you will need to install the Flysystem S3 package via Composer: `composer require league/flysystem-aws-s3-v3 "^3.0"`") — confirms NOT bundled
- **Laravel 13.x Queues docs** — https://laravel.com/docs/13.x/queues (maintenance mode pauses workers; `--force` overrides; `queue:restart` signals clean shutdown)
- **DigitalOcean Spaces Limits** — https://docs.digitalocean.com/products/spaces/details/limits/ (5 TB max object, multipart parts 5 MiB–5 GB, 10,000 parts max)
- **DigitalOcean Spaces + Laravel** — https://joelennon.com/using-digitalocean-spaces-in-laravel (the `s3` driver + `endpoint` + `region` pattern)
- **stefanzweifel/laravel-backup-restore README** — https://github.com/stefanzweifel/laravel-backup-restore/blob/main/README.md (confirms DB-only, no file restore, GTID troubleshooting)
- **MySQL 8.0 INFORMATION_SCHEMA TABLES docs** — https://dev.mysql.com/doc/refman/8.0/en/information-schema-tables-table.html (`TABLE_ROWS` is an InnoDB estimate — use `COUNT(*)`)
- **mysqldump manual** — https://dev.mysql.com/doc/refman/8.0/en/mysqldump.html (default output includes `SET FOREIGN_KEY_CHECKS=0/1`, so FK ordering is handled)

### Secondary (MEDIUM confidence)
- **Laravel News: Restore Database Backups in Laravel** — https://laravel-news.com/laravel-backup-restore (overview of `wnx/laravel-backup-restore`)
- **Spatie GitHub Discussion #1738** — https://github.com/spatie/laravel-backup/discussions/1738 (community confirmation that no native restore exists)
- **Spatie GitHub Issue #1389** — https://github.com/spatie/laravel-backup/issues/1389 (the `db-dumps/` folder structure change — Pitfall 1)

### Project-internal sources (HIGH confidence — read directly)
- `.planning/phases/06-backup-and-restore-system/06-CONTEXT.md` — D-01..D-08 locked decisions
- `.planning/phases/05-polish-pilot/05-CONTEXT.md` — D-15 deploy target, D-18 DEPLOYMENT.md
- `.planning/phases/03-payments-month-close/03-CONTEXT.md` — D-18/D-19 close-job idempotency + hard-lock, D-27/D-28 notification surface
- `DEPLOYMENT.md` — §3.3 scheduler, §4.3 supervisor, §5 prod `.env` checklist, §7 storage perms
- `app/Jobs/CloseMonthJob.php` — the post-close listener hook point (lifecycle methods)
- `app/Providers/AppServiceProvider.php` — the existing Eloquent-event-listener registration pattern to mirror
- `app/Services/NotificationService.php` — `broadcastToManagers()` is the spatie-failure wiring target
- `app/Services/MonthCloseService.php` — the in-close notification-dispatch pattern
- `config/filesystems.php` — the existing `s3` disk block (keys present, just unpopulated)
- `config/database.php` — the `mysql` connection block + the dump-key insertion point
- `config/tyro-dashboard.php` — the `resources` array (used for messes + settings); confirms the Backups UI should NOT use this
- `routes/web.php` — the `role:admin` / `role:super-admin` route-group pattern to mirror
- `routes/console.php` — the existing `class_exists` + `Schedule::command` pattern to mirror
- `composer.json` / `composer.lock` — Laravel 13.15, PHP ^8.3 (runtime 8.4), `owen-it/laravel-auditing` v14.0.4
- `.agents/skills/tyro-dashboard/SKILL.md` — "Add an app-level admin page" + "Add or change a sidebar item" recipes
- `.agents/skills/laravel-best-practices/SKILL.md` — §1 Consistency First, §3 Security, §14 Scheduling, §12 Events/Notifications

## Metadata

**Confidence breakdown:**
- Standard stack: **HIGH** — spatie v10 + flysystem-aws-s3-v3 + DO Spaces all verified against primary docs.
- Architecture (backup config + scheduling + post-close listener + UI route): **HIGH** — patterns mirror existing code in this repo; spatie config verified line-by-line against the published source.
- Custom restore orchestration: **HIGH on the approach, MEDIUM on the exact zip internals** — Pitfall 1 (zip layout) is the main residual risk; the `**/db-dumps/*.sql` glob mitigates it. Restoring via the `mysql` CLI is the well-trodden path.
- Restore-test job: **HIGH** — scratch-DB connection + COUNT(*) is a standard pattern; the only bespoke piece is the comparison logic.
- Pitfalls: **HIGH** — 10 concrete pitfalls with verified root causes; the Windows-incompat + dump-binary-path + DO-region-mismatch trio is the most likely to bite in execution.
- Security: **HIGH** — the threat model is standard for a destructive super-admin surface; all mitigations (role gate + typed confirm + CSRF + maintenance mode + audit log + super-admin-only download) are project-standard patterns.

**Research date:** 2026-06-19
**Valid until:** 2026-07-19 (30 days — stable stack; re-verify the spatie v10 latest patch version and any v11 announcement before implementation)
