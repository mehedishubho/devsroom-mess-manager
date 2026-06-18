# Phase 6: Backup and Restore System - Context

**Gathered:** 2026-06-19
**Status:** Ready for planning

<domain>
## Phase Boundary

Deliver a working **backup + restore** capability for the single-mess VPS deployment, so that a server loss, a bad migration, or an accidental/corrupt month-close never loses the mess's financial history. The system backs up the **MySQL database (all 26 tables)** and the **uploaded files** (profile photos + bazar receipts on the `public` disk) to **S3-compatible off-server object storage** on a schedule, exposes a **super-admin dashboard UI** for the safe operations plus a **guarded full restore**, runs a **periodic restore-test** that proves backups actually restore, and ships a **restore runbook** in `DEPLOYMENT.md`.

It is **NOT**: multi-mess backup orchestration, continuous/streaming DB replication, partial/point-in-time/per-table restore, a member-facing feature, or cross-region replication.

**⚠️ Scope note for downstream agents:** Phase 6 is a **post-v1 hardening phase**. It is **not in `REQUIREMENTS.md`** (all 154 requirements map to Phases 1–5, which *are* the v1 milestone) and the `ROADMAP.md` entry is a stub (`Goal: [To be planned]`). There are therefore **no formal REQ-xxx acceptance criteria to map against** — success criteria for this phase will be defined during planning, grounded in the decisions below. The v1 ship gate (Phase 5 success #12 — one real mess through a clean month-close) does **not** depend on Phase 6.

**Carrying forward from Phase 5 (already locked — do not re-ask):**
- Deploy target = **VPS (Laravel Forge primary / manual appendix)**, MySQL 8, supervisor queue worker, Laravel scheduler cron — all already wired (`DEPLOYMENT.md` §3–§4).
- Cache/queue/session on the **`database`** driver — no Redis in v1.
- File uploads on the **local `public` disk** (`storage/app/public/`, symlinked to `public/storage/`) — no cloud storage configured yet.
- `DEPLOYMENT.md` exists (Plan 05-03) but has **no backup/restore section** — this phase adds it.
- MySQL in dev **and** prod (never sqlite). `Asia/Dhaka` timezone. `__()` everywhere. Decimal money. Service layer. PHPUnit 12, Pint clean, >70% coverage standard.

The data at risk (the crown jewels): the **immutable financial snapshots** `monthly_closings` + `monthly_member_summaries` (one row per member per month, hard-locked, never editable) and the append-only `audit_logs` that backs trust/dispute resolution.

</domain>

<decisions>
## Implementation Decisions

### Backup engine & mechanism

- **D-01:** Use **`spatie/laravel-backup`** as the backup engine. It packages a **`mysqldump` of the DB + the app files into a single zip**, runs via the existing Laravel scheduler, pushes off-server to a configured filesystem disk, cleans up old backups, and notifies on backup failure / unhealthy state. One well-maintained, Laravel-idiomatic package that fits the single-mess VPS exactly. Researcher confirms the version compatible with Laravel 13 (pull via Context7 / package docs).
- **D-06 (important planner note):** `spatie/laravel-backup` is **backup-only by design — it ships NO restore command.** The restore side (D-03/D-04) is therefore **custom application code**: unzip a chosen backup, restore the DB dump (`mysql < dump.sql` or PDO-exec), and copy the files back to `storage/app/public/`. The planner MUST treat restore as bespoke work, not a spatie feature.
- **D-07:** Backup **coverage** = `mysqldump` of **all 26 domain tables** + everything under `storage/app/public/` (profile photos + bazar receipts). **Exclude secrets** — the `.env` is deliberately NOT backed up into object storage (document `APP_KEY` / credential regeneration in the restore runbook instead). Exclude spatie's own temp dir and `storage/app/laravel-backup` working area from the file set.

### Destination & retention

- **D-02:** Ship backups to an **S3-compatible object-storage disk** — default **DigitalOcean Spaces** (likely same provider as the VPS; ~$5/mo), configured as a Laravel `s3` driver disk with a custom `endpoint`. Back up **both DB and uploaded files** (photos/receipts are small).
- **Retention: daily backups kept 14 days + monthly backups kept 12 months.** The long monthly retention exists because `monthly_closings` snapshots are **immutable financial records** — a corruption discovered months later must still be recoverable. (Exact numbers are tunable in `config/backup.php` — Claude's discretion to fine-tune.)
- **Never local-only.** A VPS loss would take local backups with it; the off-server copy is the real backup. A local disk may exist transiently as spatie's working area only.

### Restore surface & validation

- **D-03:** A **super-admin "Backups" page in the Tyro dashboard** (`role:super-admin` only). It lists backups, allows downloading a backup zip, triggers an ad-hoc backup now, shows restore-test health — AND exposes a **guarded one-click FULL RESTORE**: type-the-mess-name to confirm, super-admin-only, **auto-enables maintenance mode** (`Artisan::call('down')`) before clobbering live data, restores DB + files, then returns the app to live. This is the destructive operation the user explicitly chose (option A) — the guard rails (typed confirmation + role gate + maintenance mode) are **mandatory, not optional**.
- **D-04:** Keep a **periodic restore-test** regardless of the UI. A scheduled (and on-demand) job loads the latest backup into a **scratch MySQL database** (separate connection, e.g. `mysql_restore_test`), restores the dump there, and **asserts per-table row counts match the source** — result surfaced as a **health badge** on the Backups UI. Rationale locked by the user: an untested backup is not a backup, UI or not.
- **Partial / point-in-time / per-table restore is OUT of scope** for this phase (full-restore only). Captured in `<deferred>`.

### Schedule, triggers & failure handling

- **D-05:** Backups run **nightly** via `spatie/laravel-backup`'s `backup:run` on the **existing Laravel scheduler** (the scheduler + supervisor already run per `DEPLOYMENT.md` §3.3/§4.4). **Plus** an **on-demand** artisan command / dashboard button for ad-hoc backups. **Plus** a **queued listener that fires after a successful `CloseMonthJob`** — the close creates the highest-value immutable snapshot, so capture it immediately rather than waiting for the nightly run.
- **On backup failure or unhealthy state, notify the super-admin(s).** spatie emits `BackupFailed` / `UnhealthyBackupWasFound` events; wire them to the project's notification surface. ⚠️ The default `MAIL_MAILER=log` (per `codebase/INTEGRATIONS.md`) — a real restore/failure notification needs SMTP configured in prod (carry-forward from `DEPLOYMENT.md` §5). Channel details are Claude's discretion.

### Test strategy

- **D-08:** Apply the project's standard test discipline (PHPUnit 12, `RefreshDatabase`, Pint clean, >70% coverage) but **mock the heavy process calls** — do **not** shell out to real `mysqldump`/`mysql` inside the test suite. Specifically test: (a) the **restore-test job's comparison logic** (feed two known dumps, assert pass/fail); (b) the **Backups UI auth gating** — super-admin allowed, `admin`/`user`/guest 403; (c) the **restore confirmation flow** — restore refuses without the correct typed mess name, refuses when not super-admin, flips maintenance mode; (d) the **post-close listener** fires a backup after a successful close and does nothing on close failure.

### Claude's Discretion

- Exact S3-compatible provider (DO Spaces vs AWS S3 vs Backblaze B2) + bucket name/region.
- `spatie/laravel-backup` version (researcher confirms Laravel 13 compat).
- Whether the Backups UI is a **Tyro dynamic resource** (`config/tyro-dashboard.php` `resources` array) or a **custom controller + Blade view** under `role:super-admin`.
- Restore-test scratch DB connection name, and whether the test runs on a **schedule** (e.g. daily) or **on-demand only** (button in the UI).
- Notification channel specifics (SMTP mail + log; Slack webhook if provided).
- Fine-tuning the exact retention numbers (14d daily / 12mo monthly are starting points).
- Whether to also keep a server-level snapshot (Forge/DO snapshot) as a second decoupled copy — the user chose "spatie + runbook", so a host snapshot is **optional**, not required.
- Test approach details for the custom restore orchestration (mock `Process`/`Artisan` calls vs. a tiny fixture dump).

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Project context (why this phase is post-v1, and the constraints)
- `.planning/PROJECT.md` — Constraints: MySQL-only (never sqlite), single mess, snake_case DB, service layer, `__()` everywhere, decimal money, PHPUnit 12, Pint, anti-recommendations (v1 scope discipline)
- `.planning/REQUIREMENTS.md` — **Out of Scope** table; confirm Phase 6 has **no REQ-xxx** (post-v1 hardening, not a v1 requirement)
- `.planning/ROADMAP.md` — Phase 6 stub entry (`Goal: [To be planned]`, depends-on Phase 5) + the v1 milestone definition (Phases 1–5)
- `.planning/STATE.md` — Phase 5 deploy decisions, accumulated context ("Phase 6 added: Backup and restore system")

### Phase 5 foundation this phase builds on (REQUIRED reading)
- `.planning/phases/05-polish-pilot/05-CONTEXT.md` — **D-15** deploy target (VPS + Forge + supervisor queue worker), **D-18** `DEPLOYMENT.md` + the `.env` sqlite→MySQL parity fix, the perf/docs/pilot scope
- `DEPLOYMENT.md` — §3.3 scheduler, §4.3 supervisor (the infra the backup scheduler + post-close listener run on), §5 prod `.env` checklist (`QUEUE_CONNECTION=database`, `CACHE_STORE=database`, SMTP TBD), §7 storage perms (`storage/app/public/` = where backed-up files live), §9 monitoring — **the restore runbook lands here as a new section**

### Codebase maps (already in repo)
- `.planning/codebase/INTEGRATIONS.md` — filesystem disks (default `local`, `public` disk root = `storage/app/public`, **S3 disk exists but unconfigured**), queue `database`, cache `database`, **Tyro dynamic CRUD resources** (`config/tyro-dashboard.php` `resources` array — the UI hook point)
- `.planning/codebase/CONCERNS.md` — #2 MySQL dev/prod parity, #3 no Redis (database cache/queue/session), #16 no CI pipeline
- `.planning/codebase/CONVENTIONS.md` — Pint preset, test style (`RefreshDatabase`, `test_` prefix), Form Request pattern, service-layer-not-repository
- `.planning/codebase/TESTING.md` — PHPUnit 12, factory usage (relevant to the restore-test fixtures)
- `.planning/codebase/STACK.md` — installed packages (confirm `spatie/laravel-backup` NOT yet installed)

### Prior-phase patterns to reuse (not re-derive)
- `.planning/phases/03-payments-month-close/03-CONTEXT.md` — the queued-job + idempotency + event-listener pattern (the post-close backup listener mirrors the close-completion notification listener)
- `.planning/codebase/ARCHITECTURE.md` + `.planning/codebase/STRUCTURE.md` — where Services / Jobs / Listeners / Controllers live

### External (researcher pulls via Context7 / package docs)
- `spatie/laravel-backup` documentation — `backup:run` / `backup:clean` / `backup:monitor` / `backup:list`, `config/backup.php` structure, notification channels, **confirm no restore command ships** (validates D-06)

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `app/Jobs/CloseMonthJob.php` — the queued month-close. The **post-close backup listener (D-05)** hooks its successful completion, exactly like the existing close-complete notification listener.
- `app/Providers/AppServiceProvider.php` — already wires Eloquent event listeners (e.g. `invalidateForModel`); the post-close + spatie-event listeners follow the same registration style.
- `database/seeders/PerfDemoSeeder.php` (Plan 05-01) — a known-good ~50-member dataset; can seed the **restore-test scratch DB** so the restore-test has realistic row counts to assert against.
- `routes/console.php` / `app/Console/Kernel.php` (or `routes/console.php` schedule) — Laravel scheduler already runs (`schedule:run` cron per `DEPLOYMENT.md` §4.4; `telescope:prune` is scheduled here with a `class_exists` guard — mirror that pattern for `backup:run` + the restore-test).
- Tyro `role:super-admin` middleware + the Tyro dynamic-resource config (`config/tyro-dashboard.php` `resources`) — the auth gate + UI hook point for the Backups page (D-03).
- `config/filesystems.php` — already declares an `s3` disk (driver `s3`, empty env vars) — wire this to DO Spaces with a custom `endpoint` (D-02).

### Established Patterns
- **Queued jobs** (`CloseMonthJob`, `$timeout = 120`) + supervisor worker — backups that run as jobs/commands inherit this runtime.
- **Service layer** (16 services in `app/Services/`) — put the custom restore orchestration behind a `BackupRestoreService` / `RestoreTestService`, not in the controller.
- **Form Requests** for input validation; **`__()` everywhere**; **MySQL-only**; **decimal money** (not relevant to backups, but the codebase standard).
- **`role:super-admin` / `admin` / `user`** role gating is already enforced across the app (Phase 4 IDOR work) — reuse for the Backups UI.

### Integration Points
- `composer.json` — add `spatie/laravel-backup` (version researcher-confirmed for Laravel 13).
- `config/backup.php` — **new** (spatie config: source DB + files, destination S3 disk, cleanup strategy, retention, notifications).
- `config/filesystems.php` — populate the `s3` disk (DO Spaces endpoint + key + secret + bucket + region); add to `.env` / `.env.example`.
- `app/Services/` — new `BackupRestoreService` (custom restore: unzip + `mysql` restore + file copy) + `RestoreTestService` (scratch-DB comparison).
- `app/Console/Commands/` (or `routes/console.php`) — `backup:run` schedule, on-demand backup command, restore-test command; `Artisan::call('down')`/`'up')` for maintenance mode during restore.
- Event listener on `CloseMonthJob` success → dispatch backup (D-05).
- Tyro dashboard resource **or** a custom `role:super-admin` route group for the Backups UI (list / download / trigger-now / restore-test badge / guarded full restore).
- `DEPLOYMENT.md` — new "Backup & restore runbook" section (DR steps, maintenance-mode restore, `APP_KEY`/cred regeneration, object-storage config).
- `.env` / `.env.example` — `AWS_*` / Spaces keys, `MAIL_MAILER=smtp` (so failure notifications actually send).

### ⚠️ Pre-existing considerations to fold in
- `MAIL_MAILER=log` by default — failure notifications (D-05) need a real SMTP in prod (`DEPLOYMENT.md` §5).
- `database` cache/queue/session driver — fine for one mess; the restore must not trash the live `cache`/`sessions`/`jobs` tables mid-run (maintenance mode mitigates this).
- No CI (CONCERNS #16) — the restore-test job is the closest thing to an automated backup-correctness gate; do not skip it.

</code_context>

<specifics>
## Specific Ideas

- **"An untested backup is not a backup."** The restore-test job (D-04) + health badge is the philosophical center of this phase — the user explicitly kept it even after choosing a UI-driven restore. The planner should treat the restore-test as a first-class deliverable, not an afterthought.
- **The full-Restore UI is custom code, not a spatie command (D-06).** A common misconception is that spatie/laravel-backup restores too — it does not, deliberately. Budget real work for unzip + `mysql` import + file restore + the confirmation/maintenance-mode flow.
- **Long monthly retention (12mo) because the snapshots are immutable financial records.** A corruption discovered three months after a bad close must still be recoverable. Don't optimize for storage cost at the expense of recovery depth.
- **Post-close hook (D-05) because close creates the highest-value immutable data.** Backing up right after a successful `CloseMonthJob` means the most important write of the month is captured immediately, not up to 24h later.
- **Guarded restore (type-the-mess-name + super-admin-only + auto maintenance mode) is mandatory, not optional** — this is a one-click path to overwriting live financial data; the user chose convenience (option A) but the guard rails are non-negotiable.
- **`.env` is deliberately excluded from backups** — secrets should not live in object storage. Document `APP_KEY`/credential regeneration in the restore runbook instead.

</specifics>

<deferred>
## Deferred Ideas

- **Multi-mess backup orchestration / per-mess restore** — v2 (MULTI-01..04); Phase 6 backs up the single-mess DB wholesale.
- **Continuous / streaming DB replication** (e.g. MySQL binlog replication, read replica) — overkill for one mess on one VPS; revisit at scale.
- **Partial / point-in-time / per-table / per-row restore** — the user chose full-restore only (option A); partial restore is heavy build for marginal value at one-mess scale.
- **Cross-region replication of the object-storage backups** — DO Spaces/AWS cross-region replication; defense-in-depth for a second region. Post-pilot.
- **Backup encryption (client-side encrypt before upload)** — DO Spaces provides server-side encryption; client-side envelope encryption is extra complexity. Claude's discretion to revisit if the threat model demands it.
- **Server-level snapshot as a second decoupled copy** (Forge/DO snapshot) — the user picked "spatie + runbook", so this is optional, not required; can add for defense-in-depth.
- **Member-facing "export my data"** — a separate concern (data portability), not backup/restore.
- **CI job that runs the restore-test on every PR** — CONCERNS #16 (no CI); once CI exists, the restore-test is a natural CI candidate.

### Reviewed Todos (not folded)
None — `todo match-phase 6` returned 0 matches.

</deferred>

---

*Phase: 06-backup-and-restore-system*
*Context gathered: 2026-06-19*
