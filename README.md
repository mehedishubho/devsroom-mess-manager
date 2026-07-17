<div align="center">

# Mess Management Application Using Laravel 13 + Tyro Dashboard

**Run a full mess month end-to-end from a phone — meals, bazar, payments, month-close, and correct member bills — without spreadsheets and without arguing about who owes what.**

[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-8-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-v4-06B6D4?logo=tailwindcss&logoColor=white)](https://tailwindcss.com/)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)

A web-based mess management system built for Bangladesh messes — bachelor hostels, student hostels, and shared accommodations. The mess manager enters daily meals and bazar expenses on mobile; members log in to view their bills, request meal-offs, and check their payment history. The system automates the Bangladesh-specific monthly close: meal rate derived from bazar only, fixed expenses split equally, advance balance carry-forward, and immutable monthly snapshots.

**Developed by [Devsroom](https://devsroom.com)** · Developer portfolio: [wpmhs.com](https://wpmhs.com)

</div>

> The v1 scope is **one mess, fully working for one real monthly cycle**. The schema is multi-mess-ready (`mess_id` on every domain table) but only one mess is exercised in v1.

---

## Documentation

| Guide | What it covers |
|-------|----------------|
| **[Setup · Roles · Deploy](./SETUP-USAGE-DEPLOY.md)** | Installation, **daily usage by role** (super-admin / admin / manager / user), and deployment on **VPS** + **shared hosting**. |
| **[Deployment Runbook](./DEPLOYMENT.md)** | Deep VPS / Forge hardening — supervisor, cron, HTTPS, backups. |
| **[Architecture (AGENTS.md)](./AGENTS.md)** | Bill math, month-close flow, cache strategy, role/IDOR model. |

## Table of Contents

- [Key Features](#key-features)
- [Tech Stack](#tech-stack)
- [Prerequisites](#prerequisites)
- [Installation (local dev)](#installation-local-dev)
- [Demo Dataset](#demo-dataset-optional)
- [Common Commands](#common-commands)
- [Perf / Debug Tooling (dev-only)](#perf--debug-tooling-dev-only)
- [Architecture Overview](#architecture-overview)
- [Deployment](#deployment)
- [Roadmap](#roadmap)
- [Credits](#credits)
- [License](#license)

---

## Key Features

- **Member management** — full CRUD with profile (name, mobile, email, NID, profession, room/seat, joining/leaving dates, status, emergency contact, photo). Member invite flow with email link + set-password page. Soft-delete + super-admin-only permanent removal (guarded against breaking the ledger). **Name-based URLs** (`/mess/members/john-doe`) with automatic unique-slug disambiguation for same-name members, and **duplicate prevention** (email/mobile must be unique within a mess).
- **Daily meal grid** — rows=members, columns=breakfast/lunch/dinner. "Mark all 3" / "Mark all 0" presets + per-member quick actions (All on, All off, B/L/D only). Single-transaction bulk save. N+1-safe query (< 100ms at 50 members — locked by `tests/Feature/Perf/MealGridQueryCountTest.php`).
- **Meal off + approvals** — members request meal off for a date range with a reason; manager approves/rejects (rejection requires a reason); approved meal off auto-deducts from the member's meal count.
- **Guest meals** — manager records guest meals (guest name, host member, date, meal type, qty); charged to the host member's bill.
- **Bazar / market expenses** — daily bazar purchases (date, purchaser, vendor, description, amount, optional receipt image), categorized.
- **Fixed monthly expenses** — rent, cook salary, internet, electricity, water, gas, security, etc. Split equally among active members (prorated for mid-month joiners/leavers). Fixed expenses do **not** enter the meal rate (FIXED-04).
- **Payments + advance balance** — manager records payments (Cash, bKash, Nagad, Rocket, Bank) as either `bill_payment` or `advance_deposit`; advance carries forward month-to-month and auto-adjusts against the next bill.
- **Live bill preview** — cached (1h TTL, mess-scoped key). Manager sees "if we closed today, meal rate would be ৳X" on the dashboard; member sees their own running bill on `/my`.
- **Month-close** — queued (`CloseMonthJob` on the `database` queue), idempotent (`UNIQUE(mess_id, year, month)` + `firstOrCreate` + `wasRecentlyCreated`), hard-locked post-close via `EnsureMonthIsOpen` middleware on 11 write routes, immutable snapshot to `monthly_closings` + `monthly_member_summaries`. Corrections go through a separate `monthly_corrections` table — the snapshot stays immutable (CLOSE-12).
- **4 reports** — Monthly Report (totals + per-member), Member Statement (8-section ledger per member), Expense Report (filter by date/category/month), Payment Report (filter by member/method/date).
- **Manager dashboard** — 6 stat cards (Total Members, Today's Meals, Current Meal Rate, Monthly Expenses, Total Due, Total Advance) + 3 Chart.js charts (Meal Trend 30d line, Expense Trend 6mo bar bazar-only, Payment Trend 6mo bar all-methods) + pending-meal-off alert banner.
- **Member `/my` dashboard** — 4 Overview cards (My Meals, My Bill, My Advance, My Payment History) + tabs for Profile, Meals, Meal off, Payments, My reports.
- **Exports** — PDF (Dompdf, plain-CSS A4 portrait, "Page N" footer) + Excel/`.xlsx` (Maatwebsite/Excel, numeric-formatted Amount columns so SUM/AVERAGE work) on all 4 reports × both manager and member sides.
- **Audit log** — `owen-it/laravel-auditing` writes an append-only entry on every write to meal/expense/payment/member/meal-off/guest-meal models.
- **Notifications** — in-app bell (monthly closing complete, due reminder, payment received, meal-off approval) **plus multi-channel delivery**: email, WhatsApp, Telegram, and SMS, each toggleable and configurable from the admin dashboard. Multiple channels can be active at once, with a per-notification-type routing matrix. Channels fail open — a down provider never blocks the in-app record or the caller's transaction.
- **Role-based navigation** — the sidebar adapts to the signed-in role: managers get grouped Mess / Finance / Closing / Reports / Settings sections, super-admin additionally sees the System group (admin dashboard + backups), and members get a focused self-service "My" nav instead of inaccessible manager pages.

---

## Tech Stack

| Layer | Technology |
|-------|------------|
| **Backend** | Laravel 13 (PHP 8.3+) — server-rendered Blade, no SPA framework |
| **Database** | MySQL 8 (the only DB — dev/prod parity; no sqlite) |
| **Frontend** | Tailwind CSS v4 (`@tailwindcss/vite`) + Chart.js 4.5 |
| **Auth & Admin** | Tyro Dashboard + Tyro Login (roles: `super-admin` / `admin` / `manager` / `user`) |
| **Auditing** | `owen-it/laravel-auditing` — append-only entry on every write |
| **Exports** | Dompdf (PDF) + Maatwebsite Excel (`.xlsx`) |
| **Backups** | `spatie/laravel-backup` → Local + DigitalOcean Spaces / Cloudflare R2 (S3-compatible) + Google Drive, DB-toggled per group, with an on-page activity log and one-click restore |
| **Queue** | `database` connection — `CloseMonthJob` (idempotent, `onOneServer`) |

---

## Prerequisites

- **PHP 8.4+** with extensions: `pdo_mysql`, `gd`, `zip`, `mbstring`, `curl`. (The dev build that authored this project is PHP 8.4.15 ZTS x64 VS17 — but any 8.4+ build works.)
- **MySQL 8+**. MySQL is required in both dev and prod (do NOT use sqlite — per the project's `dev/prod parity` constraint). The database name uses `snake_case` (e.g. `devsroom_mess_management`).
- **Node.js 24+** with **npm** (for the Vite build of Tailwind v4 + Chart.js).
- **Composer**.

For production, see [DEPLOYMENT.md](./DEPLOYMENT.md) — you additionally need Nginx + supervisor (for the queue worker) and HTTPS.

---

## Installation (local dev)

```bash
# 1. Clone + install PHP deps
git clone <repo-url> devsroom-mess-management
cd devsroom-mess-management
composer install

# 2. Install + build frontend deps
npm install
npm run build        # or: npm run dev   (for Vite HMR during development)

# 3. Configure environment
cp .env.example .env
php artisan key:generate

# 4. Edit .env — set the MySQL block to match your local MySQL server:
#      DB_CONNECTION=mysql
#      DB_HOST=127.0.0.1
#      DB_PORT=3306
#      DB_DATABASE=devsroom_mess_management
#      DB_USERNAME=root
#      DB_PASSWORD=<your-local-mysql-password>
#    Also set APP_TIMEZONE=Asia/Dhaka (it's the default in .env.example — verify).
#    Do NOT use sqlite. MySQL only, dev AND prod.

# 5. Run migrations
php artisan migrate

# 6. Seed the base data (optional — only expense categories; does NOT create a test user)
php artisan db:seed
```

After install, start the dev servers (in one terminal, runs `php artisan serve`, the queue listener, `pail` log tail, and Vite in parallel):

```bash
composer run dev
```

Then visit [http://localhost:8000](http://localhost:8000). Since no super-admin exists yet, you'll be redirected to a **one-time setup wizard** — fill in the name, email, and password for the initial `super-admin` account. After submission, you're logged in and can either create the first mess via `/onboarding` or seed the demo dataset for exploration.

> **Alternative — CLI path:** `php artisan mess:create-super-admin admin@example.com "Admin" --password=secret` then log in at `/login`.

To explore with a full demo dataset instead, seed the demo accounts:

---

## Demo Dataset (optional)

A reproducible ~50-member demo dataset is shipped as a guarded seeder for perf measurement, screenshots, and easy exploration. It is **NOT** run by the default `php artisan db:seed` — invoke it explicitly:

```bash
php artisan db:seed:perf-demo
```

This creates one "Demo Mess" with 50 members (48 active + 1 former + 1 inactive, exercising the denominator + proration logic), a full month of B/L/D meal entries (882 entries), 2-3 bazar purchases per day, one of each fixed-expense category, and ~25-35 payments mixing `bill_payment` + `advance_deposit`. Completes in ~2.7s.

**Demo credentials** (local dev only — these accounts do **not** exist in production; the seeder is environment-guarded and refuses to run in production without `--force`):

| Role | Email | Password | Lands on |
|------|-------|----------|----------|
| **Demo Manager** (Tyro `admin` role) | `manager@demo.test` | `password` | `/home` (manager dashboard) |
| **Demo Member** (Tyro `user` role) | `member@demo.test` | `password` | `/my` (member dashboard) |

The demo dataset is also the perf fixture used in Plan 05-02 for measuring the 4 performance budgets (grid < 100ms, dashboard < 500ms, close < 30s, cache > 80% hit-rate) — all PASS at 50 members.

---

## Common Commands

```bash
# Lint / format
vendor/bin/pint --test       # check only (Laravel preset)
vendor/bin/pint              # fix in place

# Tests
vendor/bin/phpunit                                  # full suite (243 tests)
vendor/bin/phpunit --filter=MealGridQueryCountTest  # single test class
php artisan test                                    # same, via artisan
vendor/bin/phpunit --coverage-text                  # needs pcov or xdebug (Lines 85.75% baseline)

# Local dev servers (php artisan serve + queue:listen + pail + vite)
composer run dev

# App
php artisan serve           # http://localhost:8000
php artisan tinker

# Queue + scheduler (for month-close + daily telescope:prune)
php artisan queue:work database --sleep=3 --tries=3 --max-time=3600
php artisan schedule:run
php artisan queue:listen    # alt: foreground listener during dev

# Caches (clear after .env / config changes)
php artisan config:clear
php artisan cache:clear
php artisan view:clear

# Demo dataset (guarded — refuses in production without --force)
php artisan db:seed:perf-demo
```

---

## Perf / Debug Tooling (dev-only)

Two tools ship in `require-dev` so they **never** load in production (production runs `composer install --no-dev`). Both are additionally gated by a config closure AND a hard env flag in `.env.example`:

- **Debugbar** (`barryvdh/laravel-debugbar ^4.3`) — bottom-of-page bar showing queries, cache hits/misses, timing. Disabled on `*.pdf`, `*.xlsx`, `api/*` routes (Pitfall 2 — enforced by `tests/Feature/Report/PdfDebugbarExclusionTest.php`). Toggle: `DEBUGBAR_ENABLED=false` (default in `.env.example`).
- **Telescope** (`laravel/telescope ^5.20`) — request/queue/job/cache inspection at `/telescope`. Gated to super-admin role via `Gate::define('viewTelescope', ...)` in `app/Providers/TelescopeServiceProvider.php`. `telescope:prune` scheduled daily. Toggle: `TELESCOPE_ENABLED=false` (default in `.env.example`).

For the perf methodology (DB query log for HTTP budgets, stopwatch around `CloseMonthJob->handle()`, `Cache::has()` probe loop for hit-rate), see `.planning/phases/05-polish-pilot/05-VERIFICATION.md` §2.

---

## Architecture Overview

Server-rendered **Laravel 13.15** Blade app — no SPA framework, no Inertia/Livewire. MySQL 8 as the only database. Tailwind v4 via `@tailwindcss/vite`. **Chart.js 4.5** for dashboard charts (bundled into the global `app.js`). **Tyro Dashboard** for admin UI (users/roles/privileges/settings/audit at `/dashboard`) and **Tyro Login** for auth (login/register/lockout at `/login`, `/register`, etc.).

The business logic lives in a **service layer** (17 services under `app/Services/`) — no Repository pattern. Controllers are thin; services own the math. Money is always `decimal:2` / `DECIMAL(10,2)` — never float. All user-facing strings use `__()` (English shipped, Bengali-ready for v2). Every domain table carries `mess_id` for v2 multi-tenant readiness.

For a deep walkthrough (bill math, month-close flow, cache key strategy, role/IDOR model), see [AGENTS.md § Domain Walkthrough](./AGENTS.md#domain-walkthrough).

**Layer structure:**
- `app/Http/Controllers/Mess/*` — manager routes under `role:admin` + `EnsureMessExists` middleware
- `app/Http/Controllers/My/*` — member routes under `role:user` (no `{member}` URL param — IDOR-structurally-impossible, see AGENTS.md)
- `app/Services/*` — 17 services: `BillPreviewService`, `DashboardService`, `MealGridService`, `MonthCloseService`, `ReportService`, `MemberStatementService`, `ChartBucketingService`, etc.
- `app/Jobs/CloseMonthJob.php` — queued month-close (idempotent, FOR UPDATE, snapshot)
- `app/Http/Middleware/EnsureMonthIsOpen.php` — hard-lock on 11 write routes for closed months
- `app/Providers/AppServiceProvider.php` — post-login redirect by role + the cache invalidation hook

### Roles & access

The app ships with four roles. Every manager route is gated by `roles:` middleware and the sidebar renders only the sections a role can actually use, so members never see (or 403 on) manager pages.

| Role | Sees | Lands on |
|------|------|----------|
| **super-admin** | Everything managers see **+** the System group (Tyro admin dashboard at `/dashboard`, backups, permanent member deletion) | `/dashboard` |
| **admin** | Full mess operations: members, meals, expenses, payments, reports, month-close, notification channel config | `/home` |
| **manager** | Same daily mess operations as admin (delegated mess manager) | `/home` |
| **user / mess-member** | Self-service only: own bill, payments, meal-off requests, own statement & monthly report | `/my` |

**Notifications** are role-aware too: members receive due-reminders, payment confirmations, and meal-off decisions on their configured channels; managers/super-admin receive month-close and backup-failure broadcasts.

---

## Backups

The backup system (`super-admin` only, at **Dashboard → Backups**) is built on `spatie/laravel-backup` and is designed to work on both a VPS **and** restrictive shared hosting (CloudPanel, cPanel, Plesk).

**What gets backed up:** the full MySQL database + `storage/app/public` (member photos, expense receipts). The `.env` file is **never** included. Backups land as date-stamped `.zip` files in `storage/app/backups`.

**Destinations / providers** — toggle each independently for two groups (backup destination vs. uploads mirror), DB-backed so no redeploy is needed to switch:

| Provider | Type | Env vars |
|---|---|---|
| **Local** | Always on | none — writes to `storage/app/backups` |
| **DigitalOcean Spaces** | S3-compatible | `DO_SPACES_KEY`, `DO_SPACES_SECRET`, `DO_SPACES_REGION`, `DO_SPACES_BUCKET`, `DO_SPACES_ENDPOINT` |
| **Cloudflare R2** | S3-compatible | `R2_KEY`, `R2_SECRET`, `R2_BUCKET`, `R2_ENDPOINT` |
| **Google Drive** | `masbug/flysystem-google-drive-ext` | `GOOGLE_DRIVE_CLIENT_ID`, `GOOGLE_DRIVE_CLIENT_SECRET`, `GOOGLE_DRIVE_REFRESH_TOKEN`, `GOOGLE_DRIVE_FOLDER_ID` |

**The Backups page:**
- **Backup now** / **Run restore-test** — manual triggers.
- **Activity log** — every attempt (backup / restore-test / download / delete) is recorded with status **and the captured error**, so a failure shows the real reason instead of vanishing. Each row has a **Delete** button; **Clear all** empties the log.
- **Configuration** (inline) — schedule (frequency + time), retention (keep-days + storage cap), and the per-provider toggles above. Saved changes take effect immediately.
- **Restore-test health badge** — the nightly restore-test verifies the latest backup by loading it into a scratch DB and asserting row-count parity.
- **Backup list** — download / restore / delete each archive. Restore is a guarded, typed-mess-name, audit-logged flow.

### Shared-hosting setup (CloudPanel / cPanel / Plesk)

Backups need three things that shared hosting sometimes lacks: **writable `storage/`**, the **`mysqldump` binary**, and a **scheduler cron**. Run the diagnostic once and it prints exactly what to fix for your panel:

```bash
php artisan backup:install
```

It creates `storage/app/{backups,laravel-backup,tmp}`, reports writability + owner, checks `mysqldump`, checks `open_basedir` vs. `sys_temp_dir`, and prints the cron line.

**The #1 CloudPanel/cPanel gotcha — ownership.** Deploying over SSH as `root` makes files root-owned, so PHP-FPM (which runs as the site user) can't write — the symptom is an opaque `ZipArchive::close(): Invalid argument`. Fix as root:

```bash
cd /home/<site-user>/htdocs/<domain>          # CloudPanel; cPanel: /home/<user>/public_html
chown -R <site-user>:<site-user> .            # re-own to the account PHP runs as
find storage bootstrap/cache -type d -exec chmod 775 {} \;
find storage bootstrap/cache -type f -exec chmod 664 {} \;
```

**`open_basedir` / `sys_temp_dir`:** `ZipArchive` uses the system temp dir internally; if your panel's `open_basedir` excludes it, set an in-account temp dir (panel PHP settings or `.user.ini`):
```ini
sys_temp_dir = /home/<site-user>/htdocs/<domain>/storage/app/tmp
upload_tmp_dir = /home/<site-user>/htdocs/<domain>/storage/app/tmp
```

**Automatic backups** require the Laravel scheduler running every minute (CloudPanel/cPanel Cron Jobs, or `/etc/cron.d`):
```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

The app also catches failures that `backup:run` silently swallows: if a run reports success but no zip was produced (e.g. `mysqldump` missing or the temp dir blocked), the pre-flight + post-run checks mark it failed and the activity log shows why.

For the full VPS/Forge disaster-recovery runbook (restore steps, credential rotation, retention), see [**DEPLOYMENT.md §11**](./DEPLOYMENT.md).

---

## Deployment

➡️ **Start with [SETUP-USAGE-DEPLOY.md](./SETUP-USAGE-DEPLOY.md)** — the consolidated guide covering **setup**, **daily usage by role** (super-admin / admin / manager / user), and deployment on both a **VPS** and **shared hosting (cPanel / Plesk / DirectAdmin)**.

For the full VPS/Forge production-hardening runbook, see [**DEPLOYMENT.md**](./DEPLOYMENT.md) — verbatim supervisor config, the `schedule:run` cron, the hard `APP_DEBUG=false` requirement, HTTPS, storage permissions, and production MySQL credentials.

> **Shared hosting:** the month-close runs as a queued job (`CloseMonthJob`), which normally needs a persistent queue worker via `supervisor` (so a VPS is preferred). However, shared hosting **is supported** by running jobs inline (`QUEUE_CONNECTION=sync`) — documented step-by-step in [SETUP-USAGE-DEPLOY.md §5.3](./SETUP-USAGE-DEPLOY.md#53-deploy-on-shared-hosting-cpanel--plesk--directadmin). Trade-off: "Close month" runs synchronously in the request (a few seconds for a small mess) with no background retries — fine for a single small mess; use a VPS for heavier load.

---

## Roadmap

**Recently shipped** — member soft/permanent delete, name-based member URLs, duplicate-member prevention, role-based grouped sidebar, and multi-channel notifications (email / WhatsApp / Telegram / SMS).

Still ahead:

- Bengali localization (`bn.json` + Bengali UI/PDF)
- Multi-mess runtime (schema-ready via `mess_id`)
- Real bKash / Nagad / Rocket payment-gateway integration
- PWA / offline mode, native mobile apps, real-time websockets
- 2FA + social login
- Per-member Telegram linking + at-rest encryption of stored channel credentials

See `.planning/REQUIREMENTS.md` § v2 Requirements for the full list.

## Contributing

PRs welcome. Run `vendor/bin/pint` and `vendor/bin/phpunit` before submitting.

---

## Credits

| | |
|---|---|
| **Agency** | [**Devsroom**](https://devsroom.com) — software development agency |
| **Developer portfolio** | [**wpmhs.com**](https://wpmhs.com) |

<p align="center"><em>Built with care for Bangladesh messes.</em></p>

## License

Released under the [MIT License](https://opensource.org/licenses/MIT). © [Devsroom](https://devsroom.com).

---

<sup>This README replaces the default Laravel stub. The full project plan (154 v1 requirements across 5 phases), research, and per-phase summaries live in `.planning/`.</sup>
