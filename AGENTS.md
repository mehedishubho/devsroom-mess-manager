<!-- GSD:project-start source:PROJECT.md -->
## Project

**Devsroom Mess Management**

A web-based mess management system designed for Bangladesh messes — bachelor hostels, student hostels, and shared accommodations. The mess manager enters daily meals and bazar expenses on mobile; members log in to view their bills and submit meal-off requests. The system automates the Bangladesh-specific monthly close: meal rate derived from bazar only, fixed expenses split equally, advance balance carry-forward, and immutable monthly snapshots.

The v1 scope is **one mess, fully working for one real monthly cycle**.

**Core Value:** A mess manager can run a full month end-to-end on a phone — enter meals, log bazar, take payments, close the month, and produce a correct member bill — without spreadsheets and without arguing about who owes what.

### Constraints

- **Tech stack**: Laravel 13, MySQL 8+, Tyro Dashboard, Tyro Login, Tailwind v4, Vite 7. Fixed by taste preference.
- **Database naming**: snake_case (e.g. `devsroom_mess_management`, not hyphens) — per taste preference.
- **DB driver**: MySQL in dev AND prod — do NOT use sqlite locally. Per taste preference and to avoid dev/prod parity bugs.
- **DB credentials**: Verify with user before assuming defaults — per taste preference.
- **Code style**: Laravel Pint (Laravel preset). Run before commits.
- **Tests**: PHPUnit 12 (NOT Pest, despite plugin allowance). Use `RefreshDatabase` for feature tests.
- **No inline CSS, no Bootstrap** — Tailwind only.
- **All user-facing strings use `__()`** — even if only English is shipped.
- **Single mess in v1** — every domain table has `mess_id` but only one mess exists.
<!-- GSD:project-end -->

<!-- GSD:stack-start source:codebase/STACK.md (refreshed 2026-06-19 Plan 05-03) -->
## Technology Stack

