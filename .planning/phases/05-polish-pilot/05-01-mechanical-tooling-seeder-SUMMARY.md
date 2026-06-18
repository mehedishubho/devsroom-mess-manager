---
phase: 05-polish-pilot
plan: 01
subsystem: dev-tooling + perf fixture + mechanical audits
tags: [tooling, audit, seeder, coverage, debugbar, telescope, perf]
requires:
  - "Phase 4 dashboard + exports complete (so /mess/reports/monthly.pdf exists for the T-05-01-04 verification)"
  - "Plan 05-01 PLAN.md (the wave-1 unblocker plan this summary documents)"
provides:
  - "MySQL + Asia/Dhaka dev/prod parity (.env + .env.example)"
  - "barryvdh/laravel-debugbar ^4.3 in require-dev with three-layer prod gate"
  - "laravel/telescope ^5.20 in require-dev with three-layer prod gate + daily prune"
  - "PerfDemoSeeder (~50 members, <3s, deterministic count) — keystone perf fixture + demo creds source"
  - "SeedPerfDemo artisan command with production guard"
  - "PdfDebugbarExclusionTest regression test for T-05-01-04"
  - "pcov 1.0.12 loaded — coverage measurement works (baseline Lines 85.55%)"
  - "05-MECHANICAL-AUDIT.md with all 4 audit sections + coverage driver install record"
affects:
  - "Plan 02 (perf measurement + coverage measurement) — fully unblocked, no escape hatch needed"
  - "Plan 03 (README rewrite) — demo creds available (manager@demo.test / member@demo.test)"
tech-stack:
  added:
    - "barryvdh/laravel-debugbar ^4.3 (require-dev)"
    - "laravel/telescope ^5.20 (require-dev)"
    - "pcov 1.0.12 PHP extension (system-level, php.ini edit)"
  patterns:
    - "Three-layer prod gate for dev-only tooling: require-dev + enabled closure/Gate::define + .env.example defaults"
    - "WithoutModelEvents + config(['audit.enabled'=>false]) for high-volume seeders (Pitfall 6)"
    - "exclude_paths/except for Debugbar on PDF/Excel export routes (Pitfall 2 / T-05-01-04)"
    - "Regression test (PdfDebugbarExclusionTest) to lock mitigation"
key-files:
  created:
    - "config/telescope.php"
    - "config/debugbar.php"
    - "app/Providers/TelescopeServiceProvider.php"
    - "database/migrations/2026_06_18_225802_create_telescope_entries_table.php"
    - "database/seeders/PerfDemoSeeder.php"
    - "app/Console/Commands/SeedPerfDemo.php"
    - "tests/Feature/Report/PdfDebugbarExclusionTest.php"
    - ".planning/phases/05-polish-pilot/05-MECHANICAL-AUDIT.md"
  modified:
    - ".env (dev DB MySQL + APP_TIMEZONE=Asia/Dhaka + TELESCOPE/DEBUGBAR_ENABLED toggles)"
    - ".env.example (sanitized template: APP_TIMEZONE + MySQL + TELESCOPE/DEBUGBAR_ENABLED=false)"
    - "composer.json (require-dev: +debugbar +telescope)"
    - "composer.lock"
    - "bootstrap/providers.php (registered TelescopeServiceProvider)"
    - "routes/console.php (daily telescope:prune, class_exists guard)"
    - "database/seeders/DatabaseSeeder.php (D-07 guard comment — does NOT call PerfDemoSeeder)"
    - ".gitignore (/storage/debugbar)"
    - "php.ini (system, not tracked: extension=pcov)"
decisions:
  - "D-06 (Debugbar + Telescope as require-dev) — shipped"
  - "D-07 (~50-member reproducible seeder) — shipped, deterministic count, <3s"
  - "D-18 (dev .env sqlite→MySQL parity) — shipped"
  - "D-19 (Pint clean) — shipped"
  - "D-20 (__() scan) — shipped, 0 literals needed wrapping"
  - "D-21 (Asia/Dhaka timezone) — shipped via .env (config/app.php untouched)"
  - "D-22 PREREQUISITE (coverage driver) — shipped, pcov 1.0.12, baseline 85.55% lines"
