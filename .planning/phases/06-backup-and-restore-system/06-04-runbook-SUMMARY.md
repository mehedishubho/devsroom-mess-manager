---
phase: 06-backup-and-restore-system
plan: 04
name: runbook
subsystem: backup-restore
tags: [docs, deployment, runbook, disaster-recovery, d-02, d-03, d-05, d-07]
requires:
  - "Plan 06-01 foundation (config/backup.php + .env.example Phase 6 keys)"
  - "Plan 06-02 backend service layer (BackupRestoreService + RestoreTestService + schedule + listeners)"
  - "Plan 06-03 super-admin UI (/dashboard/backups route group + typed-confirm + audit rows)"
provides:
  - "DEPLOYMENT.md §11 Backup & restore runbook — 9 subsections (11.1-11.9) covering what/where/schedule/UI-restore/CLI-restore/DO-Spaces/SMTP/host-snapshot/troubleshooting"
  - "DEPLOYMENT.md §5 Phase 6 sub-table — the 11 prod env keys an operator must provision"
  - ".env.example Phase 6 inline documentation — one-line # comments above each Phase 6 key block"
affects:
  - "DEPLOYMENT.md (footer Last updated line + §5 extension + §11 append — §1-§4 + §6-§10 untouched)"
  - ".env.example (Phase 6 block comments expanded; no key VALUES changed)"
tech-stack:
  added: []
  patterns:
    - "Runbook subsection numbering (§11.1-§11.9) — mirrors §10 troubleshooting table style for the §11.9 row"
    - "Code-wins reconciliation (Rule 1) — where the plan's draft text diverged from the actually-shipped behavior in Plans 06-01/02/03, the runbook describes the shipped behavior, not the plan draft"
decisions:
  - "Described the post-close hook as 'backup:run --only-db' (per Plan 06-02 CloseMonthJob::after) rather than the plan's draft 'backup:run' — the actually-shipped code passes ['--only-db' => true] to Artisan::call, which is the safer + faster post-close capture."
  - "Used Mess::find(Mess::activeId())->name as the typed-confirm target description (per Plan 06-03 RestoreRequest/BackupController::activeMessName) — NOT the plan/research's Mess::active()->name (which does not exist on the Mess model)."
  - "Referenced config('backup.backup.destination.disks.0', 'backups') (the nested spatie v10 key BackupRestoreService::downloadAndExtract + BackupController::backupDisk both use) — NOT the plan's flat config('backup.destination.disks.0')."
  - "Listed the 3 audit event names actually written by Plan 06-03 controllers: event='backup.restore' (success), event='backup.restore.failed' (caught exception), event='backup.download' (every download)."
  - "Added an 8th troubleshooting row (month-close completed but no immediate backup landed) to document the post-close hook's try/catch behavior (T-06-02-07) — the plan's verify block only required §11.9 to exist, not a specific row count, so this is an additive improvement inside the rule-1 reconciliation envelope."
  - "Lowercased 'scratch DB' in the .env.example comment so the plan's case-sensitive grep verify block (grep -q 'scratch DB' .env.example) passes verbatim — the plan's verify gate is the done criterion, not stylistic preference."
key-files:
  created:
    - ".planning/phases/06-backup-and-restore-system/06-04-runbook-SUMMARY.md"
  modified:
    - "DEPLOYMENT.md"
    - ".env.example"
metrics:
  duration: ~12 min
  tasks_completed: 1
  files_changed: 2
  tests_added: 0
  completed: 2026-06-19
---

# Phase 6 Plan 06-04: Backup & Restore Operator Runbook Summary

**One-liner:** Shipped the operator-facing disaster-recovery runbook as DEPLOYMENT.md §11 "Backup & restore runbook" (9 subsections: what/where/schedule/UI-restore/CLI-restore/DO-Spaces/SMTP/host-snapshot/troubleshooting), extended DEPLOYMENT.md §5 prod-env checklist with an 11-key Phase 6 sub-table, and added explanatory inline comments to the Phase 6 keys already in `.env.example` — every runbook claim reconciled against the actually-shipped behavior in Plans 06-01/02/03 (spatie v10 nested config key, post-close `--only-db` hook, `Mess::activeId()` typed-confirm target, the 3 audit event names, the 17-table restore-test parity set), and the full PHPUnit suite confirmed green at 278 tests (no code touched).

## What Shipped

