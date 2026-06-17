---
phase: 04-reports-dashboard
plan: 03
subsystem: manager dashboard + PDF/Excel exports (Wave 4 — final wave)
tags: [dashboard, charts, chart.js, dompdf, excel, exports, pdf, cache, money-taka]
requires:
  - app/Services/BillPreviewService.php
  - app/Services/BillPreviewInvalidator.php
  - app/Services/ReportService.php
  - app/Services/MemberStatementService.php
  - app/Providers/AppServiceProvider.php
  - app/Http/Controllers/HomeController.php
  - app/Http/Controllers/Mess/ReportController.php
  - app/Http/Controllers/My/MyReportController.php
  - app/Support/Money.php
  - app/Support/MealType.php
  - app/Support/ExpenseKind.php
  - app/Support/MealOffStatus.php
  - app/Models/Mess.php
  - app/Models/MealEntry.php
  - app/Models/Expense.php
  - app/Models/Payment.php
  - app/Models/MealOffRequest.php
  - resources/views/components/stat-card.blade.php
  - resources/views/components/report-toolbar.blade.php
  - resources/js/app.js (window.initDashboardChart)
  - routes/web.php
provides:
  - "App\\Services\\DashboardService — managerCards() (6 DASH-01 cards, bill-derived reuse bill-preview cache + count cards use dash:counts:{mess_id}:{YYYY}-{MM}), pendingMealOffCount(), mealTrend/expenseTrend/paymentTrend (D-08 auto-bucketing)"
  - "App\\Services\\ChartBucketingService — bucket(from,to) returns granularity day/week/month (≤60d→day, ≤365d→week, else→month)"
  - "App\\Http\\Requests\\Dashboard\\ChartRangeRequest — validates 6 from/to + 3 preset chart-range query params"
  - "App\\Http\\Controllers\\HomeController — index() renders home.blade.php with cards + 3 Chart.js series + alert banner"
  - "4 Excel exports under app/Exports/: MonthlyReportExport (FromArray), MemberStatementExport (FromCollection), ExpenseReportExport + PaymentReportExport (FromQuery, chunked — T-04-03-03). All ShouldAutoSize + WithColumnFormatting + FORMAT_NUMBER_00 on Amount columns (Pitfall 5)."
  - "App\\Http\\Controllers\\Mess\\ReportExportController — 8 manager export methods (4 reports × PDF + Excel) with safeFilename() path-traversal / header-injection prevention"
  - "App\\Http\\Controllers\\My\\MyReportExportController — 4 member export methods; member ALWAYS from $request->user()->getMemberOrNull() (no {member} URL param); monthlyExcel empties members[] for structural D-19 enforcement"
  - "resources/views/layouts/pdf.blade.php — PLAIN CSS layout (Pitfall 4 — no Tailwind, no Vite) with @page margins + counter(page) footer ('Page N' not 'N of M', D-13)"
  - "6 PDF views under mess/reports/pdf/ + my/reports/pdf/ — branded, all @extends('layouts.pdf'); member Monthly PDF OMITS per-member table (D-19)"
  - "<x-report-toolbar> wired with real route({$route}.pdf/.xlsx) hrefs (Route::has-guarded)"
  - "12 export routes added to routes/web.php (8 manager + 4 member)"
  - "28 new tests (13 dashboard + 15 export)"
affects:
  - "Phase 4 is feature-complete after this plan. Orchestrator owns phase-completion (VERIFICATION + marking phase done). Phase 5 (Polish + Pilot) is next."