metrics:
  duration: ~22 minutes
  completed: 2026-06-18
  tasks_completed: 4
  files_created: 8
  files_modified: 9
  tests_before: 233
  tests_after: 234
requirements:
  - PERF-13
---

# Phase 5 Plan 01: Mechanical Tooling + PerfDemoSeeder Summary

Closed four cheap dev/prod parity gaps (timezone UTC default, dev `.env` sqlite, Pint drift, `__()` scan) AND installed Debugbar + Telescope as require-dev with three-layer prod gating AND installed pcov 1.0.12 as the code coverage driver AND built the guarded ~50-member PerfDemoSeeder that unblocks perf measurement, mobile density testing, and the README demo-credentials story. Plan 02 (perf + coverage measurement) and Plan 03 (README demo creds) are now fully unblocked — including the previously-escape-hatched D-22 / success #9.

## Outcome (per task)

### Task 1 — Mechanical audits + .env/.env.example parity (D-18, D-19, D-20, D-21) ✅

- **D-21 timezone**: `.env` + `.env.example` set `APP_TIMEZONE=Asia/Dhaka`. `config/app.php` UNCHANGED (per "env() only inside config files" — fix lives in `.env`). Verified `now()->timezoneName == 'Asia/Dhaka'` in tinker.
- **D-18 sqlite→MySQL**: `.env` switched from `DB_CONNECTION=sqlite` (MySQL commented out) to active MySQL block with dev's actual creds. `.env.example` now ships sanitized MySQL defaults. `php artisan migrate:fresh --seed` ran cleanly (37 migrations) on MySQL. `phpunit.xml` was already MySQL — no test-side change.
- **D-19 Pint**: bumped 1.29.1 → 1.29.3 (non-breaking). `vendor/bin/pint --test` exits 0. No files needed reformatting.
- **D-20 `__()` scan**: ripgrep on `resources/views/**/*.blade.php` for un-wrapped literals → 0 hits in app views (allowlist categories: function calls, HTML attrs, vendor views, blade comments). No strings wrapped (none needed). `bn.json` deferred to v2 per PROJECT.md.
- Created `05-MECHANICAL-AUDIT.md` documenting all four audits with command output + final state.

### Task 2 — Debugbar + Telescope require-dev + three-layer prod gate (D-06) ✅

- `composer require --dev barryvdh/laravel-debugbar:^4.3 laravel/telescope:^5.20` — both in `composer.json` `require-dev`.
- Published `config/telescope.php`, `config/debugbar.php`, `app/Providers/TelescopeServiceProvider.php`, and the Telescope migration (3 tables: `telescope_entries`, `telescope_entries_tags`, `telescope_monitoring` — verified).
- **Three-layer gate verified end-to-end:**
  1. `require-dev` — prod `composer install --no-dev` cannot load them.
  2. `config/telescope.php` `enabled` closure returns false in non-local; `.env.example` ships `TELESCOPE_ENABLED=false` + `DEBUGBAR_ENABLED=false`.
  3. `TelescopeServiceProvider::gate()` = `Gate::define('viewTelescope', super-admin only)`.
