---
gsd_state_version: 1.0
milestone: v1.0
milestone_name: milestone
current_phase: 06
current_plan: 4
status: Phase 06 verified passed (4/4 plans; 3 review criticals fixed inline)
last_updated: "2026-07-12T14:16:08.000Z"
progress:
  total_phases: 6
  completed_phases: 4
  total_plans: 20
  completed_plans: 22
  percent: 100
---

# Project State

**Initialized:** 2026-06-16
**Project:** Devsroom Mess Management
**Current Phase:** 06
**Current Plan:** 4 (final plan of Phase 06 — complete)
**Last activity:** 2026-07-12 - Completed quick task 260712-s5g: Member delete, slug URLs, multi-channel notifications, duplicate prevention, role-based sidebar, app audit, README

## Project Reference

See: `.planning/PROJECT.md` (updated 2026-06-16)

**Core value:** A mess manager can run a full month end-to-end on a phone — enter meals, log bazar, take payments, close the month, and produce a correct member bill — without spreadsheets and without arguing about who owes what.

**Current focus:** Phase 06 — backup-and-restore-system

## Phase Status

| Phase | Status | Goal | Plans | Started | Completed |
|-------|--------|------|-------|---------|-----------|
| 1. Foundation | Complete | Auth + mess config + schema + audit | 3 | 2026-06-16 | 2026-06-16 |
| 2. Members + Daily Operations | In progress | Member CRUD, meal grid, meal off, bazar, fixed expenses | 5 | 2026-06-17 | — |
| 3. Payments + Month-Close | In progress (3 of 4 plans done) | Payments, advance, close, notifications | 4 | 2026-06-17 | — |
| 4. Reports + Dashboard | Complete (4 of 4 plans done; awaiting verification) | 4 reports, dashboard cards/charts, PDF/Excel | 4 | 2026-06-17 | 2026-06-18 |
| 5. Polish + Pilot | In progress (2 of 3 plans done) | Mobile UX, performance, documentation, real-mess pilot | 3 | 2026-06-18 | — |
| 6. Backup and restore system | Complete (4/4 plans; verified passed — 3 review criticals fixed inline; 6 prod human-verification items pending) | Backup and restore system | 4 | 2026-06-19 | 2026-06-19 |

## Accumulated Context

### Roadmap Evolution

- Phase 6 added: Backup and restore system

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

## Quick Tasks Completed

| # | Description | Date | Commit | Directory |
|---|-------------|------|--------|-----------|
| 260712-s5g | Member delete, slug URLs, multi-channel notifications, duplicate prevention, role-based sidebar, app audit, README | 2026-07-12 | 916369d | [260712-s5g-member-delete-slug-urls-multi-channel-no](./quick/260712-s5g-member-delete-slug-urls-multi-channel-no/) |

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

**2026-06-18** — Phase 5: context gathered.

- `05-CONTEXT.md` written (23 locked decisions across 5 areas: pilot logistics, performance & N+1 tooling, mobile polish depth, docs + deployment, mechanical audits) + `05-DISCUSSION-LOG.md` (full audit trail of all 4 discussion areas, 15 questions).
- Key decisions: pilot = dev's own/known mess, fresh-start current month (no importer), hybrid onboarding (dev configures, manager runs daily), success = one clean month-close + members see bills; perf = Debugbar + Telescope (dev-only) + reproducible ~50-member seeder (doubles as demo dataset) + manual measurement into `05-VERIFICATION.md` + Debugbar cache tab for hit-rate, budgets are a hard gate; mobile = DevTools audit now + real device in pilot, audit + meal-grid touch/density pass, 360px practical floor; docs/deploy = VPS (Forge/manual) for the queued close worker, full README + demo creds, AGENTS.md refresh + hand-written domain walkthrough, DEPLOYMENT.md + fix the `.env` sqlite→MySQL parity.
- Audit findings flagged for the phase: `config/app.php` timezone defaults to UTC with no `APP_TIMEZONE` in `.env` (re-verify `Asia/Dhaka`, D-21); live `.env` runs sqlite (parity fix, D-18); `BillPreviewService` debug-throw (verify removed before perf work).
- Resume file: `.planning/phases/05-polish-pilot/05-CONTEXT.md`
- Next: `/gsd-plan-phase 5` to break Phase 5 into executable PLAN.md files

**2026-06-18** — Phase 5 Plan 05.02: Mobile UX polish + perf audit + coverage measurement — COMPLETE (Wave 2; resumed mid-plan after Task 1 by prior agent).

