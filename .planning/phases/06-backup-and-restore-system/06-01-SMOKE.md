# 06-01 Smoke Test: Backup Configuration Foundation

**Phase:** 06 — Backup and Restore System
**Plan:** 06-01 (Foundation)
**Status:** Configuration valid; backup:run is wired but PROD-ONLY
**Last updated:** 2026-06-19

---

## 1. What this plan shipped

Plan 06-01 laid the foundation for the backup engine without yet exercising a real backup. The artifacts:

- **`composer.json` / `composer.lock`** — added `spatie/laravel-backup:^10.0` (resolved to 10.3.0) and `league/flysystem-aws-s3-v3:^3.0`. Spatie auto-discovers its `BackupServiceProvider`, so no manual provider registration was needed.
- **`config/backup.php`** — authored the project's spatie config per D-02/D-07:
  - `source.files.include` = `storage/app/public` ONLY (profile photos + bazar receipts)
  - `source.files.exclude` explicitly lists `base_path('.env')` so secrets never leave the server
  - `source.files.follow_links = false` (Pitfall 4 — public/storage symlink)
  - `destination.disks = [env('BACKUP_DISK', 'backups')]` — DO Spaces via the `backups` disk
  - Retention ladder: `keep_all=7d`, `keep_daily=14d`, `keep_weekly=8w`, `keep_monthly=12mo`, `keep_yearly=2y`, plus a 5000 MB growth guard
  - AES-256 zip encryption optional via `BACKUP_ARCHIVE_PASSWORD` (empty = no encryption; DO Spaces server-side encryption is the base layer)
  - Failure notifications wired to `mail` channel (every spatie notification class)