tech-stack:
  added: []
  patterns:
    - "Manager dashboard cache strategy: 4 bill-derived cards reuse bill-preview:{mess_id}:{YYYY}-{MM} (no new key — BillPreviewService::preview() Cache::remembers it); 3 count cards (Total Members, Today's Meals, Monthly Expenses) use ONE composite key dash:counts:{mess_id}:{YYYY}-{MM} (1h TTL)."
    - "Cache invalidation extends the EXISTING AppServiceProvider::invalidateForModel() listener body with ONE Cache::forget('dash:counts:...') call — no duplicate Event::listen (preserves < 2s refresh, DASH-05 / success #12). Key is scoped by mess_id (T-04-03-01)."
    - "D-08 auto-bucketing centralized in ChartBucketingService: range ≤60d → daily; ≤365d → weekly; else → monthly. Applied to all 3 trend queries via the fillBucketAxis() helper."
    - "Dompdf plain-CSS layout (Pitfall 4 mitigation): PDF views MUST NOT use Tailwind utilities — separate layouts/pdf.blade.php with inline <style>, position:fixed header/footer, @page margins, counter(page) (NOT counter(pages), which does not work — D-13)."
    - "Excel Pitfall 5 mitigation: every Amount column uses (float) cast in map()/array() + WithColumnFormatting + NumberFormat::FORMAT_NUMBER_00 so the manager can SUM/AVERAGE."
    - "Filename sanitization via safeFilename() (T-04-03-04): regex '~\\.\.+|[\\/]+~u' + '~[^\\p{L}\\p{N}\\-_]+~u' (using '~' delimiter to avoid the '/' literal conflict)."
    - "Member exports are IDOR-structurally-impossible: MyReportExportController has NO {member} URL param; member ALWAYS from $request->user()->getMemberOrNull(). monthlyExcel passes members=[] to MonthlyReportExport — peer rows can never leave the server for a member request (D-19 in data shape, not just view)."
    - "PDF SSRF prevention: every Pdf::loadView() chain sets setOption('isRemoteEnabled', false) — no external images/fonts loaded (T-04-03-10)."
    - "Money via App\\Support\\Money::taka() everywhere — no bdt() anywhere (Gap 1 resolution from 04-00 holds)."
key-files:
  created:
    - "app/Services/DashboardService.php"
    - "app/Services/ChartBucketingService.php"
    - "app/Http/Requests/Dashboard/ChartRangeRequest.php"
    - "app/Exports/MonthlyReportExport.php"
    - "app/Exports/MemberStatementExport.php"
    - "app/Exports/ExpenseReportExport.php"
    - "app/Exports/PaymentReportExport.php"
    - "app/Http/Controllers/Mess/ReportExportController.php"
    - "app/Http/Controllers/My/MyReportExportController.php"
    - "resources/views/layouts/pdf.blade.php"
    - "resources/views/mess/reports/pdf/monthly.blade.php"
    - "resources/views/mess/reports/pdf/member-statement.blade.php"
    - "resources/views/mess/reports/pdf/expenses.blade.php"
    - "resources/views/mess/reports/pdf/payments.blade.php"
    - "resources/views/my/reports/pdf/statement.blade.php"
    - "resources/views/my/reports/pdf/monthly.blade.php"
    - "tests/Feature/Dashboard/ManagerDashboardTest.php"
    - "tests/Feature/Dashboard/ChartRangeTest.php"
    - "tests/Feature/Dashboard/CacheInvalidationTest.php"
    - "tests/Feature/Report/PdfExportTest.php"
    - "tests/Feature/Report/ExcelExportTest.php"
  modified:
    - "app/Http/Controllers/HomeController.php"
    - "app/Providers/AppServiceProvider.php"
    - "resources/views/home.blade.php"
    - "resources/views/components/report-toolbar.blade.php"
    - "resources/views/mess/reports/monthly.blade.php"
    - "resources/views/mess/reports/member-statement.blade.php"
    - "resources/views/my/reports/statement.blade.php"
    - "resources/views/my/reports/monthly.blade.php"
    - "routes/web.php"
decisions:
  - "Dashboard cache strategy (D-17 concrete): 4 bill-derived cards (meal_rate, total_due, total_advance) reuse the EXISTING bill-preview:{mess_id}:{YYYY}-{MM} key via BillPreviewService::preview() — no new key. 3 count cards (total_members, today_meals, monthly_expenses) use ONE composite key dash:counts:{mess_id}:{YYYY}-{MM} with 1h TTL. Invalidated by the SAME AppServiceProvider listener that forgets bill-preview (extended in-place, no duplicate Event::listen — preserves < 2s refresh, success #12)."
  - "D-08 bucket threshold: range ≤60 days → daily buckets; ≤365 days → weekly; else → monthly. Pattern 7 verbatim. Tested by ChartRangeTest (30d→day, 400d→month)."
  - "PDF layout = plain CSS, not Tailwind (Pitfall 4). Page numbers via CSS counter(page) only — counter(pages) does NOT work in Dompdf (Pitfall, D-13). Footer = 'Page N', not 'Page N of M'."
  - "Excel Amount columns = raw floats with NumberFormat::FORMAT_NUMBER_00 so the manager can SUM/AVERAGE (Pitfall 5). Every map()/array() returns explicit (float) casts."
  - "Member monthly export D-19 enforced structurally in DATA, not just view: MyReportExportController::monthlyExcel passes ['members' => []] to MonthlyReportExport — peer rows can never leave the server. The PDF view also omits the per-member table."
  - "Filename sanitization: safeFilename() uses regex with '~' delimiter (NOT '/', which conflicts with the forward-slash literal). Strips /, \\, .., control chars (T-04-03-04)."
