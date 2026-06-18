---
phase: 06-backup-and-restore-system
plan: 01
name: foundation
subsystem: backup-restore
tags: [backup, restore, infrastructure, spatie, do-spaces, config]
requires:
  - "Phase 5 deploy target (VPS + Forge + supervisor + MySQL 8 + Laravel scheduler)"
provides:
  - "config/backup.php — spatie/laravel-backup v10 project config (source, destination, retention, monitor, notifications)"
  - "config/filesystems.php `backups` s3 disk — DigitalOcean Spaces destination"
  - "config/database.php `mysql.dump` block — DUMP_BINARY_PATH wiring for Windows-dev / Linux-prod parity"
  - "config/database.php `mysql_restore_test` connection — scratch DB for Plan 06-02 RestoreTestService"
  - ".env.example + dev .env Phase 6 env blocks (DO_SPACES_*, BACKUP_*, DB_RESTORE_TEST_DATABASE, DUMP_BINARY_PATH)"
  - ".planning/phases/06-backup-and-restore-system/06-01-SMOKE.md — prod validation + DO Spaces provisioning hand-off"
affects:
  - "composer.json / composer.lock (new deps: spatie/laravel-backup ^10.0, league/flysystem-aws-s3-v3 ^3.0)"
tech-stack:
  added:
    - "spatie/laravel-backup 10.3.0 (composer require ^10.0 — resolved cleanly on Laravel 13.15 + PHP 8.4)"
    - "league/flysystem-aws-s3-v3 ^3.0 (Laravel keeps the AWS SDK optional — required for the s3 disk driver)"
    - "spatie/db-dumper 4.1.1 (transitive — shells out to mysqldump)"
    - "spatie/temporary-directory 2.3.1 (transitive)"
    - "spatie/laravel-package-tools 1.93.1 (transitive)"
    - "spatie/laravel-signal-aware-command 2.1.2 (transitive)"
  patterns:
    - "Laravel s3 disk with custom endpoint (DigitalOcean Spaces — D-02)"
    - "spatie/laravel-backup v10 StrictType config objects (Config\\*Config::fromArray) — requires exact v10 key names"
    - "Dump-binary-path env wiring on the mysql connection (Pitfall 2)"
decisions:
  - "Spatie v10.3.0 pinned (not v9 fallback) — installed cleanly on Laravel 13.15 + PHP 8.4; the research's Assumption A1 held."
  - "Backups disk declared separately from the general-purpose s3 disk so spatie's default is untouched."
  - "Optional AES-256 zip encryption wired but operator-supplied (empty = no client-side encryption; DO Spaces server-side encryption at rest is the base layer)."
  - "Restore code deliberately absent — D-06 (spatie is backup-only by design); restore lives in Plan 06-02."
key-files:
  created:
    - "config/backup.php"
    - ".planning/phases/06-backup-and-restore-system/06-01-SMOKE.md"
    - ".planning/phases/06-backup-and-restore-system/deferred-items.md"
  modified:
    - "composer.json"
    - "composer.lock"
    - "config/filesystems.php"
    - "config/database.php"
    - ".env.example"
metrics:
  duration: ~25 min
  tasks_completed: 3
  files_changed: 8
  completed: 2026-06-19
---

# Phase 6 Plan 06-01: Backup Foundation Summary

**One-liner:** Installed spatie/laravel-backup v10.3.0 + flysystem-aws-s3-v3, authored the project's `config/backup.php` (source=storage/app/public + .env excluded, DO Spaces destination, retention 14d/12mo, AES-256 optional), declared the `backups` s3 disk + a byte-identical `mysql_restore_test` connection + DUMP_BINARY_PATH wiring, and wrote a smoke doc handing the prod validation + DO Spaces provisioning off to the operator and the restore build off to Plan 06-02.

## What Shipped