- **`config/filesystems.php`** — added a dedicated `backups` s3 disk pointing at DigitalOcean Spaces (separate from the general-purpose `s3` disk so spatie's default is untouched).
- **`config/database.php`** — added a `dump` block on the `mysql` connection (`dump_binary_path` from `DUMP_BINARY_PATH`, `use_single_transaction=true`, `--quick --single-transaction` extra option) and a NEW `mysql_restore_test` connection byte-identical to `mysql` except for the database name. Plan 06-02's `RestoreTestService` will use it.
- **`.env.example` + dev `.env`** — appended the Phase 6 env blocks: `DO_SPACES_*` (5 keys), `BACKUP_DISK`, `BACKUP_MAX_MB`, `BACKUP_NOTIFICATION_EMAIL`, `BACKUP_ARCHIVE_PASSWORD`, `DB_RESTORE_TEST_DATABASE`, `DUMP_BINARY_PATH`. Dev values are empty (no real DO Spaces secret).

## 2. How to validate the config (operator steps on the prod VPS)

These steps prove the config is valid and the disk declaration is correct WITHOUT producing a real backup yet:

```bash
# 1. Recompile the config cache (do NOT run config:cache if your deploy disables it).
php artisan config:clear && php artisan config:cache

# 2. backup:list should print an empty table (no backups yet) WITHOUT erroring.
#    If it errors, the backups disk declaration or DO_SPACES_* env is wrong.
php artisan backup:list

# 3. tinker inspection: confirm the key paths resolve to the expected values.
php artisan tinker
> config('backup.backup.destination.disks');
// => ["backups"]

> config('backup.cleanup.default_strategy.keep_monthly_backups_for_months');
// => 12

> config('backup.cleanup.default_strategy.keep_daily_backups_for_days');
// => 14

> config('database.connections.mysql_restore_test.database');
// => "devsroom_mess_restore_test"  (or whatever DB_RESTORE_TEST_DATABASE is set to)

> config('database.connections.mysql.dump.dump_binary_path');
// => "/usr/bin"  (or whatever DUMP_BINARY_PATH is set to)
```

## 3. Running the first real backup on the prod VPS (PROD ONLY)

Once the DO Spaces bucket is provisioned (see §5) and the prod `.env` keys are populated:

```bash
# A. DB-only smoke (faster than a full zip; proves mysqldump + s3 upload path).
php artisan backup:run --only-db

# B. Full backup (DB dump + storage/app/public files).
php artisan backup:run

# C. Confirm the zip landed on the backups disk.
php artisan backup:list

# D. Confirm the monitor health checks pass (fresh-enough + size-under-cap).
php artisan backup:monitor
```

A successful first `backup:run` is the green light that Plan 06-01's plumbing works end-to-end.

## 4. Why backup:run is NOT run on Windows dev

Spatie's v10 docs explicitly state:

> *"It's not compatible with Windows servers."*
> — https://spatie.be/docs/laravel-backup/v10/requirements

The dev OS for this project is Windows. Therefore the dev workflow for Plan 06-01 was to **exercise the config** (`config:clear`, `config:cache`, `tinker` inspection of the resolved values) but NOT to invoke `php artisan backup:run` locally. The `DUMP_BINARY_PATH` env var exists for the rare case the operator wants to test on a Linux dev box, or to point at a Windows MySQL Server bin dir for partial runs (still unsupported by spatie v10 itself).

Backups run in **prod on Ubuntu** (per `DEPLOYMENT.md` §2 `apt install mysql-server`) where `mysqldump` is on PATH at `/usr/bin/mysqldump` and the filesystem path assumptions spatie makes hold.

## 5. DO Spaces provisioning checklist

These operator steps provision the bucket and access keys. They live in the plan's `user_setup` frontmatter and will be repeated in the restore runbook that Plan 06-04 adds to `DEPLOYMENT.md`:

1. **Create the Space** in the DO control panel → Spaces → Create Space. Suggested name: `devsroom-mess-backups`. Note the region (e.g. `nyc3`).
2. **Generate Spaces access keys** in the DO control panel → Spaces → Settings → Spaces Keys → Generate new key. Record both the Key and the Secret (the Secret is shown once).
3. **Set the prod `.env` keys**:
   ```env
   DO_SPACES_KEY=...                # from step 2
   DO_SPACES_SECRET=...             # from step 2 (shown once)
   DO_SPACES_REGION=nyc3            # MUST match the endpoint subdomain
   DO_SPACES_BUCKET=devsroom-mess-backups
   DO_SPACES_ENDPOINT=https://nyc3.digitaloceanspaces.com
   BACKUP_NOTIFICATION_EMAIL=ops@example.com   # or leave blank to fall back to MAIL_FROM_ADDRESS
   BACKUP_ARCHIVE_PASSWORD=...                  # optional AES-256 zip encryption
   ```
4. **Verify region/endpoint match** (Pitfall 5). `DO_SPACES_REGION` MUST match the subdomain of `DO_SPACES_ENDPOINT`:
   - `nyc3` + `https://nyc3.digitaloceanspaces.com` ✓
   - `sfo3` + `https://sfo3.digitaloceanspaces.com` ✓
   - `nyc3` + `https://sfo3.digitaloceanspaces.com` ✗ (signature errors)

## 6. What Plan 06-02 will build on top of these artifacts

Plan 06-02 (backend restore + restore-test + tests) consumes this plan's outputs:

- **`BackupRestoreService`** reads backups from the `backups` disk via `Storage::disk('backups')`, unzips, locates the `db-dumps/<dbname>.sql` (via a `BackupPathResolver` glob to handle Pitfall 1 — the dump folder may be nested), restores via the `mysql` CLI, and copies files back to `storage/app/public/` — all behind `Artisan::call('down')` + `Artisan::call('queue:restart')` (maintenance-mode + queue-stop mandatory per D-03).
- **`RestoreTestService`** uses the `mysql_restore_test` connection already declared here (Plan 06-01) to load the latest backup's dump into the scratch DB and assert per-table `COUNT(*)` parity against the live `mysql` connection. Wipes the scratch DB at the start of every run (Pitfall 9).
- **`backup:run` schedule + `backup:restore-test` command** land in `routes/console.php` (mirroring the existing `telescope:prune` `class_exists` guard pattern). Nightly backup at 01:30, daily restore-test at 03:00, both `withoutOverlapping()->onOneServer()`.
- **Post-close backup listener** hooks `CloseMonthJob` success and dispatches an ad-hoc `backup:run --only-db` so the highest-value immutable write of the month is captured immediately (D-05).
- **Super-admin Backups UI** (`/dashboard/backups` — custom controller, not a Tyro dynamic resource) consumes the `backups` disk listing and the restore-test health badge.

## 7. Known deviations from the original plan

- **Spatie v10 key renames** (Rule 1 — Bug fix). The plan's research/Pattern 1 was based on v9 docs. v10 renamed several keys: `source.files.relative_root` → `relative_path`; `backup.zip.encryption_password` / `encryption_method` → flat `backup.password` + `backup.encryption` string (`'default'` = AES-256 when available); the per-section `monitor_backups[].health_checks` shape is unchanged. The committed `config/backup.php` follows v10's actual published structure verbatim.
- **`notifications.mail.to` fallback chain** (Rule 1 — Bug fix). Spatie v10 strictly validates mail addresses at config-load time. The plan's `env('BACKUP_NOTIFICATION_EMAIL', env('MAIL_FROM_ADDRESS'))` returns an empty string `''` when `BACKUP_NOTIFICATION_EMAIL=` is present in `.env` (Laravel's `env()` does not engage the default for empty-string values), which spatie's `filter_var` rejects. The committed config uses a runtime closure that treats empty strings as null so the fallback chain engages cleanly. Verified via tinker: `config('backup.notifications.mail.to')` resolves to `hello@example.com` in dev.
- **`config:cache` is pre-existing-broken** (out of scope, logged to `deferred-items.md`). The Tyro-Login package's `redirects.after_login` config value is a non-serializable Closure, so `php artisan config:cache` fails on a clean tree BEFORE this plan's changes too. The dev workflow uses `config:clear` (Plan 05-01 convention). This is not introduced by Plan 06-01 and is not fixed here.

## 8. Plan 06-02 onward — what's NOT in 06-01

Plan 06-01 deliberately ships no restore logic (D-06 — spatie is backup-only). The following are out of scope for this plan and land in 06-02 / 06-03 / 06-04:

- Any restore orchestration code (`BackupRestoreService`, `RestoreController`, the typed-confirm form)
- The restore-test job + the `restore_tests` migration + `RestoreTestService`
- Any scheduled `backup:run` / `backup:restore-test` command in `routes/console.php`
- The super-admin Backups UI (`/dashboard/backups`)
- The post-`CloseMonthJob` backup listener
- The restore runbook section in `DEPLOYMENT.md`
