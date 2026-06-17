# Phase 4: Reports + Dashboard - Context

**Gathered:** 2026-06-17
**Status:** Ready for planning

<domain>
## Phase Boundary

Manager can view 4 reports (Monthly Report, Member Statement, Expense Report, Payment Report) and a real dashboard (6 stat cards + 3 trend charts + pending-meal-off alert). Member can view their own Member Statement and the mess's Monthly Report (aggregates only), plus a member dashboard (My Meals, My Bill, My Advance, My Payment History). All 4 reports support PDF (Dompdf) and Excel `.xlsx` (Maatwebsite/Excel) export on both manager and member sides (member exports are limited to their own data + the aggregates-only monthly report).

This is the read-mostly layer. By Phase 4 all the data is being written correctly (phases 1-3), so reports reflect reality. The 3 plans in the roadmap deliver this slice end-to-end (RPT-01 to RPT-08, DASH-01 to DASH-06). Performance tuning, year-over-year reports, Bengali PDFs, and SMS/WhatsApp are Phase 5 / v2 and out of scope.

</domain>

<decisions>
## Implementation Decisions

### Charts (DASH-02, DASH-06)

- **D-01:** **Chart.js, bundled via Vite.** Add `chart.js` to `package.json`, initialise from data passed via Blade `@json` into a small init script in the Vite bundle. Lightweight, named in research/STACK, Tailwind-friendly, no extra Laravel charting package. No chart library is installed yet.
- **D-02:** **Line + bar mix.** Meal Trend (30d) = **line** (daily series). Expense Trend (6mo) + Payment Trend (6mo) = **bar** (discrete monthly buckets read better than lines for monthly totals).
- **D-03:** **Fully-selectable range picker on every chart** (DASH-06). Default windows stay as DASH-02 states (Meal 30d, Expense 6mo, Payment 6mo), but the user can override the range on each chart.
- **D-04:** **Full responsive chart on mobile** — one implementation that scales to 375px. No sparkline/expand-on-tap, no hide-on-mobile. Mobile-first is a project constraint; charts must render legibly at phone width.

### Chart data semantics