### Task 1 — DEPLOYMENT.md §11 + §5 extension + .env.example comments

**`DEPLOYMENT.md`** — three additive changes; §1-§4 and §6-§10 (the Plan 05-03 deploy content) untouched:

- **§11 "Backup & restore runbook"** (NEW — appended after §10 "When something breaks"). 9 subsections mirroring the project's existing documentation voice:
  - **§11.1 What gets backed up** — single zip = full `mysqldump` of the `mysql` connection (all 26+ domain tables, `--single-transaction --quick`) + everything under `storage/app/public/`; explicit callout that `.env` is EXCLUDED (D-07) so credential regeneration is required post-restore.
  - **§11.2 Where backups live** — off-server DigitalOcean Spaces via the `backups` s3 disk; retention ladder verbatim (keep_all=7d, keep_daily=14d, keep_weekly=8w, keep_monthly=12mo, keep_yearly=2y, 5000 MB growth guard); explicit "NO local copy" note.
  - **§11.3 Schedule** — 4-row table of the nightly commands (01:00 clean / 01:30 run / 02:00 monitor / 03:00 restore-test, all `class_exists`-guarded + `withoutOverlapping()->onOneServer()`); on-demand via UI + CLI; post-close hook documented as `Artisan::call('backup:run', ['--only-db' => true])` from `CloseMonthJob::after()` (try/catch — T-06-02-07), `failed()` is a no-op.
  - **§11.4 Restore PRIMARY (UI)** — 8 numbered steps from login → review health badge → download (audit row) → click Restore → typed active-mess-name confirm (`Mess::find(Mess::activeId())->name`, `RestoreRequest`, throttle:5,1 T-06-03-04) → service orchestration (down → queue:restart → Finder-based dump locator → Symfony Process array-args mysql restore → storage_path('app/public') file copy → spot-check → up-in-finally) → audit rows (`backup.restore` success, `backup.restore.failed` failure, never escapes — T-06-03-07).
  - **§11.5 Restore FALLBACK (CLI)** — 9 numbered steps for the white-screen case: SSH → `artisan down` → fetch zip (tinker snippet OR `s3cmd`/`aws`/`rclone` if Laravel itself can't boot) → unzip + locate `db-dumps/*.sql` → `mysql < dump.sql` → `cp` files + `storage:link` → **REQUIRED step 7**: `php artisan key:generate` + DB/Spaces credential rotation because `.env` is excluded (D-07 / T-06-04-02); explicit note that no v1 domain columns are APP_KEY-encrypted so the only impact is session invalidation → `artisan up` → smoke + restore-test. Also documents the GTID caveat (Pitfall 10) for the future DO-Managed-MySQL migration case.
  - **§11.6 Configure DO Spaces** — 7 numbered one-time-setup steps (create Space → generate keys → set 7 env keys → config:cache → first `backup:run --only-db` smoke → full `backup:run` → `backup:restore-test` → region/endpoint match verification per Pitfall 5).
  - **§11.7 Enable failure notifications** — the dual channel (in-app bell via `NotificationService::broadcastToManagers(NotificationType::BACKUP_FAILED, ...)` always works; email via spatie's mail channel requires `MAIL_MAILER=smtp`); the full SMTP env block; explicit blockquote warning "Do NOT ship prod with `MAIL_MAILER=log`" (T-06-04-03).
  - **§11.8 Optional host-level snapshot** — Forge daily snapshot OR DO Droplet scheduled snapshots as a second decoupled copy; explicit note that snapshots INCLUDE `.env` so access must be restricted in the DO/Forge control panel (T-06-04-04); marked OPTIONAL per CONTEXT.md deferred note.
  - **§11.9 Troubleshooting** — 8-row table mirroring §10's style: Windows-dev `mysqldump` PATH (Pitfall 2), DO Spaces region/endpoint mismatch signature error (Pitfall 5), `UnhealthyBackupWasFound`, stale restore-test scratch DB, "Restore failed. App is back online" exception path, clobbered `public/storage` symlink (Pitfall 4), GTID error (Pitfall 10, DO Managed DB only), and the post-close-hook-threw case (T-06-02-07).
- **§5 prod `.env` checklist** — appended a Phase 6 sub-table (same `| Key | Value | Why |` format as the existing table) with all 11 keys: `DO_SPACES_KEY/SECRET/REGION/BUCKET/ENDPOINT`, `BACKUP_DISK`, `BACKUP_MAX_MB`, `BACKUP_NOTIFICATION_EMAIL`, `BACKUP_ARCHIVE_PASSWORD`, `DB_RESTORE_TEST_DATABASE`, `DUMP_BINARY_PATH`. Each row cross-references the decision/pitfall that motivates it (D-02, Pitfall 5, Pitfall 2).
- **Footer** — "Last updated" line advanced to `2026-06-19 (Plan 06-04)` with a sentence pointing operators to §11.

**`.env.example`** — expanded the inline `# <purpose>` comments above the two Phase 6 key blocks (the DB block at line 34, the destination block at line 78). No key VALUES changed. Each comment cross-references the relevant `DEPLOYMENT.md` subsection (§11.3 / §11.6 / §11.7 / §11.9).

## Verification

```
All 20 plan verify-block grep checks PASS:
  §11 header + 9 subsection headers (11.1-11.9)                  ✓
  APP_KEY / BACKUP_NOTIFICATION_EMAIL / BACKUP_MAX_MB            ✓
  DB_RESTORE_TEST_DATABASE / DUMP_BINARY_PATH in DEPLOYMENT.md   ✓
  DO_SPACES_KEY= / DO_SPACES_REGION=nyc3 / BACKUP_DISK=backups   ✓
  scratch DB / mysqldump binary DIRECTORY in .env.example        ✓

PHPUnit: 278 tests / 671 assertions PASS (no code touched —
         baseline preserved from Plans 06-02/06-03).

Files staged + committed: DEPLOYMENT.md + .env.example
  (account.txt was left unstaged — out-of-scope pre-existing
   Phase 2 scratch file, not touched by this plan.)
```

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 — Bug] Plan draft referenced `Mess::active()->name`; the Mess model exposes only `Mess::activeId()`**
- **Found during:** Task 1 (reconciling §11.4 against shipped code before writing).
- **Issue:** The plan's §11.4 step 4 draft text + the research's Code Examples A/B referenced `Mess::active()?->name` as the typed-confirm target. The actual `app/Models/Mess.php` exposes only `static activeId(): ?int` — there is no `active()` accessor returning a model. Plan 06-03's `BackupController::activeMessName()` resolves the typed-confirm target as `Mess::find(Mess::activeId())?->name` for exactly this reason (documented as deviation #2 in 06-03's SUMMARY).
- **Fix:** The runbook §11.4 step 4 + §11.5 describe the typed-confirm target as `Mess::find(Mess::activeId())->name`, matching the shipped code. The research's Open Question #3 resolution (target = active mess `name` column) is unchanged — only the resolution path was corrected.
- **Files modified:** `DEPLOYMENT.md`
- **Commit:** `988045c`

