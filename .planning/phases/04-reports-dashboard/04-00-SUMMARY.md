---
phase: 04-reports-dashboard
plan: 00
subsystem: foundation (packages + sidebar + chart bootstrap)
tags: [infrastructure, sidebar, chart.js, dompdf, excel, prerequisites]
requires:
  - composer.json
  - package.json
  - resources/js/app.js
  - resources/views/layouts/app.blade.php
  - app/Support/Money.php
provides:
  - "barryvdh/laravel-dompdf v3.1.2 (PDF export facade Pdf)"
  - "maatwebsite/excel 3.1.69 (Excel export facade Excel)"
  - "chart.js 4.5.1 bundled via Vite"
  - "window.initDashboardChart(canvasId, config) global helper with destroy-before-recreate guard"
  - "Reports sidebar group (D-31) with 4 sub-entries, Route::has-guarded"
  - "tests/Feature/Report/ + tests/Feature/Dashboard/ directories"
affects:
  - "Plans 04-01, 04-02, 04-03 (all import the installed packages, sidebar group, and test directories)"
tech-stack:
  added:
    - "barryvdh/laravel-dompdf ^3.1 (v3.1.2) — PDF export"
    - "maatwebsite/excel ^3.1 (3.1.69) — .xlsx export (bundles phpoffice/phpspreadsheet ^1.30.4)"
    - "chart.js ^4.5.1 (npm) — dashboard line + bar charts"
  patterns:
    - "Vite-bundled Chart.js auto-registration via chart.js/auto"
    - "Global window.initDashboardChart helper (canvas destroy-before-recreate to prevent memory leaks)"
    - "Route::has guard on sidebar entries that reference not-yet-defined routes"
key-files:
  created:
    - "tests/Feature/Report/.gitkeep"
    - "tests/Feature/Dashboard/.gitkeep"
  modified:
    - "composer.json"
    - "composer.lock"
    - "package.json"
    - "package-lock.json"
    - "resources/js/app.js"
    - "resources/views/layouts/app.blade.php"
decisions:
  - "D-33 money helper resolution: adopt App\\Support\\Money::taka() as the canonical helper. No bdt() global function, no app/helpers.php, no composer.json autoload.files entry. Used in 14 blade views already; every Phase 4 money display will use Money::taka()."
  - "Chart.js wired into the existing global resources/js/app.js (not a per-page chunk) per research assumption A6. Bundle is ~88KB gzipped including existing app code; refactor to a per-page chunk deferred to Phase 5 perf audit."
  - "Reports sidebar group guarded with @if (Route::has('mess.reports.monthly')) so the test suite stays green until Plan 04-01 registers the routes."
metrics:
  duration: "~9 minutes"
  completed: "2026-06-17"
  tasks: 2
  files-touched: 8
---

# Phase 04 Plan 00: Wave 0 Prerequisites Summary

Wave 0 foundation for Phase 4 (Reports + Dashboard): installs the three verified packages (Dompdf, Maatwebsite/Excel, Chart.js), exposes a global Chart.js init helper with the destroy-before-recreate memory-leak guard, locks the canonical money helper decision (`App\Support\Money::taka()` — no `bdt()`), adds the Reports sidebar group (D-31), and scaffolds the Report + Dashboard test directories Plans 04-01/04-02/04-03 drop feature tests into.

## Tasks Completed

| Task | Name | Commit | Key Files |
|------|------|--------|-----------|
| 1 | Install packages + verify ext-zip + confirm money helper + expose Chart.js bootstrap | `098ab7e` | composer.json, composer.lock, package.json, package-lock.json, resources/js/app.js |
| 2 | Add Reports sidebar group + create Report/Dashboard test directories | `94ac982` | resources/views/layouts/app.blade.php, tests/Feature/Report/.gitkeep, tests/Feature/Dashboard/.gitkeep |

## Package Verification (research-confirmed versions, this session)

| Package | Required | Installed | Source |
|---------|----------|-----------|--------|
| `barryvdh/laravel-dompdf` | `^3.1` | **v3.1.2** | composer require (auto-discovered, no provider registration needed) |
| `maatwebsite/excel` | `^3.1` | **3.1.69** | composer require (auto-discovered) |
| `chart.js` (npm) | `^4.5.1` | **4.5.1** | npm install (bundles via `chart.js/auto` in app.js) |
| `ext-zip` (PHP) | loaded | **loaded** | `php -m \| grep zip` → `zip` |

Vite build (`npm run build`) succeeds: 59 modules transformed, 252.98 KB JS bundle (88.22 KB gzipped), built in 731 ms.

