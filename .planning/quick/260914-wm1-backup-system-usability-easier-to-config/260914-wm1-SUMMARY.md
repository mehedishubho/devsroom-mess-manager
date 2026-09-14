---
status: complete
quick_id: 260914-wm1
slug: backup-system-usability-easier-to-config
date: 2026-09-14
---

# Quick Task 260914-wm1 — Backup system usability: easier to configure & manage

## What shipped

Five atomic commits making the backup system configurable without guesswork and
manageable from one screen.

| Commit | Summary |
|---|---|
| `28c1ced` | One rotation rule + storage usage, growth, purge preview |
| `4895fba` | Per-run duration + archive size in the activity log |
| `87c6321` | One system-health panel instead of scattered banners |
| `0be8805` | **Bug fix:** `BackupConfig::current()` no longer memoizes the fallback default |
| `2ed4c4c` | Guided Google Drive connect, test-before-save, presets, inline help |

## Configure

- **Guided Google Drive** (`GoogleDriveController`): runs the real consent flow
  — offline access + consent prompt so Google returns a refresh token, the
  least-privilege `drive.file` scope, `state` held in the session and verified on
  return. Stores the refresh token and, when none is set, creates the
  destination folder and remembers its id. Uses the `google/apiclient` library
  already present via the Drive filesystem driver. Audit-logged.
- **Test connection on typed values** — the probe sends the current form values
  (blank secret = keep the stored one) and `Storage::forgetDisk()`s the target so
  it can't reuse a disk resolved with the old config. No save-first round trip.
- **Presets** — Light / Balanced / Maximum safety, applied client-side.
- **Inline help** per provider, including the exact redirect URI to register.

## Manage

- **System health panel** — Scheduler, Queue worker, Last backup and Off-site
  mirrors, each with its state, a one-line detail, and (when unhealthy) the exact
  command to run plus the relevant caveat (container vs host cron;
  `backup:install` for the absolute PHP path; `QUEUE_CONNECTION=sync`).
- **Storage & rotation** — per-destination count/size/oldest/newest with a
  reachable/unreachable marker; local usage against the cap (amber at 70%, rose
  at 90%); growth vs ~7 days (null until history exists, never a fake zero).
- **Purge preview** — "next cleanup will delete N archive(s) (X MB)" listing the
  real filenames, computed by the same `BackupRetention::plan()` the command
  enforces, so the preview cannot drift.
- **Run metrics** — `duration_ms` + `size_bytes` on each activity row, rendered
  as "took 12.4 s · archive 48.20 MB".
- **Plain-language config summary** in the Configuration heading.

## Bug found and fixed during verification

`BackupConfig::current()` cached its in-memory fallback whenever the early read
missed (`static::$current = static::find(1) ?? static::default()`).
`AppServiceProvider::boot()` calls it first, so a single transient miss poisoned
the whole process: the page, `backup:purge`, the schedule builder and
`CloudBackupCredentials` all then read **default** settings instead of the
operator's saved configuration — and writing through that unsaved instance
INSERTED a new row instead of updating row 1.

Reproduced cleanly (`find(1)` succeeded while `current()->exists` was false),
fixed so only a successfully loaded row is memoized, and the stray duplicate
`backup_configs` rows created by the bug were removed from the dev database
(verified back to exactly 1 row).

## Verification

No test runner exists in this repo (removed in `260724-pm2`), so:

- `php artisan migrate` — `add_metrics_to_backup_logs` applied.
- `php artisan view:cache` — all Blade compiles.
- `php artisan route:list --name=dashboard.backups` — 19 routes.
- `vendor/bin/pint --test` — PASS on every file touched.
- Tinker: `BackupRetention::plan()` → `backup:purge` still runs.
- Tinker: `RunBackupJob::dispatchSync()` → row updated with `duration_ms=1010`
  and `size_bytes=null` on a failed run (as designed).
- Tinker: `healthChecks()` reflection → correct rows/fixes for a scheduler-down
  + queue-down + no-backup + unreachable-mirror scenario.
- Tinker: Drive `createAuthUrl()` → correct `access_type=offline`, `prompt=consent`,
  `drive.file` scope, `state`, and — after the bug fix — a populated `client_id`.
- `BackupConfig::current()` → `id=1`, `exists=true` after the fix.

## Deviations

- The GSD planner sub-agent was not used: on the previous quick task it consumed
  100 turns without producing a plan file. The plan was authored directly by the
  orchestrator instead (same as `260914-uk4`).
- The `BackupConfig::current()` fix is a separate commit from the Task 4 feature
  work because it is an independent correctness fix, not a usability change.

## Not done / follow-ups

- The Google consent flow needs a real Google Cloud project + the redirect URI
  registered before it can be exercised end-to-end; only the URL construction is
  verified here. The redirect URI is derived from `APP_URL`, so production must
  have the correct HTTPS `APP_URL` (and register that exact URI).
- Growth-vs-7-days stays null on a fresh install until the page has been viewed
  on several days — by design, but worth knowing when eyeballing it.
