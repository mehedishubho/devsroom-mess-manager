# Devsroom Mess Management

**A mess manager can run a full month end-to-end on a phone — enter meals, log bazar, take payments, close the month, and produce a correct member bill — without spreadsheets and without arguing about who owes what.**

A web-based mess management system built for Bangladesh messes — bachelor hostels, student hostels, and shared accommodations. The mess manager enters daily meals and bazar expenses on mobile; members log in to view their bills, request meal-offs, and check their payment history. The system automates the Bangladesh-specific monthly close: meal rate derived from bazar only, fixed expenses split equally, advance balance carry-forward, and immutable monthly snapshots.

The v1 scope is **one mess, fully working for one real monthly cycle**. The schema is multi-mess-ready (`mess_id` on every domain table) but only one mess is exercised in v1.

---

## What It Does

- **Member management** — full CRUD with profile (name, mobile, email, NID, profession, room/seat, joining/leaving dates, status, emergency contact, photo). Member invite flow with email link + set-password page.
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
- **Notifications** — in-app (monthly closing complete, due reminder, payment received, meal-off approval). Bell icon in the nav.

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

# 6. Seed the base data (default DatabaseSeeder — creates expense categories +
#    a test user, does NOT run the perf demo seeder)
php artisan db:seed
```

After install, start the dev servers (in one terminal, runs `php artisan serve`, the queue listener, `pail` log tail, and Vite in parallel):

```bash
composer run dev
```

Then visit http://localhost:8000 and log in as the test user created by `DatabaseSeeder`, or seed the demo dataset (below) to use the demo manager/member accounts.

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

---

## Deployment

See [**DEPLOYMENT.md**](./DEPLOYMENT.md) for the full production-hardening checklist.

> **Shared hosting is ruled out.** The month-close runs as a queued job (`CloseMonthJob`) and requires a persistent queue worker via `supervisor`. Shared hosts can't reliably run a persistent worker, so a VPS (Forge-managed or manual) is required. The DEPLOYMENT.md checklist covers the Forge path (primary) and a manual VPS appendix, plus the verbatim supervisor config, the `schedule:run` cron, the hard `APP_DEBUG=false` requirement, HTTPS, storage permissions, and production MySQL credentials.

---

## License / Contributing / v2 Roadmap

- **License:** MIT (Laravel skeleton default).
- **Contributing:** PRs welcome. Run `vendor/bin/pint` and `vendor/bin/phpunit` before submitting.
- **v2 roadmap:** Bengali localization (`bn.json` + Bengali UI/PDF), multi-mess (schema-ready via `mess_id`), real bKash/Nagad/Rocket payment gateway integration, PWA/offline mode, native mobile apps, real-time websockets, 2FA + social login. See `.planning/REQUIREMENTS.md` § v2 Requirements for the full list.

---

*This README replaces the default Laravel stub. The full project plan (154 v1 requirements across 5 phases), research, and per-phase summaries live in `.planning/`.*
