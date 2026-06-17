---
gsd_state_version: 1.0
milestone: v1.0
milestone_name: milestone
current_phase: 5
current_plan: Not started
status: Ready to plan
last_updated: "2026-06-17T21:33:35.020Z"
progress:
  total_phases: 5
  completed_phases: 3
  total_plans: 13
  completed_plans: 16
  percent: 100
---

# Project State

**Initialized:** 2026-06-16
**Project:** Devsroom Mess Management
**Current Phase:** 5
**Current Plan:** Not started

## Project Reference

See: `.planning/PROJECT.md` (updated 2026-06-16)

**Core value:** A mess manager can run a full month end-to-end on a phone — enter meals, log bazar, take payments, close the month, and produce a correct member bill — without spreadsheets and without arguing about who owes what.

**Current focus:** Phase 04 — reports-dashboard

## Phase Status

| Phase | Status | Goal | Plans | Started | Completed |
|-------|--------|------|-------|---------|-----------|
| 1. Foundation | Complete | Auth + mess config + schema + audit | 3 | 2026-06-16 | 2026-06-16 |
| 2. Members + Daily Operations | In progress | Member CRUD, meal grid, meal off, bazar, fixed expenses | 5 | 2026-06-17 | — |
| 3. Payments + Month-Close | In progress (3 of 4 plans done) | Payments, advance, close, notifications | 4 | 2026-06-17 | — |
| 4. Reports + Dashboard | Complete (4 of 4 plans done; awaiting verification) | 4 reports, dashboard cards/charts, PDF/Excel | 4 | 2026-06-17 | 2026-06-18 |
| 5. Polish + Pilot | Not started | Mobile UX, performance, documentation, real-mess pilot | 3 | — | — |

## Decisions Validated

- Laravel 13.15 + MySQL 8+ stack — VALIDATED in Phase 1
- Tyro Dashboard + Tyro Login for auth — VALIDATED in Phase 1
- Single mess in v1, `mess_id` on all tables for v2 readiness — VALIDATED
- `Asia/Dhaka` time zone — VALIDATED
- `__()` everywhere (English only shipped, Bengali-ready) — VALIDATED
- Decimal money (never float) — VALIDATED via migrations
- owen-it/laravel-auditing for domain audit log — VALIDATED
- Service layer (no Repository pattern) — to be validated in Phase 2+
- PHPUnit 12 (not Pest) — VALIDATED

## Decisions Validated in Phase 4 (so far)

- D-16 Member `/my` Overview landing shown first (4 DASH-04 cards before existing tabs) — VALIDATED in Plan 04.2 (test_member_sees_overview_landing_by_default; MyController default tab flipped 'profile' → 'overview')
- D-19 Member Monthly Report is aggregates-only (NO per-member table) — VALIDATED in Plan 04.2 (test_member_monthly_has_no_per_member_table + test_member_cannot_view_per_member_dues; my/reports/monthly.blade.php never iterates $data['members'] for display)
- D-32 "My reports" tab on /my links to own statement + aggregates-only monthly — VALIDATED in Plan 04.2 (test_member_sees_reports_tab; tab added to my.blade.php)
- Open Question #3 LOCKED: My Meals / Today's Meals EXCLUDE guest meals — VALIDATED in Plan 04.2 (test_my_meals_excludes_guest_meals; MemberDashboardService::myMealsThisMonth mirrors BillPreviewService::mealTotals via MealType::value(), not guest_meals.charge_amount)
- T-04-02-01 IDOR prevention: role:user routes have NO `{member}` URL param — VALIDATED in Plan 04.2 (test_member_cannot_view_other_member_statement; MyReportController::statement/monthly derive member from $request->user()->getMemberOrNull())

## Decisions Validated in Phase 4 (Plan 04-03)