## Runtime
- **PHP**: `^8.3` (declared in `composer.json`); dev runtime is `8.4.15` (ZTS x64 VS17). Extensions loaded: `pdo_mysql`, `gd`, `zip`, `mbstring`, `curl`, `pcov 1.0.12` (coverage driver).
- **Node.js**: `v24.15.0` (development tooling only — Vite build of Tailwind + Chart.js)
## Framework
- **Laravel**: `^13.0` (installed: `13.15.0`)
- **Project type**: Laravel application skeleton (Composer name `laravel/laravel`)
- **Conventions**: PSR-4 autoload, Laravel 13 directory structure
## Frontend
- **Vite**: `^7.0.7` — build tool and dev server (`vite.config.js`)
- **Tailwind CSS**: `^4.0.0` via `@tailwindcss/vite` plugin
- **Chart.js**: `^4.5.1` — dashboard charts (bundled into global `resources/js/app.js` via `window.initDashboardChart`)
- **Axios**: `^1.11.0` — HTTP client (meal-grid AJAX save)
- **Concurrently**: `^9.0.1` — runs server, queue, pail, vite in dev
- **No SPA framework**: This is a server-rendered Blade app, not Inertia/Livewire/React
## Authentication & Authorization Packages
- **`laravel/sanctum`**: `^4.0` — API token auth (installed, `personal_access_tokens` migration present, unused in v1)
- **`hasinhayder/tyro-dashboard`**: `^1.36` — admin dashboard (users, roles, privileges, settings, invitations, audit logs, dynamic CRUD resources) at `/dashboard`
- **`hasinhayder/tyro-login`**: provides auth UI (login, register, email verification, magic links, OTP, 2FA, social login, lockout after 5 failed attempts — AUTH-04)
- **`hasinhayder/tyro`**: roles/privileges package — `User` model uses `HasTyroRoles` trait. Three roles shipped: `super-admin`, `admin`, `user`.
## Domain Packages
- **`owen-it/laravel-auditing`**: `^14.0` — domain audit log (append-only) on MealEntry, MealOffRequest, GuestMeal, Expense, Payment, Member
- **`maatwebsite/excel`**: `^3.1` — `.xlsx` exports on all 4 reports (manager + member), `FromQuery` chunked + `(float)` cast + `WithColumnFormatting` for SUM-friendly Amount columns
- **`barryvdh/laravel-dompdf`**: `^3.1` — PDF exports, plain-CSS `layouts/pdf.blade.php` (no Tailwind — Pitfall 4), `isRemoteEnabled=false` (T-04-03-10 SSRF guard)
## Developer Tooling (require-dev — never loads in prod)
- **`barryvdh/laravel-debugbar`**: `^4.3` — bottom-of-page perf bar; three-layer prod gate (require-dev + `config/debugbar.php` enabled closure + `DEBUGBAR_ENABLED=false` in `.env.example`); excluded on `*.pdf`/`*.xlsx`/`api/*` (T-05-01-04 regression-tested)
- **`laravel/telescope`**: `^5.20` — `/telescope` request/queue/job inspector; three-layer prod gate + `Gate::define('viewTelescope', super-admin only)`; `telescope:prune` daily
- **`laravel/boost`**: `^2.4` — AI agent integration (MCP tools)
- **`laravel/pint`**: `^1.27` — code style fixer (Laravel preset)
- **`laravel/pail`**: `^1.2.5` — error tailing in dev (`php artisan pail`)
- **`nunomaduro/collision`**: `^8.6` — pretty test errors
- **`mockery/mockery`**: `^1.6` — test doubles
- **`fakerphp/faker`**: `^1.23` — test data
- **`phpunit/phpunit`**: `^12.5.12` (installed: `12.5.30`) — test runner
- **`pcov 1.0.12`** (PHP extension, not Composer) — coverage driver; baseline `Lines 85.75%` (2119/2471)
## Database
- **Default connection**: `mysql` (dev `.env` + `.env.example` + `phpunit.xml`) — sqlite is NOT used anywhere (dev/prod parity constraint)
- **Cache / Sessions / Queue**: `database` driver (cache + sessions + jobs tables migrated)
- **Naming convention**: `snake_case` for all database + table + column names
- **Money**: `DECIMAL(10,2)` columns + `decimal:2` casts — never float
## Configuration
- `boost.json` enables skills: `laravel-best-practices`, `tyro-dashboard`
- `.agents/skills/` + `.claude/skills/` directories contain project-local skill definitions
- `phpunit.xml` uses a dedicated `devsroom_mess_management_testing` MySQL database (NOT sqlite `:memory:`, per the MySQL-only constraint)
## Tailwind/Vite Assets
- `resources/css/app.css` and `resources/js/app.js` are the build entry points
- `resources/js/app.js` exposes `window.initDashboardChart(canvasId, config)` with a destroy-before-recreate guard (prevents Chart.js canvas memory leak)
- `resources/views/` contains all Blade templates (welcome + 30+ app views across `mess/`, `my/`, `layouts/`, `components/`)
- HMR enabled in dev via Vite `refresh: true`
## Test Coverage (Phase 5 Plan 02 baseline)
- **243 tests, 576 assertions** (PHPUnit 12)
- **Line coverage 85.75%** via pcov (target >70%, margin +15.75pp)
- Perf regression locks: `tests/Feature/Perf/MealGridQueryCountTest.php` (grid < 15 queries at 50 members), dashboard no-N+1 test, `tests/Feature/Report/PdfDebugbarExclusionTest.php` (PDF stays clean under Debugbar)
<!-- GSD:stack-end -->

<!-- GSD:conventions-start source:CONVENTIONS.md -->
## Conventions

