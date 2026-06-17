---
phase: 04-reports-dashboard
plan: 01
subsystem: manager reports (Monthly + Member Statement + Expense + Payment)
tags: [reports, read-only, manager-only, html, filters, month-picker, d-26-snapshot, money-taka]
requires:
  - app/Services/BillPreviewService.php
  - app/Models/MonthlyClosing.php
  - app/Models/MonthlyMemberSummary.php
  - app/Models/Expense.php
  - app/Models/Payment.php
  - app/Support/Money.php
  - app/Support/PaymentMethod.php
  - app/Support/PaymentType.php
  - app/Support/MealType.php
  - app/Support/ExpenseKind.php
  - app/Models/Concerns/BelongsToActiveMess.php
  - resources/views/components/empty-state.blade.php
  - resources/views/components/mess-date-nav.blade.php
  - routes/web.php
provides:
  - "App\\Services\\ReportService — monthlyReport(year,month) with D-26 closed/open switch returning BillPreviewService-shaped array + 'source' flag; expenseReport(filters) + paymentReport(filters) paginated filtered queries"
  - "App\\Services\\MemberStatementService — forMember(memberId,year,month) wraps BillPreviewService::forMember + adds daily breakdown + guest meals + payments + D-24 period label"
  - "App\\Http\\Controllers\\Mess\\ReportController — monthly(), memberStatement(), expenses(), payments() thin View actions with cross-mess 404 via Member::firstOrFail()"
  - "4 Form Requests under app/Http/Requests/Report/ — MonthNavigationRequest, MemberStatementRequest, ExpenseReportRequest, PaymentReportRequest (D-18 sticky filters, enum-constrained via PaymentMethod::ALL)"
  - "<x-month-nav> reusable ◀ Month ▶ + dropdown + This-month link component (D-20)"
  - "<x-report-toolbar> with DISABLED PDF/Excel placeholder buttons (Plan 04-03 wires real hrefs)"
  - "4 manager report views: monthly.blade.php (totals grid + per-member table + closed-month badge), member-statement.blade.php (meal-rate math + daily breakdown + payments split + closing summary), expenses.blade.php + _filters/expenses partial, payments.blade.php + _filters/payments partial"
  - "4 mess.reports.* routes inside role:admin + EnsureMessExists group"
  - "tests/Feature/Report/{MonthlyReportTest, MemberStatementTest, ExpenseReportTest, PaymentReportTest} — 26 tests covering role enforcement, D-26 switch, cross-mess 404, advance_applied pitfall guard, sticky filters, totals math, presets, empty states"
affects:
  - "Plan 04-02 (member views): reuses ReportService + MemberStatementService; member routes will derive member_id from session, not URL"
  - "Plan 04-03 (exports): reuses the controllers' data shape; report-toolbar's disabled PDF/Excel buttons become real links"
tech-stack:
  added: []
  patterns:
    - "D-26 closed/open month switch centralized in ReportService::monthlyReport + MemberStatementService::forMember — MonthlyClosing lookup → snapshot path; absence → BillPreviewService live compute"
    - "Snapshot-to-live shape mapping: MonthlyMemberSummary columns mapped verbatim to BillPreviewService member-row keys; 'advance_applied' snapshot column surfaced as 'bill_payments' (Pitfall 3)"
    - "Cross-mess protection via Member::where('id', \$id)->firstOrFail() + BelongsToActiveMess global scope (MessScope auto-filters → 404)"
    - "Filter Form Requests with strict rules: date 'after_or_equal', enum 'in:PaymentMethod::ALL', category/member 'exists' — no \$request->all()"
    - "Sticky GET filters (D-18): inputs pre-filled from request()->query(); presets emit start/end-of-month query params"
    - "Money via App\\Support\\Money::taka() everywhere — no bdt() anywhere (Gap 1 resolution from 04-00 holds)"
