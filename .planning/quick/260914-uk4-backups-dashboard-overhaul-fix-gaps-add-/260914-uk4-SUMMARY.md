---
status: complete
quick_id: 260914-uk4
slug: backups-dashboard-overhaul-fix-gaps-add-
date: 2026-09-14
---

# Quick Task 260914-uk4 — Backups dashboard overhaul

## What shipped

Six atomic commits overhauling `/dashboard/backups`: the verified bugs are
fixed, the dead DigitalOcean Spaces system is gone, and the page gained the
operational features it was missing.

| Commit | Task | Summary |
|---|---|---|
| `f044870` | 1 | Removed the DigitalOcean Spaces destination system entirely |
| `6025954` | 2 | Purge logging, all-disk delete, monitor noise, log audit, log retention |
| `5c7187e` | 3 | Clearable secrets, notification email, archive encryption, test notification |
| `7aea69c` | 4 | Stats header, per-destination status, checksums, search/sort/paging, bulk delete |
| `a4b6214` | 5 | Filterable/paginated/exportable activity log with actionable failure hints |
| `ceb2593` | 6 | Queued backup + restore, safety backup, upload restore, maintenance recovery |

## Bugs fixed (all verified against the code before changing it)

1. **Purge was invisible.** `backup:purge` (the scheduled rotation command) never
   wrote a `BackupLog` row, and spatie's `Cleanup*` events only fire from
   `backup:clean` — which is not scheduled. Rotation now logs success/failure
   with the deleted paths and the real error.
2. **UI delete was local-only.** `BackupController::destroy()` deleted from
   `backups-local` while `PurgeBackups` fanned out over every destination — a
   delete looked successful while the cloud mirror copy survived. Both paths now
   fan out over `BackupDestinations::all()`.
3. **Monitor spam buried failures.** A healthy `backup:monitor` result was logged
   every night at 02:00. It is now logged only on a state change (a recovery from
   a failure); unhealthy results always log.
4. **Log deletion was unaudited.** `destroyLog()` / `clearLogs()` now write manual
   Audit rows like download/delete/restore already did.
5. **The restore ran on the request thread.** A gateway timeout could kill the
   worker before `finally { artisan up }`, stranding the site in maintenance mode.
6. **`backup_logs` grew without bound** — new `backup:prune-logs`, scheduled 03:00.
7. **Saved secrets could not be cleared** — the "empty = keep" rule made a
   rotated/leaked credential unremovable from the UI.
8. **Dead `enabled_spaces` column** dropped.

## Features added

- Stats header: last successful backup + age, archive count, total size, next run.
- Per-archive destination presence (Local / Drive / R2).
- sha256 per archive: recalled from cache, computed on demand by a **Verify**
  action; a mismatch is audit-logged and reported as a failure.
- Search by filename, sortable columns, pagination, total + filtered size.
- Bulk select + bulk delete across every destination.
- Activity log: filter by action/status (whitelisted), paginate, CSV export, and
  a "what to do" hint on known failure signatures.
- Notification email + AES-256 archive encryption configurable from the page
  (with a "send test notification" action); `.env` remains the fallback.
- Pre-restore safety backup, so a bad restore is reversible.
- Restore from an uploaded `.zip` (validated, ≤ 500 MB, typed confirm, throttled).
- Queued backup/restore with an in-place-updating `running` activity row.
- "Bring the app back online" recovery action.

## Deviations / decisions

- **No test suite.** `tests/` and `phpunit.xml` were removed in quick task
  260724-pm2; this task did not reintroduce one. Verification used the project's
  actual gates (below) plus two targeted tinker smokes.
- **Queue now required for the UI.** "Backup now" and restores are queued, so the
  queue worker must be running. This is a behaviour change and is documented in
  DEPLOYMENT §11.3/§11.4 and the README.
- **Purge logs every run** (one row/day) rather than only on deletion — knowing
  rotation ran at all is the point; the volume is negligible.
- **`RestoreController` no longer injects `BackupRestoreService`** — the job owns
  it. The controller keeps the upload audit trail.
- The daily `backup:monitor` healthy-state comparison reads the previous
  `monitor` log row rather than adding a state table.

## Verification (project has no test runner)

- `php artisan migrate` — both new migrations applied (drop `enabled_spaces`;
  add `notification_email` / `encrypt_backups` / `archive_password`).
- `php artisan view:cache` — all Blade compiles.
- `php artisan route:list --name=dashboard.backups` — 17 routes, all present.
- `php artisan schedule:list` — `backup:purge` 01:00, `backup:run` 01:30,
  `backup:monitor` 02:00, `backup:prune-logs` 03:00.
- `vendor/bin/pint --test` — PASS on every file this task touched. (9 pre-existing
  style issues elsewhere in the repo were left alone — out of scope.)
- Tinker smoke 1: `backup:purge` wrote a `purge`/`success` row.
- Tinker smoke 2: `RunBackupJob::dispatchSync()` on a `running` row updated that
  same row in place to `failure` with the real mysqldump diagnostic — confirming
  the progress mechanism and the duplicate-logging mute.
- `BackupArchive::destinations()` / `checksum()` / `cachedChecksum()` verified.
- `grep -ri "do_spaces\|digitalocean"` outside `.planning/` returns nothing
  related to backups (remaining hits are VPS-provider prose, unrelated to the
  removed destination).

## Not done / follow-ups

- `backup:run` itself could not be exercised on this Windows dev box (spatie v10
  is Linux-only; `DUMP_BINARY_PATH` points at `/usr/bin`), so the end-to-end
  archive creation and a real restore still need the prod/Linux verification
  already listed as Phase 6's human-verification items.
- The 9 pre-existing Pint issues elsewhere in the repo remain.