## Code Style
- **Laravel Pint** is configured (Laravel preset). Run `vendor/bin/pint` before commits.
- **PSR-12** is the base standard.
- **EditorConfig** present (`.editorconfig`) — uses 4-space indent, LF line endings, UTF-8.
## PHP
### Attribute-Based Model Configuration
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
- New models should follow the same pattern (use `#[Fillable]`, `#[Hidden]` attributes)
- Avoid the older `$fillable` / `$hidden` property arrays when starting fresh
### Casts Method (Laravel 11+ style)
- Use the `casts()` method, not the deprecated `$casts` property
### Imports
- One `use` per import, alphabetically grouped
- Example from `User.php`:
### Migration Style
- Use anonymous classes, not named classes
- Always implement both `up()` and `down()`
- Use `Blueprint` typed parameter
- Foreign keys use `foreignId('user_id')` + `constrained()` pattern
### Test Style
- Extends `Tests\TestCase`
- Test methods prefixed with `test_` (PHPUnit snake_case, not Pest)
- `void` return type on test methods
## Database
- **snake_case** for all column and table names
- **Plural** table names (`users`, `personal_access_tokens`)
- **Timestamps** (`$table->timestamps()`) on all domain tables
- **Soft deletes** preferred for user-facing entities (not seen in skeleton, but standard)
- **Foreign keys**: `foreignId('user_id')->constrained()->cascadeOnDelete()` pattern
## Authorization
- Use Tyro roles/privileges, not Laravel Gates/Policies (initially)
- Check role: `$user->hasRole('admin')` (Tyro API)
- Use middleware `role:admin` for route protection
## Frontend
- **Tailwind CSS v4** with `@tailwindcss/vite` plugin
- **No JavaScript framework** — vanilla JS in `resources/js/app.js`
- **Axios** for AJAX (configured but no usage yet)
- All Blade, no Inertia/Livewire
## Error Handling
- Skeleton has no custom error handling
- Follow Laravel defaults: throw `ValidationException`, `ModelNotFoundException`, `AuthorizationException` from actions
- Use Form Requests for validation (`app/Http/Requests/`)
- Use Policies for authorization
## Service Registration
- Register bindings in `AppServiceProvider::register()` for app-wide singletons
- Use `boot()` for view composers, gates, observer registration
## Git
- Initialized in this session
- No commits yet
- `.gitignore` covers vendor, node_modules, .env, storage logs/cache, IDE folders, build artifacts
## PHPStan / Static Analysis
- **Not configured** — consider adding `larastan` (`nunomaduro/larastan`) for type safety
## Things To Avoid
- Don't add `$fillable` / `$hidden` property arrays to new models (use attributes)
- Don't use the deprecated `$casts` property (use the `casts()` method)
- Don't create named migration classes (use anonymous)
- Don't bypass Tyro for auth/roles (don't roll custom auth when Tyro provides it)
- Don't use hyphens in database names (per taste preference: `devsroom_mess_management`)
- Don't assume default DB credentials (per taste preference — verify with user)
<!-- GSD:conventions-end -->

<!-- GSD:architecture-start source:ARCHITECTURE.md (refreshed 2026-06-19 Plan 05-03) -->
## Architecture