### Task 1 — Install spatie/laravel-backup + flysystem-aws-s3-v3 + publish backup config
- `composer require spatie/laravel-backup:^10.0 league/flysystem-aws-s3-v3:^3.0` resolved cleanly to spatie 10.3.0 + flysystem-aws-s3-v3 3.x. **Assumption A1 held** — no v9 fallback needed.
- `php artisan vendor:publish --provider="Spatie\Backup\BackupServiceProvider" --tag="backup-config"` published the default `config/backup.php`.
- Authored `config/backup.php` with project values: `source.files.include = [storage/app/public)]`, `exclude` includes `base_path('.env')` (D-07), `follow_links=false` (Pitfall 4), `destination.disks = [env('BACKUP_DISK', 'backups')]`, retention `keep_daily=14 / keep_monthly=12` (D-02), 5000 MB growth guard (T-06-01-04), AES-256 zip encryption optional via `BACKUP_ARCHIVE_PASSWORD`.
- Verified via tinker: `config('backup.backup.destination.disks')` = `["backups"]`; `config('backup.cleanup.default_strategy.keep_monthly_backups_for_months')` = `12`.

### Task 2 — backups disk + mysql_restore_test + DUMP_BINARY_PATH + .env.example
- `config/filesystems.php`: added a dedicated `backups` s3 disk pointing at DO Spaces (5 `DO_SPACES_*` env keys, `throw=true`). Separate from the general-purpose `s3` disk (Pitfall 5: region MUST match endpoint subdomain).
- `config/database.php`: added a `dump` block inside `mysql` (`dump_binary_path` from `DUMP_BINARY_PATH`, `use_single_transaction=true`, `--quick --single-transaction` extra option — Pitfall 2) and a NEW `mysql_restore_test` connection byte-identical to `mysql` except for the database name. Verified via tinker: `config('database.connections.mysql_restore_test.database')` = `devsroom_mess_restore_test`.
- `.env.example` + dev `.env`: appended Phase 6 blocks (8 backup env keys + `DB_RESTORE_TEST_DATABASE` + `DUMP_BINARY_PATH`). Dev values empty (no real DO Spaces secret in dev).
- Verified live `mysql` connection still resolves: `DB::connection()->getPdo()` returns OK via tinker.

### Task 3 — Smoke doc
- Wrote `.planning/phases/06-backup-and-restore-system/06-01-SMOKE.md` (~135 lines) covering: (1) what shipped, (2) prod operator validation steps, (3) first-real-backup prod-only runbook, (4) Windows-dev incompat note quoting spatie v10 docs, (5) DO Spaces provisioning checklist, (6) hand-off to Plan 06-02, (7) known deviations, (8) what's explicitly out of scope for 06-01.

## Verification

All three tasks' `<verify>` blocks passed:

```
php -l config/backup.php           ✓ No syntax errors
php -l config/filesystems.php      ✓ No syntax errors
php -l config/database.php         ✓ No syntax errors
grep base_path('.env') backup.php  ✓ (D-07 .env exclusion enforced)
grep follow_links => false         ✓ (Pitfall 4)
grep keep_monthly=12 / keep_daily=14  ✓ (D-02 retention)
grep BACKUP_DISK=backups .env.example  ✓
grep backups disk / dump / mysql_restore_test / DO_SPACES_* in .env.example  ✓
vendor/bin/pint --test config/     ✓ passed
vendor/bin/phpunit --testdox       ✓ OK (243 tests, 576 assertions) — NO regression
```

Pre-existing `config:cache` failure (tyro-login Closure non-serializable) was logged to `deferred-items.md` — out of scope, not introduced by this plan.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 — Bug] spatie v10 config key renames**
- **Found during:** Task 1 (config:cache blew up)
- **Issue:** The plan's research/Pattern 1 used v9-era key names: `source.files.relative_root`, `backup.zip.encryption_password`/`encryption_method`, `database_dump_filename_base='db'`. v10 renamed/retyped several: `relative_root` → `relative_path`; the `zip` block was flattened to `password` + `encryption` (a string enum: `'none'`/`'default'`/`'aes128'`/`'aes192'`/`'aes256'`); `database_dump_filename_base` requires the enum value `'database'` or `'connection'` (NOT `'db'`). The published default's full key set was the source of truth.
- **Fix:** Rewrote `config/backup.php` to mirror the actual published v10 structure verbatim, with the project's specific values layered in (source/destination/retention/notifications).
- **Files modified:** `config/backup.php`
- **Commit:** `ed76c31` (initial), `0695661` (final v10-aligned shape)