- **D-05:** **Meal Trend metric = daily meal count** (total meals consumed across the mess that day, e.g. 42.5). Shows mess activity/volume. (Not daily rate — rate is noisy mid-month before bazar accrues.)
- **D-06:** **Expense Trend metric = monthly bazar total only.** Bazar is the variable cost and the meal-rate driver — matches the locked "meal rate = bazar only" rule. (Not bazar+fixed, not stacked by category.)
- **D-07:** **Payment Trend metric = monthly total collected** across all methods. Simplest "money in" view. (Not split by method or type.)
- **D-08:** **Range × granularity = auto-bucket by range.** Daily buckets when the selected range is ≤ ~60 days; weekly/monthly buckets when wider. Adapts automatically as the user changes the range (so a 6-month Meal range doesn't produce ~180 dense points).

### Exports (RPT-07, RPT-08)

- **D-09:** **Install `maatwebsite/excel`** for real `.xlsx` export. Named in RPT-08.
- **D-10:** **Install Dompdf** (`barryvdh/laravel-dompdf` or the Laravel DOMPDF wrapper) for PDF export. Named in RPT-07.
- **D-11:** **All 4 reports get a PDF export button** (Monthly, Member Statement, Expense, Payment).
- **D-12:** **All 4 reports get an Excel `.xlsx` export button.**
- **D-13:** **PDF layout = portrait A4, branded.** Mess name + period in the header; generated-date + page number in the footer. **Planner note:** the Monthly Report has a wide per-member table — portrait will need column compaction (smaller font / wrapping / fewer columns) so it fits. English-only shipped in v1, so Dompdf's default fonts are fine; Bengali PDF is a v2 concern (LOC-03) — Dompdf has known Bengali font issues to solve then.

### Dashboard layout (DASH-01, DASH-02, DASH-03, DASH-04, DASH-05)

- **D-14:** **Manager `/home` becomes the dashboard.** 6 stat cards on top + 3 charts below. The current link-card grid on `/home` is **replaced**; quick-nav already lives in the sidebar (Members, Settings, Audit, Payments, etc.).
- **D-15:** **6 DASH-01 stat cards** (Total Members, Today's Meals, Current Meal Rate, Monthly Expenses, Total Due, Total Advance) in a responsive grid. **DASH-03 pending meal-off count = a highlighted alert/banner** at the top of the dashboard linking to the meal-off approval queue (not a 7th card).
- **D-16:** **Member `/my` gets a new "Overview" landing shown first** — the 4 DASH-04 cards (My Meals this month, My Bill this month, My Advance, My Payment History). The existing tabs (Profile / Meal off / Meals / Payments) remain and drill into detail.
- **D-17:** **Dashboard caching reuses the bill-preview cache + targeted keys.** Bill-related cards (Current Meal Rate, Total Due, Total Advance, My Bill) read from the existing `bill-preview:{mess_id}:{YYYY}-{MM}` key (Phase 3 D-14). Count-based cards (Total Members, Today's Meals, Monthly Expenses) get their own short-TTL cache keys. All invalidated on write (Phase 3 D-15). Targets DASH-05 + success #12 (refresh < 2s).

### Filters & member visibility (RPT-03, RPT-04, RPT-06)

- **D-18:** **Report filter UX = GET form + sticky query-string + presets.** Filters live in the URL (shareable, back-button friendly) with "This month" / "Last month" preset buttons. Applied to Expense Report (date / category / month) and Payment Report (member / method / date range). Matches the Phase 2 meal-grid date-nav pattern.
- **D-19:** **Member Monthly Report (RPT-06) = aggregates only.** Totals, meal rate, total bazar, total fixed, total due, total advance. **No per-member table** — a member does not see other members' dues/advances. (Privacy call. The manager's Monthly Report keeps the full per-member table.)
- **D-20:** **Period navigation = month picker (◀ Month ▶ arrows + dropdown)** on Monthly Report and Member Statement. Matches Phase 2 meal-grid date nav.
- **D-21:** **Member Statement history = full.** A member can view their own statement for the current month plus any past month.

### Member Statement content (RPT-02, RPT-05)

- **D-22:** **Member Statement = full ledger.** Sections: meals summary (B/L/D counts + meal cost), guest meals, payments (bill payments + advance deposits), fixed share, opening advance/due, and closing bill/due/advance. (It's the member's own data — full detail is appropriate.)
- **D-23:** **Meals shown as a daily breakdown** (each date's B/L/D) + per-type and grand totals. Full auditability — a member can verify each day.
- **D-24:** **Statement label = "As of today, {date}"** for the current (open) month; **"for {Month Year}"** for closed months. Matches Phase 3 D-16 (live compute vs snapshot).
- **D-25:** **Show the meal-rate math** on the statement: meal rate (৳/meal) × the member's meal count = meal cost. Transparency builds trust (this is the document members argue over).

### Report data source (carried forward from Phase 3 D-16)

- **D-26:** **Closed month → read `MonthlyMemberSummary` snapshot; current/unclosed month → live compute via `BillPreviewService`.** A past month that was never closed computes against the last day of that month using whatever data exists. This is already locked in Phase 3; restated here so the planner doesn't re-derive it. All 4 reports + dashboard bill cards follow this rule.

### Empty & first-run states

- **D-27:** **Empty charts = friendly placeholder + hint** ("No data yet — charts appear once you have expenses/meals/payments"). For a new mess or a range outside history. Not empty axes, not hidden.
- **D-28:** **Empty report (period with no data) = clear empty state + hint** ("No data for {Month Year} yet"). Not a wall of ৳0.00, not a redirect.
- **D-29:** **Zero meal rate on the dashboard = "৳0.00 / meal — no bazar recorded yet" + hint.** Explains the zero rather than hiding the card or showing an em-dash.
- **D-30:** **Pre-first-close: everything works off live compute (BillPreviewService).** Reports and the dashboard do **not** require a month-close to function. Closed-month-only features (closed-month banner, snapshot source) simply don't appear until a close exists. No gating banner, no disabled reports.

### Report navigation & export permissions

- **D-31:** **Manager sidebar = a "Reports" group with 4 sub-entries** (Monthly Report, Member Statement, Expense Report, Payment Report). Not a hub/index page, not flat top-level entries.
- **D-32:** **Member access = a new "My reports" tab on `/my`** (alongside Profile / Meal off / Meals / Payments) that opens the member's own statement + the mess monthly report. Coexists with the Overview landing cards from D-16 — Overview is the at-a-glance dashboard, My reports is the detailed/exportable view.
- **D-33:** **Member can export their own Member Statement as PDF + Excel.** RPT-05 + RPT-07/08 apply to members for their own data.
- **D-34:** **Member can export the aggregates-only Monthly Report as PDF + Excel.** Consistent with being able to view it (D-19).

### Claude's Discretion

- Exact Chart.js theme / color palette / tooltip & legend styling (keep mobile-legible contrast)
- Vite entry strategy for Chart.js init (global `resources/js/app.js` vs a per-page chunk)
- The date-range picker component for charts (native HTML date inputs vs a small JS picker lib)
- PDF filename conventions (e.g. `member-statement-{member}-{YYYY-MM}.pdf`)
- Whether Excel cells use raw numbers (recommended — lets the manager sum/formula) vs `bdt()`-formatted strings
- Report table pagination threshold (when to paginate on-screen vs show-all; exports always include all rows regardless)
- Layout of the "My reports" tab (cards vs list for the two reports)
- Test depth for exports (assert download response + content-type + filename; full content assertion is brittle — prefer that)
- Exact ~60-day auto-bucket threshold (D-08) and the weekly/monthly bucket labels
- Whether the dashboard "Monthly Expenses" card means bazar-only, fixed-only, or total (default: total monthly expenses; confirm during planning)

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Project context
- `.planning/PROJECT.md` — Vision, constraints, key decisions (esp. "Cached current-month aggregates", "Meal rate = bazar only", "Manager-records payments", mobile-first)
- `.planning/REQUIREMENTS.md` — **RPT-01 to RPT-08** (reports + export) and **DASH-01 to DASH-06** (dashboard) are the Phase 4 requirement surface
- `.planning/ROADMAP.md` — Phase 4 success criteria, out-of-scope items, plan breakdown (3 plans)
- `.planning/STATE.md` — Current progress, validations from Phases 1-3

### Prior phase context (MOST RELEVANT — Phase 4 builds on Phase 3)
- `.planning/phases/03-payments-month-close/03-CONTEXT.md` — **34 locked Phase 3 decisions.** Carry forward especially: **D-14/D-15** (single cache key `bill-preview:{mess_id}:{YYYY}-{MM}`, 1h TTL, database driver, invalidate on write), **D-16** (current month = live compute, closed month = `MonthlyMemberSummary` snapshot → restated as D-26 here), **D-33** (`bdt()` helper), **D-34** (per-mess `date_format` DD-MM-YYYY), **D-12/D-13** (meal rate excludes mid-month joiners from denominator; fixed cost prorated by days)
- `.planning/phases/03-payments-month-close/03-DISCUSSION-LOG.md` — full Q&A audit trail for Phase 3
- `.planning/phases/02-members-daily-operations/02-CONTEXT.md` — Phase 2 decisions (esp. meal-grid date-nav pattern reused for report month picker; `x-tab-nav` member tab pattern; `kind` on expense categories)
- `.planning/phases/01-foundation/01-CONTEXT.md` — 24 locked Phase 1 decisions (timezone, decimal money, `mess_id` everywhere, Auditable trait, service layer, `__()` everywhere)

### Codebase maps (already in repo)
- `.planning/codebase/STACK.md` — Installed packages, runtime versions (**confirms Chart.js / Dompdf / Maatwebsite-Excel are NOT yet installed**)
- `.planning/codebase/CONVENTIONS.md` — Code style, attribute-based model config, migration style, test style, Form Request pattern
- `.planning/codebase/STRUCTURE.md` — Directory layout, where controllers/services/views go
- `.planning/codebase/INTEGRATIONS.md` — Cache (`database` driver, no tags), queue, session drivers
- `.planning/codebase/TESTING.md` — PHPUnit 12 patterns, RefreshDatabase, factory usage

### Research
- `.planning/research/SUMMARY.md` — Stack decisions, anti-features
- `.planning/research/PITFALLS.md` — **Phase 4-relevant pitfalls:** #11 (cache staleness — solved by Phase 3 invalidation, reused here), #15 (cache stampede — single-key pattern avoids it)
- `.planning/research/ARCHITECTURE.md` — Service-layer-no-repository, Form Requests, Auditable trait
- `.planning/research/STACK.md` — Why Chart.js was chosen as the intended chart lib

### Skills (project-local, used during implementation)
- `.agents/skills/laravel-best-practices/SKILL.md` — Laravel 13 best practices, N+1 detection, caching patterns
- `.agents/skills/tyro-dashboard/SKILL.md` — Tyro patterns, app integration, sidebar overrides

### Taste preferences
- `.commandcode/taste/taste.md` — Laravel 13, MySQL, snake_case DB names, **always use `Mess::activeId()`** for `mess_id`

### Schema + service references (the reusable core)
- `app/Services/BillPreviewService.php` — **Primary reusable asset.** `preview(year, month)` returns totals (total_bazar, total_meals, meal_rate, total_fixed, days_in_month) + per-member rows (meals, meal_cost, fixed_share, guest_total, bill, bill_payments, advance_payments, due, advance_balance, due_balance). `forMember(memberId, year, month)` returns one member's row. `cacheKey(messId, year, month)` exposes the shared cache key. Feeds the Monthly Report, Member Statement, and the dashboard bill cards directly.
- `app/Services/BillPreviewInvalidator.php` — `forDate($date)` / `forToday()` forget the bill-preview cache key. Reuse for dashboard/report invalidation.
- `database/migrations/2026_06_16_221400_create_monthly_closings_table.php` — has `unique(['mess_id','year','month'])`
- `database/migrations/2026_06_16_221500_create_monthly_member_summaries_table.php` — closed-month snapshot source for reports
- `database/migrations/2026_06_16_221100_create_expense_categories_table.php` + `_221200_create_expenses_table.php` — expense data (category `kind` drives bazar vs fixed)
- `database/migrations/2026_06_16_221300_create_payments_table.php` — payment data (type, method)
- `database/migrations/2026_06_16_221700_create_advance_balances_table.php` — `balance` + `due_balance` per member

### External package docs (to consult during research/planning)
- Chart.js docs — line/bar config, responsive options, `@json` data-binding pattern
- `maatwebsite/excel` docs — `FromQuery` / `FromCollection` exports, `Excel::download`
- Laravel Dompdf docs — `PDF::loadView()`, page headers/footers, page size/orientation

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `App\Services\BillPreviewService` — **the centerpiece.** Already computes everything the Monthly Report, Member Statement, and dashboard bill cards need. Phase 4 wraps it in report views + export adapters; it does **not** re-derive bill math. (`forMember()` feeds the member statement; `preview()`'s totals feed the monthly report + dashboard cards.)
- `App\Services\BillPreviewInvalidator` — call after any write that affects a report/dashboard to keep cache fresh (Phase 3 D-15 behaviour).
- `app/helpers.php` — `bdt($amount)` for all money display (Phase 3 D-33).
- Per-mess `date_format` setting helper (default DD-MM-YYYY) — Phase 3 D-34. Use for all report/dashboard date labels.
- `resources/views/layouts/app.blade.php` — manager layout with sidebar + mobile drawer. Phase 4 adds the "Reports" sidebar group (D-31) and transforms `/home` into the dashboard (D-14).
- `resources/views/my.blade.php` + `<x-tab-nav>` — member tab-nav pattern. Phase 4 adds the Overview landing (D-16) and the "My reports" tab (D-32).
- `App\Services\MealGridService` / `ExpenseService` — service-layer pattern reference for any new `ReportService` / `DashboardService`.
- `App\Http\Controllers\HomeController` / `MyController` — entry points for the manager dashboard (`/home`) and member dashboard (`/my`).
- `Mess::activeId()`, `BelongsToActiveMess`, `MessScope` — every report query is auto-scoped to the active mess.
- Models already in place: `Member`, `Expense`, `ExpenseCategory`, `Payment`, `MealEntry`, `GuestMeal`, `AdvanceBalance`, `MonthlyClosing`, `MonthlyMemberSummary`.

### Established Patterns
- **Service layer** in `app/Services/{Domain}Service.php`. Controllers delegate. No Repository pattern. → a `ReportService` / `DashboardService` (or extending `BillPreviewService`) fits here.
- **`Cache::remember` / `Cache::forget`** with string keys, **`database` driver, no tags** (Phase 3 D-14). Reports/dashboard reuse the `bill-preview:{mess_id}:{YYYY}-{MM}` key + add targeted count keys (D-17).
- **Attribute-based model config**, `casts()` method, anonymous-class migrations, Form Requests for input, `__()` everywhere.
- **Mobile-first 375px**, Tailwind v4 + Blade, no inline CSS.
- **Tyro role checks**: `$user->hasRole('admin')` = manager, `'user'` = member. Member report routes enforce `role:user` + the member can only see their own data (RPT-05) and aggregates (D-19).
- **PHPUnit 12**, `test_` prefix, `RefreshDatabase`, direct controller invocation via Reflection in tests.

### Integration Points
- **Routes** (`routes/web.php`): manager report routes under `role:admin` + `EnsureMessExists` (e.g. `/mess/reports/monthly`, `/mess/reports/member-statement`, `/mess/reports/expenses`, `/mess/reports/payments` + their `.pdf` / `.xlsx` export variants). Member routes under `role:user` (e.g. `/my/reports/statement`, `/my/reports/monthly` + exports).
- **Sidebar nav** (`resources/views/layouts/app.blade.php`): add the "Reports" group with 4 sub-entries (D-31).
- **`/home`** (`HomeController` + `home.blade.php`): replace link-card grid with 6 stat cards + 3 charts (D-14/D-15).
- **`/my`** (`MyController` + `my.blade.php`): add Overview landing (D-16) + "My reports" tab (D-32).
- **`composer.json`**: add `barryvdh/laravel-dompdf` (or equivalent) + `maatwebsite/excel` (D-09/D-10).
- **`package.json` + Vite**: add `chart.js`; wire an init script that reads Blade `@json` datasets (D-01).
- **`.env`**: no new keys required (cache + db already configured). Confirm `CACHE_STORE=database`.

### ⚠️ Pre-existing issue downstream agents must know about
- `app/Services/BillPreviewService.php` line 92 currently contains a leftover `throw new \RuntimeException('DBG:...')` debug statement from Phase 3.3 WIP that **breaks bill preview entirely**. Phase 4 reports/dashboard depend on this service. **This is a Phase 3.3 completion fix** (remove the debug throw + dead `Log::debug` block at lines 92-97), but it must be resolved before Phase 4 reports can reuse `BillPreviewService`. Flagged here so the planner/researcher don't assume the service works today.

</code_context>

<specifics>
## Specific Ideas

- **Manager dashboard** (`/home`, redesigned): top — "Pending meal-off requests" alert banner (if any) linking to approvals. Below — 6 stat cards in a responsive grid (Total Members, Today's Meals, Current Meal Rate, Monthly Expenses, Total Due, Total Advance). Below that — 3 charts in a stack (mobile) / grid (desktop): Meal Trend (line, 30d, daily count), Expense Trend (bar, 6mo, monthly bazar), Payment Trend (bar, 6mo, monthly collected). Each chart has its own range picker; ranges auto-bucket (D-08). Empty chart → placeholder + hint (D-27).
- **Member dashboard** (`/my` Overview landing): 4 cards — My Meals (this month's total), My Bill (this month, "as of today"), My Advance (current balance), My Payment History (recent, link to full). Plus the existing tab nav for Profile / Meal off / Meals / Payments, and a new "My reports" tab.
- **Member Statement** (`/my/reports/statement` + manager view at `/mess/reports/member-statement/{member}`): month picker ◀ ▶ at top. Label "As of today, {date}" (open month) or "for {Month Year}" (closed). Sections: meal-rate math line (rate × my meals = meal cost), daily meal breakdown table (date / B / L / D) with per-type + grand totals, guest meals, payments (bill payments + advance deposits), fixed share, opening & closing advance/due, net due/advance. "Download PDF" + "Download Excel" buttons.
- **Monthly Report** (manager `/mess/reports/monthly` — full; member view — aggregates only per D-19): month picker ◀ ▶. Totals (members, meals, meal rate, total bazar, total fixed, total due, total advance). Manager version adds the per-member table. PDF (portrait, branded, column-compacted) + Excel buttons.
- **Expense Report** (`/mess/reports/expenses`): GET filter bar (date range + category + month, "This month" / "Last month" presets, sticky in URL). Table of expenses. PDF + Excel.
- **Payment Report** (`/mess/reports/payments`): GET filter bar (member + method + date range, presets, sticky). Table of payments. PDF + Excel.
- **Empty states**: every report/chart surfaces a one-line hint when there's no data for the selection (D-27/D-28/D-29).

</specifics>

<deferred>
## Deferred Ideas

- **Bengali PDF export** — v2 (LOC-03). English-only shipped in v1; Dompdf Bengali font handling is a v2 problem.
- **Year-over-year / advanced reports** — v2 (RPT-ADV-01 to RPT-ADV-03).
- **Real-time dashboard updates (websockets)** — anti-recommendation in PROJECT.md; v2 (RT-01/02).
- **Report scheduling / email digests** — v2 (COMM-03).
- **CSV export** — chose `.xlsx` via Maatwebsite/Excel (D-09) for all reports; a lightweight CSV fallback was considered and deferred.
- **Sparkline / expand-on-tap mobile charts** — chose full responsive charts (D-04) instead.
- **Server-rendered SVG charts / ApexCharts** — chose Chart.js via Vite (D-01) instead.
- **Stacked-by-category expense chart / split-by-method payment chart** — chose single-series simplicity (D-06/D-07); richer breakdowns can come later.
- **Dashboard auto-refresh / AJAX polling** — page-load refresh + cached cards (D-17) is enough for v1; no polling.
- **Landscape PDF for the wide monthly table** — chose portrait + compaction (D-13) for consistency; revisit if readability suffers in UAT.

</deferred>

---

*Phase: 04-reports-dashboard*
*Context gathered: 2026-06-17*