## Pattern
- Server-rendered Blade templates (no SPA, no Inertia/Livewire)
- Eloquent ORM for persistence; **service layer** for business logic (no Repository pattern)
- Service container for dependency injection (services injected into controllers via constructor)
- PSR-4 autoloading under `App\`, `Database\Factories\`, `Database\Seeders\`, `Tests\`
- Money always `decimal:2` / `DECIMAL(10,2)` — never float

## Layers
```
HTTP Request
  → Middleware (auth, role:admin|user|super-admin, EnsureMessExists, EnsureMonthIsOpen on 11 write routes)
  → Controller (app/Http/Controllers/Mess/* for admin, app/Http/Controllers/My/* for member)
  → Form Request (app/Http/Requests/* — validation, never $this->validate())
  → Service (app/Services/* — 17 services, owns the math)
  → Model + DB (app/Models/* — Eloquent + Auditable trait on 6 domain models)
  → Blade view (resources/views/**.blade.php — Tailwind v4, components under components/)
  → Cache (1h TTL, mess-scoped keys — see Domain Walkthrough)
```

### Request Flow (typical)
`GET /home` → `HomeController::index` → `DashboardService::managerCards()` → reads `bill-preview:{mess_id}:{Y}-{MM}` cache (1h) → if miss: `BillPreviewService::preview()` computes → returns 6 cards + 3 chart datasets → `home.blade.php` renders `<x-stat-card>` + Chart.js via `window.initDashboardChart`.

## Key Components
### `App\Models\User`
- Extends `Illuminate\Foundation\Auth\User as Authenticatable`
- Uses `HasApiTokens` (Sanctum), `HasFactory`, `Notifiable`, `HasTyroRoles`, `HasTwoFactorAuth`
- Fillable: `name`, `email`, `password` (via `#[Fillable]` attribute)
- Hidden: `password`, `remember_token` (via `#[Hidden]` attribute)
- Casts: `email_verified_at` → datetime, `password` → hashed
- `getMemberOrNull(): ?Member` — derives the Member row for member-side routes (the IDOR-structural fix)

### `App\Providers\AppServiceProvider`
- `boot()`: role-based post-login redirect (D-02 — admin→/home, user→/my, super-admin→/dashboard or /onboarding) + the `registerBillPreviewInvalidation()` hook that fires `Cache::forget` on `bill-preview:` + `dash:counts:` keys for the affected (year, month) whenever MealEntry/GuestMeal/MealOffRequest/Expense/Payment is saved or deleted. Single listener body, fires for both saved+deleted on all 5 models — preserves <2s refresh (success #12).

### `App\Http\Controllers\Controller`
- Abstract base class, no shared logic

## Authorization Model
- **Tyro roles**: `super-admin`, `admin`, `user` (verbatim slugs — see Domain Walkthrough §Role/IDOR)
- **Tyro privileges**: fine-grained, assigned to roles
- **Route middleware**: `role:admin` (manager), `role:user` (member), `role:super-admin` (onboarding), `EnsureMessExists` (refuses if no mess configured), `EnsureMonthIsOpen` (alias `month.open` — refuses writes to closed months on 11 routes)
- **Gate**: `viewTelescope` defined for super-admin only

## Authentication Flow
- Session-based for web (Tyro Login at `/login`, `/register`, `/password/*`, `/verify-email`)
- Token-based for API (Sanctum — `personal_access_tokens` table present, **unused in v1**)
- Login → role-based redirect set in `AppServiceProvider::boot()` (D-02)
- Lockout after 5 failed attempts for 15 minutes (AUTH-04, Tyro built-in)
- Email verification optional (`TYRO_LOGIN_REQUIRE_EMAIL_VERIFICATION=false`)

## Data Flow (Typical)
Write path: `POST /mess/meals` → `EnsureMonthIsOpen` middleware (refuses if month closed) → `MealGridController::save` → `MealGridService::saveGrid` (single transaction) → `MealEntry` saves fire `eloquent.saved: App\Models\MealEntry` → `AppServiceProvider::invalidateForModel()` → `Cache::forget('bill-preview:...')` + `Cache::forget('dash:counts:...')`. Next read of `/home` or `/mess/bill-preview` recomputes (<2s).

## Data Model (Current — 16 domain models + Laravel defaults)
- `messes` + `settings` (per-mess config: meal values, currency, date format)
- `members` (status: active/inactive/former; `mess_id` scoped via `MessScope` global scope; per-mess-unique **`slug`** is the route binding key → `/mess/members/{slug}`; SoftDeletes — `delete()` soft-deletes, `forceDelete()` permanent + dependency-guarded)
- `meal_entries` (UNIQUE mess_id+member_id+date; B/L/D booleans)
- `meal_off_requests` (status: pending/approved/rejected; from_date/to_date + reason)
- `guest_meals` (host member FK + charge_amount)
- `expense_categories` (kind: bazar/fixed/other — kind lives HERE, not on expense)
- `expenses` (`purchased_by` snake_case FK; `expense_category_id`; `date`)
- `payments` (type: `bill_payment`/`advance_deposit` schema-enforced; method enum)
- `advance_balances` (per-member running `balance` + `due_balance`)
- `monthly_closings` (UNIQUE mess_id+year+month — the idempotency lock)
- `monthly_member_summaries` (immutable snapshot per close)
- `monthly_corrections` (post-close adjustment entries; snapshot stays immutable)
- `notifications` (in-app, always-on canonical record: close_complete, due_reminder, payment_received, meal_off_decision, backup_failed). Multi-channel delivery fans out from `NotificationService::send()` via `ChannelManager` to the mess's enabled external channels (`App\Notifications\Channels\{Email,Telegram,Whatsapp,Sms}Channel`) — each **fails open**. Channel toggles + credentials + per-type routing live in `settings` (key `notifications.config`); per-user preferences live on `users.notification_preferences` (JSON) and are intersected with the mess-enabled set at dispatch.
- `member_invitations` (invite flow)
- Laravel defaults: `users` (adds `notification_preferences` JSON), `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `personal_access_tokens`, telescope tables (3)
- Tyro tables: `roles`, `privileges`, `role_user`, `privilege_role`, `invitations`, `audit_logs`

## Routing (`routes/web.php`)
- `GET /` → welcome view
- **Onboarding** (`role:super-admin` + `EnsureMessExists`): `/onboarding` create/store
- **Manager** (`role:admin` + `EnsureMessExists`): `/home`, `/mess/settings`, `/mess/notifications` (channel config), `/mess/members` (resource), `DELETE /mess/members/{member}` (soft delete), `DELETE /mess/members/{member}/force` (`role:super-admin` — permanent, dependency-guarded), `/mess/meals`, `/mess/guest-meals`, `/mess/meal-off` (approval), `/mess/expenses` (bazar + fixed), `/mess/categories`, `/mess/payments`, `/mess/advance-balances`, `/mess/bill-preview`, `/mess/reports/{monthly,member-statement,expenses,payments}` + `.pdf`/`.xlsx` variants, `/mess/close`, `/mess/closings`, `/mess/closings/{c}/corrections`, `/mess/due-reminder`, `/mess/audit`
- **Member** (`role:user`): `/my`, `/my/profile`, `/my/meal-off`, `/my/payments`, `/my/bill-preview`, `/my/reports/{statement,monthly}` + `.pdf`/`.xlsx` variants — **NO `{member}` URL param anywhere** (T-04-02-01; member-side resolution is by `auth()->user()`, never by a URL value)
- **Shared** (`auth` + `EnsureMessExists`): `/notifications`, `/notification-preferences` (per-user channel choices)
- Public: `/set-password` (from invite link)
- 11 manager write routes additionally carry the `month.open` middleware alias (`EnsureMonthIsOpen`)
- Tyro dashboard auto-registered at `/dashboard/*`; Tyro login at `/login`, `/register`, `/logout`, `/password/*`
- `routes/console.php`: `telescope:prune` daily (class_exists guard for prod)

## Build & Runtime
- **Dev**: `composer run dev` starts `php artisan serve`, `queue:listen`, `pail`, `vite` in parallel
- **Build**: `npm run build` (Vite production build — Tailwind v4 + Chart.js into `public/build/`)
- **Test**: `composer run test` → `php artisan test` (PHPUnit 12, 243 tests)
- **Migrate**: `php artisan migrate`
- **Seed**: `php artisan db:seed` (default — creates expense categories + test user; does NOT run PerfDemoSeeder). Demo dataset: `php artisan db:seed:perf-demo` (guarded).

## Extension Points
- `config/tyro-dashboard.php`, `config/tyro-login.php` — Tyro config
- `config/debugbar.php`, `config/telescope.php` — dev tooling (require-dev)
- `App\Notifications\Contracts\NotificationChannel` — implement to add a delivery channel; register its key in `MessNotificationSettings::CHANNELS` + `ChannelManager::CHANNEL_CLASSES`
- `App\Services\MessNotificationSettings` — per-mess channel config (one JSON row in `settings`, key `notifications.config`)
- `App\Providers\AppServiceProvider::boot()` — post-login redirect + cache invalidation hook
- `App\Providers\TelescopeServiceProvider::gate()` — who can view /telescope
- Custom policies, form requests, jobs, listeners under `app/*/`
<!-- GSD:architecture-end -->

<!-- GSD:skills-start source:skills/ -->
## Project Skills

| Skill | Description | Path |
|-------|-------------|------|
| laravel-best-practices | "Apply this skill whenever writing, reviewing, or refactoring Laravel PHP code. This includes creating or modifying controllers, models, migrations, form requests, policies, jobs, scheduled commands, service classes, and Eloquent queries. Triggers for N+1 and query performance issues, caching strategies, authorization and security patterns, validation, error handling, queue and job configuration, route definitions, and architectural decisions. Also use for Laravel code reviews and refactoring existing Laravel code to follow best practices. Covers any task involving Laravel backend PHP code patterns." | `.claude/skills/laravel-best-practices/SKILL.md` |
| tyro-dashboard | "Use for Tyro Dashboard framework or app integration work: admin pages, routes, sidebar/menu changes, settings, CRUD resources, Blade overrides, RBAC, media, plugins, service providers, public APIs, and upgrade-safe framework maintenance for hasinhayder/tyro-dashboard, hasinhayder/tyro, tyro-login, or Laravel apps consuming Tyro Dashboard." | `.claude/skills/tyro-dashboard/SKILL.md` |
<!-- GSD:skills-end -->

<!-- GSD:workflow-start source:GSD defaults -->
## GSD Workflow Enforcement

Before using Edit, Write, or other file-changing tools, start work through a GSD command so planning artifacts and execution context stay in sync.

Use these entry points:
- `/gsd-quick` for small fixes, doc updates, and ad-hoc tasks
- `/gsd-debug` for investigation and bug fixing
- `/gsd-execute-phase` for planned phase work

Do not make direct repo edits outside a GSD workflow unless the user explicitly asks to bypass it.
<!-- GSD:workflow-end -->



<!-- GSD:profile-start -->
## Developer Profile

> Profile not yet configured. Run `/gsd-profile-user` to generate your developer profile.
> This section is managed by `generate-claude-profile` -- do not edit manually.
<!-- GSD:profile-end -->

---

## Domain Walkthrough

Hand-written (Plan 05-03, D-17). This is the load-bearing reference for anyone (human or agent) touching the core business logic. The four topics below explain the math and invariants that the service layer implements; the code in `app/Services/` is the single source of truth — if this doc ever disagrees with the code, the code wins.

### Bill math

Source of truth: [`app/Services/BillPreviewService.php`](../app/Services/BillPreviewService.php) (used both for the live preview mid-month AND verbatim by the month-close snapshot — they cannot drift).

```
meal_rate      = total_bazar / total_meals         (bazar ONLY — FIXED-04 excludes fixed)
fixed_share    = total_fixed / active_member_count (prorated by active_days for mid-month joiners — FIXED-03)
member_meals   = Σ MealEntry B/L/D × MealType::value()  (breakfast=0.5, lunch=1, dinner=1, configurable in settings)
guest_total    = Σ GuestMeal.charge_amount          (charged to the host member's bill at the configured meal value)
gross_bill     = (member_meals × meal_rate) + fixed_share + guest_total
bill_payments  = Σ Payment WHERE type = bill_payment
net_bill       = gross_bill − bill_payments
```

Carry-forward (per member, tracked in `advance_balances`):
- Positive `net_bill` (member owes) → recorded into `due_balance`.
- Negative `net_bill` (member overpaid) → recorded into `balance` (the advance carried into the next month).
- `advance_deposit` payments (PaymentType::ADVANCE_DEPOSIT) increase `balance`; they are **NOT** auto-applied against the gross bill here (CR-03 — see the long comment in `BillPreviewService::compute` lines 153-160). The `advance_applied` field on the snapshot is the bill-payment-type payments made against this month, NOT an advance drawdown. A rename to `bill_payments_applied` is tracked as follow-up.

Mid-month joiner/leaver handling:
- A member is **eligible for the meal-rate denominator** only if `joining_date <= month_start` AND `leaving_date >= month_end` (or null) AND status is active or former (NOT inactive). See `eligibleForDenominator()`.
- `fixed_share` is **prorated by `active_days / days_in_month`** — `active_days` counts overlap with the month window (`activeDaysForMember()`).

### Month-close flow

Source of truth: [`app/Jobs/CloseMonthJob.php`](../app/Jobs/CloseMonthJob.php) (queued), [`app/Services/MonthCloseService.php`](../app/Services/MonthCloseService.php) (the math), [`app/Http/Middleware/EnsureMonthIsOpen.php`](../app/Http/Middleware/EnsureMonthIsOpen.php) (the hard lock).

Properties (all locked by tests in `tests/Feature/Close/`):
1. **Queued** (CLOSE-02): `CloseMonthJob implements ShouldQueue` on the `database` queue. `$tries = 1`, `$timeout = 120`. Triggered from `POST /mess/close` → `MonthCloseController::trigger` → `CloseMonthJob::dispatch`.
2. **Idempotent** (CLOSE-07): `MonthlyClosing::firstOrCreate(['mess_id', 'year', 'month'], ...)` backed by a `UNIQUE(mess_id, year, month)` index. If the row already exists, `wasRecentlyCreated` is false → the handler returns the existing closing + summaries **without rewriting anything**. Safe to double-click.
3. **Atomic**: `MonthCloseService::close` wraps everything in `DB::transaction`.
4. **Immutable snapshot** (CLOSE-09): for each member, a `MonthlyMemberSummary` row is created from `BillPreviewService::preview()` output. Money is frozen via `number_format()` into normalized 2-decimal strings (never round-trips through float).
5. **Hard-locked post-close** (CLOSE-10): the `EnsureMonthIsOpen` middleware (alias `month.open`) is attached to **11 manager write routes** (meal save, guest-meal create/update, meal-off request/approve/reject, bazar/fixed store, payment store/update/destroy). If the request's date falls in a closed `(year, month)`, the middleware refuses with a "MONTH CLOSED" validation error. Strict `Y-m-d` parsing (WR-04 — `Carbon::createFromFormat('!Y-m-d', …)` rejects e.g. `2026-02-31`).
6. **Corrections are append-only** (CLOSE-12): corrections go through the separate `/mess/closings/{closing}/corrections` routes → `monthly_corrections` table. They apply immediately to the member's `advance_balances` + audit log; the original `monthly_member_summaries` snapshot stays immutable. Corrections routes are intentionally NOT locked by `month.open` (they target closed months by design).
7. **Math reuses BillPreviewService verbatim** (D-18): the close path calls `app(BillPreviewService::class)->preview($year, $month)` — same cache, same formulas. There is a parity test (`test_close_numbers_match_bill_preview_service_for_same_inputs`).
8. **Notification**: on close-complete, `NotificationService::notifyCloseComplete()` writes an in-app `close_complete` notification to all managers + super-admins (NOTIF-01).

### Cache key strategy

Source of truth: [`app/Providers/AppServiceProvider.php`](../app/Providers/AppServiceProvider.php) (`registerBillPreviewInvalidation` + `invalidateForModel`), [`app/Services/BillPreviewService.php`](../app/Services/BillPreviewService.php), [`app/Services/BillPreviewInvalidator.php`](../app/Services/BillPreviewInvalidator.php), [`app/Services/DashboardService.php`](../app/Services/DashboardService.php).

Two keys per `(mess_id, year, month)`, both database-driver, 1-hour TTL, **NO cache tags** (the `database` driver doesn't support them):

| Key pattern | Tenant | Owner | Invalidated by |
|---|---|---|---|
| `bill-preview:{mess_id}:{YYYY}-{MM}` | mess + month | `BillPreviewService::cacheKey()` | `BillPreviewInvalidator::forDate()` |
| `dash:counts:{mess_id}:{YYYY}-{MM}` | mess + month | `DashboardService::managerCards()` | inline `Cache::forget()` in `AppServiceProvider::invalidateForModel()` |

Invalidation hook (`AppServiceProvider::boot()` → `registerBillPreviewInvalidation()`):
- Fires on BOTH `eloquent.saved: {Model}` AND `eloquent.deleted: {Model}` for these 5 models: `MealEntry`, `GuestMeal`, `MealOffRequest`, `Expense`, `Payment`.
- A single listener body (`invalidateForModel`) handles all 10 events — NO duplicate `Event::listen` calls (preserves <2s refresh, success #12).
- Date resolution prefers the business date column (`date` for 4 models, `from_date` for `MealOffRequest`) — never falls back to `now()`, which would invalidate the wrong month. Falls back to `created_at` only when the model genuinely lacks a business date.
- Both keys are scoped by `Mess::activeId()` → **cross-mess cache bleed is structurally impossible** (T-04-03-01, regression-locked by `tests/Feature/CacheInvalidationTest`).

Cache hit-rate budget: >80% (D-09). Measured at 100.0% on a warm pure-read loop (Plan 05-02 §2).

### Role / IDOR model

Source of truth: [`app/Providers/AppServiceProvider.php`](../app/Providers/AppServiceProvider.php) (post-login redirect), [`app/Http/Controllers/My/MyReportController.php`](../app/Http/Controllers/My/MyReportController.php) (the IDOR-structural fix), [`routes/web.php`](../routes/web.php).

**Roles** (Tyro slugs, verbatim — do not rename):
| Slug | Routes | Lands on after login |
|---|---|---|
| `super-admin` | `/dashboard/*` (Tyro), `/onboarding` | `/dashboard` (or `/onboarding` if no mess exists) |
| `admin` (the "manager") | `/home`, all `/mess/*` | `/home` |
| `user` (the "member") | `/my`, `/my/*` | `/my` |

**IDOR model — structurally impossible, not just policy-enforced** (T-04-02-01):
- Member routes (`role:user`) have **NO `{member}` URL parameter** anywhere. `MyReportController::statement()` / `::monthly()`, `MyBillPreviewController`, `MyPaymentController`, `MyController` all derive the member from `$request->user()->getMemberOrNull()`. Any `?member_id=` query param in the URL is **ignored** by these controllers.
- A member therefore literally cannot address another member's data through the URL — there is nothing for a policy to check because there is nothing to forge.
- Manager-side member access (`/mess/members/{member}`, member-statement exports with `?member_id=`) is guarded by `Member::where('id', $id)->firstOrFail()` + the `MessScope` global scope on `Member` — a cross-mess member ID returns 404 (T-04-03-08).
- Member-side Monthly Report is **aggregates-only** (D-19): `MyReportExportController::monthlyExcel` passes `['members' => []]` to `MonthlyReportExport` — peer rows can NEVER leave the server for a member request (enforced in DATA shape, not just view).

**Cross-mess isolation** (v1: single mess, but the model is ready):
- Every domain table carries `mess_id`. The `MessScope` global scope auto-filters reads by the active mess.
- `Mess::activeId()` is the single source of truth for "current mess" (cached). Cache keys include `mess_id` so v2 multi-mess doesn't bleed.

**Telescope access**: `Gate::define('viewTelescope', fn (User $u) => $u->hasRole('super-admin'))` in `TelescopeServiceProvider::gate()`. In local env Telescope bypasses the gate automatically; in staging/prod only super-admin can view `/telescope`.
