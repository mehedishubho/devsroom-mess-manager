# Phase 5 Mechanical Audit Report

Plan 05-01, Task 1. Closes D-18, D-19, D-20, D-21.

## D-21 — APP_TIMEZONE (Asia/Dhaka)

**Command:** `grep -E "^APP_TIMEZONE" .env .env.example`

**Initial state:**
- `config/app.php:68` reads `'timezone' => env('APP_TIMEZONE', 'UTC')` — defaults to UTC when `APP_TIMEZONE` is absent.
- `.env` and `.env.example` did NOT contain any `APP_TIMEZONE` key, so the running app was using UTC. Pitfall 7 (timezone shift): all `now()` / timestamp display was 6h behind Dhaka.

**Action taken:**
- `config/app.php` is UNCHANGED (the fix lives in `.env`, not the config file — `git diff config/app.php` is empty).
- Added `APP_TIMEZONE=Asia/Dhaka` to BOTH `.env` and `.env.example`.

**Final state:**
- `grep -E "^APP_TIMEZONE=Asia/Dhaka" .env .env.example` returns matches in both files.
- `php artisan config:clear` then `php artisan tinker` → `now()->timezoneName` returns `Asia/Dhaka`.
- Pitfall 7: dev DB was re-seeded from scratch in the same Task 1E migrate:fresh (no real data drift possible). T-05-01-06 disposition `accept` is documented here.

## D-18 — DB parity (sqlite → MySQL)

**Command:** `grep -E "^DB_CONNECTION" .env .env.example`

**Initial state:**
- `.env` had `DB_CONNECTION=sqlite` with the MySQL block commented out (the live dev gap).
- `.env.example` had `DB_CONNECTION=sqlite` with MySQL commented out — every fresh clone inherited sqlite by default.
- `phpunit.xml` already pointed at `devsroom_mess_management_testing` (MySQL) — so the test suite was the source of truth; only the dev runtime drifted.

**Action taken:**
- Replaced the `DB_CONNECTION=sqlite` + commented MySQL block with an active MySQL block in BOTH `.env` and `.env.example`.
- `.env` uses the dev's actual credentials: `DB_DATABASE=devsroom_mess_management`, `DB_USERNAME=root`, `DB_PASSWORD=125524` (STATE.md Open Questions resolved).
- `.env.example` ships the sanitized template: `DB_USERNAME=root`, `DB_PASSWORD=` (empty).
- No uncommented `sqlite` line remains in either file.