- `routes/console.php` schedules `telescope:prune` daily wrapped in `class_exists(Telescope::class)` so prod doesn't error (Pitfall 1).
- Debugbar `except` array excludes `*.pdf`, `*.xlsx`, `api/*` (Pitfall 2 / T-05-01-04). `capture_ajax=true` (we want meal-grid AJAX timing); `ajax_handler_auto_show=false` + `ajax_handler_enable_tab=false` so the bar can't be injected into JSON bodies (T-05-01-05).
- **T-05-01-04 mitigation ENFORCED end-to-end** (not just configured): wrote `tests/Feature/Report/PdfDebugbarExclusionTest.php` that hits `/mess/reports/monthly.pdf` with Debugbar explicitly enabled (`config(['debugbar.enabled' => true])`) and asserts status=200, content-type=application/pdf, body starts with `%PDF`, and contains NO `debugbar` / `phpdebugbar` / `<script>phpdebugbar` string anywhere. 10 assertions, all pass. Future changes that break the exclude rule fail this test.

### Task 3 — Guarded ~50-member PerfDemoSeeder (D-07) ✅

- `database/seeders/PerfDemoSeeder.php` — `Demo Mess` + 48 active + 1 former + 1 inactive member (50 total). One full month of B/L/D meals (882 entries on day 18), 54-90 bazar expenses, 6 fixed expenses, ~25-35 payments mixing `bill_payment` + `advance_deposit`.
- Demo creds feed Plan 03 README: `manager@demo.test` (admin) + `member@demo.test` (user), password `"password"`.
- **Pitfall 6 applied:** `use WithoutModelEvents` + `config(['audit.enabled' => false])` so audit + cache hooks do not fire N×50 times. Seeder completes in **~2.7s** (well under the 30s budget).
- **Schema correctness:** used `purchased_by` (snake_case — matches `expenses` migration), filtered `expense_categories` by `kind` (the kind column lives on the category, not the expense — `drop_expense_type_from_expenses` removed it). Seeded Tyro roles with `Role::firstOrCreate(['slug' => 'admin'], ...)` (assignRole takes a Role object, not a string).
- `Mess::forgetActiveIdCache()` warms the cache so factories pick up the Demo Mess.
- `database/seeders/DatabaseSeeder.php` guarded — does NOT call `PerfDemoSeeder` (comment documents the explicit-run path).
- `app/Console/Commands/SeedPerfDemo.php` wraps with `app()->isProduction()` guard (`--force` override available, NOT RECOMMENDED).
- **Determinism verified** across two runs: Member count = 50 → 50 (identical). MealEntries = 882 → 882 (49 members × 18 days). Bazar expenses varied 71-75 (within the 54-90 range — acceptable for a perf fixture). Payments 31 → 31.

### Task 4 — Coverage driver install (pcov 1.0.12 — D-22 prerequisite) ✅