## Money Helper Decision Locked (Gap 1 from research)

- `App\Support\Money::taka($value)` is the canonical money helper for Phase 4 (returns `"৳1,234.00"`).
- Used in **14 blade views** (verified via `grep -rln "Money::taka"`).
- `grep -rc "function bdt" app/` → 0 matches across every file. No `bdt()` exists or is created.
- `composer.json` has **no** `"files": ["app/helpers.php"]` autoload entry (verified via `grep -c "app/helpers.php" composer.json` → 0).
- Every Phase 4 report/dashboard view that displays money MUST use `Money::taka()`. Do NOT introduce `bdt()`.

## Chart.js Bootstrap (resources/js/app.js)

The global `window.initDashboardChart(canvasId, config)` helper:
- Imports `chart.js/auto` (auto-registers all controllers — line + bar + others).
- Destroys any existing chart instance on the canvas before creating a new one (`if (el.__chart) el.__chart.destroy();`) — prevents the memory leak + ghost-chart pitfall (RESEARCH Pitfall 2).
- Hard-codes mobile-legible defaults: `responsive: true`, `maintainAspectRatio: false`, `x.ticks.maxRotation: 45`, font sizes 10–11.
- Returns the chart instance so callers can introspect it.
- Plan 04-03 will call this from inline `<script>` blocks on `/home` with `@json`-injected datasets.

## Reports Sidebar Group (D-31)

Added between the existing "Bill preview" and "Close month" entries. The group contains:
1. **Reports** group header (uppercase, emerald when active, slate when not)
2. Monthly Report — `route('mess.reports.monthly')`
3. Member Statement — `route('mess.reports.member-statement')`
4. Expense Report — `route('mess.reports.expenses')`
5. Payment Report — `route('mess.reports.payments')`

All 4 sub-links use the existing sidebar pattern verbatim (Tailwind classes, `min-h-[44px]` touch targets, `routeIs(...)` active highlighting with emerald accent). See deviation below for the `Route::has` guard.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Guarded Reports sidebar with `Route::has` to prevent breaking the test suite**

- **Found during:** Task 2 verification (ran `php artisan test` to confirm no regression)
- **Issue:** Adding `route('mess.reports.monthly')` etc. as literal `href`s in the sidebar caused **32 existing tests to fail** with `RouteNotFoundException [mess.reports.monthly] not defined` on every page that renders the layout (`/home`, `/onboarding/create`, etc.). The plan's threat model T-04-00-02 explicitly accepted this transient state for *users* (both 04-00 and 04-01 land before any user hits `/home`), but the plan's `<verification>` block also requires "full suite still passes (154 tests; no regression)". These two requirements are mutually exclusive without a guard.
- **Fix:** Wrapped the entire Reports group (header + 4 links) in `@if (Route::has('mess.reports.monthly'))`. Until Plan 04-01 registers the routes, the sidebar renders nothing where the group would be. Once Plan 04-01 lands, the guard passes through and the full group appears automatically.
- **Files modified:** `resources/views/layouts/app.blade.php`
- **Commit:** `ec63e6b`
- **Verification:** `php artisan test` → **162 passed** (0 failures). `vendor/bin/pint --test app/ tests/` clean. All 4 route literals + Reports header still present in the file (count = 1 each).

## Self-Check

All claims verified by direct command:

- [x] `composer.json` contains `"barryvdh/laravel-dompdf": "^3.1"` and `"maatwebsite/excel": "^3.1`
- [x] `package.json` contains `"chart.js": "^4.5.1"` (starts with `^4.`)
- [x] `resources/js/app.js` contains `import Chart from 'chart.js/auto';`, `window.initDashboardChart = function`, `if (el.__chart) {`, `el.__chart.destroy();`
- [x] `php -m` contains `zip`
- [x] `grep -rc "function bdt" app/` returns 0 for every file
- [x] `composer.json` does NOT contain `app/helpers.php`
- [x] `npm run build` exits 0 (252.98 KB JS bundle)
- [x] `resources/views/layouts/app.blade.php` contains all 4 route literals + Reports header
- [x] `tests/Feature/Report/.gitkeep` exists
- [x] `tests/Feature/Dashboard/.gitkeep` exists
- [x] `php artisan test` → 162 passed (no regression)
- [x] `vendor/bin/pint --test app/ tests/` → passed
- [x] Commits `098ab7e`, `94ac982`, `ec63e6b` exist in `git log`

## Self-Check: PASSED