metrics:
  duration: "~25 minutes"
  completed: "2026-06-18"
  tasks: 3
  files-touched: 29
---

# Phase 04 Plan 03: Manager Dashboard + PDF/Excel Exports Summary

Wave 4 of Phase 4 (Reports + Dashboard). Transforms manager `/home` into the real dashboard (D-14: 6 stat cards + 3 Chart.js charts + pending-meal-off alert banner) and wires PDF (Dompdf) + Excel (Maatwebsite/Excel) exports on all 4 reports for both manager and member sides. The cache-invalidation hook is extended in-place (one `Cache::forget('dash:counts:...')` line in the existing listener body) — preserving the `< 2s` refresh contract (DASH-05 / success #12). Completes Phase 4 — the read surface is fully built.

## Tasks Completed

| Task | Name | Commit | Key Files |
|------|------|--------|-----------|
| 1 | RED: ManagerDashboardTest + ChartRangeTest + CacheInvalidationTest (13 failing) | `1d87e00` | tests/Feature/Dashboard/*.php |
| 1 | GREEN: DashboardService + ChartBucketingService + HomeController + cache hook + home.blade.php | `e10d70a` | app/Services/{Dashboard,ChartBucketing}Service.php, app/Http/Controllers/HomeController.php, app/Http/Requests/Dashboard/ChartRangeRequest.php, app/Providers/AppServiceProvider.php, resources/views/home.blade.php, tests/Feature/Dashboard/*.php |
| 2 | 4 Excel exports + layouts/pdf.blade.php + 6 PDF views + report-toolbar wiring | `b04afe6` | app/Exports/*.php, resources/views/layouts/pdf.blade.php, resources/views/{mess,my}/reports/pdf/*.blade.php, resources/views/components/report-toolbar.blade.php, 4 report view updates |
| 3 | RED: PdfExportTest + ExcelExportTest (15 failing) | `281b2d3` | tests/Feature/Report/{PdfExportTest,ExcelExportTest}.php |
| 3 | GREEN: ReportExportController + MyReportExportController + 12 routes + safeFilename | `7779943` | app/Http/Controllers/Mess/ReportExportController.php, app/Http/Controllers/My/MyReportExportController.php, routes/web.php, tests/Feature/Report/*.php |

## What Was Built

### 1. DashboardService (`app/Services/DashboardService.php`)

Constructor-injects `BillPreviewService` (for bill-derived cards) and `ChartBucketingService`. Methods:

- **`pendingMealOffCount(): int`** — `MealOffRequest::where('mess_id', Mess::activeId())->where('status', MealOffStatus::PENDING)->count()`. Not cached (COUNT is cheap; the banner is the one element the manager most wants to be live).
- **`managerCards(): array`** — 6 DASH-01 cards. 3 count-based cards (`total_members`, `today_meals`, `monthly_expenses`) come from a single composite cache key `dash:counts:{mess_id}:{YYYY}-{MM}` (1h TTL). 3 bill-derived cards (`meal_rate`, `total_due`, `total_advance`) reuse the existing `bill-preview:{mess_id}:{YYYY}-{MM}` cache via `BillPreviewService::preview()`. `todayMealTotal(messId, date)` uses `MealType::value()` for the configured per-type values (Pitfall A3 — never hard-coded 0.5/1/1) and EXCLUDES guest meals (Open Question #3 LOCKED).
- **`mealTrend/expenseTrend/paymentTrend(messId, from, to): array`** — D-05/D-06/D-07 series with D-08 auto-bucketing (≤60d daily, ≤365d weekly, else monthly). Meal Trend = regular B/L/D meal values (EXCLUDES guest meals, Q3 LOCKED). Expense Trend = bazar-only (D-06, `ExpenseCategory::where('kind', ExpenseKind::BAZAR)`). Payment Trend = all methods + both types (D-07).

### 2. ChartBucketingService (`app/Services/ChartBucketingService.php`)

RESEARCH Pattern 7 verbatim. `bucket(Carbon $from, Carbon $to): array` returns `['granularity' => 'day'|'week'|'month', 'step' => '1 day'|'1 week'|'1 month']`. Threshold: ≤60 days → daily; ≤365 days → weekly; else → monthly.

### 3. HomeController (`app/Http/Controllers/HomeController.php`) — replaced

`index(ChartRangeRequest $request): View` resolves 3 chart ranges from GET query with D-02 defaults (Meal 30 days back, Expense + Payment 6 months back). Renders `home` view with `cards`, `pendingMealOff`, and a `charts` array containing each chart's `type`, `labels`, `values`, and current `range` (for sticky form re-fill).

### 4. AppServiceProvider cache hook (`app/Providers/AppServiceProvider.php`) — extended

The EXISTING `invalidateForModel(BillPreviewInvalidator, Model)` listener body now appends one `Cache::forget("dash:counts:{mess_id}:{YYYY}-{MM}")` call after the existing `$invalidator->forDate($dateStr)`. Same listener fires for both `saved` and `deleted` events on all 5 models (MealEntry, GuestMeal, MealOffRequest, Expense, Payment). Key is scoped by `Mess::activeId()` → cross-mess bleed is impossible (T-04-03-01).

### 5. home.blade.php (`resources/views/home.blade.php`) — replaced

- DASH-01: 6 `<x-stat-card>` cards in a `grid grid-cols-2 lg:grid-cols-3 gap-3`. Zero-meal-rate hint (D-29) shown when `$cards['meal_rate'] === 0.0`.
- DASH-03: Pending meal-off alert banner (amber) with `trans_choice` count + link to `route('mess.meal-off.index')`. Rendered only when `$pendingMealOff > 0`.
- DASH-02: 3 Chart.js canvases in stacked cards. Each chart has its own GET range-picker form (D-03) that submits hidden inputs for the OTHER two charts' ranges so changing one doesn't reset the others. Parent `<div style="height: 280px;">` is MANDATORY for `maintainAspectRatio: false` (Pitfall 2).
- D-27: empty-state when all 3 charts have no data.
- Chart init script (under `@once`) calls `window.initDashboardChart(canvasId, config)` from Plan 4.0 with `@json`-injected labels/values. The helper's destroy-before-recreate guard handles range changes.

### 6. 4 Excel Exports (`app/Exports/*.php`)

| Class | Source | Concerns | Amount column format |
|-------|--------|----------|----------------------|
| `MonthlyReportExport` | FromArray (ReportService::monthlyReport()) | WithHeadings + WithColumnFormatting + ShouldAutoSize | B-G = FORMAT_NUMBER_00 |
| `MemberStatementExport` | FromCollection (MemberStatementService::forMember()) | WithMapping + WithHeadings + WithColumnFormatting + ShouldAutoSize | D = FORMAT_NUMBER_00 |
| `ExpenseReportExport` | FromQuery (chunks — T-04-03-03) | WithMapping + WithHeadings + WithColumnFormatting + ShouldAutoSize | E = FORMAT_NUMBER_00 |
| `PaymentReportExport` | FromQuery (chunks — T-04-03-03) | WithMapping + WithHeadings + WithColumnFormatting + ShouldAutoSize | E = FORMAT_NUMBER_00 |

All `map()` / `array()` methods return explicit `(float)` casts on money columns.

### 7. layouts/pdf.blade.php (`resources/views/layouts/pdf.blade.php`) — plain CSS

Pitfall 4 mitigation. Inline `<style>` with: `@page { margin: 140px 30px 80px 30px }` (reserves space for fixed header/footer), `position: fixed` header (mess name + report title + period) and footer (`counter(page)` → "Page N", D-13 — NOT `counter(pages)` which doesn't work in Dompdf). NO Tailwind, NO `@vite`. Branded `<h1>` with mess name.

### 8. 6 PDF views (`resources/views/{mess,my}/reports/pdf/*.blade.php`)

All `@extends('layouts.pdf')` + `@section('report-body')`. Plain CSS classes (`pdf-table-compact`, `math-line`, `totals`, etc.) from the layout. The Monthly Report uses `pdf-table-compact` (D-13 column compaction — 9px font for the wide per-member table). Member statement views render the meal-rate math (D-25), daily breakdown, guest meals, bill payments + advance deposits split, and closing summary. **`advance_applied` is NEVER displayed** (Pitfall 3). The member Monthly PDF (`my/reports/pdf/monthly.blade.php`) OMITS the per-member table entirely (D-19) and shows only totals + the privacy note.

### 9. report-toolbar.blade.php — wired

Replaces Plan 4.1's disabled placeholder buttons with real `<a>` links to `route({$route}.pdf)` / `route({$route}.xlsx)`. Uses `Route::has($pdfRoute)` guard so the toolbar degrades gracefully when routes aren't registered yet. Carries year/month + extra + filters so the export matches what's on screen (D-18 sticky).

### 10. ReportExportController (`app/Http/Controllers/Mess/ReportExportController.php`)

8 manager export methods (4 reports × PDF + Excel). Filenames sanitized via `safeFilename()` (regex with `~` delimiter, strips `/`, `\`, `..`, control chars — T-04-03-04). All PDF renders set `setOption('isRemoteEnabled', false)` (T-04-03-10). `member-statement` export uses `?member_id=X` query param + `Member::firstOrFail()` → MessScope auto-filters → 404 for cross-mess members (T-04-03-08). Expense + Payment PDFs re-query WITHOUT pagination (full filter result for export).

### 11. MyReportExportController (`app/Http/Controllers/My/MyReportExportController.php`)

4 member export methods. Member identity ALWAYS from `$request->user()->getMemberOrNull()` — NO `{member}` URL param (T-04-03-05). `monthlyExcel` passes `['members' => []]` to `MonthlyReportExport` — structural D-19 enforcement in data shape (T-04-03-06). 403 returned when the user has no Member record.

### 12. Routes (`routes/web.php`) — 12 new

8 manager routes added inside the existing `role:admin` + `EnsureMessExists` group (under `mess/reports`): `monthly.pdf/.xlsx`, `member-statement.pdf/.xlsx`, `expenses.pdf/.xlsx`, `payments.pdf/.xlsx`. 4 member routes added inside the existing `role:user` group (under `my/reports`): `statement.pdf/.xlsx`, `monthly.pdf/.xlsx`. Verified via `php artisan route:list --name=mess.reports` (12 routes) and `--name=my.reports` (6 routes).

### 13. Tests (28 new, all green)

| File | Tests | Coverage |
|------|-------|----------|
| `ManagerDashboardTest.php` | 6 | 6 card labels render, pending-meal-off banner (present when 2 pending + absent when 0), 3 chart init calls, zero-meal-rate hint, role:user 403 |
| `ChartRangeTest.php` | 4 | default ranges (Meal 30d, Expense/Payment 6mo), custom range respected, ChartBucketingService picks day for 30d + month for 400d |
| `CacheInvalidationTest.php` | 3 | expense write forgets dash:counts, payment write forgets both dash:counts + bill-preview, mess-scoped (write in mess 1 does NOT forget mess 2) |
| `PdfExportTest.php` | 9 | monthly/member-statement/expenses/payments PDF downloads (Content-Type application/pdf + sanitized filename), member own statement + aggregates-monthly PDF, role:user 403 on manager exports, unauth → /login, cross-mess member → 404 |
| `ExcelExportTest.php` | 6 | monthly/expenses/payments/member-statement/member-monthly .xlsx downloads (Content-Type spreadsheetml.sheet), expenses/payments honor filters, filename sanitized (member name `../evil/..` → no `..` or `/` in filename) |

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] safeFilename regex delimiter conflicted with the slash literal it was matching**

- **Found during:** Task 3 GREEN run — all 15 export tests failed with `ErrorException: preg_replace(): Unknown modifier ']'`.
- **Issue:** My initial `safeFilename()` used `/` as the PCRE delimiter while ALSO matching forward-slash (`/[\\\/]/u`). PHP's parser interpreted the literal `/` inside the character class as the closing delimiter, then the next char (`]`) became an unknown modifier.
- **Fix:** Switched the delimiter to `~` (`~\.\.+|[\\/]+~u` and `~[^\p{L}\p{N}\-_]+~u`) — the character class no longer contains the delimiter, so the regex parses cleanly. Also collapsed the 3-step strip into 2 steps (parent-dir + path-separator in one pass).
- **Files modified:** `app/Http/Controllers/Mess/ReportExportController.php`
- **Verification:** `test_filename_sanitized` passes; member name `../evil/..` produces a sanitized filename with no `..` or `/`.
- **Commit:** `7779943`

**2. [Rule 1 - Bug] Test assertions too strict on Dompdf / Maatwebsite filename quoting**

- **Found during:** Task 3 GREEN run — `test_monthly_pdf_downloads` failed with `Header [Content-Disposition] was found, but value [attachment; filename=monthly-report-2026-06.pdf] does not match [attachment; filename="monthly-report-2026-06.pdf"]`. `test_monthly_excel_downloads` failed with `Failed asserting that 'monthly-report-2026-06.xlsx' ends with ".xlsx"`.
- **Issue:** My tests asserted the quoted form `filename="..."` for both PDF and Excel, but Dompdf emits `filename=...` (unquoted) and Maatwebsite also emits `filename=...` (unquoted). Both forms are RFC-6266 compliant — the plan spec didn't mandate quoting. The actual filenames are correctly sanitized and content-types are correct; the assertions were the bug.
- **Fix:** Relaxed to assert on the sanitized basename substring (`monthly-report-{year}-{month}.pdf` must appear in `Content-Disposition`) and that the value ends with `.xlsx`. The filename-sanitization test (`test_filename_sanitized`) still strictly asserts no `..` or `/` in the parsed filename.
- **Files modified:** `tests/Feature/Report/PdfExportTest.php`, `tests/Feature/Report/ExcelExportTest.php`
- **Verification:** both tests pass; the RFC-compliant filename form is verified.
- **Commit:** `7779943`

## Security Properties Verified

| Threat ID | Mitigation | Test |
|-----------|------------|------|
| T-04-03-01 (cache cross-mess) | `dash:counts:{mess_id}:{YYYY}-{MM}` key is ALWAYS scoped by `Mess::activeId()` in `DashboardService::managerCards()` AND in the `AppServiceProvider::invalidateForModel()` extension | `CacheInvalidationTest::test_dash_counts_key_is_mess_scoped` — write in mess 1 leaves mess 2's key intact |
| T-04-03-04 (filename path traversal / header injection) | `ReportExportController::safeFilename()` strips `/`, `\`, `..`, control chars (regex with `~` delimiter) | `ExcelExportTest::test_filename_sanitized` — member name `../evil/..` produces no `..` or `/` in the parsed filename |
| T-04-03-05 (IDOR via member_id on member exports) | `MyReportExportController` has NO `{member}` URL param; member ALWAYS from `$request->user()->getMemberOrNull()` | Verified by reading the controller (no `Member::where(...)` or route-model binding) |
| T-04-03-06 (peer dues in member monthly export) | `MyReportExportController::monthlyExcel` empties `members` array before passing to `MonthlyReportExport` — structural enforcement in DATA, not just view. PDF view `my/reports/pdf/monthly` OMITS per-member table. | `PdfExportTest::test_member_aggregates_monthly_pdf` (200 + .pdf Content-Type); the view was verified by reading it |
| T-04-03-07 (elevation of privilege) | Manager export routes inside `['auth', 'role:admin', EnsureMessExists::class]` middleware | `PdfExportTest::test_member_role_forbidden_on_manager_exports` — role:user → 403 |
| T-04-03-08 (cross-mess member in export) | `Member::where('id', $id)->firstOrFail()` triggers MessScope → 404 | `PdfExportTest::test_cross_mess_member_pdf_returns_404` |
| T-04-03-10 (Dompdf SSRF via remote resources) | Every `Pdf::loadView()` chain sets `setOption('isRemoteEnabled', false)` | Verified by reading both controllers (8 PDF methods in manager + 2 in member all set the option) |
| T-04-03-03 (DoS via huge Excel export) | `ExpenseReportExport` + `PaymentReportExport` implement `FromQuery` (chunked); `MemberStatementExport` uses `FromCollection` (small fixed shape); `MonthlyReportExport` uses `FromArray` (cached preview) | Verified by reading the export classes (implements `FromQuery` / `FromCollection` / `FromArray`) |

## Self-Check

All claims verified by direct command:

- [x] `app/Services/DashboardService.php` contains `class DashboardService` + literal `dash:counts:` + `MealType::value(` (Pitfall A3 — no hard-coded 0.5/1/1)
- [x] `app/Services/DashboardService.php` contains `ExpenseKind::BAZAR` (D-06 expense trend filter)
- [x] `app/Services/ChartBucketingService.php` returns `'day'` for ≤60d, `'month'` for >365d (tested)
- [x] `app/Http/Controllers/HomeController.php` contains `DashboardService` (DI) + `view('home'`
- [x] `app/Http/Requests/Dashboard/ChartRangeRequest.php` exists with rules for all 6 from/to + 3 preset query params
- [x] `app/Providers/AppServiceProvider.php` contains literal `dash:counts:` (cache forget extended into existing listener) — verified ONE `Event::listen("eloquent.saved:` block per model (no duplicate listener added)
- [x] `resources/views/home.blade.php` contains `initDashboardChart('meal-trend-chart'`, `initDashboardChart('expense-trend-chart'`, `initDashboardChart('payment-trend-chart'`
- [x] `resources/views/home.blade.php` contains all 6 card labels via `__()` + alert banner link `route('mess.meal-off.index')`
- [x] `resources/views/home.blade.php` does NOT contain the old link-card grid (Members/Mess settings/Audit log/Payments/Advance balances/Bill preview `<a>` blocks removed)
- [x] All 4 Excel exports exist under `app/Exports/` and contain `NumberFormat::FORMAT_NUMBER_00` + `(float)` casts
- [x] `app/Exports/ExpenseReportExport.php` + `PaymentReportExport.php` contain `implements FromQuery`
- [x] `resources/views/layouts/pdf.blade.php` contains `counter(page)` + `@page` + `position: fixed` + does NOT contain `counter(pages)` or `@vite` or `class="grid`
- [x] All 6 PDF view files exist under `resources/views/mess/reports/pdf/` + `resources/views/my/reports/pdf/`
- [x] `resources/views/mess/reports/pdf/monthly.blade.php` contains `@extends('layouts.pdf')` and `Money::taka(`
- [x] `resources/views/my/reports/pdf/monthly.blade.php` does NOT contain `@foreach ($data['members']` (D-19 — no per-member table)
- [x] None of the member-statement PDF views contain `advance_applied` (Pitfall 3 — grep returned 0 matches)
- [x] `resources/views/components/report-toolbar.blade.php` contains real `route(` calls (no `aria-disabled` placeholders)
- [x] `app/Http/Controllers/Mess/ReportExportController.php` contains `class ReportExportController` with 8 methods + `Pdf::loadView(` + `Excel::download(` + `safeFilename` method
- [x] `app/Http/Controllers/My/MyReportExportController.php` contains `$request->user()->getMemberOrNull()` + does NOT contain `{member}` route-model binding + `monthlyExcel` empties `members`
- [x] `routes/web.php` contains 12 new export routes; `php artisan route:list --name=mess.reports` shows 12 routes (4 HTML + 8 export); `--name=my.reports` shows 6 routes (2 HTML + 4 export)
- [x] `php artisan test --filter='PdfExportTest|ExcelExportTest'` → 15 passed
- [x] `php artisan test --filter='ManagerDashboardTest|ChartRangeTest|CacheInvalidationTest'` → 13 passed
- [x] `php artisan test` → **233 passed** (205 prior + 13 dashboard + 15 export) — no regression
- [x] `vendor/bin/pint --test` → passed
- [x] Commits `1d87e00`, `e10d70a`, `b04afe6`, `281b2d3`, `7779943` exist in `git log`
- [x] None of the new files contain `bdt(` (uses `Money::taka()` everywhere — Gap 1 resolution from 04-00 holds)

## Self-Check: PASSED