**2. [Rule 1 — Bug] Plan draft used the flat config key `config('backup.destination.disks.0')`; spatie v10 nests it under a top-level `backup` key**
- **Found during:** Task 1 (reconciling §11.5 against shipped code).
- **Issue:** The plan + research Pattern used `config('backup.destination.disks.0')`. The committed `config/backup.php` returns `['backup' => [...], ...]` (spatie v10 nests the destination config under a top-level `backup` key), so the actual key is `config('backup.backup.destination.disks.0')`. Plan 06-02's `BackupRestoreService::downloadAndExtract` + Plan 06-03's `BackupController::backupDisk` + `RestoreController::show` all already use the nested key (documented as deviation #1 in 06-03's SUMMARY).
- **Fix:** The runbook §11.5 step 3 CLI snippet uses `Storage::disk(config('backup.backup.destination.disks.0', 'backups'))` so an operator copy-pasting the tinker block hits the same disk the controllers use.
- **Files modified:** `DEPLOYMENT.md`
- **Commit:** `988045c`

**3. [Rule 1 — Bug] Plan draft described the post-close hook as `backup:run`; the shipped code calls `backup:run --only-db`**
- **Found during:** Task 1 (reconciling §11.3 against shipped code).
- **Issue:** The plan's §11.3 bullet for the post-close hook described "an ad-hoc `backup:run` immediately." Plan 06-02's `CloseMonthJob::after()` actually calls `Artisan::call('backup:run', ['--only-db' => true])` — the `--only-db` flag is faster and is the right choice for a post-close capture (the close itself writes no files; only DB rows).
- **Fix:** Runbook §11.3 documents the post-close hook as `Artisan::call('backup:run', ['--only-db' => true])` and explains why (close produces DB rows only). The §11.9 troubleshooting row for "month-close completed but no immediate backup landed" also reflects this hook's try/catch behavior (T-06-02-07).
- **Files modified:** `DEPLOYMENT.md`
- **Commit:** `988045c`

**4. [Rule 3 — Blocking] Plan verify block was case-sensitive on the `.env.example` comments**
- **Found during:** Task 1 verification (the `grep -q "scratch DB" .env.example` + `grep -q "mysqldump binary DIRECTORY" .env.example` checks failed on first run).
- **Issue:** My first `.env.example` draft used "Scratch DB" (capital S, natural sentence case). The plan's verify gate (the done criterion) is literally `grep -q "scratch DB" .env.example` + `grep -q "mysqldump binary DIRECTORY" .env.example` — case-sensitive.
- **Fix:** Lowercased the comment to "scratch DB the periodic restore-test job loads + wipes..." so the plan's verify block passes verbatim. The "mysqldump binary DIRECTORY" string was already uppercase in my draft and passed.
- **Files modified:** `.env.example`
- **Commit:** `988045c`

### Out-of-scope Discoveries

- **`account.txt` was already modified before this plan started** (11 lines added, looks like stray notes from an earlier session — Phase 2 era per `git log`). It is NOT part of this plan's scope and was deliberately left unstaged. No fix attempted (scope boundary — only fix issues directly caused by the current task's changes).

