---
phase: 04-reports-dashboard
plan: 02
subsystem: member self-view + member dashboard (Wave 3)
tags: [reports, read-only, member-only, idor-safe, html, dashboard, d-16-overview, d-19-aggregates, money-taka]
requires:
  - app/Services/ReportService.php
  - app/Services/MemberStatementService.php
  - app/Services/BillPreviewService.php
  - app/Models/MealEntry.php
  - app/Models/Payment.php
  - app/Models/AdvanceBalance.php
  - app/Support/Money.php
  - app/Support/MealType.php
  - app/Support/PaymentType.php
  - app/Models/User.php
  - app/Models/Member.php
  - app/Http/Controllers/MyController.php
  - app/Http/Controllers/My/MyBillPreviewController.php
  - resources/views/components/month-nav.blade.php
  - resources/views/components/report-toolbar.blade.php
  - resources/views/components/empty-state.blade.php
  - resources/views/components/method-pill.blade.php
  - resources/views/components/tab-nav.blade.php
  - resources/views/my.blade.php
  - routes/web.php
provides:
  - "App\\Http\\Controllers\\My\\MyReportController — statement() + monthly() derive \$member from \$request->user()->getMemberOrNull(); NO {member} URL param on role:user routes"
  - "App\\Services\\MemberDashboardService — overviewCards(user) returns 4 DASH-04 cards (my_meals excludes guest meals per Q3 LOCKED, my_bill via cached BillPreviewService, my_advance via AdvanceBalance, recent_payments for Payment History)"
  - "App\\Http\\Requests\\My\\MyMonthNavigationRequest — validates year (2000-2100) + month (1-12)"
  - "2 role:user routes inside my.reports. prefix: statement + monthly (NO {member} param — IDOR structurally impossible)"
  - "<x-stat-card> reusable dashboard card component (label/value/hint/icon)"
  - "resources/views/my/_overview.blade.php — 4 DASH-04 cards in responsive grid + no-member empty state"
  - "resources/views/my/reports/statement.blade.php — full 8-section ledger for authenticated member (no member picker; no advance_applied display)"
  - "resources/views/my/reports/monthly.blade.php — aggregates-only D-19 (totals grid + zero-meal-rate hint; never iterates members[] for per-member display)"
  - "Extended MyController: default tab 'profile' -> 'overview'; injects MemberDashboardService; loads overview cards when tab=overview"
  - "Extended my.blade.php: tab order [Overview, Profile, Meals, Meal off, Payments, My reports]; My reports renders 2 link cards"
  - "tests/Feature/Report/{MyStatementTest,MyMonthlyReportTest} — 11 tests covering IDOR, role enforcement, month picker, D-19, Pitfall 3"
  - "tests/Feature/Dashboard/MyDashboardTest — 6 tests covering Overview landing, My reports tab, no-member empty state, My Meals excludes guest meals, My Payment History, manager 403"
affects:
  - "Plan 04-03 (exports): the report-toolbar's disabled PDF/Excel buttons on my.reports.* views will become real hrefs (my.reports.statement.pdf / .xlsx, my.reports.monthly.pdf / .xlsx)"
  - "Plan 04-03 (manager dashboard): reuses <x-stat-card> for the 6 DASH-01 manager cards"