- **Task 1 (mobile UX audit + touch-target pass)** shipped by prior executor in commit `5e40bb1`: audited all 4 manager daily-ops trees (meals/bazar/expenses/payments) at 320/375/768/1024; added the `touch-target` utility to resources/css/app.css; fixed 11 missing touch-target sites in the payments tree (index filter, create/edit buttons, _form inputs); wrote 05-VERIFICATION.md §1 (Mobile Responsive Audit, subsections 1.1–1.6). Live-browser visual confirmation deferred to Plan 05-03 HUMAN-UAT #3. 234 tests green.
- **Task 2 (4 perf budgets + N+1 lock)** completed by this resumed executor: committed the prior agent's untracked `tests/Feature/Perf/MealGridQueryCountTest.php` (`f7543ce`) — 2/2 OK, locks `whereIn('member_id', ...)` N+1-safety at 50 members + dashboard no-N+1-pattern at 50 members. Measured all 4 D-10 HARD budgets PROGRAMMATICALLY (CLI executor cannot eyeball DevTools — methodology documented in §2): grid 1.25ms (3 queries, target <100ms) PASS, dashboard 0.31ms (2 queries, target <500ms) PASS, close 0.12s (target <30s) PASS, cache 100.0% (10/10 hits, target >80%) PASS. All 4 PASS with strong margins — NO service code modified, NO budget relaxed (D-10 honored). Recorded in §2. Close-month measurement was rolled back so the dev DB stays clean for HUMAN-UAT (closings=0, summaries=0, members=50, meals=882).
- **Task 3 (coverage >70% + targeted gap-fill)** completed: re-verified pcov 1.0.12 loaded (NO N/A escape hatch per the hard rule). Baseline `vendor/bin/phpunit --coverage-text` = 85.55% Lines (2114/2471) — already +15.55pp above target. Targeted gap-fill: wrote `tests/Unit/BillPreviewInvalidatorTest.php` (`b8eef5c`, 7 tests / 10 assertions) — lifts BillPreviewInvalidator from 54.55% → 100% Lines by locking all 4 cache-invalidation branches. Final: 85.75% Lines (2119/2471). Remaining gaps documented as a bounded boot/glue list (6 entries: ExpenseCategoryService, Telescope/AppService providers, ExpenseCategory/MonthlyClosing controllers, EnsureMonthIsOpen) — NOT a PHASE SPLIT.
- Deviations: [Rule 3 — Blocking] PerfDemoSeeder's manager@demo.test already had the admin role assigned → wrapped assignment in `! $admin->roles()->exists()` guard (in the local measurement script only, deleted after use). [Rule 3 — Blocking] Measurement script's first 2 attempts failed (used `$this` outside object context + Collision masked the real error) → rewrote to measure the service layer directly instead of going through the HTTP kernel; methodologically equivalent (service call IS the per-request DB work).
- Tests: 234 (Plan 01 end) → 243 (+7 from BillPreviewInvalidatorTest, +2 from MealGridQueryCountTest); `vendor/bin/pint --test tests/Feature/Perf/ tests/Unit/` clean.
- Commits this plan (this resumed executor): `f7543ce` (Task 2 query-count smoke test), `3a77c92` (Task 2 perf budgets §2), `b8eef5c` (Task 3 BillPreviewInvalidator gap-fill), `b4d101e` (Task 3 coverage §3). Plus `5e40bb1` from the prior Task 1 agent.
- Decisions validated this plan: D-08 (manual perf measurement) — recorded in §2; D-09 (cache hit-rate >80%) — measured 100%; D-10 (HARD gate) — all 4 budgets PASS, no relaxation; D-11/D-12/D-13/D-14 (mobile UX) — Task 1; D-22 (coverage >70%) — measured 85.75% via pcov, no N/A escape hatch.
- Resume file: `.planning/phases/05-polish-pilot/05-02-mobile-perf-coverage-SUMMARY.md`
- Next: Plan 05-03 — README rewrite + AGENTS.md Domain Walkthrough + DEPLOYMENT.md prod checklist + clear 4 Phase 4 HUMAN-UAT items + run the one-mess pilot (human/manual, autonomous: false) — v1 milestone ship. Plan 03 inherits the deferred live-browser Debugbar/Telescope visual cross-check (HUMAN-UAT #3).

**2026-06-18** — Phase 5 Plan 05.01: Mechanical tooling + PerfDemoSeeder — COMPLETE (Wave 1 unblocker).

- The keystone Wave 1 plan is done. Plan 02 (perf measurement + coverage measurement) and Plan 03 (README + AGENTS.md + DEPLOYMENT.md + pilot) are now FULLY unblocked — including the previously-escape-hatched D-22 / success #9 (coverage measurement).
- **Task 1** closed 4 mechanical audits: D-21 (APP_TIMEZONE=Asia/Dhaka in .env + .env.example; config/app.php unchanged), D-18 (dev .env sqlite→MySQL with full MySQL block + actual dev creds; phpunit.xml was already MySQL), D-19 (`vendor/bin/pint --test` exits 0; Pint bumped 1.29.1→1.29.3), D-20 (`__()` scan: 0 literals needed wrapping in app views; bn.json deferred to v2). Wrote `.planning/phases/05-polish-pilot/05-MECHANICAL-AUDIT.md`.
- **Task 2** installed `barryvdh/laravel-debugbar:^4.3` + `laravel/telescope:^5.20` in `require-dev` with the three-layer prod gate (1: require-dev so `composer install --no-dev` on prod can't load them; 2: telescope `enabled => env('TELESCOPE_ENABLED', fn() => app()->environment('local'))` + `.env.example TELESCOPE_ENABLED=false + DEBUGBAR_ENABLED=false`; 3: `TelescopeServiceProvider::gate()` = `Gate::define('viewTelescope', super-admin only)`). telescope:prune scheduled daily in `routes/console.php` (class_exists guard). Debugbar `except: ['*.pdf', '*.xlsx', 'api/*']` + `ajax_handler_enable_tab=false` + `ajax_handler_auto_show=false`.
- **T-05-01-04 mitigation ENFORCED end-to-end** (not just configured): wrote `tests/Feature/Report/PdfDebugbarExclusionTest.php` — hits `/mess/reports/monthly.pdf` with Debugbar explicitly enabled, asserts status=200, content-type=application/pdf, body starts with `%PDF`, no `debugbar`/`phpdebugbar`/`<script>phpdebugbar` string anywhere. 10 assertions pass; locks the exclude rule as a regression gate.
- **Task 3** built `database/seeders/PerfDemoSeeder.php` — Demo Mess + 48 active + 1 former + 1 inactive members (50 total, deterministic count), 882 meal entries (49 members × 18 days), 54-90 bazar + 6 fixed expenses, ~25-35 payments. Uses `WithoutModelEvents` + `config(['audit.enabled' => false])` (Pitfall 6); completes in ~2.7s (<<30s budget). Demo creds: `manager@demo.test` (admin) + `member@demo.test` (user), password `"password"` — feeds Plan 03's README. Schema correctness verified against migrations: `purchased_by` (snake_case) + `expense_categories.kind` (kind lives on the category, not the expense — `drop_expense_type_from_expenses` removed it). Tyro roles created via `Role::firstOrCreate(['slug'=>...])` (assignRole takes a Role object, not a string). `DatabaseSeeder` guarded (does NOT call PerfDemoSeeder). `app/Console/Commands/SeedPerfDemo.php` wraps with `app()->isProduction()` guard. Determinism verified: Member count = 50 → 50 across two runs; other counts within stated ranges.
- **Task 4** installed pcov 1.0.12 (preferred over xdebug per Pitfall 3 — purpose-built for line coverage, no slowdown on normal runs). Downloaded `php_pcov-1.0.12-8.4-ts-vs17-x64.zip` from `https://windows.php.net/downloads/pecl/releases/pcov/1.0.12/` (exact-match DLL for PHP 8.4.15 ZTS x64 VS17 — resolves Assumption A2 positively). Dropped `php_pcov.dll` in `C:\Program Files\php-8.4.15\ext\`; appended `[pcov] extension=pcov pcov.enabled=1` to the loaded `php.ini`. Verified: `php -m` lists `pcov`; `php --ri pcov` reports enabled v1.0.12. **Baseline coverage: Lines 85.55%** (2114/2471), Methods 67.75%, Classes 46.96% — already above the >70% target. Pitfall 3 sanity check: 12.9s → 16.4s with --coverage (~27% slowdown only when coverage is explicitly requested; normal runs unaffected). **PHASE SPLIT NOT triggered** — pcov installed cleanly on first try.
- Deviations: [Rule 1] plan's seeder example used `purchasedBy` (camelCase, wrong) + `Expense kind=BAZAR` (column dropped in migration); fixed to `purchased_by` + filter `expense_categories` by kind. [Rule 1] `assignRole('admin')` string form is wrong for Tyro — fixed to pass Role object. [Rule 2] T-05-01-04 mitigation needed durable enforcement — added regression test. [Rule 3] `storage/debugbar/` was appearing untracked — added to .gitignore.
- Tests: 233 → 234 passed (+1 for the T-05-01-04 regression test); `vendor/bin/pint --test` clean.
- Commits this plan: `8d9b563` (Task 1 mechanical audits), `aaab1e4` (Task 2 Debugbar+Telescope install), `9fbd05b` (T-05-01-04 regression test), `4a0d5f7` (Task 3 PerfDemoSeeder + SeedPerfDemo), `bf8a109` (.gitignore storage/debugbar), `eb981e0` (Task 4 pcov install audit doc).
- Decisions validated this plan: D-06 (Debugbar+Telescope require-dev three-layer gate), D-07 (PerfDemoSeeder 50 members <3s deterministic), D-18 (dev .env sqlite→MySQL), D-19 (Pint clean), D-20 (__() scan clean), D-21 (Asia/Dhaka everywhere via .env), D-22 PREREQUISITE (pcov loaded, baseline 85.55% lines).
- Resume file: `.planning/phases/05-polish-pilot/05-01-mechanical-tooling-seeder-SUMMARY.md`
- Next: Plan 05.02 — performance audit (Debugbar measurement against the 4 budgets: grid <100ms, dashboard <500ms, close <30s, cache hit >80%) + coverage measurement + targeted fill against the 85.55% baseline. The seeder + Debugbar/Telescope + pcov all landed here, so Plan 02 has no escape hatch.

**2026-06-19** — Phase 6: context gathered (backup & restore). Note: gathered AHEAD of Phase 5 completion (Phase 5 Plan 05-03 pilot still pending); current_phase stays 05.

- `06-CONTEXT.md` written (8 locked decisions D-01..D-08 across 5 areas: backup engine & mechanism, destination & retention, restore surface & validation, schedule/triggers/failure handling, test strategy) + `06-DISCUSSION-LOG.md` (full audit trail of 4 discussion areas + 2 follow-ups).
- Key decisions: `spatie/laravel-backup` as the engine (mysqldump + files zip, scheduled, off-site push, cleanup, failure notification); destination = S3-compatible object storage (DigitalOcean Spaces default), DB + uploaded files, daily 14d + monthly 12mo retention; **super-admin dashboard UI with a guarded one-click FULL RESTORE** (type-the-mess-name + role gate + auto maintenance mode — user chose option A over the recommended "restore stays CLI"); **periodic restore-test job** (scratch MySQL DB, row-count assertions, health badge — user explicitly kept this); schedule = nightly via existing Laravel scheduler + on-demand + post-`CloseMonthJob` listener + notify-on-failure.
- ⚠️ Critical planner note (D-06): `spatie/laravel-backup` ships NO restore command — the guarded full-restore UI + restore-test are bespoke application code (unzip + `mysql` import + file copy), not spatie features.
- Scope: Phase 6 is a **post-v1 hardening phase**, NOT in `REQUIREMENTS.md` (no REQ-xxx to map; success criteria defined in planning). Builds on Phase 5's locked VPS + Forge + supervisor + MySQL deploy target.
- Resume file: `.planning/phases/06-backup-and-restore-system/06-CONTEXT.md`
- Next: `/gsd-plan-phase 6` to break Phase 6 into executable PLAN.md files (or finish Phase 5 Plan 05-03 pilot first — Phase 6 depends on Phase 5).

**2026-06-19** — Phase 6 Plan 06.01: Backup foundation (config + DO Spaces disk + DUMP_BINARY_PATH + smoke doc) — COMPLETE.

- Plan 06-01 = engine + plumbing only. No restore code (D-06 — spatie is backup-only by design); restore + restore-test + UI land in Plans 06-02/03/04.
- **Task 1**: `composer require spatie/laravel-backup:^10.0 league/flysystem-aws-s3-v3:^3.0` resolved cleanly to spatie **10.3.0** + flysystem-aws-s3-v3 3.x — research Assumption A1 held, no v9 fallback needed. Published + authored `config/backup.php`: `source.files.include=[storage/app/public)]`, `exclude` lists `base_path('.env')` (D-07), `follow_links=false` (Pitfall 4), `destination.disks=[env('BACKUP_DISK','backups')]`, retention keep_daily=14/keep_monthly=12 (D-02), 5000 MB growth guard (T-06-01-04), AES-256 zip encryption optional via `BACKUP_ARCHIVE_PASSWORD`. Verified via tinker: `config('backup.backup.destination.disks')=["backups"]`, `keep_monthly_backups_for_months=12`.
- **Task 2**: Added dedicated `backups` s3 disk (DO Spaces — 5 `DO_SPACES_*` env keys, `throw=true`, separate from the general-purpose `s3` disk); `dump` block inside `mysql` connection (`DUMP_BINARY_PATH`, `use_single_transaction=true`, `--quick --single-transaction` — Pitfall 2); NEW `mysql_restore_test` connection byte-identical to `mysql` except database name (for Plan 06-02 RestoreTestService). `.env.example` + dev `.env` synced with Phase 6 blocks (empty dev values — no real DO secrets). `DB::connection()->getPdo()` = OK via tinker.
- **Task 3**: Wrote `.planning/phases/06-backup-and-restore-system/06-01-SMOKE.md` (~135 lines): what shipped, prod validation steps, first-real-backup runbook, Windows-dev incompat note (spatie v10 docs verbatim), DO Spaces provisioning checklist, Plan 06-02 hand-off, known deviations.
- **Deviations [Rule 1 — Bug]**: (1) spatie v10 renamed several config keys vs the plan's v9-based research — `relative_root`→`relative_path`, `zip.encryption_password/method`→flat `password`+`encryption` (string enum), `database_dump_filename_base` requires `'database'`/`'connection'` enum (NOT `'db'`). Rewrote `config/backup.php` to mirror v10's published structure. (2) `notifications.mail.to` empty-string-vs-null quirk — Laravel's `env()` returns `''` for `KEY=` (not null), short-circuiting the default; spatie v10 strictly validates emails at config-load. Wrapped the notifications block in a runtime closure that coerces empty→null. Verified via tinker: `backup.notifications.mail.to=hello@example.com`.
- **Out-of-scope [logged to deferred-items.md]**: `php artisan config:cache` is pre-existing-broken (tyro-login's `redirects.after_login` Closure is non-serializable). Reproduced on a clean `git stash` of commit `c6dcc9c` (before this plan). Not introduced by 06-01; dev workflow uses `config:clear` per Plan 05-01 convention.
- Tests: 243 passed / 576 assertions (NO regression — spatie auto-discovery clean); `vendor/bin/pint --test config/` clean.
- Commits this plan: `ed76c31` (Task 1 — composer deps + config/backup.php), `0695661` (Task 2 — backups disk + mysql_restore_test + DUMP_BINARY_PATH + .env.example + v10 mail fallback fix), `c039eab` (Task 3 — smoke doc).
- Decisions implemented this plan: D-01 (spatie engine), D-02 (DO Spaces + 14d/12mo retention), D-06 (restore deferred to Plan 06-02), D-07 (.env excluded). D-03/D-04/D-05 land in 06-02/03/04.
- Resume file: `.planning/phases/06-backup-and-restore-system/06-01-foundation-SUMMARY.md`
- Next: Plan 06-02 (Wave 2) — `BackupRestoreService` + `RestoreTestService` + `backup:restore-test` command + `routes/console.php` schedule + post-`CloseMonthJob` listener + tests (mocked heavy processes per D-08).

**2026-06-19** — Phase 6 Plan 06.02: Backend restore services + restore-test + schedule + listeners — COMPLETE.

- Plan 06-02 = the bespoke backend for Phase 6. No UI in this plan (Plan 06-03 owns the controller + Blade view); this plan ships the service layer + listeners + schedule that 06-03 surfaces.
- **Task 1** (TDD RED→GREEN): `BackupPathResolver` (Finder-based recursive `db-dumps/*.sql` locator — **deviation: switched from PHP `glob()` because it does NOT walk `**` recursively**), `BackupRestoreService` (down → queue:restart → try { downloadAndExtract, locateSqlDump, restoreDatabase, restoreFiles, verifyRestore } → finally { up + cleanup }; restoreDatabase uses Symfony Process ARRAY args per Pattern 4a; restoreFiles copies into `storage_path('app/public')` NEVER `public_path('storage')` per Pitfall 4; public `buildMysqlProcess()` test seam), `RestoreTestService` (per-table COUNT(*) on mysql vs `mysql_restore_test` against 17 hard-coded domain tables — NO `information_schema` InnoDB estimate), `restore_tests` migration (NO mess_id — cross-mess infrastructure) + model + factory. 14 tests GREEN.
- **Task 2** (TDD RED→GREEN): `CloseMonthJob::after()` + `failed()` hooks (research Pattern 6a — no listener file is created; `after()` calls `Artisan::call('backup:run', ['--only-db' => true])` in try/catch so a backup failure NEVER breaks the close path — T-06-02-07), `NotifyOnBackupFailure` listener (`BackupHasFailed|UnhealthyBackupWasFound` → `NotificationService::broadcastToManagers(NotificationType::BACKUP_FAILED, [...])`; **deviation: spatie v10 events have `$diskName` as a string, NOT a `BackupDestination` object as research Pattern 7 assumed**), `NotificationType::BACKUP_FAILED` constant added, `AppServiceProvider::registerBackupFailureListeners()` (class_exists-guarded Event::listen wiring), `RestoreTestRun` artisan command (`backup:restore-test`), `routes/console.php` appended with `backup:clean` (01:00) + `backup:run` (01:30, withoutOverlapping) + `backup:monitor` (02:00) + `backup:restore-test` (03:00, withoutOverlapping), all onOneServer. 7 tests GREEN.
- **Deviations [Rule 1/3]**: (1) BackupPathResolver switched glob()→Finder (PHP `glob()` doesn't walk `**`); (2) spatie v10 event signatures differ from research Pattern 7 (flat `$diskName` string, no BackupDestination object); (3) `Artisan::fake()` does not exist in Laravel 13 → replaced with `Artisan::swap($mockerySpy)` that bypasses the facade's `resolvedInstance` cache; (4) spatie's own `EventHandler` mail path crashes in tests (needs the unconfigured `backups` s3 disk) → silenced via `EventHandler::disable()` / `enable()` toggle; (5) added `locateSqlDump()` protected seam on BOTH services so the suite can bypass the resolver via `shouldReceive('locateSqlDump')`.
- **D-08 enforcement**: every Process + Artisan::call + DB::connection('mysql_restore_test') call is mocked via Mockery partial mocks + `shouldAllowMockingProtectedMethods()` + protected seams. Full suite runs in ~18s with NO real subprocess.
- **Threat mitigations verified by test**: T-06-02-01 (down-first + always-up finally) by `test_up_is_called_in_finally_even_on_exception`; T-06-02-03 (Pitfall 4 symlink) by `test_restore_files_writes_into_storage_app_public_never_public_storage`; T-06-02-04 (path resolver multi-match) by `test_locate_sql_dump_throws_when_*_present`; T-06-02-07 (close-path isolation) by `test_after_hook_does_not_propagate_backup_failures`. T-06-02-08 (audit trail) is deferred to Plan 06-03 (the controller writes the audit row, not the service).
- Tests: 243 → 264 passed (+21 new: 14 Task 1 + 7 Task 2; +1 NotificationType::ALL assertion updated); `vendor/bin/pint --test` clean.
- Commits this plan: `8f5f897` (RED Task 1), `602cc1b` (GREEN Task 1), `31f4a02` (RED Task 2), `ab6dd3b` (GREEN Task 2).
- Decisions implemented this plan: D-04 (restore-test scratch-DB + COUNT parity), D-05 (nightly schedule + post-close hook + spatie failure wiring), D-06 (custom restore orchestration), D-08 (mocked heavy processes).
- Resume file: `.planning/phases/06-backup-and-restore-system/06-02-backend-restore-tests-SUMMARY.md`
- Next: Plan 06-03 (Wave 2) — super-admin controller + Blade view under `role:super-admin` that surfaces this service layer (list backups, download, run-now, restore-test health badge, guarded full-restore form with typed mess-name confirm). Plan 06-03 must NOT contain any restore logic — the service layer is complete.

**2026-06-19** — Phase 6 Plan 06.03: Super-admin Backups UI (controllers + Blade + tests) — COMPLETE.

- Plan 06-03 = the super-admin-facing surface for the backup/restore system. NO restore logic in the controllers — they orchestrate `BackupRestoreService` (Plan 06-02) and write tamper-evident manual `Audit` rows.
- **Task 1** (`bb33a21`): `BackupController` (research Pattern 3 — custom controller, NOT a Tyro dynamic resource) with `index()` (lists zips from `Storage::disk(config('backup.backup.destination.disks.0', 'backups'))` + reads the latest `RestoreTest` for the health badge), `runNow()` (`backup:run`), `runRestoreTest()` (`backup:restore-test`), `download()` (audit-logged via private `writeAudit` helper writing a manual OwenIt Audit row keyed by `event='backup.download'`), and a public static `activeMessName()` helper. `RestoreController` (mirrors `MonthCloseController`): `show()` renders the typed-confirm form, `store()` runs the service in try/catch — success writes `event='backup.restore'`, failure writes `event='backup.restore.failed'` (T-06-03-07), exception never escapes. `RestoreRequest`: `path` required + `mess_name in:<active mess name>` (D-03 typed-confirm; degrades to unmatchable sentinel when no active mess). `/dashboard/backups` route group (6 named routes) with `role:super-admin` + `throttle:5,1` on the restore POST. `errors/maintenance-backup-restore.blade.php` for the down-mode render.
- **Task 2** (`4570d0d`): 4 Blade views (`index` = list table + Backup-now + Run-restore-test buttons + `_health_badge` partial; `restore` = typed-confirm form; `_restore_form` = @csrf + path hidden + mess_name input + __()-wrapped error messages; `_health_badge` = status color map reading RestoreTest.status). APPENDED a role:super-admin-guarded Backups link to the published `admin-sidebar.blade.php` override using `routeIs('dashboard.backups.*')`. `view:cache` succeeds.
- **Task 3** (`4ac5642`, TDD RED→GREEN): 14 new tests — 6 `BackupControllerAuthTest` (super-admin 200 / admin 403 / user 403 / guest redirect / restore-test POST dispatches `backup:restore-test` via Artisan::swap spy / admin run 403) + 5 `RestoreConfirmationTest` (show typed-confirm / refuse without mess_name / refuse with wrong mess_name / correct mess_name runs service + writes `backup.restore` audit + redirects / service-throw writes `backup.restore.failed` + no escape) + 3 `BackupDownloadAccessLogTest` (download streams + `backup.download` audit / 404 missing / admin 403).
- **Deviations [Rule 1/3]**: (1) Used `config('backup.backup.destination.disks.0', 'backups')` (NOT `config('backup.destination.disks.0')`) — spatie v10 nests under top-level `backup` key; matches the key Plan 06-02's `BackupRestoreService` already uses. (2) Used `Mess::find(Mess::activeId())?->name` (NOT `Mess::active()?->name`) — Mess has no `active()` accessor, only `activeId()`; typed-confirm target still the active mess `name` (Open Q #3 LOCKED). (3) Blade views extend `layouts.app` (NOT `tyro-dashboard::layouts.admin`) — every other project custom-admin page does the same; emerald palette + min-h-[44px] touch targets. (4) `BackupControllerAuthTest` fakes the `backups` disk in setUp so the s3 adapter doesn't crash on null `DO_SPACES_BUCKET`. (5) Test 12 asserts 200 + Content-Disposition + the audit row (NOT streamed bytes — Laravel's testing client doesn't execute the streamDownload closure).
- **D-08 enforcement**: BackupRestoreService is a Mockery mock bound via `$this->app->instance()`; `backup:run` + `backup:restore-test` via `Artisan::swap($spy)` (Laravel 13 has no `Artisan::fake()` — confirmed in 06-02); `Storage::fake('backups')` for download + index tests; `withoutMiddleware(ThrottleRequests::class)` per-test so the restore POST validation tests don't rate-limit (production still enforces throttle:5,1).
- **Threat mitigations verified by test**: T-06-03-01 (role gate) by 5 auth tests; T-06-03-02 (typed confirm) by 2 RestoreConfirmation tests; T-06-03-05 (PII-leak download audit) by 3 DownloadAudit tests; T-06-03-06 (path traversal 404) by the missing-path test; T-06-03-07 (audit trail on success AND failure) by 2 restore tests. T-06-03-08 (maintenance-mode flip) is owned by Plan 06-02's BackupRestoreService (verified there); T-06-03-09 (sidebar link visible to non-super-admins) is structural — `@if(auth()->user()?->hasRole('super-admin') && Route::has(...))`.
- Tests: 264 → 278 passed (+14 new); `vendor/bin/pint --test` clean.
- Commits this plan: `bb33a21` (Task 1 controllers + form request + routes + maintenance view), `4570d0d` (Task 2 Blade views + sidebar), `4ac5642` (Task 3 tests).
- Decisions implemented this plan: D-03 (super-admin guarded one-click full-restore UI with typed-mess-name confirm + role gate + audit-log), D-08b/c (UI auth gating + restore confirmation flow tested with mocked heavy processes).
- Resume file: `.planning/phases/06-backup-and-restore-system/06-03-super-admin-ui-SUMMARY.md`
- Next: Plan 06-04 (Wave 3) — restore runbook in `DEPLOYMENT.md` (DR steps, maintenance-mode restore, `APP_KEY`/credential regeneration, DO Spaces provisioning).

**2026-06-19** — Phase 6 Plan 06.04: Backup & restore operator runbook in DEPLOYMENT.md + .env.example comments — COMPLETE (final plan of Phase 6; Phase 6 is feature-complete at 4/4).

- Plan 06-04 = the operator-facing disaster-recovery documentation. Documentation-only — zero code touched; the backup/restore SYSTEM was fully implemented in Plans 06-01/02/03.
- **Task 1** (`988045c`): Three additive changes to `DEPLOYMENT.md` (§1-§4 + §6-§10 from Plan 05-03 untouched): (a) APPENDED §11 "Backup & restore runbook" — 9 subsections 11.1-11.9 covering what gets backed up (mysqldump of all 26+ domain tables + storage/app/public; .env excluded D-07) / where (DO Spaces via `backups` s3 disk, retention ladder keep_daily=14 + keep_monthly=12) / schedule (4-row table: 01:00 clean, 01:30 run, 02:00 monitor, 03:00 restore-test — all class_exists-guarded + withoutOverlapping+onOneServer; plus on-demand UI + CLI; plus post-close `Artisan::call('backup:run', ['--only-db' => true])` from `CloseMonthJob::after()` wrapped in try/catch T-06-02-07) / Restore PRIMARY via `/dashboard/backups` UI (role:super-admin → review health badge → download audit row → Restore → type active mess name `Mess::find(Mess::activeId())->name` + throttle:5,1 → BackupRestoreService down→queue:restart→Finder-dump-locator→Symfony-Process-array-args-mysql-restore→storage_path('app/public')-file-copy→up-in-finally → audit rows `backup.restore` / `backup.restore.failed` never escapes T-06-03-07) / Restore FALLBACK via CLI when app down (SSH → artisan down → fetch zip from DO Spaces (tinker snippet or s3cmd/aws/rclone if Laravel can't boot) → unzip + locate db-dumps/*.sql → mysql < dump → cp files + storage:link → **REQUIRED step 7: `php artisan key:generate` + DB/Spaces credential rotation because .env is excluded D-07 T-06-04-02** → artisan up → smoke + restore-test) / Configure DO Spaces one-time (7 steps + region/endpoint match per Pitfall 5) / SMTP for failure emails (`MAIL_MAILER=smtp` required — explicit blockquote "Do NOT ship prod with MAIL_MAILER=log" T-06-04-03) / Optional Forge/DO host snapshot as defense-in-depth (note: includes .env so restrict access T-06-04-04; OPTIONAL per CONTEXT.md deferred note) / 8-row troubleshooting table mirroring §10 style (Windows-dev mysqldump PATH Pitfall 2, DO Spaces region/endpoint mismatch signature Pitfall 5, UnhealthyBackupWasFound, stale restore-test scratch DB, "Restore failed. App is back online" exception path, clobbered public/storage symlink Pitfall 4, GTID error Pitfall 10 DO-Managed-DB-only, post-close-hook-threw T-06-02-07). (b) EXTENDED §5 prod .env checklist with a Phase 6 sub-table (same `| Key | Value | Why |` format) listing all 11 keys: DO_SPACES_KEY/SECRET/REGION/BUCKET/ENDPOINT, BACKUP_DISK, BACKUP_MAX_MB, BACKUP_NOTIFICATION_EMAIL, BACKUP_ARCHIVE_PASSWORD, DB_RESTORE_TEST_DATABASE, DUMP_BINARY_PATH. (c) Updated footer "Last updated: 2026-06-19 (Plan 06-04)" + sentence pointing operators to §11.
- **`.env.example`** — expanded the inline `# <purpose>` comments above the two Phase 6 key blocks (added by Plan 06-01). NO key values changed. Each comment cross-references DEPLOYMENT.md §11.3/§11.6/§11.7/§11.9. Used lowercase "scratch DB" + uppercase "mysqldump binary DIRECTORY" so the plan's case-sensitive verify-block greps pass verbatim.
- **Reconciliation [Rule 1 — code wins]**: the runbook describes the ACTUALLY-SHIPPED behavior from Plans 06-01/02/03, NOT the plan/research draft where they diverged. (1) Typed-confirm target = `Mess::find(Mess::activeId())->name` (NOT the plan's `Mess::active()->name` — Mess has no `active()` accessor). (2) Disk config key = `config('backup.backup.destination.disks.0', 'backups')` (NOT the plan's flat `config('backup.destination.disks.0')` — spatie v10 nests under top-level `backup` key). (3) Post-close hook = `backup:run --only-db` (NOT bare `backup:run` — `CloseMonthJob::after()` passes `['--only-db' => true]` to `Artisan::call`). (4) The 3 audit event names actually written by Plan 06-03 controllers: `backup.restore` / `backup.restore.failed` / `backup.download`.
- **Out-of-scope**: `account.txt` was already modified (Phase 2 era stray notes) before this plan started — left unstaged (scope boundary).
- **Threat mitigations verified by documentation**: T-06-04-01 (runbook completeness) by all 20 plan verify-block greps PASS; T-06-04-02 (.env-excluded post-restore cred regen) by §11.5 step 7 explicit key:generate + rotation; T-06-04-03 (MAIL_MAILER=log) by §11.7 blockquote warning; T-06-04-04 (host snapshot access) by §11.8 restrict-access sentence; T-06-04-05 (DO Spaces region/endpoint) by §11.6 step 7 + §5 table + §11.9 row.
- Tests: 278 / 671 assertions PASS (NO code touched — baseline preserved from Plans 06-02/06-03).
- Commits this plan: `988045c` (Task 1 — DEPLOYMENT.md §11 + §5 extension + .env.example comments).
- Decisions implemented this plan: D-02 (DO Spaces + retention documented), D-03 (UI restore path documented as PRIMARY), D-05 (schedule + post-close hook + notify-on-failure documented), D-07 (.env exclusion + credential regeneration documented as REQUIRED).
- Resume file: `.planning/phases/06-backup-and-restore-system/06-04-runbook-SUMMARY.md`
- Next: Phase 6 is feature-complete (4/4 plans). Orchestrator owns phase-completion (VERIFICATION + marking phase done). Phase 5 Plan 05-03 (the v1 milestone pilot) remains the only incomplete plan in Phases 1-6.

**2026-06-19** — Phase 6 execution + verification + gap closure — COMPLETE (verified passed).

- Executed all 4 plans (3 waves): 06-01 foundation → 06-02 backend restore+tests → 06-03 super-admin UI → 06-04 runbook. 278 tests green after the 4 plans.
- Code review (06-REVIEW.md) + goal-backward verification (06-VERIFICATION.md) found **3 Critical gaps** the green suite could NOT catch (Plan 06-02's D-08 mock strategy stubbed exactly the seams behind which they hid): **CR-01** silent file-loss on restore (spatie relative_path strip + wrong source path → copyDirectory no-op); **CR-02** dead post-close backup (Laravel has no `after()` job hook); **CR-03** audit new_values double-JSON-encoded.
- Fixed inline (per user "Fix inline now") + 5 warnings (WR-01 stream, WR-03 stdin pipe, WR-05 path guard, WR-06 stack-leak, WR-07 spatie schedule guard) with NEW non-mocked/non-vacuous tests. Commits: `65cf87a` (CR-02), `26826fb` (CR-01+WR-01+WR-03), `5a370bd` (CR-03+WR-05+WR-06+WR-07). Full suite **279 tests / 683 assertions green**.
- Re-verification: 06-VERIFICATION.md status → **passed** (8/8 must-haves). Pre-existing flakiness noted: the full suite intermittently OOM-crashes in the Phase 4 Dompdf/phpspreadsheet export tests under the default memory_limit (passes with `php -d memory_limit=1024M vendor/bin/phpunit`); NOT a Phase 6 regression.
- **Still pending before v1 ship**: Phase 5 Plan 05-03 (real-mess pilot, human/manual) + Phase 6's 6 prod human-verification items (real DO Spaces provisioning + first backup:run, real scratch-DB restore-test, real SMTP failure email, end-to-end destructive restore on the prod VPS, real file-restore confirmation, down/queue:restart/up ordering under live supervisor). Advisory: `/gsd-secure-phase 6` (security_enforcement defaults on; no 06-SECURITY.md yet — Phase 6 plans carry STRIDE threat models with verified mitigations).
- Resume file: `.planning/phases/06-backup-and-restore-system/06-VERIFICATION.md`

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