- **Gap confirmed:** `php -m` showed neither pcov nor xdebug. PHPUnit would have errored "No code coverage driver available."
- **pcov chosen (not xdebug)** per Pitfall 3: purpose-built for line coverage, 2-3× faster than xdebug, doesn't slow normal test runs. The Assumption A2 uncertainty (DLL availability for PHP 8.4 ZTS Windows) **resolved positively** — an exact-match DLL exists.
- Downloaded `php_pcov-1.0.12-8.4-ts-vs17-x64.zip` from `https://windows.php.net/downloads/pecl/releases/pcov/1.0.12/`. Filename ABI matches `PHP Extension Build => API20240924,TS,VS17` + `Architecture => x64`.
- Dropped `php_pcov.dll` into `C:\Program Files\php-8.4.15\ext\`. Edited the loaded `php.ini` (the "Loaded Configuration File" per `php --ini`): appended `[pcov] extension=pcov pcov.enabled=1`.
- Verified: `php -m` lists `pcov`; `php --ri pcov` reports `PCOV support => Enabled, version 1.0.12`.
- **Baseline coverage (Plan 02 Task 3 measures + improves this):**
  - Lines: **85.55%** (2114/2471) — already above the >70% target.
  - Methods: 67.75% (250/369).
  - Classes: 46.96% (54/115).
- Pitfall 3 sanity check: `vendor/bin/phpunit` = 12.9s, `vendor/bin/phpunit --coverage-text` = 16.4s (~27% slowdown when coverage is explicitly requested; normal runs unaffected). Within acceptable range.
- **PHASE SPLIT escalation NOT triggered** — pcov installed cleanly on the first try; no fallback to xdebug needed. D-22 / success #9 is fully unblocked (no escape hatch, no N/A branch).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 — Bug] Plan's seeder example had wrong FK + column names**
- **Found during:** Task 3 implementation
- **Issue:** Plan's PerfDemoSeeder example used `->for($members->random(), 'purchasedBy')` (camelCase) and `Expense::factory()->create(['kind' => ExpenseKind::BAZAR])`. Both were wrong:
  - `Expense` schema column is `purchased_by` (snake_case), not `purchasedBy`. The relationship method `purchasedByMember()` exists, but the seeder was setting the FK column directly via attributes — needed the actual column name.
  - `Expense` has NO `kind` column (it was dropped by `2026_06_17_110100_drop_expense_type_from_expenses`). `kind` lives on `expense_categories`. The seeder must filter categories by kind and use the `expense_category_id` FK on the expense.
- **Fix:** Used `'purchased_by' => $members->random()->id` (direct column write) and `ExpenseCategory::where('kind', ExpenseKind::BAZAR)->pluck('id')` to pick the right category IDs. Verified by reading the migration + the Expense model fillable.
- **Files modified:** `database/seeders/PerfDemoSeeder.php`
- **Commit:** `4a0d5f7`

**2. [Rule 1 — Bug] Plan's example used `assignRole('admin')` (string) but Tyro takes a Role object**
- **Found during:** Task 3 implementation (first seeder run failed)
- **Issue:** `App\Models\User::assignRole()` is typed `HasinHayder\Tyro\Models\Role $role` — passing a string throws TypeError.
- **Fix:** Used `Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator'])` and passed the Role object. Also had to seed the three Tyro roles explicitly (they don't exist after `migrate:fresh` — the role seeder isn't part of `DatabaseSeeder`).
- **Files modified:** `database/seeders/PerfDemoSeeder.php`
- **Commit:** `4a0d5f7`

**3. [Rule 2 — Critical functionality] T-05-01-04 mitigation needed to be enforced, not just configured**
- **Found during:** Task 2 acceptance review
- **Issue:** Plan's verify step (H) called for downloading `/mess/reports/monthly.pdf` with Debugbar enabled and confirming the body is clean — but offered no durable enforcement. A future change to `config/debugbar.php` could silently break the exclusion.
- **Fix:** Wrote `tests/Feature/Report/PdfDebugbarExclusionTest.php` — a PHPUnit regression test that dispatches an authenticated request to the PDF route with Debugbar explicitly enabled (`config(['debugbar.enabled' => true])`) and asserts the body is a valid PDF with no debugbar payload anywhere. This locks the exclusion as a hard gate.
- **Files modified:** `tests/Feature/Report/PdfDebugbarExclusionTest.php` (new)
- **Commit:** `9fbd05b`

**4. [Rule 3 — Blocking] storage/debugbar/ files appearing untracked after first run**
- **Found during:** Post-Task-2 test run
- **Issue:** Debugbar writes JSON snapshots to `storage/debugbar/` when enabled. Not in `.gitignore`. First test run that triggered Debugbar left untracked files.
- **Fix:** Added `/storage/debugbar` to `.gitignore` (alongside the existing `/storage/pail` pattern).
- **Files modified:** `.gitignore`
- **Commit:** `bf8a109`

### Other Notes

- **Plan said** `composer update laravel/pint` for the 1.29.1 → 1.29.3 bump; **executed** before this plan started (visible in `composer.lock` history). Pint audit ran clean (exit 0).
- **Plan referenced** the seeder using `app()->instance('currentMessId', $mess->id)` in the research Pattern 3 example; the orchestrator's actual implementation uses `Mess::forgetActiveIdCache()` instead — verified to work via the seed run producing all 50 members + meals under the Demo Mess.
- **Plan example** also wrote `config(['audit.enabled' => false])` — verified `config/audit.php` has `'enabled' => env('AUDIT_ENABLED', true)` so this runtime override works.