key-files:
  created:
    - "app/Services/ReportService.php"
    - "app/Services/MemberStatementService.php"
    - "app/Http/Controllers/Mess/ReportController.php"
    - "app/Http/Requests/Report/MonthNavigationRequest.php"
    - "app/Http/Requests/Report/MemberStatementRequest.php"
    - "app/Http/Requests/Report/ExpenseReportRequest.php"
    - "app/Http/Requests/Report/PaymentReportRequest.php"
    - "resources/views/components/month-nav.blade.php"
    - "resources/views/components/report-toolbar.blade.php"
    - "resources/views/mess/reports/monthly.blade.php"
    - "resources/views/mess/reports/member-statement.blade.php"
    - "resources/views/mess/reports/expenses.blade.php"
    - "resources/views/mess/reports/payments.blade.php"
    - "resources/views/mess/reports/_filters/expenses.blade.php"
    - "resources/views/mess/reports/_filters/payments.blade.php"
    - "tests/Feature/Report/MonthlyReportTest.php"
    - "tests/Feature/Report/MemberStatementTest.php"
    - "tests/Feature/Report/ExpenseReportTest.php"
    - "tests/Feature/Report/PaymentReportTest.php"
  modified:
    - "routes/web.php"
decisions:
  - "PaymentReportRequest method enum sourced from App\\Support\\PaymentMethod::ALL (not hard-coded list) — single source of truth matches the migration's stored values (cash, bkash, nagad, rocket, bank)"
  - "MonthlyMemberSummary.advance_applied column is mapped to 'bill_payments' on the way out of ReportService (Pitfall 3 — it equals bill_payments, NOT advance consumption; the snapshot's column name is misleading per CR-03). The internal 'advance_applied' key is kept for shape parity but views MUST NOT display it — verified by test_statement_excludes_advance_applied_display"
  - "Snapshot path returns advance_balance=0.0 + active_days=0 + advance_payments=0.0 because the MonthlyMemberSummary schema does not carry these carried-forward columns. The BillPreviewService live path is authoritative for those. This is acceptable because a closed month's statement shows the snapshot's balance_due as the closing due (which IS what the close persisted)"
  - "Closed-month badge rendered as a small emerald pill next to the period label when data.source === 'snapshot' (research Open Question #4 LOCKED resolution)"
  - "month-nav dropdown lists the last 24 months (now()->subMonths(23)..now()) — covers a full year of history + the current month without overwhelming the picker"
  - "report-toolbar's PDF/Excel buttons are disabled placeholders (aria-disabled, cursor-not-allowed) per the plan — Plan 04-03 wires the real hrefs"
metrics:
  duration: "~9 minutes"
  completed: "2026-06-17"
  tasks: 3
  files-touched: 19
---

# Phase 04 Plan 01: Manager Reports Summary

Builds the 4 manager-side reports (RPT-01..RPT-04): Monthly Report, Member Statement, Expense Report, Payment Report. HTML only — PDF/Excel exports land in Plan 04-03 (the report-toolbar has disabled placeholder buttons for them). All money flows through `App\Support\Money::taka()`; the D-26 closed/open month switch is centralized in `ReportService` and `MemberStatementService`; cross-mess access returns 404 via `Member::firstOrFail()` + the `BelongsToActiveMess` global scope.

## Tasks Completed