## Authentication Gates

None. The plan touched only documentation + `.env.example` comments.

## Known Stubs

None. The plan is documentation-only — every claim is grounded in the actually-shipped code from Plans 06-01/02/03, with the reconciliation noted under Deviations. There are no TODOs, no placeholder text, no "coming soon" markers anywhere in the runbook.

## Threat Flags

None new beyond the plan's `<threat_model>` register. Every threat mitigation is documentation-grounded:

| Threat | Mitigation | Verified by |
|--------|------------|-------------|
| T-06-04-01 (runbook incomplete → cannot recover from disaster) | §11 has all 9 subsections: what/where/schedule/UI-restore/CLI-restore/DO-Spaces/SMTP/host-snapshot/troubleshooting | All 20 plan verify-block greps PASS |
| T-06-04-02 (operator copies dev `.env` after restore because `.env` is excluded) | §11.5 step 7 explicitly documents `php artisan key:generate` + DB/Spaces credential rotation as REQUIRED, with the explanation of WHY (D-07 — secrets must not live in object storage) | `grep "APP_KEY" DEPLOYMENT.md` PASS; §11.5 step 7 is the dedicated regeneration section |
| T-06-04-03 (operator ships prod with `MAIL_MAILER=log`, misses failure emails) | §11.7 ends with the explicit blockquote "Do NOT ship prod with `MAIL_MAILER=log` — you will not receive failure emails" + the SMTP env block | `grep "MAIL_MAILER=log" DEPLOYMENT.md` PASS |
| T-06-04-04 (host snapshot enabled but `.env` access not restricted) | §11.8 documents this as OPTIONAL + notes the snapshot includes `.env` (sensitive — restrict access in the DO/Forge control panel) | §11.8 has the access-restriction sentence |
| T-06-04-05 (DO Spaces region/endpoint misconfigured → signature errors) | §11.6 step 7 + the §5 table + §11.9 troubleshooting row all state the region MUST match the endpoint subdomain (Pitfall 5) | `grep "nyc3 + https://nyc3.digitaloceanspaces.com" DEPLOYMENT.md` PASS in 3 places (§5, §11.6, §11.9) |

No new network endpoints, auth paths, file access patterns, or trust-boundary schema changes were introduced by this plan (it ships zero code).

## Self-Check: PASSED

- `DEPLOYMENT.md` — FOUND (modified in place; §11 appended, §5 extended, footer updated)
- `.env.example` — FOUND (Phase 6 block comments expanded; no key values changed)
- `.planning/phases/06-backup-and-restore-system/06-04-runbook-SUMMARY.md` — FOUND (this file)
- Commit `988045c` — FOUND (`docs(06-04): backup & restore runbook in DEPLOYMENT.md + .env.example comments`)
- PHPUnit: 278 tests / 671 assertions PASS (baseline preserved — no code touched)
- All 20 plan verify-block grep checks PASS