## Authentication Gates

None encountered.

## Known Stubs

None. All deliverables are fully wired.

## Threat Flags

No new threat surface introduced beyond what's documented in the plan's `<threat_model>`. All 11 threats (T-05-01-01 through T-05-01-11) mitigated as planned:

| Threat | Mitigation | Verified by |
|---|---|---|
| T-05-01-01 Telescope exposed in prod | Three-layer gate | `composer.json` require-dev + `config/telescope.php` enabled closure + `TelescopeServiceProvider::gate()` |
| T-05-01-02 Debugbar leaks in prod | Three-layer gate | Same + `.env.example DEBUGBAR_ENABLED=false` |
| T-05-01-03 telescope_entries unbounded | Daily prune + 24h retention | `routes/console.php` `Schedule::command('telescope:prune')->daily()` + `config('telescope.prune.hours') = 24` |
| T-05-01-04 Debugbar corrupts PDF | `except: ['*.pdf']` + regression test | `PdfDebugbarExclusionTest` passes with Debugbar explicitly enabled |
| T-05-01-05 Debugbar corrupts AJAX JSON | `ajax_handler_enable_tab=false` + `auto_show=false` | `config/debugbar.php` lines 216-217 |
| T-05-01-06 APP_TIMEZONE drift | Accepted (dev-only, fresh-start per D-02) | `migrate:fresh --seed` re-seeded; pilot starts fresh |
| T-05-01-07 .env accidentally committed | `.gitignore` covers `.env` | `git check-ignore -v .env` matches `.gitignore:3:.env` |
| T-05-01-08 sqlite→MySQL parity regression | phpunit was already MySQL; only dev .env changed | 234 tests pass |
| T-05-01-09 PerfDemoSeeder demo creds leak | DatabaseSeeder doesn't call it; SeedPerfDemo isProduction guard | Code review |
| T-05-01-10 xdebug 5× slowdown | pcov chosen instead (no slowdown) | Pitfall 3 sanity check: 12.9s → 16.4s (~27% with --coverage; normal runs unaffected) |
| T-05-01-11 Wrong php.ini edited | Used `php --ini` "Loaded Configuration File"; re-ran `php -m` to prove CLI picked it up | `php -m` lists pcov; `php --ri pcov` reports enabled |

## Self-Check: PASSED

### Created files exist
- `.planning/phases/05-polish-pilot/05-MECHANICAL-AUDIT.md` ✓
- `config/telescope.php` ✓
- `config/debugbar.php` ✓
- `app/Providers/TelescopeServiceProvider.php` ✓
- `database/migrations/2026_06_18_225802_create_telescope_entries_table.php` ✓
- `database/seeders/PerfDemoSeeder.php` ✓
- `app/Console/Commands/SeedPerfDemo.php` ✓
- `tests/Feature/Report/PdfDebugbarExclusionTest.php` ✓

### Commits exist
- `8d9b563` (Task 1 — mechanical audits + .env parity) ✓
- `aaab1e4` (Task 2 — Debugbar + Telescope require-dev) ✓
- `9fbd05b` (T-05-01-04 regression test) ✓
- `4a0d5f7` (Task 3 — PerfDemoSeeder + SeedPerfDemo) ✓
- `bf8a109` (.gitignore storage/debugbar) ✓
- `eb981e0` (Task 4 — pcov install audit doc) ✓

### Acceptance criteria verified
- All 4 tasks executed per the plan ✓
- Each task committed individually ✓
- SUMMARY.md created at the specified path ✓
- Plan 02 (perf + coverage) and Plan 03 (README demo creds) fully unblocked ✓
- **No PHASE SPLIT signal** — pcov installed cleanly, coverage measurement works, D-22 / success #9 has no escape hatch.