| Task | Name | Commit | Key Files |
|------|------|--------|-----------|
| 1 | ReportService + MemberStatementService + Form Requests + month-nav + Monthly/MemberStatement tests | `e22113e` | app/Services/ReportService.php, app/Services/MemberStatementService.php, app/Http/Requests/Report/*.php, resources/views/components/month-nav.blade.php, tests/Feature/Report/{MonthlyReportTest,MemberStatementTest}.php |
| 2 | ReportController + routes + 4 report views + filter partials + report-toolbar | `300eadc` | app/Http/Controllers/Mess/ReportController.php, routes/web.php, resources/views/components/report-toolbar.blade.php, resources/views/mess/reports/*.blade.php |
| 3 | ExpenseReportTest + PaymentReportTest feature tests | `6d7d45b` | tests/Feature/Report/{ExpenseReportTest,PaymentReportTest}.php |

## What Was Built

### 1. ReportService (`app/Services/ReportService.php`)

Constructor-injects `BillPreviewService`. Three public methods:

- **`monthlyReport(int $year, int $month): array`** — D-26 switch. Looks up `MonthlyClosing::query()->where('mess_id', Mess::activeId())->where('year', $year)->where('month', $month)->first()`. If found → reads `MonthlyMemberSummary` rows and maps them to the exact shape `BillPreviewService::preview()` returns (year, month, total_bazar, total_meals, meal_rate, total_fixed, days_in_month, members[]) + a `'source' => 'snapshot'` flag. If absent → delegates to `$this->preview->preview($year, $month)` and tags on `'source' => 'live'`. The view switches the period label + closed-month badge on this flag.
- **`expenseReport(array $filters): array`** — paginated (50/page) query on `Expense` with eager-loaded `category`, `purchasedByMember`, `enteredBy`. Filters: `from`, `to`, `category_id`, `month` (YYYY-MM). `->appends(request()->query())` keeps filters sticky across pagination. Returns `['rows' => LengthAwarePaginator, 'totals' => ['amount' => float]]`.
- **`paymentReport(array $filters): array`** — same shape, query on `Payment` with eager-loaded `member`, `enteredBy`. Filters: `member_id`, `method`, `from`, `to`.

### 2. MemberStatementService (`app/Services/MemberStatementService.php`)

Constructor-injects `BillPreviewService`. Single public method `forMember(int $memberId, int $year, int $month): array`. Returns:

- `row` — the member's bill row (from `BillPreviewService::forMember()` for open month; from `MonthlyMemberSummary` for closed month, shape-mapped verbatim).
- `daily` — array of `['date', 'breakfast', 'lunch', 'dinner', 'meal_value']` from `MealEntry::where('member_id', ...)->whereBetween('date', ...)`. `meal_value` per row = `MealType::value(BREAKFAST)` + `MealType::value(LUNCH)` + `MealType::value(DINNER)` for the checked boxes (D-23).
- `guests` — `GuestMeal` rows for the month.
- `payments` — `Payment` rows for the month (split in the view into bill payments vs advance deposits by `type`).
- `is_closed` — bool.
- `period_label` — D-24: `"As of today, {j F Y}"` for open month; `"{F Y}"` for closed month.
- `source` — `'live'` or `'snapshot'`.

### 3. ReportController (`app/Http/Controllers/Mess/ReportController.php`)

Thin View-returning actions, mirroring `BillPreviewController`:

- `monthly(MonthNavigationRequest)` — resolves year/month (defaults to now), calls `ReportService::monthlyReport()`, loads the active `Mess`, returns the view.
- `memberStatement(MemberStatementRequest)` — `Member::where('id', $request->integer('member_id'))->firstOrFail()` (MessScope auto-filters by active mess_id → cross-mess member returns 404), calls `MemberStatementService::forMember()`, loads the member picker (`Member::whereIn('status', [ACTIVE, FORMER])->orderBy('name')`).
- `expenses(ExpenseReportRequest)` — calls `ReportService::expenseReport($request->validated())`, loads categories.
- `payments(PaymentReportRequest)` — calls `ReportService::paymentReport($request->validated())`, loads members.

### 4. Routes (`routes/web.php`)

Inside the existing `['auth', 'role:admin', EnsureMessExists::class]` group, after `mess/bill-preview` and before `mess/close`:

```php
Route::prefix('mess/reports')->name('mess.reports.')->group(function () {
    Route::get('monthly', ...)->name('monthly');
    Route::get('member-statement', ...)->name('member-statement');
    Route::get('expenses', ...)->name('expenses');
    Route::get('payments', ...)->name('payments');
});
```

All 4 routes verified via `php artisan route:list --name=mess.reports`. Member role (`role:user`) returns 403; unauthenticated requests redirect to `/login`.

### 5. Components

- **`<x-month-nav>`** (`resources/views/components/month-nav.blade.php`) — props `['year', 'month', 'route' => 'mess.reports.monthly', 'extra' => []]`. Renders ◀ arrow (subMonth), a `<select>` dropdown listing the last 24 months, ▶ arrow (addMonth), and a "This month" link. The `extra` prop carries additional query params (used by the Member Statement to keep `member_id` sticky across month navigation). Mirrors the structure of `<x-mess-date-nav>` (same Tailwind classes, `min-h-[44px]` touch targets, SVG chevrons).
- **`<x-report-toolbar>`** (`resources/views/components/report-toolbar.blade.php`) — props `['route', 'year', 'month', 'showExports' => false, 'extra' => []]`. Renders the month-nav on the left and disabled PDF/Excel placeholder buttons on the right (Plan 04-03 will swap these for real `route('mess.reports.monthly.pdf')` hrefs).

### 6. Views

- **`monthly.blade.php`** — title + period + closed-month badge. 6-card totals grid (Members, Meals, Meal Rate with D-29 zero-bazar hint, Total Bazar, Total Fixed, Due/Advance). Per-member table (Member, Status pill, Meals, Meal Cost, Fixed, Guest, Bill, Paid, Due, Advance) with each member name linking to their statement. Empty state (D-28) when no members.
- **`member-statement.blade.php`** — title + member name + period label + closed-month badge. GET form with member `<select>` + `<x-month-nav>` (carrying `member_id`). Meal-rate math card (D-25: `Money::taka(rate) / meal × {meals} = Money::taka(meal_cost)`). Daily meal breakdown table (Date, B, L, D, Meal Value) with per-type + grand totals. Guest meals table (conditional). Two-column payments section: bill payments + advance deposits, each with its own empty hint. Closing summary card (Opening Advance, Opening Due, Fixed Share, Meal Cost, Bill, Paid, Closing Due, Advance Balance). **`advance_applied` is NEVER displayed** (Pitfall 3).
- **`expenses.blade.php`** + **`_filters/expenses.blade.php`** — title + total. Sticky GET filter form (From, To, Category) + Apply + This-month + Last-month preset links. Table (Date, Category with kind badge, Description, Vendor, Purchased by, Amount). Pagination. Empty state when no rows.
- **`payments.blade.php`** + **`_filters/payments.blade.php`** — title + total collected. Sticky GET filter form (Member, Method — sourced from `PaymentMethod::ALL`, From, To) + presets. Table (Date, Member, Method pill with per-method color, Type, Amount, Reference). Pagination. Empty state.

### 7. Tests (26 new, all green)

| File | Tests | Coverage |
|------|-------|----------|
| `MonthlyReportTest.php` | 6 | guest redirect, member 403, totals + member table render, month-picker changes period, closed-month snapshot path, empty-period hint |
| `MemberStatementTest.php` | 5 | manager views active-mess member with meal-rate math + daily breakdown, cross-mess member → 404, member role 403, statement excludes `advance_applied` literal |
| `ExpenseReportTest.php` | 7 | guest redirect, member 403, list with totals, sticky date filter in URL, category filter, preset links present, empty state |
| `PaymentReportTest.php` | 8 | guest redirect, member 403, list rendering, totals sum math (100+200.50+300.25 = ৳600.75), method filter, member filter, preset links, empty state |

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Login route name is `tyro-login.login`, not `login`**

- **Found during:** Task 1 test run — the 3 `test_guest_is_redirected_to_login` tests failed with `RouteNotFoundException [login] not defined`.
- **Issue:** The plan's test boilerplate used `->assertRedirect(route('login'))`. The actual route name in this codebase (tyro-login package) is `tyro-login.login`. The path `/login` is correct, but the named-route lookup fails.
- **Fix:** Changed all 3 guest-redirect assertions to use the path string `'/login'` directly — matches the established pattern in `tests/Feature/Auth/RouteAccessTest.php` (which uses `->assertRedirect('/login')`, not `route('login')`).
- **Files modified:** `tests/Feature/Report/MonthlyReportTest.php`, `tests/Feature/Report/ExpenseReportTest.php`, `tests/Feature/Report/PaymentReportTest.php`
- **Verification:** All 3 guest-redirect tests now pass. `php artisan test --filter=Report` → 26 passed.

**2. [Rule 1 - Bug] PaymentReportTest member-filter assertion was too strict**

- **Found during:** Task 3 test run — `test_member_filter` failed because `assertDontSee('Beta Member')` matched Beta's name in the filter `<select>` dropdown (which legitimately lists ALL members so the manager can switch the filter).
- **Issue:** The filter dropdown always renders every member's name (correct UX — the manager needs to be able to pick any member). The data table is what should be filter-scoped, not the entire page.
- **Fix:** Changed the assertion to verify the filter is enforced on the data, not the page: assert Alpha's payment row appears, assert the totals reflect the filter (৳100.00 for Alpha only, not ৳300.00 combined), assert Beta's payment amount (৳200.00) does NOT appear in the table body.
- **Files modified:** `tests/Feature/Report/PaymentReportTest.php`
- **Verification:** `test_member_filter` passes; the filter UX correctly shows all members in the dropdown while filtering the payment rows + totals.

## Security Properties Verified

| Threat ID | Mitigation | Test |
|-----------|------------|------|
| T-04-01-01 (Elevation of privilege) | Routes inside `['auth', 'role:admin', EnsureMessExists::class]` middleware | `MonthlyReportTest::test_member_role_forbidden`, `MemberStatementTest::test_member_role_forbidden`, `ExpenseReportTest::test_member_role_forbidden`, `PaymentReportTest::test_member_role_forbidden` — all assert 403 |
| T-04-01-02 (Cross-mess info disclosure) | `Member::where('id', $id)->firstOrFail()` triggers MessScope → 404 | `MemberStatementTest::test_manager_cannot_view_cross_mess_member` asserts 404 |
| T-04-01-03 (Mass-assignment / tampering) | All filters via Form Requests with strict rules; no `$request->all()` | `ExpenseReportRequest`, `PaymentReportRequest`, `MemberStatementRequest`, `MonthNavigationRequest` — `method` enum from `PaymentMethod::ALL`, `exists:expense_categories,id`, `exists:members,id`, `after_or_equal:to` |
| T-04-01-04 (`advance_applied` info disclosure) | View omits `advance_applied` entirely; service maps snapshot column to `bill_payments` | `MemberStatementTest::test_statement_excludes_advance_applied_display` asserts the literal `advance_applied` does not appear |
| T-04-01-06 (DoS via unbounded queries) | `paginate(50)` + Form Request date-range validation | Verified by reading ReportService (paginate(50) on both expenseReport + paymentReport) |

## Self-Check

All claims verified by direct command:

- [x] `app/Services/ReportService.php` exists, contains `class ReportService`, literal `BillPreviewService` (DI), literal `MonthlyClosing::query()` (D-26 path)
- [x] `app/Services/MemberStatementService.php` exists, contains `dailyBreakdown` method + `->where('member_id', $memberId)` for MealEntry
- [x] All 4 Form Requests exist under `app/Http/Requests/Report/`
- [x] `app/Http/Requests/Report/ExpenseReportRequest.php` contains `'from'` and `'category_id'` rule keys
- [x] `app/Http/Requests/Report/PaymentReportRequest.php` contains `'method'` rule key (sourced from `PaymentMethod::ALL`)
- [x] `resources/views/components/month-nav.blade.php` exists with `@props([` declaration
- [x] `resources/views/components/report-toolbar.blade.php` exists with `@props([` declaration
- [x] `app/Http/Controllers/Mess/ReportController.php` contains `class ReportController` + methods `monthly`, `memberStatement`, `expenses`, `payments`
- [x] `routes/web.php` contains `Route::prefix('mess/reports')->name('mess.reports.')` block with all 4 GET routes
- [x] `php artisan route:list --name=mess.reports` lists all 4 routes
- [x] `resources/views/mess/reports/monthly.blade.php` contains `Money::taka(`, `__('Monthly Report')`, `<x-month-nav`
- [x] `resources/views/mess/reports/member-statement.blade.php` contains `__('Meal rate')`, `Money::taka(`, `<x-month-nav`, daily breakdown table
- [x] `resources/views/mess/reports/member-statement.blade.php` does NOT contain `advance_applied` (grep returned 0 matches)
- [x] `resources/views/mess/reports/expenses.blade.php` includes `_filters/expenses` partial
- [x] `resources/views/mess/reports/payments.blade.php` includes `_filters/payments` partial
- [x] None of the new view files contain `bdt(` (grep returned 0 matches in `resources/views/mess/reports/`)
- [x] None of the new app/ files contain `bdt(` (grep returned 0 matches in `app/`)
- [x] `tests/Feature/Report/MonthlyReportTest.php` exits 0 with 6 test methods
- [x] `tests/Feature/Report/MemberStatementTest.php` exits 0 with 5 test methods including `test_manager_cannot_view_cross_mess_member`
- [x] `tests/Feature/Report/ExpenseReportTest.php` exits 0 with 7 test methods including `test_member_role_forbidden`
- [x] `tests/Feature/Report/PaymentReportTest.php` exits 0 with 8 test methods including `test_member_role_forbidden`
- [x] All test files use `$this->actingAs(` (not raw `$this->get`)
- [x] `php artisan test --filter=Report` → 26 passed (75 assertions)
- [x] `php artisan test` → 188 passed (427 assertions) — no regression on the 162 prior tests
- [x] `vendor/bin/pint --test app/ tests/` → passed
- [x] Commits `e22113e`, `300eadc`, `6d7d45b` exist in `git log`

## Self-Check: PASSED