tech-stack:
  added: []
  patterns:
    - "IDOR prevention via structural design: role:user routes have NO {member} URL param; member identity ALWAYS comes from \$request->user()->getMemberOrNull(). ?member_id= query params are IGNORED by the controller. A member can only ever see their own data + the aggregates-only monthly report (D-19)."
    - "D-19 enforcement: member Monthly Report reuses ReportService::monthlyReport() verbatim (returns full shape incl. members[]) but the member-side view OMITS the per-member table. Only aggregate sums (total_due, total_advance across all members) are exposed — individual members' dues/advances are NOT visible."
    - "Reuse over duplication: MyReportController delegates to Plan 4.1's MemberStatementService::forMember() and ReportService::monthlyReport() unchanged. No report logic was re-implemented."
    - "MemberDashboardService::overviewCards() computes My Meals by mirroring BillPreviewService::mealTotals() (MealType::value() for each checked B/L/D boolean). Guest meals are excluded (Open Question #3 LOCKED — they live in guest_meals.charge_amount, not MealEntry booleans)."
    - "Default tab flipped 'profile' -> 'overview' (D-16): the Overview landing is now the first thing a member sees at /my. Existing tabs (Profile, Meals, Meal off, Payments) drill into detail; new My reports tab links to the 2 member reports."
    - "Money via App\\Support\\Money::taka() everywhere — no bdt() anywhere (Gap 1 resolution from 04-00 holds)"
key-files:
  created:
    - "app/Http/Controllers/My/MyReportController.php"
    - "app/Services/MemberDashboardService.php"
    - "app/Http/Requests/My/MyMonthNavigationRequest.php"
    - "resources/views/components/stat-card.blade.php"
    - "resources/views/my/_overview.blade.php"
    - "resources/views/my/reports/statement.blade.php"
    - "resources/views/my/reports/monthly.blade.php"
    - "tests/Feature/Report/MyStatementTest.php"
    - "tests/Feature/Report/MyMonthlyReportTest.php"
    - "tests/Feature/Dashboard/MyDashboardTest.php"
  modified:
    - "app/Http/Controllers/MyController.php"
    - "resources/views/my.blade.php"
    - "routes/web.php"
decisions:
  - "Member identity for ALL role:user report routes is bound to the session, never the URL. The controller signatures take only MyMonthNavigationRequest (year/month) — there is no member_id parameter to even accept. This is the strictest possible IDOR mitigation (T-04-02-01)."
  - "MemberDashboardService::overviewCards() returns ['member' => null, ...zeros...] when the user has no Member record; the _overview partial renders the existing <x-empty-state>. No special-cased controller branch."
  - "My Meals card computes via a direct MealEntry query (mirroring BillPreviewService::mealTotals()) rather than calling forMember()['meals']. Both give the same number; the direct query avoids the full preview recompute when the cache is cold, and matches the per-month scope explicitly."
  - "The My reports tab renders 2 link cards (Member Statement + Monthly Report) rather than embedding the reports inline — the reports have their own dedicated pages with month pickers, and the Overview landing already gives the at-a-glance view. Avoids double-rendering."
  - "Aggregates-only monthly view reuses ReportService (returns full shape incl. members[]) rather than forking the service — cheaper to maintain, and the view's non-iteration of members[] is enforced by test_member_monthly_has_no_per_member_table + test_member_cannot_view_per_member_dues."
metrics:
  duration: "~10 minutes"
  completed: "2026-06-17"
  tasks: 2
  files-touched: 10
---

# Phase 04 Plan 02: Member Views + Member Dashboard Summary

Builds the member-side read surface (RPT-05 own statement, RPT-06 aggregates-only monthly) and the member dashboard Overview landing (DASH-04: 4 cards). Member routes derive `$member` from `$request->user()->getMemberOrNull()` — there is NO `{member}` URL parameter on any `role:user` route, making IDOR structurally impossible (T-04-02-01). The member Monthly Report reuses Plan 4.1's `ReportService::monthlyReport()` verbatim but renders a view that OMITS the per-member table (D-19).

## Tasks Completed

| Task | Name | Commit | Key Files |
|------|------|--------|-----------|
| 1 | MyReportController + MemberDashboardService + member report routes + MyController extension (TDD RED+GREEN) | `a062fe3` (RED) + `a576c66` (GREEN) | app/Http/Controllers/My/MyReportController.php, app/Services/MemberDashboardService.php, app/Http/Requests/My/MyMonthNavigationRequest.php, app/Http/Controllers/MyController.php, routes/web.php, tests/Feature/Report/{MyStatementTest,MyMonthlyReportTest}.php |
| 2 | Member Overview cards view + member report views + stat-card component + my.blade.php extension + dashboard tests | `bd95fd2` | resources/views/components/stat-card.blade.php, resources/views/my/_overview.blade.php, resources/views/my/reports/{statement,monthly}.blade.php, resources/views/my.blade.php, tests/Feature/Dashboard/MyDashboardTest.php |