- D-14 Manager /home is the real dashboard (6 stat cards + 3 Chart.js charts + pending-meal-off alert banner) — VALIDATED in Plan 04.3 (home.blade.php replaced the old link-card grid; ManagerDashboardTest::test_home_shows_all_6_card_labels + test_home_renders_chart_init_with_data + test_home_shows_pending_meal_off_banner_when_pending)
- D-15 6 DASH-01 stat cards (Total Members, Today's Meals, Current Meal Rate, Monthly Expenses, Total Due, Total Advance) + DASH-03 alert banner linking to mess.meal-off.index — VALIDATED in Plan 04.3
- D-17 Dashboard caching reuses bill-preview key + new composite `dash:counts:{mess_id}:{YYYY}-{MM}` key for count cards — VALIDATED in Plan 04.3 (DashboardService::managerCards; CacheInvalidationTest::test_dash_counts_key_is_mess_scoped verifies mess-scoped invalidation)
- DASH-05 < 2s refresh — VALIDATED in Plan 04.3 (AppServiceProvider::invalidateForModel extended IN-PLACE with one Cache::forget('dash:counts:...') line; same listener body fires for saved+deleted on all 5 models; no duplicate Event::listen)
- D-08 Range × granularity auto-bucket (≤60d→day, ≤365d→week, else→month) — VALIDATED in Plan 04.3 (ChartBucketingService::bucket; ChartRangeTest::test_autobucket_picks_daily_for_short_range + test_autobucket_picks_monthly_for_long_range)
- D-13 PDF portrait A4 branded, footer "Page N" via CSS counter(page) only (counter(pages) does NOT work in Dompdf) — VALIDATED in Plan 04.3 (resources/views/layouts/pdf.blade.php uses plain CSS + counter(page); Pitfall 4 mitigation)
- Pitfall 4 (Dompdf + Tailwind) — VALIDATED in Plan 04.3 (layouts/pdf.blade.php is plain CSS with inline `<style>`; no @vite, no Tailwind utilities)
- Pitfall 5 (Excel SUM returns text) — VALIDATED in Plan 04.3 (all 4 Excel exports use `(float)` cast + `WithColumnFormatting` + `NumberFormat::FORMAT_NUMBER_00` on Amount columns)
- T-04-03-01 cache cross-mess bleed — VALIDATED in Plan 04.3 (key ALWAYS scoped by Mess::activeId() in both DashboardService and AppServiceProvider extension)
- T-04-03-04 export filename path traversal / header injection — VALIDATED in Plan 04.3 (ReportExportController::safeFilename strips /, \, .. via regex with `~` delimiter; ExcelExportTest::test_filename_sanitized)
- T-04-03-05/06 IDOR + peer-dues disclosure on member exports — VALIDATED in Plan 04.3 (MyReportExportController has NO `{member}` URL param; monthlyExcel empties `members` array before passing to MonthlyReportExport — structural D-19 enforcement in DATA, not just view)
- T-04-03-08 cross-mess member in export — VALIDATED in Plan 04.3 (Member::where('id', $id)->firstOrFail() triggers MessScope → 404; PdfExportTest::test_cross_mess_member_pdf_returns_404)
- T-04-03-10 Dompdf SSRF via remote resources — VALIDATED in Plan 04.3 (every Pdf::loadView() chain sets setOption('isRemoteEnabled', false))

## Decisions Still Pending

(None — the two month-close decisions below were validated in Plan 03.4 and moved to the Phase 3 validated list.)

## Decisions Validated in Phase 3 (now including 03.4)

- Hard-lock on month close via `EnsureMonthIsOpen` middleware (Phase 3, Plan 3.4) — VALIDATED (test_payment_write_to_closed_month_is_rejected, test_closed_month_for_one_mess_does_not_lock_another)
- Idempotent month-close via `UNIQUE (mess_id, year, month)` + `firstOrCreate` + `wasRecentlyCreated` (Phase 3, Plan 3.4) — VALIDATED (test_idempotent_close_does_not_duplicate_rows, test_second_close_returns_same_closing_and_same_summaries)
- Corrections apply immediately to balances; `monthly_member_summaries` snapshot stays immutable (D-24, CLOSE-12) — VALIDATED (test_correction_does_not_mutate_existing_member_summary_snapshot)
- Close math reuses `BillPreviewService` verbatim (D-18) — VALIDATED (test_close_numbers_match_bill_preview_service_for_same_inputs)

## Decisions Validated in Phase 3 (so far)

- 1-hour cache TTL on current-month aggregates — VALIDATED in Plan 03.3 (`Cache::remember($key, now()->addHour(), …)`, single shared key `bill-preview:{mess_id}:{year}-{MM}`, manual invalidation via Eloquent `saved`/`deleted` events on MealEntry, MealOffRequest, GuestMeal, Expense, Payment per D-14/D-15)

## Blockers

None.

## Session Notes

**2026-06-16** — Project initialized.

- Codebase mapped (7 docs in `.planning/codebase/`)
- PROJECT.md created with full context and 14 adopted recommendations
- config.json created (YOLO mode, coarse granularity, parallel, balanced model profile)
- Research completed (4 docs + SUMMARY.md in `.planning/research/`)
- REQUIREMENTS.md created with 154 v1 requirements across 17 categories
- ROADMAP.md created with 5 phases, 18 plans total
- All artifacts committed to git

**2026-06-16** — Phase 1: planning artifacts complete.

- `01-DISCUSSION-LOG.md` + `01-CONTEXT.md` written (24 locked decisions)
- `01-RESEARCH.md` written (12 sections, 57KB — verifies Tyro 2FA needs 3 env keys, owen-it/laravel-auditing not yet installed, `config/app.php` hardcoded UTC, D-04 invite flow needs custom controller)
- `01-UI-SPEC.md` written and verified (Tailwind v4 + Blade contract, 6/6 dimensions PASS, committed as `afe9ad0`)
- Next: `/gsd-plan-phase 1` to break Phase 1 into executable PLAN.md files

**2026-06-17** — Phase 2: context gathered.

- `02-CONTEXT.md` written (24 locked decisions, 4 main + 4 follow-up + 4 deep-dive areas)
- `02-DISCUSSION-LOG.md` written (full audit trail of all 12 discussion areas)
- Key decisions: local photo storage + mobile-first camera UI, single "Save all" meal grid, deduct meal off on approval, single `room_or_seat` field, live AJAX member search, kind-on-category schema reconciliation, guest charge uses configured meal_value
- Next: `/gsd-plan-phase 2` to break Phase 2 into executable PLAN.md files

**2026-06-17** — Phase 3: context gathered.

- `03-CONTEXT.md` written (34 locked decisions across 7 areas: payment recording, advance balance math, live bill preview + caching, month-close job + corrections + notifications, manager close UX, audit + tests strategy, currency/date display)
- `03-DISCUSSION-LOG.md` written (full audit trail of all 7 discussion areas)
- Key decisions: single payment form with type toggle, separate due_balance column on advance_balances, mid-month joiners excluded from meal rate denominator, prorated fixed cost share by days, single shared cache key per (mess, year, month) with manual invalidation, firstOrCreate + unique index for idempotency, EnsureMonthIsOpen middleware for hard-lock, corrections apply immediately to balances (snapshot stays immutable), comprehensive MonthCloseService unit tests (10+ scenarios), bdt() helper + per-mess date_format
- One new migration: add `due_balance` column to `advance_balances` (all other schema already shipped in Phase 1)
- Resume file: `.planning/phases/03-payments-month-close/03-CONTEXT.md`
- Next: `/gsd-plan-phase 3` to break Phase 3 into executable PLAN.md files

**2026-06-17** — Phase 4: context gathered.

- `04-CONTEXT.md` written (34 locked decisions across 8 areas: charts, chart data semantics, exports, dashboard layout, filters & member visibility, member statement content, report data source, empty states, report nav & export perms)
- `04-DISCUSSION-LOG.md` written (full audit trail of all 8 discussion areas)
- Key decisions: Chart.js via Vite (line for Meal 30d + bar for Expense/Payment 6mo, fully-selectable range, auto-bucket by range, full responsive on mobile); Maatwebsite/Excel (.xlsx) + Dompdf PDF on all 4 reports (portrait A4 branded); manager `/home` becomes the dashboard (6 stat cards + 3 charts + pending-meal-off alert banner, nav → sidebar); member `/my` Overview landing (4 cards) + new "My reports" tab; reuse bill-preview cache key + targeted short-TTL keys for counts; GET + sticky query-string + This/Last-month presets on Expense/Payment reports; member Monthly Report = aggregates only (privacy); month picker ◀ ▶; member statement = full ledger with daily breakdown + rate math, full history; empty/first-run states = placeholder + hint; pre-first-close works off live compute (no gate); member can export own statement + aggregates-only monthly report (PDF + Excel)
- **⚠️ Pre-existing issue flagged:** `app/Services/BillPreviewService.php:92` has a leftover `throw new \RuntimeException('DBG:...')` from Phase 3.3 WIP that breaks bill preview — Phase 4 reports reuse this service, so this Phase 3.3 fix must land before Phase 4 reports work
- New dependencies Phase 4 will add: `chart.js` (npm), `maatwebsite/excel` + a Dompdf wrapper (composer)
- Resume file: `.planning/phases/04-reports-dashboard/04-CONTEXT.md`
- Next: `/gsd-plan-phase 4` to break Phase 4 into executable PLAN.md files

**2026-06-17** — Phase 3 Plan 03.3: Live bill preview + 1-hour cache — COMPLETE.

- Finalized a pre-existing implementation that had arrived as commit `7bc68f8` (single commit by prior session). All 11 plan tasks/must_haves verified satisfied by code inspection.
- Fixed 3 bugs in the pre-existing code that made every BillPreview* test fail with ParseError / RuntimeException: (a) stray `throw new \RuntimeException('DBG:…')` in `BillPreviewService::compute`, (b) malformed trailing `}erEnd) + 1; } }` causing `Unmatched ')'` parse error at line 312, (c) broken Blade tail `ion` after `@endsection` in `my.blade.php`.
- Fixed the test environment: switched `phpunit.xml` from `sqlite :memory:` (no `pdo_sqlite` extension available) to a dedicated `devsroom_mess_management_testing` MySQL database per the PROJECT.md "MySQL only" constraint, and added the missing `APP_KEY`. This unblocked the entire suite, not just 03.3.
- Corrected a wrong test assertion in `MyBillPreviewTest::test_member_without_mess_sees_placeholder` (was asserting "No data for this month yet" but the controller correctly returns the no-member screen for users without a Member record).
- Verified cache invalidation hooks wired in `AppServiceProvider::boot()` for all 5 models (MealEntry, MealOffRequest, GuestMeal, Expense, Payment) on both `saved` + `deleted` events (D-15). Verified cache key `bill-preview:{mess_id}:{year}-{MM}` and 1-hour TTL via `Cache::remember($key, now()->addHour(), …)` (D-14, PREVIEW-05).
- Tests: 03.3 suite 7/7 green (incl. the previously-failing `test_preview_computes_meal_rate`); full suite 116/116 green (was 0 runnable before the env fix); `vendor/bin/pint --test app/ tests/` clean.
- Commits: `b4ce6ee` (bug fixes), `35b42ae` (test env), `fe931b1` (pint + test fix). Docs commit pending.
- Resume file: `.planning/phases/03-payments-month-close/03.3-live-bill-preview-caching-SUMMARY.md`
- Next: Plan 03.4 — Month-close job (queued, idempotent, hard-locked) + corrections + notifications.

**2026-06-17** — Phase 3 Plan 03.4: Month-close job + corrections + notifications — COMPLETE (resumed after interruption).

- Resumed a ~50%-complete plan: the prior executor had committed the entire backend domain layer (8 commits: models w/ Auditable, NotificationType, NotificationService, MonthCloseService, MonthlyCorrectionService, CloseMonthJob, EnsureMonthIsOpen middleware + month.open alias applied to 11 routes, MonthClose/MonthlyClosing/MonthlyCorrection controllers + form requests, factories, migrations). Suite was green at 116 tests. This session did NOT redo any committed backend — it built the presentation + wiring + tests + docs on top.
- Committed this session: (1) NotificationController + NotificationBell component + bell blade; (2) DueReminderController + 9 views (close index/modal, closings index/show/member-summaries, corrections create/index, notifications index, due-reminder index) + sidebar links + `<x-notification-bell />` in layout + closed-month banner on /home + NOTIF-02 wired into MealOffApprovalService + NOTIF-03 wired into PaymentService; (3) 7 test files / 38 tests.
- Critical safety properties verified by test: idempotency (firstOrCreate + UNIQUE), close math == BillPreviewService math (byte-for-byte), mid-month joiner excluded from denominator, zero-meal member included in summary, positive net_bill → due_balance, negative → advance_balance, payment subtracted from snapshot net_bill, second close is a no-op, snapshot immutable after correction, close_complete notification to all managers + super-admins, mess isolation, bill-preview cache invalidated on close, payment write to closed month rejected (mess-scoped), corrections apply immediately + audit log written, member forbidden from corrections, due reminder skips clear members, notification index lists own + marks read, closed-month banner shows when current month is closed.
- Deviations: (a) corrections routes intentionally NOT locked by `month.open` (they target closed months by design — D-24/CLOSE-12); (b) test math corrected — the plan's example conflated meal_rate with bill; actual formulas used (`meal_rate = total_bazar / total_meals`, `bill = meals × rate`) with a dedicated parity test against BillPreviewService.
- Tests: 116 → 154 passed (+38 new); `vendor/bin/pint --test` clean.
- Commits this session: `4ba1e34` (notification controller + bell), `56400e4` (routes + views + layout + NOTIF wiring), `adf09fc` (test suite).
- Decisions validated: Hard-lock via EnsureMonthIsOpen (D-19) and Idempotent month-close via UNIQUE (D-18) moved Pending → Validated.
- Resume file: `.planning/phases/03-payments-month-close/03.4-month-close-job-corrections-notifications-SUMMARY.md`
- Next: Phase 03 is feature-complete (4/4 plans). Orchestrator owns phase-completion (VERIFICATION + marking phase done). Phase 4 (Reports + Dashboard) is the next phase.

**2026-06-17** — Phase 4 Plan 04.0: Wave 0 prerequisites — COMPLETE.

- Installed the 3 packages verified compatible with Laravel 13.15 in research: `barryvdh/laravel-dompdf` v3.1.2 (PDF), `maatwebsite/excel` 3.1.69 (.xlsx, bundles phpspreadsheet), `chart.js` 4.5.1 (npm, charts). All auto-discovered — no manual provider registration needed.
- Confirmed `App\Support\Money::taka()` is the canonical money helper for Phase 4 (used in 14 blade views). **Did NOT create `bdt()` or `app/helpers.php`** — resolves Gap 1 from research. `composer.json` keeps no `autoload.files` entry.
- Exposed `window.initDashboardChart(canvasId, config)` in `resources/js/app.js` with destroy-before-recreate guard (`if (el.__chart) el.__chart.destroy()`) to prevent the canvas memory leak (research Pitfall 2). Vite build: 252.98 KB JS bundle (88.22 KB gzipped), 731 ms.
- Added Reports sidebar group (D-31) between "Bill preview" and "Close month" — 4 sub-entries: Monthly Report, Member Statement, Expense Report, Payment Report. Same Tailwind classes, `min-h-[44px]` touch targets, `routeIs()` active highlighting as existing entries.
- Created `tests/Feature/Report/.gitkeep` + `tests/Feature/Dashboard/.gitkeep` for Plans 04-01/02/03.
- Verified `ext-zip` is loaded (Excel .xlsx runtime will not fail).
- Deviation [Rule 3 - Blocking]: initial sidebar implementation used literal `route('mess.reports.*')` calls which broke 32 existing tests (`RouteNotFoundException`) because the routes are defined in Plan 04-01. Threat model T-04-00-02 accepted this for users but the plan also requires "no regression". Fixed by wrapping the entire Reports group in `@if (Route::has('mess.reports.monthly'))`. Once Plan 04-01 registers the routes, the guard passes through automatically.
- Tests: 162 passed (no regression). `vendor/bin/pint --test app/ tests/` clean.
- Commits this session: `098ab7e` (packages + Chart.js bootstrap), `94ac982` (sidebar + test dirs), `ec63e6b` (Route::has guard fix).
- Decisions validated this plan: `Money::taka()` canonical (Gap 1 resolution); Chart.js bundled in global app.js (research A6).
- Resume file: `.planning/phases/04-reports-dashboard/04-00-SUMMARY.md`
- Next: Plan 04-01 (Wave 1 — Monthly + Member Statement + Expense + Payment report routes, controllers, views, services). When 04-01 lands, the sidebar Reports group will automatically become visible.

**2026-06-17** — Phase 4 Plan 04.2: Member self-view + member dashboard — COMPLETE.

- Built the member-side read surface: RPT-05 (own Member Statement) + RPT-06 (aggregates-only Monthly Report) + DASH-04 (member Overview landing with 4 cards). Member routes are `role:user` with NO `{member}` URL parameter — `MyReportController::statement()` + `::monthly()` derive `$member` from `$request->user()->getMemberOrNull()`. IDOR is structurally impossible (T-04-02-01).
- Reuses Plan 4.1's services verbatim — no report logic was re-implemented. `MyReportController` delegates to `MemberStatementService::forMember()` and `ReportService::monthlyReport()` unchanged.
- D-19 enforcement: the member Monthly Report reuses `ReportService::monthlyReport()` (which returns the full shape incl. members[]) but the member-side `monthly.blade.php` view OMITS the per-member table. Only aggregate sums (total_due, total_advance) are exposed. Verified by `test_member_monthly_has_no_per_member_table` + `test_member_cannot_view_per_member_dues`.
- New `MemberDashboardService::overviewCards(user)` produces the 4 DASH-04 cards: My Meals (this month, EXCLUDES guest meals per Open Question #3 LOCKED — mirrors `BillPreviewService::mealTotals()` via `MealType::value()`), My Bill (cached via BillPreviewService), My Advance (AdvanceBalance), recent 5 payments for the Payment History card.
- `<x-stat-card>` reusable dashboard card component (label/value/hint/icon) — will be reused by 04-03's manager dashboard.
- `MyController::index()` default tab flipped `'profile'` → `'overview'` (D-16). `my.blade.php` tab order is now [Overview, Profile, Meals, Meal off, Payments, My reports]. Overview renders `_overview` partial; My reports renders 2 link cards (Member Statement + Mess Monthly Report).
- 2 member statement/monthly views: full 8-section ledger (NO member picker, NO advance_applied display — Pitfall 3) + aggregates-only monthly.
- 17 new tests (6 MyStatement + 5 MyMonthlyReport + 6 MyDashboard) covering IDOR, role enforcement, month picker, D-19 no-per-member-table, Pitfall 3, My Meals excludes guest meals (Q3 LOCKED), no-member empty state.
- Deviations: (1) [Rule 1] test fixture violated meal_entries UNIQUE(mess_id, member_id, date) — fixed by using 3 distinct dates; (2) [Rule 1] test asserted on reference field that the dashboard card intentionally hides — fixed to assert on amount + View-all link.
- Tests: 188 → 205 passed (+17 new); `vendor/bin/pint --test` clean.
- Commits this session: `a062fe3` (RED tests), `a576c66` (controllers + service + routes + stubs), `bd95fd2` (full views + dashboard tests).
- Decisions validated this plan: D-16 (Overview landing first), D-19 (member monthly aggregates-only), D-32 (My reports tab), Open Question #3 LOCKED (My Meals excludes guest meals).
- Resume file: `.planning/phases/04-reports-dashboard/04-02-member-views-SUMMARY.md`
- Next: Plan 04-03 (Wave 4 — PDF/Excel exports for all 4 reports + manager dashboard with charts; reuses `<x-stat-card>` + wires the report-toolbar's disabled PDF/Excel buttons to real routes).

**2026-06-18** — Phase 4 Plan 04.3: Manager dashboard + PDF/Excel exports — COMPLETE (final wave of Phase 4).

- Transformed manager `/home` into the real dashboard (D-14): 6 DASH-01 `<x-stat-card>` cards + 3 Chart.js charts (Meal Trend = line, Expense Trend = bar bazar-only, Payment Trend = bar all-methods) + DASH-03 pending-meal-off alert banner. Replaces the old link-card grid. Chart init via the existing `window.initDashboardChart(canvasId, config)` helper from Plan 4.0 (destroy-before-recreate guard).
- Cache strategy (D-17): 3 bill-derived cards (`meal_rate`, `total_due`, `total_advance`) reuse the existing `bill-preview:{mess_id}:{YYYY}-{MM}` key via `BillPreviewService::preview()` (no new key); 3 count cards (`total_members`, `today_meals`, `monthly_expenses`) use ONE new composite key `dash:counts:{mess_id}:{YYYY}-{MM}` (1h TTL). `DashboardService::todayMealTotal` uses `MealType::value()` (never hard-coded 0.5/1/1, Pitfall A3) and EXCLUDES guest meals (Open Question #3 LOCKED). "Monthly Expenses" card = total bazar + fixed (Open Question #5 LOCKED). Expense Trend chart stays bazar-only (D-06).
- Cache invalidation (DASH-05): extended the EXISTING `AppServiceProvider::invalidateForModel()` listener body with ONE `Cache::forget("dash:counts:{mess_id}:{YYYY}-{MM}")` line. NO duplicate `Event::listen` block. Same hook fires for both `saved` + `deleted` events on all 5 models (MealEntry, GuestMeal, MealOffRequest, Expense, Payment). Key scoped by `Mess::activeId()` → cross-mess bleed impossible (T-04-03-01). Preserves < 2s refresh (success #12).
- D-08 auto-bucketing centralized in `ChartBucketingService::bucket(from, to)`: ≤60d → daily; ≤365d → weekly; else → monthly. Applied to all 3 trend queries via a `fillBucketAxis()` helper.
- Exports (RPT-07 PDF, RPT-08 Excel) on all 4 reports × both sides. 12 new routes: 8 manager (`.pdf` + `.xlsx` for monthly / member-statement / expenses / payments) + 4 member (statement + monthly). PDF uses Dompdf via a dedicated plain-CSS `layouts/pdf.blade.php` (Pitfall 4 — NO Tailwind, NO `@vite`; `@page` margins + `position: fixed` header/footer + `counter(page)` → "Page N", D-13, NOT `counter(pages)` which doesn't work in Dompdf). Every `Pdf::loadView()` sets `setOption('isRemoteEnabled', false)` (T-04-03-10 SSRF prevention). Excel uses Maatwebsite/Excel: 4 export classes (`MonthlyReportExport` FromArray, `MemberStatementExport` FromCollection, `ExpenseReportExport` + `PaymentReportExport` FromQuery — chunked, T-04-03-03 DoS mitigation). All `map()`/`array()` return explicit `(float)` casts + `WithColumnFormatting` + `NumberFormat::FORMAT_NUMBER_00` on Amount columns (Pitfall 5 — manager can SUM/AVERAGE).
- Filename sanitization via `ReportExportController::safeFilename()` (T-04-03-04): regex with `~` delimiter strips `/`, `\`, `..`, control chars. Prevents path traversal + header injection in `Content-Disposition`. Member names also pass through `Str::slug()`.
- Member exports are IDOR-structurally-impossible (T-04-03-05/06): `MyReportExportController` has NO `{member}` URL param — member ALWAYS from `$request->user()->getMemberOrNull()`. `monthlyExcel` passes `['members' => []]` to `MonthlyReportExport` — peer rows can NEVER leave the server for a member request (D-19 enforced in DATA shape, not just view). The member Monthly PDF view also omits the per-member table.
- Cross-mess member export returns 404 (T-04-03-08): `Member::where('id', $id)->firstOrFail()` triggers the `MessScope` global scope.
- `<x-report-toolbar>` now renders real `<a>` links to `route({$route}.pdf)` / `route({$route}.xlsx)` (was disabled placeholders in Plan 4.1/4.2). `Route::has($pdfRoute)` guard degrades gracefully.
- Deviations: (1) [Rule 1] `safeFilename` regex delimiter conflicted with the slash literal it was matching — switched delimiter `/` → `~`. (2) [Rule 1] test assertions too strict on Dompdf / Maatwebsite filename quoting (RFC-6266 allows unquoted form) — relaxed to assert sanitized basename substring + `.xlsx` suffix.
- Tests: 205 → 233 passed (+28 new: 6 ManagerDashboard + 4 ChartRange + 3 CacheInvalidation + 9 PdfExport + 6 ExcelExport); `vendor/bin/pint --test` clean.
- Commits this session: `1d87e00` (RED tests for dashboard), `e10d70a` (DashboardService + ChartBucketingService + HomeController + cache hook + home view), `b04afe6` (4 Excel exports + plain-CSS PDF layout + 6 PDF views + report-toolbar wiring), `281b2d3` (RED tests for exports), `7779943` (ReportExportController + MyReportExportController + 12 routes + safeFilename).
- Decisions validated this plan: D-14 (dashboard), D-15 (6 cards + banner), D-17 (cache reuse), DASH-05 (< 2s refresh), D-08 (auto-bucket), D-13 (portrait PDF Page N), Pitfall 4 (plain CSS), Pitfall 5 (numeric Excel), T-04-03-01/04/05/06/08/10.
- Resume file: `.planning/phases/04-reports-dashboard/04-03-dashboard-exports-SUMMARY.md`
- Next: Phase 4 is feature-complete (4/4 plans). Orchestrator owns phase-completion (VERIFICATION + marking phase done). Phase 5 (Polish + Pilot) is the next phase.

**2026-06-17** — Phase 4 Plan 04.1: Manager reports (Monthly + Member Statement + Expense + Payment) — COMPLETE.

- Built the 4 manager-side reports (RPT-01..RPT-04) as HTML-only (PDF/Excel exports deferred to Plan 04-03 — the `<x-report-toolbar>` has disabled placeholder buttons for them).
- Created `app/Services/ReportService.php` with the D-26 closed/open switch centralized: `MonthlyClosing` lookup → snapshot path (reads `MonthlyMemberSummary`, maps columns to `BillPreviewService::preview()` shape verbatim, tags `'source' => 'snapshot'`); absence → live compute via `BillPreviewService::preview()` (tags `'source' => 'live'`). Also exposes `expenseReport(filters)` + `paymentReport(filters)` paginated (50/page) filtered queries.
- Created `app/Services/MemberStatementService.php` wrapping `BillPreviewService::forMember()` + adding the daily meal breakdown (D-23, `MealEntry` B/L/D booleans → `MealType::value` sum), guest meals, payments, D-24 period label ("As of today" vs "{Month Year}"), `is_closed`, `source`.
- 4 Form Requests under `app/Http/Requests/Report/` (MonthNavigation, MemberStatement, ExpenseReport, PaymentReport). PaymentReportRequest's `method` enum sourced from `App\Support\PaymentMethod::ALL` — single source of truth matches the migration's stored values.
- `<x-month-nav>` component (D-20 — ◀ Month ▶ + dropdown of last 24 months + This-month link), mirroring `<x-mess-date-nav>` structure. Carries an `extra` prop so member_id stays sticky across month navigation on the Member Statement.
- `<x-report-toolbar>` with month-nav + disabled PDF/Excel placeholders.
- 4 views: `monthly.blade.php` (totals grid with D-29 zero-bazar hint + per-member table + closed-month badge + D-28 empty state), `member-statement.blade.php` (D-25 meal-rate math + daily breakdown + payments split into bill payments vs advance deposits + closing summary; **`advance_applied` is NEVER displayed — Pitfall 3**), `expenses.blade.php` + `_filters/expenses.blade.php`, `payments.blade.php` + `_filters/payments.blade.php`. All money via `App\Support\Money::taka()` (no `bdt()`).
- 4 routes inside `role:admin` + `EnsureMessExists`: `mess.reports.{monthly,member-statement,expenses,payments}`. Cross-mess member access → 404 via `Member::where('id', $id)->firstOrFail()` + MessScope.
- 26 new feature tests (6 + 5 + 7 + 8) covering role enforcement (admin/user/guest), D-26 snapshot path, cross-mess 404, advance_applied pitfall guard, sticky filters in URL, totals math (100+200.50+300.25 = ৳600.75), presets, empty states.
- Deviations: (1) [Rule 1] Login route is named `tyro-login.login` (not `login`) — guest-redirect tests use the path `/login` directly, matching `tests/Feature/Auth/RouteAccessTest.php`. (2) [Rule 1] `PaymentReportTest::test_member_filter` initially asserted `assertDontSee('Beta Member')` but Beta legitimately appears in the filter dropdown; changed to assert on the data table + totals (Alpha's ৳100.00 appears, Beta's ৳200.00 doesn't, combined ৳300.00 doesn't).
- Tests: 162 → 188 passed (+26 new); `vendor/bin/pint --test app/ tests/` clean.
- Commits this session: `e22113e` (services + form requests + month-nav + Monthly/MemberStatement tests), `300eadc` (controller + routes + 4 views + filter partials + report-toolbar), `6d7d45b` (Expense/Payment report tests).
- Decisions validated this plan: D-26 closed/open switch (MonthlyClosing snapshot vs BillPreviewService live compute); MonthlyMemberSummary.advance_applied column surfaced as bill_payments (Pitfall 3); closed-month badge next to period label.
- Resume file: `.planning/phases/04-reports-dashboard/04-01-manager-reports-SUMMARY.md`
- Next: Plan 04-02 (member views — reuses ReportService + MemberStatementService; member routes derive member_id from session, not URL).

## Open Questions for User

(Asked in initial questioning, captured in PROJECT.md):

- None — all answered during initial questioning.

(To surface during planning):

- Actual MySQL credentials for dev environment (per taste preference: verify with user)
- Specific Tyro roles to use (`admin`, `manager`, `member` vs. `super-admin`, `manager`, `user`)
- Bengali font choice for v2 (if/when v2 starts)
- Real mess for pilot (Phase 5)

3 (config + audit + invite + onboarding): custom app layout with sidebar + mobile drawer; MessConfigController + form; AuditController + paginated log; MemberInviteController + SetPasswordController + Mailable; OnboardingController + form; EnsureMessExists middleware; Tyro resources for messes + settings.

- 38 tests pass (3 in Plan 1.1, 12 in Plan 1.2, 23 in Plan 1.3); pint clean.
- Commits: `90e3135`, `81e7fe9`, `542559d`, `69703c6`; phase marked complete; ready for Phase 2.

## Open Questions for User

(Asked in initial questioning, captured in PROJECT.md):

- None — all answered during initial questioning.

(To surface during planning):

- Actual MySQL credentials for dev environment — RESOLVED (used .env from previous session; password `125524`)
- Specific Tyro roles to use — RESOLVED (`super-admin`, `admin`, `user` verbatim)
- Bengali font choice for v2 (if/when v2 starts)
- Real mess for pilot (Phase 5)