**2. [Rule 1 — Bug] `notifications.mail.to` empty-string vs null quirk**
- **Found during:** Task 2 (artisan CLI invocations blew up with `InvalidConfig::invalidEmail`)
- **Issue:** The plan's `env('BACKUP_NOTIFICATION_EMAIL', env('MAIL_FROM_ADDRESS'))` returns `''` (empty string, not null) when `BACKUP_NOTIFICATION_EMAIL=` is present in `.env`. Laravel's `env()` does NOT engage the default for empty-string values, so spatie received `''` and `filter_var('', FILTER_VALIDATE_EMAIL)` returned `false`, throwing `InvalidConfig::invalidEmail`. (Note: the PHPUnit suite passed because it doesn't bootstrap Artisan commands that load spatie's lazy `Config::fromArray` — the tinker CLI does.)
- **Fix:** Wrapped the `notifications` block in a runtime closure that treats empty strings as null, so the `MAIL_FROM_ADDRESS` fallback engages cleanly.
- **Files modified:** `config/backup.php`
- **Commit:** `0695661`
- **Verified:** `php artisan tinker --execute="echo config('backup.notifications.mail.to');"` returns `hello@example.com` in dev.

### Out-of-scope Discoveries

**3. [Out of scope — logged] `php artisan config:cache` is pre-existing-broken**
- **Found during:** Task 1 verify step
- **Symptom:** `LogicException: Your configuration files could not be serialized because the value at "tyro-login.redirects.after_login" is non-serializable.`
- **Root cause:** `hasinhayder/tyro-login`'s config registers a Closure for `redirects.after_login`.
- **Verified pre-existing:** Reproduced on `git stash` of a clean tree (commit `c6dcc9c` before this plan). NOT introduced by 06-01.
- **Action:** Logged to `.planning/phases/06-backup-and-restore-system/deferred-items.md`. Not fixed. The dev workflow already uses `config:clear` per Plan 05-01 convention.

## Authentication Gates

None. The plan never touched a system requiring authentication (operator-only DO Spaces provisioning is documented in the smoke doc + plan frontmatter `user_setup`, but is not a code-side auth gate).

## Known Stubs

None. Plan 06-01 ships config + docs only — no UI, no service code, no job code, no test code. The smoke doc's §6 explicitly enumerates what Plan 06-02 will build on top of these artifacts (RestoreTestService consumes the `mysql_restore_test` connection; BackupRestoreService reads from the `backups` disk; the schedule/console commands land in Plan 06-02's `routes/console.php`).

## Threat Flags

None new beyond the plan's `<threat_model>` register. The shipped config directly implements the mitigations called out in the threat register:
- **T-06-01-01 (.env leak)**: `exclude` array contains `base_path('.env')` (verified by grep).
- **T-06-01-04 (unbounded growth)**: `MaximumStorageInMegabytes=5000` health check + `delete_oldest_backups_when_using_more_megabytes_than=5000` cleanup guard both wired.
- **T-06-01-05 (notification SMTP creds)**: `from.address` + `to` use env() lookups only — no hardcoded addresses.

No new network endpoints, auth paths, file access patterns, or trust-boundary schema changes were introduced by this plan.

## Self-Check: PASSED

- `config/backup.php` — FOUND
- `config/filesystems.php` (backups disk) — FOUND (`grep "'backups' =>" config/filesystems.php` ✓)
- `config/database.php` (dump + mysql_restore_test) — FOUND (`grep "'mysql_restore_test' =>" config/database.php` ✓)
- `.env.example` (Phase 6 blocks) — FOUND (`grep "DO_SPACES_KEY=" .env.example` ✓)
- `.planning/phases/06-backup-and-restore-system/06-01-SMOKE.md` — FOUND
- Commit `ed76c31` (Task 1) — FOUND via `git log --oneline`
- Commit `0695661` (Task 2) — FOUND via `git log --oneline`
- Commit `c039eab` (Task 3) — FOUND via `git log --oneline`
- PHPUnit: 243 tests / 576 assertions PASS (no regression)
- Pint: `config/` clean
