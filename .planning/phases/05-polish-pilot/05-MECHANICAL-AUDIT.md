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