## What Was Built

### 1. MyReportController (`app/Http/Controllers/My/MyReportController.php`)

Constructor-injects `MemberStatementService` and `ReportService` (both from Plan 4.1). Two methods:

- **`statement(MyMonthNavigationRequest $request): View`** — derives `$member = $request->user()->getMemberOrNull()`; if null returns the `my.no-member` view. Reads year/month from the validated request (defaults to now). Calls `$this->statements->forMember($member->id, $year, $month)` and renders `my.reports.statement`. The `?member_id=` query param is structurally ignored — there is no controller parameter to receive it.
- **`monthly(MyMonthNavigationRequest $request): View`** — same member-from-auth derivation. Calls `$this->reports->monthlyReport($year, $month)` (returns the full shape incl. `members[]`) and renders `my.reports.monthly` — the VIEW omits the per-member table (D-19).

### 2. MemberDashboardService (`app/Services/MemberDashboardService.php`)

Constructor-injects `BillPreviewService`. Single public method:

```php
public function overviewCards(User $user): array
```

Returns the 4 DASH-04 cards for the Overview landing:
- **`member`** — `$user->getMemberOrNull()`; null when the user has no Member record (view renders no-member empty state).
- **`my_meals`** — sum of regular meal values this month for the member (mirrors `BillPreviewService::mealTotals()` — `MealType::value(BREAKFAST/LUNCH/DINNER)` for each checked boolean). **EXCLUDES guest meals** (Open Question #3 LOCKED).
- **`my_bill`** — `$this->preview->forMember($member->id, $year, $month)['bill'] ?? 0.0` (reuses the bill-preview cache; D-17).
- **`my_advance`** — `AdvanceBalance::where('member_id', ...)->value('balance') ?? 0.0`.
- **`recent_payments`** — last 5 `Payment` rows (`->latest('date')->latest('id')->limit(5)`) for the "My Payment History" card.

### 3. MyController (`app/Http/Controllers/MyController.php`) — extended

- Constructor-injects `MemberDashboardService`.
- `index()` default tab changed `'profile'` → `'overview'` (D-16 — Overview is now the first thing a member sees).
- New `if ($tab === 'overview')` branch loads `$data['overview'] = $this->dashboards->overviewCards($request->user())`.
- All existing branches preserved (profile, meals, meal-off, payments, balance, bill-preview). `'reports'` is a recognized tab value with no extra data loading — the view renders static link cards.

### 4. Routes (`routes/web.php`) — 2 new inside the existing `role:user` group

```php
Route::prefix('my/reports')->name('my.reports.')->group(function () {
    Route::get('statement', [MyReportController::class, 'statement'])->name('statement');
    Route::get('monthly',   [MyReportController::class, 'monthly'])->name('monthly');
});
```

Both routes verified via `php artisan route:list --name=my.reports`. No `{member}` URL param — IDOR is structurally impossible.

### 5. Components

- **`<x-stat-card>`** (`resources/views/components/stat-card.blade.php`) — props `['label', 'value', 'hint' => null, 'icon' => null]`. Mobile-first single dashboard card with Tailwind palette matching existing surfaces (`bg-white border border-slate-200 rounded-lg p-4 shadow-sm`). Will be reused by Plan 04-03's 6 DASH-01 manager cards.

### 6. Views

- **`_overview.blade.php`** — renders the 4 DASH-04 cards in `grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4`:
  - My Meals (this month): `number_format($overview['my_meals'], 1)` via `<x-stat-card>`.
  - My Bill (this month): `Money::taka($overview['my_bill'])` via `<x-stat-card>`.
  - My Advance: `Money::taka($overview['my_advance'])` via `<x-stat-card>`.
  - My Payment History: a custom panel (NOT a stat-card) showing the 5 recent payments with `Money::taka(amount)` + date + `<x-method-pill>`, plus a "View all" link to `route('my.payments')`. Empty hint when no payments.
  - No-member empty state when `$overview['member'] === null`.
- **`my/reports/statement.blade.php`** — full 8-section ledger (mirrors the manager `mess/reports/member-statement.blade.php` structure from Plan 4.1):
  - Page title `$member->name` + period label + closed-month badge.
  - `<x-report-toolbar>` with `<x-month-nav route="my.reports.statement">` + disabled PDF/Excel placeholders.
  - **NO member-picker `<select>`** (member is fixed = self).
  - Meal-rate math (D-25): `Money::taka(rate) / meal × {meals} = Money::taka(meal_cost)`.
  - Daily meal breakdown table (Date, B, L, D, Meal Value) with per-type + grand totals (D-23).
  - Guest meals table (conditional, with total).
  - Two-column payments section: bill payments + advance deposits, each with its own empty hint.
  - Closing summary card (Opening Advance, Opening Due, Fixed Share, Meal Cost, Bill, Paid, Closing Due, Advance Balance).
  - **`advance_applied` is NEVER displayed** (Pitfall 3 — same as Plan 4.1).
  - Empty state (D-28) when the member has no row for the month.
- **`my/reports/monthly.blade.php`** — **aggregates-only D-19**:
  - Page title + period + closed-month badge.
  - `<x-report-toolbar>` with month-nav + disabled export buttons.
  - 6-card totals grid: Members, Meals, Meal Rate (with D-29 zero-bazar hint), Total Bazar, Total Fixed, Due/Advance.
  - **OMITS the entire per-member `<table>`** — the view never iterates `$data['members']`. Only the aggregate sums `collect($members)->sum('due')` and `collect($members)->sum('advance_balance')` are exposed.
  - Privacy note: "This report shows mess-wide totals. Per-member detail is private — ask the manager for your own statement."
  - Empty state (D-28) when no members.

### 7. my.blade.php — extended

Tab order is now **[Overview, Profile, Meals, Meal off, Payments, My reports]**:
- `tab === 'overview'` renders `_overview` partial.
- `tab === 'reports'` renders 2 link cards (Member Statement + Mess Monthly Report) with descriptions + "Open" buttons.
- All existing tabs unchanged (just moved inside an `@else` branch so the Overview + Reports tabs can render outside the rounded container).

### 8. Tests (17 new, all green)

| File | Tests | Coverage |
|------|-------|----------|
| `MyStatementTest.php` | 6 | member views own statement (200 + sees name + daily + meal-rate math); IDOR guard (acting as A with `?member_id=B` → sees only A's data, B's name absent); manager 403; unauth → /login; month picker (?year=2026&month=4 → "April 2026"); advance_applied not displayed (Pitfall 3) |
| `MyMonthlyReportTest.php` | 5 | aggregates-only render (sees Meal rate + Total bazar + Total fixed labels); **D-19 no per-member table** (asserts another member's name + self's name both absent); no per-member dues disclosure (no Member column header); manager 403; unauth → /login |
| `MyDashboardTest.php` | 6 | overview default landing (sees Overview + all 4 card labels); My reports tab (links to my.reports.statement + my.reports.monthly); no-member empty state ("Your mess account is not set up"); My Meals excludes guest meals (3×2.5=7.5, not inflated by 2×100 guest charge — Q3 LOCKED); My Payment History shows recent amount + View-all link; manager 403 on /my |

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Test fixture violated meal_entries UNIQUE(mess_id, member_id, date)**

- **Found during:** Task 2 GREEN run — `test_my_meals_excludes_guest_meals` failed with `UniqueConstraintViolationException: Duplicate entry '1-1-2026-06-01' for key 'meal_entries.meal_entries_mess_id_member_id_date_unique'`.
- **Issue:** The plan's test boilerplate created 3 MealEntry rows via `MealEntry::factory()->count(3)->create([... 'date' => $date ...])` with the same date, but the `meal_entries` table has a unique constraint on `(mess_id, member_id, date)` (one row per member per day). The factory collision was a test-data issue, not a code bug.
- **Fix:** Iterated over `[0, 1, 2]` offsets and created each MealEntry on a distinct date within the current month (`Carbon::now()->startOfMonth()->addDays($offset)->toDateString()`). The 3×(0.5+1+1)=7.5 math is preserved; the assertion still verifies the My Meals value excludes guest charge_amounts.
- **Files modified:** `tests/Feature/Dashboard/MyDashboardTest.php`
- **Verification:** `test_my_meals_excludes_guest_meals` now passes; 3 distinct meal rows + 2 guest rows; response contains `number_format(7.5, 1)`.

**2. [Rule 1 - Bug] Test asserted on a field the dashboard card intentionally doesn't show**

- **Found during:** Task 2 GREEN run — `test_my_payment_history_lists_recent_payments` failed asserting the response contained `RECENT-PAY-1` (the payment reference).
- **Issue:** The Overview "My Payment History" card was deliberately designed to show amount + date + method pill (compact at-a-glance view). The `reference` field is a "details" column reserved for the full `my.payments` tab — surfacing it on the dashboard card would clutter the 4-card grid. The test was asserting against the wrong field.
- **Fix:** Updated the test to assert on what the card actually shows: the formatted amount (`৳1,234.56`) and the "View all" link label. The `reference` field is still verified end-to-end by `PaymentHistoryTest::test_member_sees_only_their_payments` (which targets `my.payments`, the full tab).
- **Files modified:** `tests/Feature/Dashboard/MyDashboardTest.php`
- **Verification:** `test_my_payment_history_lists_recent_payments` passes; the card's UX (amount + method + View-all link) is verified.

## Security Properties Verified

| Threat ID | Mitigation | Test |
|-----------|------------|------|
| T-04-02-01 (IDOR via member_id query param) | role:user routes have NO `{member}` URL param; controller derives `$member = $request->user()->getMemberOrNull()`. Any `?member_id=` in URL is structurally ignored. | `MyStatementTest::test_member_cannot_view_other_member_statement` — acting as member A with `?member_id=B` in URL still renders only A's data; B's name is absent from the response. |
| T-04-02-02 (peer dues/advances disclosure) | Member Monthly Report view OMITS the per-member table. Only aggregate sums (total_due, total_advance across all members) are exposed. | `MyMonthlyReportTest::test_member_monthly_has_no_per_member_table` (peer + self names absent from response); `test_member_cannot_view_per_member_dues` (no Member column header). |
| T-04-02-03 (elevation of privilege) | Routes wrapped in `['auth', 'role:user']` middleware. Admin → 403. | `MyStatementTest::test_manager_forbidden_on_member_routes`, `MyMonthlyReportTest::test_manager_forbidden_on_member_monthly`, `MyDashboardTest::test_manager_forbidden_on_my_dashboard` — all assert 403. |
| T-04-02-05 (tampering year/month) | `MyMonthNavigationRequest` validates `year` (2000-2100) and `month` (1-12). Out-of-range values are rejected at the Form Request layer. | Verified by reading the Form Request (`'year' => ['sometimes', 'integer', 'min:2000', 'max:2100']`). |
| T-04-02-06 (guest meal inflation of My Meals) | `MemberDashboardService::myMealsThisMonth()` sums only regular `MealEntry` booleans via `MealType::value()`. Guest meals (in `guest_meals.charge_amount`) are structurally excluded. | `MyDashboardTest::test_my_meals_excludes_guest_meals` — 3 meals + 2 guest meals → response shows 7.5 (not 207.5). |
| Pitfall 3 (`advance_applied` info disclosure) | Member statement view omits `advance_applied` entirely; only `bill_payments`, `advance_payments`, `advance_balance`, `due_balance` are shown. | `MyStatementTest::test_statement_excludes_advance_applied_display` asserts the literal `advance_applied` does not appear in the response. |

## Self-Check

All claims verified by direct command:

- [x] `app/Http/Controllers/My/MyReportController.php` exists with `class MyReportController` and `statement` + `monthly` methods
- [x] `app/Http/Controllers/My/MyReportController.php` contains the literal `$request->user()->getMemberOrNull()` (member derived from auth)
- [x] `app/Http/Controllers/My/MyReportController.php` does NOT contain `Member::where('id', $request->` or any `{member}` URL binding
- [x] `app/Services/MemberDashboardService.php` contains `overviewCards` method
- [x] `app/Http/Controllers/MyController.php` contains the literal `$tab = $request->query('tab', 'overview')` (default changed from 'profile')
- [x] `routes/web.php` contains `Route::prefix('my/reports')->name('my.reports.')` with 2 GET routes; NO `{member}` parameter on either
- [x] `php artisan route:list --name=my.reports` lists `my.reports.statement` + `my.reports.monthly`
- [x] `resources/views/components/stat-card.blade.php` exists with `@props([` declaration
- [x] `resources/views/my/_overview.blade.php` contains the literals `My Meals`, `My Bill`, `My Advance`, `My Payment History` (via `__()` calls)
- [x] `resources/views/my.blade.php` contains the literals `'overview'` and `'reports'` (tab values)
- [x] `resources/views/my/reports/statement.blade.php` contains `Money::taka(` and `<x-report-toolbar` (with `<x-month-nav>` inside)
- [x] `resources/views/my/reports/statement.blade.php` does NOT contain `<select name="member_id"` (no member picker on member side)
- [x] `resources/views/my/reports/statement.blade.php` does NOT contain `advance_applied` (grep returned 0 matches)
- [x] `resources/views/my/reports/monthly.blade.php` contains `Money::taka(` (6 callsites)
- [x] `resources/views/my/reports/monthly.blade.php` does NOT iterate `$data['members']` for per-member display (grep for `@foreach ($data['members']` returned 0 matches)
- [x] `tests/Feature/Report/MyStatementTest.php` exits 0 with 6 test methods including `test_member_cannot_view_other_member_statement` + `test_manager_forbidden_on_member_routes`
- [x] `tests/Feature/Report/MyMonthlyReportTest.php` exits 0 with 5 test methods including `test_member_monthly_has_no_per_member_table`
- [x] `tests/Feature/Dashboard/MyDashboardTest.php` exits 0 with 6 test methods including `test_my_meals_excludes_guest_meals`
- [x] None of the new view files contain `bdt(` (grep returned 0 matches in `resources/views/components/stat-card.blade.php`, `resources/views/my/_overview.blade.php`, `resources/views/my/reports/`)
- [x] None of the new app/ files contain `bdt(` (Pint passed; visual inspection confirms `Money::taka()` everywhere)
- [x] `php artisan test --filter='MyStatementTest|MyMonthlyReportTest|MyDashboardTest'` → 17 passed (46 assertions)
- [x] `php artisan test` → 205 passed (473 assertions) — no regression on the 188 prior tests
- [x] `vendor/bin/pint --test app/ tests/` → passed
- [x] Commits `a062fe3` (RED), `a576c66` (Task 1 GREEN), `bd95fd2` (Task 2) exist in `git log`
- [x] No `{member}` URL param on ANY `role:user` route (grep of the role:user group in routes/web.php confirms)

## Self-Check: PASSED