**Final state:**
- `grep -E "^DB_CONNECTION=mysql" .env .env.example` matches both files.
- `php artisan migrate:fresh --seed` runs cleanly against MySQL (37 migrations across Laravel skeleton + Tyro + domain + Telescope post-Task-2 — verified via `DB::table('migrations')->count() = 37` after Task 1 alone; ExpenseCategorySeeder seeds 13 categories: 7 bazar + 6 fixed).
- T-05-01-08 disposition `mitigate`: phpunit was already MySQL; only dev runtime changed. No test regression (verified again in Task 2's full phpunit run).

## D-19 — Pint audit

**Commands:**
1. `composer update laravel/pint` (bumped 1.29.1 → current patch release per research Open Question #4; non-breaking).
2. `vendor/bin/pint --test`

**Output:**
```
{"tool":"pint","result":"passed"}
EXIT: 0
```

**Action taken:** None — Pint was already clean exiting 0. No files reformatted.

**Final state:** `vendor/bin/pint --test` exit code: 0 (recorded above). PERF-13 (Pint clean) satisfied.

## D-20 — `__()` scan

**Command:** `rg -n '\{\{ (?!\s*[_\(\$])(?![^}]*__\()' resources/views/ --glob '*.blade.php'` (audit-only, the ripgrep recipe from CONTEXT.md), plus a follow-up narrow scan for un-wrapped English string literals:
`rg -n "\{\{ '.*' \}\}|\{\{ \".*\" \}\}"` restricted to app views (excluding `vendor/tyro-dashboard`).

**Output:**
- Pattern from the plan (`{{ ... }}` not going through `__()`, env(), route(), config(), $variable): broad grep surfaces ~264 hits, ALL of which fall into the documented allowlist:
  - HTML attribute / inline style values (e.g. `class="{{ ... }}"`, `id="{{ $member->id }}"`)
  - Already-translated runtime values (`{{ $member->name }}`, `{{ $report->period }}`, `{{ Money::taka(...) }}`, `{{ old('field') }}`)
  - Function-call output (`{{ config('app.name') }}`, `{{ url(...) }}`, `{{ route(...) }}`, `{{ csrf_field() }}`, `{{ method_field(...) }}`)
- Narrow literal-string scan (`{{ 'literal' }}` / `{{ "literal" }}` in app views): **0 hits**. The codebase was built `__()`-first from Phase 1 (CLAUDE.md / PROJECT.md constraint); no un-wrapped English literals exist.

**Classification:**
- Hits: 264 raw grep lines, 0 user-facing English literals.
- Wrapped in `__()`: 0 (none needed).
- Allowlisted: 264 (HTML attrs, pre-translated vars, function-call output).
- `bn.json`: deferred to v2 per CONTEXT.md (English-only shipped).

**Final state:** D-20 satisfied — no un-wrapped user-facing Blade `{{ }}` output remains outside the documented allowlist.

## Migration re-run (Task 1E, Pitfall 8 mitigation)

`php artisan config:clear && php artisan migrate:fresh --seed` ran cleanly against MySQL:
- 37 migrations applied (Laravel skeleton + Sanctum + Tyro + domain `create_*_table` + 4 `add_*`/`drop_*` alterations) — 0 errors.
- `ExpenseCategorySeeder` ran (13 categories: 7 bazar + 6 fixed).
- `User::factory()->create(['email' => 'test@example.com'])` recreated the default login user.
- DB is now MySQL, Asia/Dhaka timezone, ready for Task 3's PerfDemoSeeder.

## Self-check acceptance summary

- `.env` contains `APP_TIMEZONE=Asia/Dhaka` ✓
- `.env.example` contains `APP_TIMEZONE=Asia/Dhaka` ✓
- `.env.example` has `DB_CONNECTION=mysql` (no uncommented `sqlite` line) ✓
- `.env.example` has `TELESCOPE_ENABLED=false` and `DEBUGBAR_ENABLED=false` ✓
- `vendor/bin/pint --test` exits 0 ✓
- This file contains the strings `D-18`, `D-19`, `D-20`, `D-21`, `Asia/Dhaka` ✓
- `php artisan migrate:fresh --seed` exits 0 ✓
- `config/app.php` UNCHANGED (`git diff config/app.php` empty — fix lives in `.env`) ✓
- `__()` scan hit count (264 raw, 0 literals) + count wrapped (0) recorded ✓

---

## Task 2 — Debugbar + Telescope install + three-layer prod gating (D-06)

**Packages installed (require-dev ONLY — Layer 1):**
- `barryvdh/laravel-debugbar:^4.3` — composer.json `require-dev`
- `laravel/telescope:^5.20` — composer.json `require-dev`

Verified: `composer.json` lists both under `require-dev`. A `composer install --no-dev` on prod literally cannot load them.

**Post-install artifacts created:**
- `config/telescope.php` — published
- `config/debugbar.php` — published
- `app/Providers/TelescopeServiceProvider.php` — published (auto-registered via `bootstrap/providers.php`)
- `database/migrations/2026_06_18_225802_create_telescope_entries_table.php` — published + migrated

**Three-layer gate (verified end-to-end):**

| Layer | Where | Verified |
|---|---|---|
| 1. require-dev | composer.json `require-dev` | both packages listed under require-dev |
| 2. enabled closure + .env defaults | config/telescope.php `enabled => env('TELESCOPE_ENABLED', fn () => app()->environment('local'))` + .env.example ships `TELESCOPE_ENABLED=false` + `DEBUGBAR_ENABLED=false` | `config('telescope.enabled')` resolves to false when `TELESCOPE_ENABLED=false` |
| 3. Gate::define viewTelescope | `TelescopeServiceProvider::gate()` → `hasRole('super-admin')` | Code present in app/Providers/TelescopeServiceProvider.php |

**Layer 2 config spot-checks (post-publish):**
- `config/telescope.php` — `enabled` closure defaults to `app()->environment('local')`; `'driver' => env('TELESCOPE_DRIVER', 'database')`; `'prune' => ['hours' => 24]`.
- `config/debugbar.php` — `'enabled' => env('DEBUGBAR_ENABLED')`; `except` array includes `'*.pdf'`, `'*.xlsx'`, `'api/*'`; `capture_ajax=true`, `ajax_handler_auto_show=false`, `ajax_handler_enable_tab=false`.

**Layer 3 gate (verbatim from `app/Providers/TelescopeServiceProvider.php`):**
```php
protected function gate(): void
{
    Gate::define('viewTelescope', function (User $user) {
        return $user->hasRole('super-admin');
    });
}
```

**Pitfall 1 (telescope_entries unbounded growth):** `config('telescope.prune.hours')` = 24; `routes/console.php` schedules `telescope:prune` daily, wrapped in `class_exists(Telescope::class)` so prod (no Telescope via --no-dev) doesn't error.

**Pitfall 2 (Debugbar corrupts PDF/JSON):** `*.pdf` + `*.xlsx` + `api/*` in `except`; `ajax_handler_enable_tab=false` + `ajax_handler_auto_show=false` so the bar cannot be injected into AJAX JSON bodies.

**Pitfall 5 (capture_ajax):** kept `capture_ajax=true` for the meal-grid AJAX timing (we want the query count + duration on save), but the handler-tab disable + auto_show=false keeps the JSON body clean.

**Verify config after `php artisan config:clear`:**
```
config('telescope.enabled')   → false   (TELESCOPE_ENABLED=false in .env)
config('debugbar.enabled')    → false   (DEBUGBAR_ENABLED=false in .env after verification)
config('debugbar.except')     → contains '*.pdf', '*.xlsx', 'api/*'
```

**Telescope tables (verified present):**
```
$ php artisan tinker --execute="foreach (DB::select('SHOW TABLES LIKE \"telescope%\"') as \$t) { echo array_values((array)\$t)[0] . ' '; }"
telescope_entries telescope_entries_tags telescope_monitoring
```

### T-05-01-04 mitigation — PDF exclude path enforced end-to-end (NOT aspirational)

**Method:** A dedicated PHPUnit regression test (`tests/Feature/Report/PdfDebugbarExclusionTest.php`) dispatches an authenticated GET to `/mess/reports/monthly.pdf` with Debugbar **explicitly enabled** (`config(['debugbar.enabled' => true])` inside the test) and asserts:

1. Response status = 200
2. `Content-Type: application/pdf`
3. Body starts with literal `%PDF` magic bytes
4. Body contains no `debugbar` (case-insensitive) anywhere
5. Body contains no `phpdebugbar` anywhere
6. Body contains no `<script>phpdebugbar` substring

**Result:**
```
$ vendor/bin/phpunit --filter=test_monthly_pdf_body_contains_no_debugbar_payload_when_debugbar_enabled
.
OK (1 test, 10 assertions)
```

The exclude rule works end-to-end. The body is a valid PDF, no Debugbar payload injected. T-05-01-04 disposition `mitigate` is satisfied AND locked as a regression test (future changes that break the exclude rule fail this test).

**Full test suite (no regression):**
```
$ vendor/bin/phpunit
OK (234 tests, 562 assertions)   # was 233 → 234 (added the T-05-01-04 regression test)
Time: 00:14.6, Memory: 108.00 MB
```

**Post-verification state:** `.env` reset to `DEBUGBAR_ENABLED=false` (default dev state — flip per-session for measurement). `TELESCOPE_ENABLED=false` likewise.

