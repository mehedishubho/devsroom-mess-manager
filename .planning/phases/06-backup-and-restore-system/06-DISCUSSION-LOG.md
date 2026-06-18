# Phase 6: Backup and Restore System - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in `06-CONTEXT.md` — this log preserves the alternatives considered.

**Date:** 2026-06-19
**Phase:** 06-backup-and-restore-system
**Mode:** discuss (standard)
**Areas discussed:** Scope & mechanism, Destination & retention, Restore & validation, Schedule & triggers

---

## How this discussion started

Phase 6 is a **freshly-added, thinly-specified phase** — `ROADMAP.md` lists `Goal: [To be planned]`, `Requirements: TBD`, and it is **not present in `REQUIREMENTS.md`** (all 154 requirements map to Phases 1–5, which constitute the v1 milestone). So the discussion first established the **domain boundary** (what "backup and restore system" means for THIS app) before drilling into gray areas.

Carrying-forward context from Phase 5 (already locked, not re-asked): deploy target = VPS (Forge primary / manual appendix) + MySQL 8 + supervisor queue worker + Laravel scheduler cron; cache/queue/session on `database` driver; file uploads on local `public` disk; `DEPLOYMENT.md` exists with no backup section; no `spatie/laravel-backup`; no CI.

The data at risk: immutable financial snapshots (`monthly_closings`, `monthly_member_summaries`) + append-only `audit_logs`.

**Proposed domain boundary (accepted):** deliver backup + restore for the single-mess VPS — automated DB + uploaded-file backups to off-server object storage on a schedule, a tested restore path, a super-admin UI, and a runbook. NOT multi-mess, NOT continuous replication, NOT member-facing.

---

## Area selection

| Option | Description | Selected |
|--------|-------------|----------|
| Scope & mechanism | spatie/laravel-backup vs ops-runbook-only vs both (keystone — gates the rest) | ✓ |
| Destination & retention | Off-server destination, retention window, DB-only vs DB+files | ✓ |
| Restore & validation | CLI/runbook vs admin UI; full vs partial; restore-testing discipline | ✓ |
| Schedule & triggers | Nightly vs post-close hook vs on-demand; failure notification | ✓ |

**User's choice:** all four areas.

---

## Scope & mechanism

| Option | Description | Selected |
|--------|-------------|----------|
| spatie + runbook (Recommended) | `spatie/laravel-backup`: mysqldump + files zip, scheduled, off-site push, cleanup, failure notification. Plus restore runbook + restore-test. | ✓ |
| Ops runbook only | No app code; mysqldump + rsync cron + VPS snapshot documented in `DEPLOYMENT.md`. Simplest; unmonitored. | |
| spatie + host snapshot | spatie in-app AND an independent provider snapshot as a second decoupled copy. Defense in depth. | |

**User's choice:** spatie + runbook.
**Notes:** Chose the Laravel-ecosystem standard package over a pure-docs approach so backups are visible to the app and monitored. A host-level snapshot is left as Claude's discretion (optional defense-in-depth), not a requirement.

---

## Destination & retention

| Option | Description | Selected |
|--------|-------------|----------|
| DO Spaces / S3 (Recommended) | S3-compatible object storage (~$5/mo); DB + files; daily 14d + monthly 12mo retention. | ✓ |
| SFTP to a 2nd server | Ship over SFTP to another box you operate. No per-GB cloud cost; you run the second box. | |
| Forge / host built-in | Forge DB backup or DO snapshot. Zero config; couples to host control plane; host-dependent retention. | |

**User's choice:** DO Spaces / S3, DB + uploaded files, daily 14d + monthly 12mo.
**Notes:** Long monthly retention chosen because `monthly_closings` snapshots are immutable financial records — a corruption found months later must still be recoverable. Local-only explicitly rejected (a VPS loss takes local backups with it).

---

## Restore & validation

| Option | Description | Selected |
|--------|-------------|----------|
| CLI + runbook + test (Recommended) | artisan restore + DR runbook + periodic restore-test job (scratch DB, row-count assertions). | |
| CLI + runbook, manual test | Same restore/docs; validation is a manual checklist item, not scripted. | |
| Super-admin UI | A Backups page in the Tyro dashboard for list/download/restore. More visible; more work. | ✓ (then refined) |

**User's choice:** Super-admin UI — then refined to **guarded one-click full restore** (option A) after a clarifying follow-up.

### Follow-up 1 — what the UI actually does
| Option | Description | Selected |
|--------|-------------|----------|
| Safe ops; restore stays CLI (Recommended) | UI lists/downloads/triggers/health; destructive full-DB restore stays CLI + runbook. | |
| Guarded full-restore button | Real "Restore" button: type-the-mess-name, super-admin-only, auto maintenance mode. | ✓ |
| Restore + partial/point-in-time | Full restore + per-table/per-row restore. Most complex; overkill for one mess. | |

**User's choice (via free-text "UI should be handle by admin dashboard", then confirmed):** Guarded one-click full restore in the dashboard (option A). Guard rails (typed confirmation + super-admin role gate + auto maintenance mode) are mandatory.

### Follow-up 2 — restore-test discipline
| Option | Description | Selected |
|--------|-------------|----------|
| Yes, periodic test job (Recommended) | Scheduled/on-demand job loads latest backup into a scratch MySQL DB, asserts row counts, surfaces a health badge. | ✓ |
| Manual test only | Validation is a documented checklist item; no scripted test. | |

**User's choice:** Yes, periodic test job.
**Notes:** Critical planner note recorded as D-06 — `spatie/laravel-backup` ships NO restore command; the guarded full-restore UI is bespoke restore code (unzip + mysql import + file copy), not a spatie feature. Partial/point-in-time/per-table restore explicitly out of scope (deferred).

---

## Schedule & triggers

| Option | Description | Selected |
|--------|-------------|----------|
| Nightly + close hook + notify (Recommended) | `backup:run` nightly on scheduler + on-demand + queued listener after successful `CloseMonthJob`; notify super-admin on failure. | ✓ |
| Nightly + on-demand only | Nightly + ad-hoc; no post-close hook. Close captured within ~24h. | |
| Post-close hook only | Back up only when a month closes. Lightest; mid-month server loss loses up to a month. | |

**User's choice:** Nightly + post-close hook + on-demand + notify-on-failure.
**Notes:** Post-close hook added because `CloseMonthJob` creates the highest-value immutable snapshot — capture it immediately rather than waiting for the nightly run. Failure notification depends on a real SMTP (default `MAIL_MAILER=log`), flagged as a carry-forward prod config item.

---

## Claude's Discretion (left open for the user implicitly)

- S3-compatible provider choice (DO Spaces default), bucket/region.
- `spatie/laravel-backup` version (researcher confirms Laravel 13 compat).
- Backups UI: Tyro dynamic resource vs custom controller.
- Restore-test scratch DB connection name; scheduled vs on-demand-only.
- Notification channel specifics (mail + log; Slack webhook optional).
- Exact retention fine-tuning (14d/12mo are starting points).
- Whether to add an optional host snapshot as a second copy.
- Test approach for the custom restore orchestration (mock `Process`/`Artisan` vs fixture dump).

## Deferred Ideas

- Multi-mess backup orchestration / per-mess restore (v2).
- Continuous/streaming DB replication / read replica.
- Partial / point-in-time / per-table restore (chose full-restore only).
- Cross-region replication of object-storage backups.
- Backup encryption (client-side envelope) — server-side encryption already provided.
- Server-level snapshot as a required second copy (made optional/discretion).
- Member-facing "export my data" (separate concern).
- CI job running the restore-test on every PR (CONCERNS #16 — no CI yet).
