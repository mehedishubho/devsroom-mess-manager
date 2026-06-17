# Phase 4: Reports + Dashboard - Research

**Researched:** 2026-06-17
**Domain:** Laravel 13 read-mostly layer (reports + dashboard) — PDF/Excel export, Chart.js, cache reuse, role-scoped routing
**Confidence:** HIGH (core stack verified against registries; reusable assets verified by reading source files; gaps explicitly surfaced)

## Summary

Phase 4 is the read-mostly layer. Phases 1-3 wrote the data correctly; Phase 4 wraps the existing `BillPreviewService` (which already computes every number the Monthly Report, Member Statement, and dashboard bill cards need) in 4 report views + a real dashboard. The work is fundamentally: install 3 packages, build read-only report tables/cards/charts on top of cached aggregates, wire 4 routes per report (HTML + PDF + Excel + member-side variant), and harden everything with PHPUnit feature tests.

Three verified package versions unlock the phase (all confirmed against the Packagist registry this session, not training data):

- `barryvdh/laravel-dompdf` **v3.1.2** — `illuminate/support ^9|^10|^11|^12|^13.0` (Laravel 13 added in v3.1.1). `[VERIFIED: composer show -a]`
- `maatwebsite/excel` **3.1.69** (latest stable) — `illuminate/support 5.8.*||^6.0||^7.0||^8.0||^9.0||^10.0||^11.0||^12.0||^13.0` (the `||^13.0` clause appeared in 3.1.68; 3.1.69 is current). `[VERIFIED: repo.packagist.org/p2/maatwebsite/excel.json]`
- `chart.js` **4.5.1** (latest, `npm view chart.js version`). `[VERIFIED: npm registry]`

**Two real gaps the planner MUST address (both flagged by reading the actual code, not the CONTEXT summary):**

1. **`bdt()` helper does not exist.** Phase 3 D-33 and the 04-CONTEXT `<canonical_refs>` both claim `app/helpers.php` defines `bdt($amount)`. **It does not.** No `app/helpers.php` file exists, `composer.json` has no `autoload.files` entry, and `grep` finds zero `bdt(` callsites anywhere in `app/` or `resources/`. The actual money helper is `App\Support\Money::taka($amount)` (in `app/Support/Money.php`), used in **14 blade views** already. Per-mess `date_format` is also not wired to a global helper — views hard-code `->format('d-m-Y')` / `->format('d M Y')`. Plan 4.1 Wave 0 must decide: (a) adopt `Money::taka()` as the canonical helper and update CONTEXT's `bdt()` references, or (b) actually create `app/helpers.php` with `bdt()` + a `mess_date()` helper and add it to composer autoload. Either way, **do not assume `bdt()` works** — every report that displays money must use whichever helper the planner locks.
2. **The "DBG debug throw" is already fixed** (04-CONTEXT correction is accurate — commit `b4ce6ee`). Confirmed by reading `BillPreviewService.php` lines 87-196: `compute()` returns cleanly, no `RuntimeException`, no `Log::debug`. The service is ready to reuse as-is.

**Primary recommendation:** Install the 3 packages first (Wave 0), then build each report as `Controller → ReportService (wraps BillPreviewService for closed-month logic + adds the report-specific query) → Blade view → Dompdf/Excel export adapter`. Reuse the `bill-preview:{mess_id}:{YYYY}-{MM}` cache key for bill-derived cards; add short-TTL count keys (`dash:counts:{mess_id}:{YYYY}-{MM}`) for Total Members / Today's Meals / Monthly Expenses, invalidated by the same `AppServiceProvider::boot()` Eloquent-event hooks already wired in Phase 3.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions (D-01 .. D-34) — verbatim summary, planner MUST honor

**Charts (DASH-02, DASH-06):**
- **D-01:** Chart.js, bundled via Vite. Add `chart.js` to `package.json`, initialise from data passed via Blade `@json` into a small init script in the Vite bundle. No chart library is installed yet.
- **D-02:** Line + bar mix. Meal Trend (30d) = **line** (daily series). Expense Trend (6mo) + Payment Trend (6mo) = **bar** (discrete monthly buckets).
- **D-03:** Fully-selectable range picker on every chart. Default windows stay (Meal 30d, Expense 6mo, Payment 6mo), user can override.
- **D-04:** Full responsive chart on mobile — one implementation that scales to 375px. No sparkline/expand-on-tap, no hide-on-mobile.

**Chart data semantics:**
- **D-05:** Meal Trend metric = **daily meal count** (total meals consumed across the mess that day, e.g. 42.5). Not daily rate.
- **D-06:** Expense Trend metric = **monthly bazar total only** (not bazar+fixed, not stacked by category).
- **D-07:** Payment Trend metric = **monthly total collected** across all methods (not split by method/type).
- **D-08:** Range × granularity = **auto-bucket by range**. Daily buckets when range ≤ ~60 days; weekly/monthly buckets when wider. Adapts as the user changes the range.

**Exports (RPT-07, RPT-08):**
- **D-09:** Install `maatwebsite/excel` for real `.xlsx` export.
- **D-10:** Install Dompdf (`barryvdh/laravel-dompdf`).
- **D-11:** All 4 reports get a PDF export button.
- **D-12:** All 4 reports get an Excel `.xlsx` export button.
- **D-13:** PDF layout = **portrait A4, branded**. Mess name + period in header; generated-date + page number in footer. Planner note: the Monthly Report has a wide per-member table — portrait will need column compaction (smaller font / wrapping / fewer columns) so it fits. English-only shipped in v1 (Dompdf default fonts fine); Bengali PDF is v2 (LOC-03) — Dompdf has known Bengali font issues then.

**Dashboard layout (DASH-01, DASH-02, DASH-03, DASH-04, DASH-05):**
- **D-14:** Manager `/home` becomes the dashboard. 6 stat cards on top + 3 charts below. The current link-card grid on `/home` is **replaced**; quick-nav already lives in the sidebar.
- **D-15:** 6 DASH-01 stat cards (Total Members, Today's Meals, Current Meal Rate, Monthly Expenses, Total Due, Total Advance) in a responsive grid. DASH-03 pending meal-off count = a highlighted alert/banner at the top linking to the meal-off approval queue (not a 7th card).
- **D-16:** Member `/my` gets a new "Overview" landing shown first — the 4 DASH-04 cards (My Meals this month, My Bill this month, My Advance, My Payment History). Existing tabs (Profile / Meal off / Meals / Payments) remain and drill into detail.
- **D-17:** Dashboard caching reuses the bill-preview cache + targeted keys. Bill-related cards (Current Meal Rate, Total Due, Total Advance, My Bill) read from the existing `bill-preview:{mess_id}:{YYYY}-{MM}` key (Phase 3 D-14). Count-based cards (Total Members, Today's Meals, Monthly Expenses) get their own short-TTL cache keys. All invalidated on write (Phase 3 D-15). Targets DASH-05 + success #12 (refresh < 2s).

**Filters & member visibility (RPT-03, RPT-04, RPT-06):**
- **D-18:** Report filter UX = **GET form + sticky query-string + presets**. Filters live in the URL (shareable, back-button friendly) with "This month" / "Last month" preset buttons. Applied to Expense Report (date / category / month) and Payment Report (member / method / date range). Matches Phase 2 meal-grid date-nav pattern.
- **D-19:** Member Monthly Report (RPT-06) = **aggregates only**. Totals, meal rate, total bazar, total fixed, total due, total advance. **No per-member table** — a member does not see other members' dues/advances. (Privacy call. Manager's Monthly Report keeps the full per-member table.)
- **D-20:** Period navigation = **month picker (◀ Month ▶ arrows + dropdown)** on Monthly Report and Member Statement. Matches Phase 2 meal-grid date nav.
- **D-21:** Member Statement history = full. A member can view their own statement for current month plus any past month.

**Member Statement content (RPT-02, RPT-05):**
- **D-22:** Member Statement = **full ledger.** Sections: meals summary (B/L/D counts + meal cost), guest meals, payments (bill payments + advance deposits), fixed share, opening advance/due, closing bill/due/advance.
- **D-23:** Meals shown as a **daily breakdown** (each date's B/L/D) + per-type and grand totals. Full auditability.
- **D-24:** Statement label = **"As of today, {date}"** for the current (open) month; **"for {Month Year}"** for closed months. Matches Phase 3 D-16.
- **D-25:** Show the **meal-rate math** on the statement: meal rate (৳/meal) × the member's meal count = meal cost.

**Report data source (carried forward from Phase 3 D-16):**
- **D-26:** Closed month → read `MonthlyMemberSummary` snapshot; current/unclosed month → live compute via `BillPreviewService`. A past month that was never closed computes against the last day of that month using whatever data exists. All 4 reports + dashboard bill cards follow this rule.

**Empty & first-run states:**
- **D-27:** Empty charts = friendly placeholder + hint ("No data yet — charts appear once you have expenses/meals/payments"). Not empty axes, not hidden.
- **D-28:** Empty report (period with no data) = clear empty state + hint ("No data for {Month Year} yet"). Not a wall of ৳0.00, not a redirect.
- **D-29:** Zero meal rate on the dashboard = "৳0.00 / meal — no bazar recorded yet" + hint. Explains the zero rather than hiding the card.
- **D-30:** Pre-first-close: everything works off live compute (BillPreviewService). Reports and the dashboard do **not** require a month-close to function. No gating banner, no disabled reports.

**Report navigation & export permissions:**
- **D-31:** Manager sidebar = a **"Reports" group with 4 sub-entries** (Monthly Report, Member Statement, Expense Report, Payment Report). Not a hub/index page, not flat top-level entries.
- **D-32:** Member access = a new **"My reports" tab on `/my`** (alongside Profile / Meal off / Meals / Payments) that opens the member's own statement + the mess monthly report. Coexists with the Overview landing cards from D-16.
- **D-33:** Member can export their own Member Statement as PDF + Excel. RPT-05 + RPT-07/08 apply to members for their own data.
- **D-34:** Member can export the aggregates-only Monthly Report as PDF + Excel. Consistent with D-19.

### Claude's Discretion (research may recommend; planner locks)
- Exact Chart.js theme / color palette / tooltip & legend styling (keep mobile-legible contrast)
- Vite entry strategy for Chart.js init (global `resources/js/app.js` vs a per-page chunk) — **recommendation below**
- The date-range picker component for charts (native HTML date inputs vs a small JS picker lib) — **recommendation below**
- PDF filename conventions (e.g. `member-statement-{member}-{YYYY-MM}.pdf`)
- Whether Excel cells use raw numbers (recommended) vs `bdt()`-formatted strings — **recommendation below**
- Report table pagination threshold (when to paginate on-screen vs show-all; exports always include all rows)
- Layout of the "My reports" tab (cards vs list for the two reports)
- Test depth for exports (assert download response + content-type + filename; full content assertion is brittle)
- Exact ~60-day auto-bucket threshold (D-08) and the weekly/monthly bucket labels
- Whether the dashboard "Monthly Expenses" card means bazar-only, fixed-only, or total (default: total monthly expenses; confirm during planning)

### Deferred Ideas (OUT OF SCOPE — do not research)
- Bengali PDF export (v2 / LOC-03)
- Year-over-year / advanced reports (v2 / RPT-ADV-01..03)
- Real-time dashboard updates / websockets (v2 / RT-01..02; anti-recommendation in PROJECT.md)
- Report scheduling / email digests (v2 / COMM-03)
- CSV export (chose `.xlsx` only — D-09)
- Sparkline / expand-on-tap mobile charts (chose full responsive — D-04)
- Server-rendered SVG charts / ApexCharts (chose Chart.js — D-01)
- Stacked-by-category expense chart / split-by-method payment chart (chose single-series — D-06/D-07)
- Dashboard auto-refresh / AJAX polling (page-load + cached cards — D-17)
- Landscape PDF for the wide monthly table (chose portrait + compaction — D-13)
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| RPT-01 | Manager Monthly Report (totals, meal rate, due, advance) | `BillPreviewService::preview($y,$m)` returns every aggregate needed; see **BillPreviewService Return Shape** below. Closed-month path uses `MonthlyMemberSummary`. |
| RPT-02 | Manager Member Statement (any member, any month) | `BillPreviewService::forMember($memberId,$y,$m)` returns the member's row; D-23 daily breakdown requires a side query against `meal_entries` (date + B/L/D booleans) + `guest_meals` (charge_amount) for the month. |
| RPT-03 | Expense Report (filter date/category/month) | Direct query on `expenses` table joined to `expense_categories` (use `kind` for bazar/fixed split); GET-form filter per D-18; index on `(mess_id, date)` already exists. |
| RPT-04 | Payment Report (filter member/method/date) | Direct query on `payments` table (`member_id`, `method`, `date`, `type`); indexes on `(mess_id, date)`, `(mess_id, member_id, type)`, `(mess_id, method)` already exist. |
| RPT-05 | Member own Member Statement | Same as RPT-02 but scoped to `$member = $request->user()->getMemberOrNull()`. Route guard: `role:user`. |
| RPT-06 | Member mess Monthly Report (aggregates only) | Same data source as RPT-01 but the view **omits** the per-member table (D-19). Render totals + meal rate + total bazar + total fixed + total due + total advance only. |
| RPT-07 | PDF export (all 4 reports, both roles) | `barryvdh/laravel-dompdf` v3.1.2 — `Pdf::loadView()->setPaper('a4','portrait')->download()`. See **PDF Pattern** below for header/footer/page numbers. |
| RPT-08 | Excel `.xlsx` export (all 4 reports, both roles) | `maatwebsite/excel` 3.1.69 — `FromCollection` + `WithHeadings` + `WithMapping` + `ShouldAutoSize`; `Excel::download($export, $file)`. See **Excel Pattern** below. |
| DASH-01 | Manager dashboard: 6 cards | See **Dashboard Cache Strategy** — 4 bill-derived cards reuse `bill-preview:{mess_id}:{YYYY}-{MM}`; 3 count cards need new short-TTL key. |
| DASH-02 | Manager dashboard: 3 charts | Chart.js 4.5.1 via Vite. Daily/weekly/monthly bucketing per D-08; query shapes in **Trend Query Patterns**. |
| DASH-03 | Pending meal-off count as alert banner | `MealOffRequest::where('status','pending')->count()` for the active mess. Banner at top of `/home` linking to `mess.meal-off.index`. |
| DASH-04 | Member dashboard: 4 cards | `forMember()` for My Bill; `Member::mealEntries()` this-month sum for My Meals; `AdvanceBalance::balance/due_balance` for My Advance; recent `Payment` rows for My Payment History. |
| DASH-05 | Dashboard cards cached 1h, invalidated on write | Reuse Phase 3 cache infrastructure (`AppServiceProvider::boot()` already forgets the bill-preview key on writes to 5 models). New count keys must hook into the same events. |
| DASH-06 | Charts support date-range filtering | Range picker writes to URL query (sticky, D-18-style); server buckets per D-08. See **Auto-Bucket Rule**. |
</phase_requirements>

## Project Constraints (from CLAUDE.md + codebase)

No project-root `CLAUDE.md` exists at `D:\Devsroom-Work\devsroom-mess-management\`. The actionable directives come from `.planning/codebase/CONVENTIONS.md`, `.planning/codebase/INTEGRATIONS.md`, and `.commandcode/taste/taste.md`. Phase 4 MUST honor:

- **PHP 8.3+ / Laravel 13.15** (runtime PHP 8.4) — all new code must run on both. `[VERIFIED: composer.json, STACK.md]`
- **MySQL only** for dev + test (taste pref). `phpunit.xml` is on `devsroom_mess_management_testing` MySQL DB, **not** sqlite (Phase 3.3 fix). `[VERIFIED: phpunit.xml, STATE.md session note]`
- **PSR-12 + Laravel Pint** (`vendor/bin/pint --test` must stay clean — gated by all prior phases).
- **Attribute-based models** (`#[Fillable(...)]`, `casts()` method — not `$fillable`/`$casts` properties).
- **Anonymous-class migrations** (`return new class extends Migration {}`).
- **Service layer, no Repository pattern** — a `ReportService` / `DashboardService` fits in `app/Services/`.
- **Form Requests for ALL user input** — applies to the report filter GET params (a `ReportFiltersRequest` with `validate()` rules), even though they're read-only.
- **Tyro role checks**: `$user->hasRole('admin')` = manager, `'user'` = member, `'super-admin'`. Route middleware: `role:admin`, `role:user`, `role:super-admin`. `EnsureMessExists` wraps every mess-scoped route.
- **`Mess::activeId()`** is the only correct source for `mess_id` — never hard-code.
- **Mobile-first 375px**, Tailwind v4 + Blade, no inline CSS, `__()` on every user-facing string, `min-h-[44px]` touch targets (matches existing nav/components).
- **Cache = `database` driver, NO tags** (`Cache::remember`/`Cache::forget` with string keys only — see INTEGRATIONS.md).
- **PHPUnit 12** (not Pest) — `test_` snake_case prefix, `RefreshDatabase`, `$this->get/post()` feature tests.
- **`App\Notifications`** is a custom model, NOT Laravel's `Illuminate\Notifications`. (Doesn't directly affect Phase 4 but relevant if reports link to notification center.)
- **Vite + Tailwind entry points**: `resources/css/app.css` + `resources/js/app.js` (already in `vite.config.js` input + `@vite(...)` in layout head). Chart.js must hook into this same bundle.

## Standard Stack

### Core (Phase 4 additions — none installed yet)

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| `barryvdh/laravel-dompdf` | **v3.1.2** | PDF generation via `Pdf::loadView()` | De facto Laravel PDF lib; v3.1.1+ explicitly adds Laravel 13 to its `illuminate/support` constraint (PR by Laravel Shift). Wraps `dompdf/dompdf ^3.0`. `[VERIFIED: packagist p2 metadata 2026-06-17]` |
| `maatwebsite/excel` | **3.1.69** | `.xlsx` export via `FromCollection`/`FromQuery` + `Excel::download()` | Named in RPT-08; the only mature Laravel Excel wrapper. 3.1.68+ adds `\|\|^13.0` to its `illuminate/support` constraint. Bundles `phpoffice/phpspreadsheet ^1.30.4`. `[VERIFIED: packagist p2 metadata + composer show -a]` |
| `chart.js` (npm) | **4.5.1** | Client-side line + bar charts | Named in D-01; lightweight, Tailwind-friendly, no Laravel-charting dep. `[VERIFIED: npm view chart.js version]` |

### Already installed (verified — reuse, do not reinstall)

| Library | Version | Phase 4 use |
|---------|---------|-------------|
| `laravel/framework` | 13.15 | `Cache`, `Pdf` facade (after dompdf install), `Excel` facade (after excel install), Blade, Eloquent |
| `owen-it/laravel-auditing` | ^14.0 | Reports are read-only — no audit writes needed, but `MonthlyClosing`/`MonthlyCorrection` audit trail already exists |
| `tailwindcss` | ^4.0 | Card + chart + filter-bar styling |
| `laravel-vite-plugin` | ^2.0 | Already wired; chart.js hooks into existing input list |

### Alternatives Considered

| Instead of | Could Use | Tradeoff (why we stick with the standard) |
|------------|-----------|------------------------------------------|
| `barryvdh/laravel-dompdf` | `laravel/snappy` (wkhtmltopdf) | Snappy needs a system binary; Dompdf is pure-PHP. Bengali font handling is bad in both (deferred to v2 anyway). CONTEXT D-10 names Dompdf. |
| `barryvdh/laravel-dompdf` | `dompdf/dompdf` directly | Loses the Laravel facade + `loadView()` + config publishing. No upside. |
| `maatwebsite/excel` 3.1.x | `phpoffice/phpspreadsheet` directly | Loses `FromCollection`/`WithHeadings`/`WithMapping` ergonomics + the `Excel::download()` facade. Much more boilerplate. |
| `maatwebsite/excel` | `spatie/simple-excel` | Lighter, but no `.xlsx` write styling; CSV-only is insufficient for RPT-08. |
| `chart.js` | ApexCharts | CONTEXT D-01 explicitly chose Chart.js; ApexCharts adds a license check for advanced features and is heavier. |
| `chart.js` | `consoletvs/charts` (Laravel charting pkg) | Abandoned-ish; pulls in unwanted JS deps. CONTEXT D-01: "no extra Laravel charting package." |

**Installation commands (run in Plan 4.1 Wave 0):**
```bash
# Composer (PDF + Excel)
composer require barryvdh/laravel-dompdf:^3.1
composer require maatwebsite/excel:^3.1

# npm (Chart.js)
npm install chart.js
```

Both composer packages use **package auto-discovery** — no manual provider registration needed on Laravel 13. `[CITED: github.com/barryvdh/laravel-dompdf README, laravel-excel.com/3.1/getting-started/installation.html]`

**Version verification snapshot (this session):**
```
barryvdh/laravel-dompdf v3.1.2 — illuminate/support: ^9|^10|^11|^12|^13.0, dompdf/dompdf: ^3.0, php: ^8.1
maatwebsite/excel        3.1.69 — illuminate/support: ...||^12.0||^13.0,  phpspreadsheet: ^1.30.4
chart.js                 4.5.1 (latest)
```

## Architecture Patterns

### Recommended Project Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Mess/
│   │   │   ├── ReportController.php          # Monthly + Member Statement + Expense + Payment (HTML)
│   │   │   └── ReportExportController.php    # PDF + Excel variants (or fold into ReportController)
│   │   └── My/
│   │       └── MyReportController.php        # member-side Monthly (aggregates) + Member Statement
│   └── Requests/
│       └── Report/
│           ├── MonthNavigationRequest.php    # ?year=?&month=? validation
│           ├── ExpenseReportRequest.php      # ?from=&to=&category_id=&month=  (D-18)
│           └── PaymentReportRequest.php      # ?member_id=&method=&from=&to=   (D-18)
├── Services/
│   ├── BillPreviewService.php                # EXISTING — do not modify
│   ├── BillPreviewInvalidator.php            # EXISTING — do not modify
│   ├── ReportService.php                     # NEW — closed-vs-open month routing (D-26) + report-specific queries
│   ├── DashboardService.php                  # NEW — 6 manager cards + 4 member cards + chart series
│   └── ChartBucketingService.php             # NEW (optional) — auto-bucket logic for D-08
├── Exports/                                   # NEW directory (Maatwebsite convention)
│   ├── MonthlyReportExport.php
│   ├── MemberStatementExport.php
│   ├── ExpenseReportExport.php
│   └── PaymentReportExport.php
└── Support/
    └── Money.php                             # EXISTING — the actual money helper (see "Gap 1")

resources/
├── views/
│   ├── mess/
│   │   └── reports/                           # NEW
│   │       ├── monthly.blade.php
│   │       ├── member-statement.blade.php
│   │       ├── expenses.blade.php
│   │       ├── payments.blade.php
│   │       ├── _filters/                      # shared filter-bar partials
│   │       └── pdf/                           # PDF-specific layouts (no app chrome)
│   │           ├── _header.blade.php          # mess name + period
│   │           ├── _footer.blade.php          # generated date + counter(page)
│   │           ├── monthly.blade.php
│   │           ├── member-statement.blade.php
│   │           ├── expenses.blade.php
│   │           └── payments.blade.php
│   ├── my/
│   │   ├── reports/                           # NEW (D-32)
│   │   │   ├── statement.blade.php
│   │   │   └── monthly.blade.php              # aggregates-only (D-19)
│   │   └── _overview.blade.php                # NEW — D-16 member Overview cards
│   ├── home.blade.php                         # REPLACE — D-14 dashboard
│   ├── my.blade.php                           # EXTEND — add Overview + My reports tab
│   ├── components/
│   │   ├── month-nav.blade.php                # NEW — ◀ Month ▶ + dropdown (D-20, analogous to mess-date-nav)
│   │   ├── stat-card.blade.php                # NEW — reusable dashboard card
│   │   └── report-toolbar.blade.php           # NEW — PDF/Excel download buttons + filter presets
│   └── layouts/
│       ├── app.blade.php                      # EXTEND — add "Reports" sidebar group (D-31)
│       └── pdf.blade.php                      # NEW — minimal layout for PDFs (no sidebar)
└── js/
    └── app.js                                 # EXTEND — import chart.js, expose window.initChart()

routes/web.php                                 # EXTEND — add report routes + member report routes
tests/Feature/
├── Report/                                    # NEW
└── Dashboard/                                 # NEW
```

### Pattern 1: Controller → Service → View (no Repository, no API Resource)

Reports are read-only; the controller is thin. Report-specific queries live in `ReportService`, which delegates to `BillPreviewService` for anything bill-shaped and to direct Eloquent for raw lists (expenses/payments). The D-26 closed-vs-open switch is centralized.

```php
// Source: matches existing app/Http/Controllers/Mess/BillPreviewController.php pattern
namespace App\Http\Controllers\Mess;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\MonthNavigationRequest;
use App\Services\ReportService;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
    ) {}

    public function monthly(MonthNavigationRequest $request): View
    {
        $year  = $request->integer('year', now()->year);
        $month = $request->integer('month', now()->month);

        // D-26: service picks snapshot vs live compute internally
        $data = $this->reports->monthlyReport($year, $month);

        return view('mess.reports.monthly', compact('data', 'year', 'month'));
    }
}
```

### Pattern 2: D-26 Closed-vs-Open Switch (centralize in ReportService)

```php
// Source: derived from Phase 3 D-16 + BillPreviewService source
class ReportService
{
    public function __construct(
        private readonly BillPreviewService $preview,
    ) {}

    public function monthlyReport(int $year, int $month): array
    {
        $messId = Mess::activeId();

        // Closed month → use the immutable snapshot
        $closing = MonthlyClosing::query()
            ->where('mess_id', $messId)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        if ($closing) {
            return $this->fromSnapshot($closing);   // monthly_closings totals + monthly_member_summaries rows
        }

        // Open or never-closed month → live compute (caps at last day of month for past months)
        return $this->preview->preview($year, $month);
    }
}
```

### Pattern 3: Route Shape for HTML/PDF/Excel Variants

Use distinct route names per content type (cleaner than `?format=pdf` query switching) — matches the existing `mess.closings.show` naming pattern.

```php
// routes/web.php — appended to the existing role:admin + EnsureMessExists group
Route::prefix('mess/reports')->name('mess.reports.')->group(function () {
    Route::get('monthly',                  [ReportController::class, 'monthly'])->name('monthly');
    Route::get('monthly.pdf',              [ReportExportController::class, 'monthlyPdf'])->name('monthly.pdf');
    Route::get('monthly.xlsx',             [ReportExportController::class, 'monthlyExcel'])->name('monthly.xlsx');

    Route::get('member-statement',                 [ReportController::class, 'memberStatement'])->name('member-statement');
    Route::get('member-statement/{member}/pdf',    [ReportExportController::class, 'memberStatementPdf'])->name('member-statement.pdf');
    Route::get('member-statement/{member}/xlsx',   [ReportExportController::class, 'memberStatementExcel'])->name('member-statement.xlsx');

    Route::get('expenses',  [ReportController::class, 'expenses'])->name('expenses');
    Route::get('expenses.pdf',  [ReportExportController::class, 'expensesPdf'])->name('expenses.pdf');
    Route::get('expenses.xlsx', [ReportExportController::class, 'expensesExcel'])->name('expenses.xlsx');

    Route::get('payments',  [ReportController::class, 'payments'])->name('payments');
    Route::get('payments.pdf',  [ReportExportController::class, 'paymentsPdf'])->name('payments.pdf');
    Route::get('payments.xlsx', [ReportExportController::class, 'paymentsExcel'])->name('payments.xlsx');
});

// Member-side (role:user) — D-32, scoped to own data + aggregates-only monthly (D-19)
Route::middleware(['auth', 'role:user'])->prefix('my/reports')->name('my.reports.')->group(function () {
    Route::get('statement',       [MyReportController::class, 'statement'])->name('statement');
    Route::get('statement.pdf',   [MyReportController::class, 'statementPdf'])->name('statement.pdf');
    Route::get('statement.xlsx',  [MyReportController::class, 'statementExcel'])->name('statement.xlsx');
    Route::get('monthly',         [MyReportController::class, 'monthly'])->name('monthly');       // aggregates only
    Route::get('monthly.pdf',     [MyReportController::class, 'monthlyPdf'])->name('monthly.pdf');
    Route::get('monthly.xlsx',    [MyReportController::class, 'monthlyExcel'])->name('monthly.xlsx');
});
```

**Authorization note:** the manager's `member-statement/{member}` route must verify the `{member}` belongs to the active mess — leverage the existing `BelongsToActiveMess` scope / `MessScope` (already in place per 04-CONTEXT `<code_context>`). The member's own `my/reports/statement` derives `$member` from `$request->user()->getMemberOrNull()` (the exact pattern in `MyController::index`), never from a URL param — no cross-member data leak.

### Pattern 4: PDF Export with Header/Footer + Page Numbers

Dompdf v3 (via the wrapper) renders header/footer by **`position: fixed`** elements repeated on every page, plus `@page` margins to reserve space. Page numbers use the CSS `counter(page)` function. `[CITED: github.com/dompdf/dompdf/issues/1190, Laracasts DOMPDF thread]`

```php
// Source: barryvdh/laravel-dompdf v3.x API + dompdf CSS counter pattern
use Barryvdh\DomPDF\Facade\Pdf;

public function monthlyPdf(int $year, int $month)
{
    $data = $this->reports->monthlyReport($year, $month);
    $mess = Mess::findOrFail(Mess::activeId());

    $pdf = Pdf::loadView('mess.reports.pdf.monthly', [
        'data' => $data,
        'mess' => $mess,
        'period' => ucfirst(Carbon::create($year, $month)->translatedFormat('F Y')),
        'generatedAt' => now()->format('d-m-Y H:i'),
    ])
        ->setPaper('a4', 'portrait')
        ->setOption('isHtml5ParserEnabled', true)
        ->setOption('isRemoteEnabled', false); // no external images/fonts (offline-safe)

    return $pdf->download("monthly-report-{$year}-{$month}.pdf");
}
```

```html
{{-- resources/views/mess/reports/pdf/monthly.blade.php --}}
@extends('layouts.pdf')

@section('report-body')
    {{-- Header: fixed → repeats on every page --}}
    <div class="pdf-header">
        <strong>{{ $mess->name }}</strong> — {{ __('Monthly Report') }} — {{ $period }}
    </div>

    {{-- Body — D-13 column-compacted per-member table --}}
    <table class="pdf-table-compact">
        <thead>
            <tr>
                <th>{{ __('Member') }}</th>
                <th class="num">{{ __('Meals') }}</th>
                <th class="num">{{ __('Meal cost') }}</th>
                <th class="num">{{ __('Fixed') }}</th>
                <th class="num">{{ __('Bill') }}</th>
                <th class="num">{{ __('Due') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data['members'] as $row)
                <tr>
                    <td>{{ $row['name'] }}</td>
                    <td class="num">{{ number_format($row['meals'], 1) }}</td>
                    <td class="num">{{ $row['meal_cost'] }}</td>  {{-- raw number, see D-12 Excel recommendation --}}
                    <td class="num">{{ $row['fixed_share'] }}</td>
                    <td class="num">{{ $row['bill'] }}</td>
                    <td class="num">{{ $row['due'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Footer: fixed → repeats; counter(page) prints "Page N" --}}
    <div class="pdf-footer">
        <span class="page-num"></span>
        <span class="generated">{{ __('Generated') }}: {{ $generatedAt }}</span>
    </div>
@endsection
```

```css
/* resources/views/layouts/pdf.blade.php <style> block */
@page { margin: 140px 30px 80px 30px; }   /* reserve space for fixed header/footer */

.pdf-header { position: fixed; top: 30px; left: 30px; right: 30px; height: 90px;
              border-bottom: 1px solid #999; font-size: 11px; }
.pdf-footer { position: fixed; bottom: 30px; left: 30px; right: 30px;
              border-top: 1px solid #999; font-size: 9px; color: #666;
              display: flex; justify-content: space-between; }
.pdf-footer .page-num::after { content: "Page " counter(page); }

.pdf-table-compact { width: 100%; border-collapse: collapse; font-size: 9px; }  /* D-13 compaction */
.pdf-table-compact th, .pdf-table-compact td { border: 1px solid #ccc; padding: 2px 4px; }
.pdf-table-compact .num { text-align: right; white-space: nowrap; }
```

**Important Dompdf limitation:** `counter(page)` works; `counter(pages)` (total page count) does **NOT** work in pure CSS. `[VERIFIED: groups.google.com/g/dompdf]` CONTEXT D-13 asks for "generated-date + page number in the footer" — that means **"Page N"** not **"Page N of M"**. If the planner wants "N of M", it requires a two-pass render workaround (render once, count pages, re-render with the total) — **not recommended for v1**.

### Pattern 5: Excel Export with Raw Numbers (so the manager can sum/formula)

CONTEXT's discretion area explicitly recommends raw numbers. `FromCollection` + `WithHeadings` + `WithMapping` + `WithColumnFormatting` + `ShouldAutoSize` covers every report.

```php
// Source: laravel-excel.com/3.1/exports/collection.html + mapping.html + column-formatting.html
namespace App\Exports;

use App\Models\Payment;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class PaymentReportExport implements FromQuery, WithHeadings, WithMapping, WithColumnFormatting, ShouldAutoSize
{
    public function __construct(
        private readonly int $messId,
        private readonly ?string $from = null,
        private readonly ?string $to = null,
        private readonly ?int $memberId = null,
        private readonly ?string $method = null,
    ) {}

    public function query()
    {
        return Payment::query()
            ->where('mess_id', $this->messId)
            ->when($this->from, fn ($q, $d) => $q->where('date', '>=', $d))
            ->when($this->to,   fn ($q, $d) => $q->where('date', '<=', $d))
            ->when($this->memberId, fn ($q, $id) => $q->where('member_id', $id))
            ->when($this->method,  fn ($q, $m) => $q->where('method', $m))
            ->orderBy('date');
    }

    public function headings(): array
    {
        return ['Date', 'Member', 'Method', 'Type', 'Amount', 'Reference'];
    }

    /** @param Payment $row */
    public function map($row): array
    {
        return [
            $row->date->format('Y-m-d'),
            $row->member->name,
            $row->method,
            $row->type,
            (float) $row->amount,    // RAW NUMBER — Excel treats it as numeric, manager can SUM()
            $row->reference,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'E' => NumberFormat::FORMAT_NUMBER_00,   // Amount column = 2-decimal number
        ];
    }
}
```

```php
// Controller action
use App\Exports\PaymentReportExport;
use Maatwebsite\Excel\Facades\Excel;

public function paymentsExcel(PaymentReportRequest $request)
{
    $export = new PaymentReportExport(
        messId: Mess::activeId(),
        from: $request->date('from'),
        to: $request->date('to'),
        memberId: $request->integer('member_id') ?: null,
        method: $request->input('method') ?: null,
    );

    $filename = sprintf('payments-%s-to-%s.xlsx', $request->input('from', 'all'), $request->input('to', 'now'));
    return Excel::download($export, $filename);
}
```

**`FromCollection` vs `FromQuery`:** use `FromQuery` when the dataset could be large (expenses/payments across many months) — Maatwebsite chunks it automatically and avoids loading everything into memory. `[CITED: laravel-excel.com/3.1/getting-started/installation.html]` Use `FromCollection` for small fixed-shape exports like the Monthly Report's per-member table (built from `BillPreviewService::preview()` which returns an array, not a query).

### Pattern 6: Chart.js via Vite + Blade `@json` (D-01)

**Recommendation on the discretion area:** wire Chart.js into the **existing global `resources/js/app.js`** (not a per-page chunk). Rationale: 3 charts all live on `/home`, the layout already loads `app.js` everywhere via `@vite(...)`, and a per-page chunk adds Vite config complexity for ~70KB of gzipped Chart.js. Laravel Vite plugin already handles code-splitting automatically if a chunk is wanted later.

```js
// resources/js/app.js  (extended)
import './bootstrap';
import Chart from 'chart.js/auto';   // auto-register all controllers (line + bar needed)

// Expose a global init fn that Blade can call with @json datasets
window.initDashboardChart = function (canvasId, config) {
    const el = document.getElementById(canvasId);
    if (!el) return null;

    // D-08 + Chart.js contract: destroy before recreate when range changes
    if (el.__chart) { el.__chart.destroy(); }
    el.__chart = new Chart(el.getContext('2d'), {
        type: config.type,            // 'line' for Meal Trend, 'bar' for Expense/Payment
        data: config.data,            // { labels: [...], datasets: [{ label, data, borderColor/backgroundColor }] }
        options: {
            responsive: true,
            maintainAspectRatio: false,   // KEY for 375px — chart fills its parent's fixed height
            plugins: {
                legend: { labels: { boxWidth: 12, font: { size: 11 } } },
                tooltip: { mode: 'index', intersect: false },
            },
            scales: {
                x: { ticks: { font: { size: 10 }, maxRotation: 45 } },
                y: { ticks: { font: { size: 10 } }, beginAtZero: true },
            },
        },
    });
    return el.__chart;
};
```

```blade
{{-- resources/views/home.blade.php (D-14 dashboard) --}}
<div class="chart-card">
    <h3>{{ __('Meal Trend') }}</h3>
    {{-- Parent MUST have a fixed height for maintainAspectRatio:false to work --}}
    <div style="height: 280px;">
        <canvas id="meal-trend-chart"></canvas>
    </div>
</div>

@once
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            window.initDashboardChart('meal-trend-chart', {
                type: 'line',
                data: {
                    labels: @json($charts['meal']['labels']),
                    datasets: [{
                        label: '@lang('Meals')',
                        data: @json($charts['meal']['values']),
                        borderColor: '#059669',
                        backgroundColor: 'rgba(5,150,105,0.1)',
                        tension: 0.3, fill: true,
                    }],
                },
            });
        });
    </script>
@endonce
```

**Range-change flow (D-03 + D-06):** each chart has its own `<form method="GET">` with `from`/`to` (or `preset`) inputs that update the URL query string — sticky URLs (D-18 pattern), no AJAX. Server re-runs the bucketed query, the page re-renders with fresh `@json`, and `initDashboardChart` destroys the old chart before creating the new one (the `if (el.__chart) destroy()` guard handles it).

### Pattern 7: Auto-Bucket Rule (D-08)

Recommendation on the discretion area: **60-day threshold, bucket labels fixed.**

```php
// Source: D-08 + standard time-series bucketing
class ChartBucketingService
{
    public function bucket(Carbon $from, Carbon $to): array
    {
        $days = $from->diffInDays($to);

        if ($days <= 60) {
            return ['granularity' => 'day',   'step' => '1 day'];
        }
        if ($days <= 365) {
            return ['granularity' => 'week',  'step' => '1 week'];
        }
        return ['granularity' => 'month', 'step' => '1 month'];
    }
}
```

The chart's series is built by iterating buckets and running one aggregated query per bucket (or, for daily, a single `GROUP BY DATE(...)` query). See **Trend Query Patterns** below.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Bill math (meal rate, fixed share, due, advance) | Re-derive totals from raw `meal_entries`/`expenses`/`payments` in a new service | `BillPreviewService::preview()` / `forMember()` | Already correct, already cached, already battle-tested by Phase 3 (4 unit tests for math parity against close). Re-deriving risks divergence. |
| Closed-month snapshot lookup | Re-query raw data for past months | `MonthlyClosing` + `MonthlyMemberSummary` (immutable snapshot) | D-26 lock + CLOSE-09 immutability. Live re-compute for a closed month can give different numbers than the close did (if data was edited after close, which is forbidden, but defensive). |
| Currency formatting | `number_format($x, 2) . ' ৳'` inline | `App\Support\Money::taka($x)` | Single source of truth; the 14 callsites that already use it prove it's the project's de facto helper (even though CONTEXT calls it `bdt()` — see Gap 1). |
| Cache invalidation | Hook into `saved`/`deleted` again for new models | Existing `AppServiceProvider::registerBillPreviewInvalidation()` already covers `MealEntry`, `GuestMeal`, `MealOffRequest`, `Expense`, `Payment` | Re-registering listeners double-fires the invalidation. Extend the existing hook only if a new count-cache key needs additional invalidation, and only in the same listener body. |
| PDF page numbers | Compute page count and inject as a string | CSS `counter(page)` in a fixed-position footer | Dompdf supports it natively; manual counting needs a 2-pass render (skip for v1). |
| Excel column types | Cast everything to a formatted string | `WithColumnFormatting` + `NumberFormat::FORMAT_NUMBER_00` | Raw numbers let the manager SUM/AVERAGE in Excel; strings don't. D-12 + discretion area both favor raw numbers. |
| Chart rendering | Build SVG/canvas primitives by hand | Chart.js 4.5.1 | 1-line config; mobile responsive handled by `maintainAspectRatio:false`. |
| Date nav (◀ ▶ + picker) | New month-picker component | Adapt the existing `<x-mess-date-nav>` (already at `resources/views/components/mess-date-nav.blade.php`) | Phase 2 D-XX pattern locked; CONTEXT D-20 says "matches Phase 2 meal-grid date nav." Build `<x-month-nav>` mirroring its structure. |
| Member tab nav | Rebuild the tabs for the new "My reports" + Overview entries | Existing `<x-tab-nav>` component + the `$tabs` array pattern in `my.blade.php` | Already supports arbitrary tabs; just add 2 entries. |
| Money for trend charts | `SUM(amount)` then divide | `->sum('amount')` on a queryBuilder | MySQL decimal arithmetic is exact (PITFALLS #2 prevention); PHP float would lose precision. |

**Key insight:** Phase 4 is overwhelmingly integration work on top of correctly-built Phase 1-3 services. The biggest risk is **not** building new logic — it's re-deriving numbers that `BillPreviewService` already produces and accidentally diverging.

## BillPreviewService Return Shape (exact field map)

Reading `app/Services/BillPreviewService.php` lines 87-196 verbatim. The planner wires reports and dashboard cards to **these exact keys** — no translation layer needed.

### `preview(int $year, int $month): array` — top-level shape

```
[
    'year'          => int,        // the year passed in
    'month'         => int,        // the month passed in
    'total_bazar'   => float,      // SUM(expenses.amount) where category.kind=bazar, this month
    'total_meals'   => float,      // SUM of meal values across denominator-eligible members
    'meal_rate'     => float,      // round(total_bazar / total_meals, 2) — 0.0 when no meals
    'total_fixed'   => float,      // SUM(expenses.amount) where category.kind=fixed, this month
    'days_in_month' => int,        // Carbon::create($y,$m,1)->daysInMonth
    'members'       => array<int, array>,  // one row per ACTIVE+FORMER member, see below
]
```

### `members[]` row shape (each entry)

| Key | Type | Source / formula | Card/report use |
|-----|------|------------------|-----------------|
| `member_id` | int | `Member::id` | Member Statement lookup |
| `name` | string | `Member::name` | All per-member tables |
| `meals` | float | sum of B(0.5)+L(1)+D(1) for the member this month | RPT-02 meals summary, DASH-04 My Meals |
| `meal_cost` | float | `round(meals * meal_rate, 2)` | RPT-02, member statement math line (D-25) |
| `fixed_share` | float | `round(total_fixed * active_days / days_in_month, 2)` | RPT-02 fixed share section |
| `guest_total` | float | SUM(guest_meals.charge_amount) this month | RPT-02 guest meals section |
| `bill` | float | `round(meal_cost + fixed_share + guest_total, 2)` | RPT-02 closing bill, DASH-01 Total (sum across members) |
| `bill_payments` | float | SUM(payments.amount) where `type=bill_payment` this month | RPT-02 payments section, "Paid so far" |
| `advance_payments` | float | SUM(payments.amount) where `type=advance_deposit` this month | RPT-02 payments section |
| `advance_applied` | float | **identical to `bill_payments`** (see code comment at lines 152-161: name retained for stability, NOT advance-consuming logic) | **Do not use as "advance consumed"** — it equals `bill_payments`. |
| `due` | float | `round(bill - advance_applied, 2)` = `round(bill - bill_payments, 2)` | RPT-02 due, DASH-01 Total Due |
| `advance_balance` | float | `AdvanceBalance::balance` (carried-forward credit, current value) | DASH-01 Total Advance, DASH-04 My Advance |
| `due_balance` | float | `AdvanceBalance::due_balance` (carried-forward debt, current value) | RPT-02 opening due |
| `active_days` | int | days this member was active in the month (1..days_in_month) | Proration audit (D-12 from Phase 3) |
| `status` | string | `Member::status` (active/inactive/former) | Filter / grey-out indicator |

### `forMember(int $memberId, int $year, int $month): ?array`

Returns **one member's row** from `preview()['members']` (or `null` if the member has no row). Same shape as a single `members[]` entry above. Used directly by:
- `MyBillPreviewController::index` (existing — Phase 3)
- DASH-04 "My Bill" card (Phase 4)
- RPT-05 member Member Statement (Phase 4)
- RPT-02 manager Member Statement (Phase 4 — wrap with daily breakdown)

### `cacheKey(int $messId, int $year, int $month): string`

Returns `"bill-preview:{$messId}:{$year}-" . str_pad($month, 2, '0', STR_PAD_LEFT)`. Example: `bill-preview:1:2026-06`. **Phase 4 reuses this exact key** for all bill-derived dashboard cards and for the Monthly Report's totals (D-17).

### Important caveats (from reading the source)

1. **`advance_applied` is misleadingly named** — see the in-code comment at lines 152-161. It snapshots `bill_payments`, not advance consumption. The `due` formula is `bill - bill_payments` (advance deposits live in `advance_balance` / `due_balance` separately). Reports MUST NOT present `advance_applied` as "advance used against this bill" — that would misrepresent the locked Phase 3 model.
2. **`members[]` includes `FORMER` members** (line 117: `whereIn('status', [ACTIVE, FORMER])`). The Monthly Report's per-member table will show former members too — that's correct (their bills still need settling) but the planner may want a status badge column.
3. **Denominator excludes mid-month joiners/leavers** (lines 128-135, 297-312). `total_meals` only counts eligible members; `meal_rate` is correct. Don't recompute.
4. **`meal_rate` is `0.0` when `total_meals <= 0`** (line 137). Triggers DASH-01/D-29 "no bazar recorded yet" empty state.
5. **All money values are `float`** in the array (PHP), backed by `DECIMAL(10,2)` in MySQL. Display via `Money::taka()` (or `bdt()` if Gap 1 resolves that way); export to Excel as raw floats with `NumberFormat::FORMAT_NUMBER_00`.

## Dashboard Cache Strategy (D-17 concrete keys + hooks)

### Reuse: bill-derived cards (read `bill-preview:{mess_id}:{YYYY}-{MM}`)

| DASH-01/04 card | BillPreviewService field | Notes |
|---|---|---|
| Current Meal Rate | `preview.meal_rate` | |
| Total Due (manager) | `array_sum(array_column($preview['members'], 'due'))` | Sum across members |
| Total Advance (manager) | `array_sum(array_column($preview['members'], 'advance_balance'))` | Sum across members |
| My Bill (member) | `forMember(...).bill` | |
| My Advance (member) | `forMember(...).advance_balance` | |

These need **no new cache key** — calling `BillPreviewService::preview()` or `forMember()` already hits `Cache::remember($cacheKey, now()->addHour(), …)`. `[VERIFIED: BillPreviewService.php lines 36-38]`

### New: short-TTL count keys (database driver, no tags)

```
dash:counts:{mess_id}:{YYYY}-{MM}     →  { total_members, today_meals, monthly_expenses }
```

- **TTL:** 1 hour (matches the bill-preview pattern, D-17 / DASH-05).
- **Why one composite key, not three:** the database cache driver doesn't support tags (PITFALLS #11 prevention: avoid tag-dependency). One key = one `Cache::forget` call on any invalidating write.
- **Granularity:** keyed by `(mess_id, year, month)` so cross-month navigation doesn't pollute. `today_meals` recomputes on each miss but that's fine (1 query).

```php
// DashboardService.php
public function managerCards(): array
{
    $messId = Mess::activeId();
    $now    = now();
    $key    = "dash:counts:{$messId}:{$now->year}-" . str_pad($now->month, 2, '0', STR_PAD_LEFT);

    return Cache::remember($key, now()->addHour(), function () use ($messId, $now) {
        return [
            'total_members'    => Member::where('mess_id', $messId)->where('status', 'active')->count(),
            'today_meals'      => $this->todayMealTotal($messId, $now),
            'monthly_expenses' => (float) Expense::where('mess_id', $messId)
                ->whereBetween('date', [$now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString()])
                ->sum('amount'),  // discretion area: confirm bazar-only vs total; default total
        ];
    });
}

private function todayMealTotal(int $messId, Carbon $date): float
{
    // SUM of meal values across today's entries (breakfast=0.5, lunch=1, dinner=1)
    return (float) MealEntry::where('mess_id', $messId)->where('date', $date->toDateString())
        ->selectRaw('SUM(breakfast * 0.5 + lunch * 1.0 + dinner * 1.0) AS total')
        ->value('total');
}
```

> **Note on `today_meals`:** `MealEntry::breakfast/lunch/dinner` are booleans; the meal values (0.5/1/1) are settings. For correctness with per-mess overrides, read the configured meal values rather than hard-coding 0.5/1/1. The hard-coded version matches `BillPreviewService::mealTotals()` which uses `MealType::value(...)` — reuse `MealType::value()` here too for consistency.

### Invalidation hooks (extend the existing `AppServiceProvider` listener)

The existing `registerBillPreviewInvalidation()` already forgets the bill-preview key on writes to 5 models. To invalidate the count cache too, **extend the same listener body** (don't add a second listener — it would double-fire):

```php
// app/Providers/AppServiceProvider.php — extend invalidateForModel()
private function invalidateForModel(BillPreviewInvalidator $invalidator, Model $model): void
{
    $date = match (true) {
        isset($model->date) => $model->date,
        isset($model->from_date) => $model->from_date,
        isset($model->created_at) => $model->created_at,
        default => null,
    };

    if ($date === null) return;

    $dateStr = $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : (string) $date;
    $invalidator->forDate($dateStr);

    // NEW for Phase 4: also forget the matching count key
    $carbon = Carbon::parse($dateStr);
    $messId = Mess::activeId();
    if ($messId !== null) {
        Cache::forget("dash:counts:{$messId}:{$carbon->year}-" . str_pad($carbon->month, 2, '0', STR_PAD_LEFT));
    }
}
```

**`< 2s` refresh proof (success criterion #12):** the write transaction commits → Eloquent fires `saved`/`deleted` → the listener runs synchronously (Laravel's default event dispatcher) → both cache keys are forgotten → the next GET recomputes. No async, no queue. Sub-millisecond invalidation. `[VERIFIED: matches Phase 3 D-15 pattern, validated in STATE.md]`

## Trend Query Patterns (DASH-02 + D-05/D-06/D-07 + D-08)

All three trend queries follow the same shape: build the bucket list, run one `GROUP BY` query (MySQL does the aggregation), and zip the results onto the bucket axis (filling missing buckets with 0). No N+1.

### Meal Trend (line, daily default) — D-05: daily meal count

```php
// Source: BillPreviewService::mealTotals() pattern + MySQL GROUP BY
public function mealTrend(int $messId, Carbon $from, Carbon $to): array
{
    $rows = MealEntry::query()
        ->where('mess_id', $messId)
        ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
        ->groupBy('date')
        ->orderBy('date')
        ->get(['date', \DB::raw('SUM(breakfast * 0.5 + lunch * 1.0 + dinner * 1.0) AS total')])
        ->keyBy('date');

    $labels = []; $values = [];
    $cursor = $from->copy();
    while ($cursor <= $to) {
        $labels[] = $cursor->format('d M');
        $values[] = (float) ($rows[$cursor->toDateString()]->total ?? 0);
        $cursor->addDay();
    }
    return ['labels' => $labels, 'values' => $values];
}
```

**Index exists:** `meal_entries` has `index(['mess_id', 'date'])` — covered. `[VERIFIED: migration 2026_06_16_220800]`

### Expense Trend (bar, monthly default) — D-06: monthly bazar total

```php
public function expenseTrend(int $messId, Carbon $from, Carbon $to): array
{
    // Filter to BAZAR kind only (D-06)
    $bazarCategoryIds = ExpenseCategory::where('kind', ExpenseKind::BAZAR)->pluck('id')->all();

    $rows = Expense::query()
        ->where('mess_id', $messId)
        ->whereIn('expense_category_id', $bazarCategoryIds)
        ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
        ->groupBy('period')           // 'period' = YEAR-month string
        ->orderBy('period')
        ->get([
            \DB::raw("DATE_FORMAT(date, '%Y-%m') AS period"),
            \DB::raw('SUM(amount) AS total'),
        ])
        ->keyBy('period');

    // build monthly buckets (or weekly/daily per ChartBucketingService)
    $labels = []; $values = [];
    $cursor = $from->copy()->startOfMonth();
    while ($cursor <= $to) {
        $key = $cursor->format('Y-m');
        $labels[] = $cursor->translatedFormat('M Y');
        $values[] = (float) ($rows[$key]->total ?? 0);
        $cursor->addMonth();
    }
    return ['labels' => $labels, 'values' => $values];
}
```

**Index exists:** `expenses` has `index(['mess_id', 'date'])` and `index(['mess_id', 'expense_category_id'])`. `[VERIFIED: migration 2026_06_16_221200]`

### Payment Trend (bar, monthly default) — D-07: monthly total collected

```php
public function paymentTrend(int $messId, Carbon $from, Carbon $to): array
{
    // All methods + both types (bill_payment + advance_deposit) — D-07 says "monthly total collected"
    $rows = Payment::query()
        ->where('mess_id', $messId)
        ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
        ->groupBy('period')
        ->orderBy('period')
        ->get([
            \DB::raw("DATE_FORMAT(date, '%Y-%m') AS period"),
            \DB::raw('SUM(amount) AS total'),
        ])
        ->keyBy('period');
    // ... same bucket-fill loop as expenseTrend
}
```

**Index exists:** `payments` has `index(['mess_id', 'date'])`. `[VERIFIED: migration 2026_06_16_221300]`

**Weekly bucket alternative:** for ranges 61-365 days, replace `DATE_FORMAT(date,'%Y-%m')` with `DATE_FORMAT(date,'%x-W%v')` (ISO year + week number) and iterate `$cursor->addWeek()`. Daily uses the raw `date` column. All three share the bucket-fill loop — extract to a helper.

## Filter UX Pattern (D-18, D-20)

### Report filter bar (Expense + Payment reports) — D-18

GET-form, sticky in URL, presets. Reuses the pattern from the existing `mess/bill-preview/index.blade.php` filter form (year/month dropdowns + Apply button):

```blade
{{-- resources/views/mess/reports/_filters/expenses.blade.php --}}
<form method="GET" class="flex flex-wrap items-end gap-2 mb-4">
    <label class="text-xs text-slate-600">{{ __('From') }}
        <input type="date" name="from" value="{{ request()->query('from') }}" class="block rounded-md border-slate-300">
    </label>
    <label class="text-xs text-slate-600">{{ __('To') }}
        <input type="date" name="to" value="{{ request()->query('to') }}" class="block rounded-md border-slate-300">
    </label>
    <label class="text-xs text-slate-600">{{ __('Category') }}
        <select name="category_id" class="block rounded-md border-slate-300">
            <option value="">{{ __('All') }}</option>
            @foreach ($categories as $c)
                <option value="{{ $c->id }}" @selected(request()->query('category_id') == $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
    </label>
    <button type="submit" class="rounded-md bg-slate-800 px-3 py-2 text-sm font-medium text-white">{{ __('Apply') }}</button>
    <a href="{{ route('mess.reports.expenses', ['from' => now()->startOfMonth()->toDateString(), 'to' => now()->endOfMonth()->toDateString()]) }}"
       class="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700">{{ __('This month') }}</a>
    <a href="{{ route('mess.reports.expenses', ['from' => now()->subMonth()->startOfMonth()->toDateString(), 'to' => now()->subMonth()->endOfMonth()->toDateString()]) }}"
       class="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700">{{ __('Last month') }}</a>
</form>
```

The controller reads `request()->only(['from','to','category_id'])` and feeds them to `ExpenseReportRequest` validation + the query. The PDF/Excel export endpoints accept the **same query string** (so the export matches what's on screen).

### Month nav (Monthly Report + Member Statement) — D-20

Build a new `<x-month-nav>` mirroring the existing `<x-mess-date-nav>` (which is day-scoped). Same structure: ◀ arrow, dropdown, ▶ arrow, "This month" link.

```blade
{{-- resources/views/components/month-nav.blade.php --}}
@props(['year', 'month', 'route' => 'mess.reports.monthly'])

@php
    $carbon = \Carbon\Carbon::create($year, $month, 1);
    $prev = ['year' => $carbon->copy()->subMonth()->year, 'month' => $carbon->copy()->subMonth()->month];
    $next = ['year' => $carbon->copy()->addMonth()->year, 'month' => $carbon->copy()->addMonth()->month];
    $thisMonth = ['year' => now()->year, 'month' => now()->month];
@endphp

<div class="flex items-center gap-2">
    <a href="{{ route($route, $prev) }}" class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center rounded-md border border-slate-300 bg-white">◀</a>
    <select onchange="window.location = this.value" class="min-h-[44px] rounded-md border-slate-300">
        @for ($y = now()->year - 2; $y <= now()->year; $y++)
            @for ($m = 1; $m <= 12; $m++)
                <option value="{{ route($route, ['year' => $y, 'month' => $m]) }}" @selected($y === $year && $m === $month)>
                    {{ \Carbon\Carbon::create($y, $m, 1)->translatedFormat('F Y') }}
                </option>
            @endfor
        @endfor
    </select>
    <a href="{{ route($route, $next) }}" class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center rounded-md border border-slate-300 bg-white">▶</a>
    <a href="{{ route($route, $thisMonth) }}" class="min-h-[44px] inline-flex items-center px-3 rounded-md border border-slate-300 bg-white text-sm">{{ __('This month') }}</a>
</div>
```

## Common Pitfalls (Phase 4-specific)

### Pitfall 1: Stale dashboard after first-of-month rollover

**What goes wrong:** On the 1st of the month at 00:01, the manager opens `/home`. The dashboard shows last month's bill preview because the cache key `bill-preview:1:2026-06` was populated yesterday and hasn't expired (1h TTL, but the write-hook only fires on actual writes — the date changed without a write).
**Why it happens:** The cache key includes the year-month, so a new month means a new key — which is a cache MISS and recomputes correctly. **Except** the count cache `dash:counts:{mess_id}:{YYYY}-{MM}` was populated last month and is now being read with last month's key. The controller needs to use `now()->year`/`now()->month` when building the key, which it does — so this is actually fine.
**How to avoid:** Always derive the cache key from `now()` (or from the explicitly-selected year/month for past-month navigation), never from a stored timestamp. Verify in tests by mocking `now()`.

### Pitfall 2: Chart.js canvas reuse leaks memory + draws ghost charts

**What goes wrong:** Range change re-renders the page; the old Chart instance lingers because it wasn't `destroy()`-ed.
**Why it happens:** Chart.js binds event listeners + a canvas context; without `destroy()`, each re-render stacks.
**How to avoid:** Pattern 6's `if (el.__chart) el.__chart.destroy()` guard. Store the instance on the element (`el.__chart`) so any subsequent init can find it.
**Warning signs:** Browser memory grows; chart canvas shows doubled lines.

### Pitfall 3: `advance_applied` field name misleads report authors

**What goes wrong:** A future author reads `BillPreviewService`'s `$row['advance_applied']` and assumes it's "advance balance consumed against this bill" — they build a report showing "Advance applied: ৳X" against the bill.
**Why it happens:** Misleading name (acknowledged in source code comment lines 152-161). Actually equals `bill_payments`.
**How to avoid:** In the Member Statement, present ONLY `bill_payments` (bill-type payments this month) and `advance_payments` (deposit-type payments this month), plus the `advance_balance` / `due_balance` carried-forward columns. Do NOT show `advance_applied` to users. Add a comment in `ReportService` pointing to the source-code caveat.

### Pitfall 4: Dompdf chokes on Tailwind CSS

**What goes wrong:** PDF view uses `<div class="grid grid-cols-3 ...">` (Tailwind utility classes) and the PDF renders unstyled.
**Why it happens:** Dompdf does NOT run JavaScript and has limited CSS support; Tailwind v4 utilities are JIT-compiled and rely on the Vite-built stylesheet, which Dompdf can't load (no browser).
**How to avoid:** **PDF views must use a dedicated minimal stylesheet inline in `layouts/pdf.blade.php`** (plain CSS, not Tailwind utilities). The screen report views can use Tailwind; the PDF export views are separate templates under `mess/reports/pdf/`. Do NOT try to reuse screen views for PDF.

### Pitfall 5: Excel SUM returns text-formatted numbers

**What goes wrong:** Manager opens the `.xlsx`, selects the Amount column, the Excel status bar shows "Count: 12" instead of "Sum: ৳1,234.00".
**Why it happens:** `WithMapping::map()` returned `$row->amount` as a string (e.g., via `bdt()` formatting) instead of a numeric type.
**How to avoid:** Pattern 5 — return `(float) $row->amount` from `map()` AND declare `columnFormats()` with `NumberFormat::FORMAT_NUMBER_00` for the amount column. Discretion area in CONTEXT explicitly recommends this.

### Pitfall 6: Cache stampede when 11 members open `/home` at 9am

**What goes wrong:** First-load-after-TTL-expiry triggers 11 simultaneous recomputations; DB load spikes.
**Why it happens:** Standard cache stampede — PITFALLS #15.
**How to avoid:** Phase 3 already locked the pattern: `Cache::remember` with 1h TTL means only the FIRST viewer pays the recompute cost; the rest get the cached value (Laravel's `Cache::lock()` is available if needed but the project's pattern doesn't use it). For Phase 4 the same applies. **No additional locking needed** — the queries are cheap (single-table `SUM` with index). `[CITED: .planning/research/PITFALLS.md #15]`

### Pitfall 7: Manager exports a member statement for a member in another mess

**What goes wrong:** `/mess/reports/member-statement/{member}/pdf` accepts any `member_id`; a manager with `mess_id=1` could request member from `mess_id=2`.
**Why it happens:** Missing scope enforcement on the route-model binding.
**How to avoid:** The existing `BelongsToActiveMess` / `MessScope` global scope (per 04-CONTEXT `<code_context>`) auto-filters queries by `mess_id`. Use explicit route-model binding (`Route::get('.../{member}', ...)` with `Member $member`) — the global scope will return 404 for a cross-mess member. **Verify with a feature test** that asserts a 403/404 when the member belongs to another mess.

## Code Examples (verified, ready to copy)

### Example 1: Minimal PDF download (Dompdf v3.x facade)

```php
// Source: github.com/barryvdh/laravel-dompdf README
use Barryvdh\DomPDF\Facade\Pdf;

public function downloadPdf()
{
    $pdf = Pdf::loadView('mess.reports.pdf.monthly', ['data' => $data])
        ->setPaper('a4', 'portrait');
    return $pdf->download('monthly-report.pdf');  // triggers browser download
    // Alternative: $pdf->stream() to display inline
}
```

### Example 2: Minimal Excel download (Maatwebsite 3.1.x)

```php
// Source: laravel-excel.com/3.1/exports/collection.html
use App\Exports\MonthlyReportExport;
use Maatwebsite\Excel\Facades\Excel;

public function downloadExcel()
{
    return Excel::download(new MonthlyReportExport($this->reports->monthlyReport($y, $m)), 'monthly-report.xlsx');
}
```

### Example 3: Dompdf PDF from an array (not a query) — Monthly Report

For the Monthly Report, data comes from `BillPreviewService::preview()` (an array), not a query. Use `FromCollection`:

```php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;       // Array, not Collection, for nested shape
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class MonthlyReportExport implements FromArray, WithHeadings, WithColumnFormatting, ShouldAutoSize
{
    public function __construct(private readonly array $preview) {}

    public function array(): array
    {
        return array_map(fn ($m) => [
            $m['name'],
            (float) $m['meals'],
            (float) $m['meal_cost'],
            (float) $m['fixed_share'],
            (float) $m['bill'],
            (float) $m['bill_payments'],
            (float) $m['due'],
        ], $this->preview['members']);
    }

    public function headings(): array
    {
        return ['Member', 'Meals', 'Meal Cost', 'Fixed', 'Bill', 'Paid', 'Due'];
    }

    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_NUMBER_00,
            'C' => NumberFormat::FORMAT_NUMBER_00,
            'D' => NumberFormat::FORMAT_NUMBER_00,
            'E' => NumberFormat::FORMAT_NUMBER_00,
            'F' => NumberFormat::FORMAT_NUMBER_00,
            'G' => NumberFormat::FORMAT_NUMBER_00,
        ];
    }
}
```

### Example 4: Chart.js minimal init (line + bar)

```js
// Line — Meal Trend (D-02)
new Chart(document.getElementById('meal-trend').getContext('2d'), {
    type: 'line',
    data: {
        labels: ['Day 1', 'Day 2', /* ... */],
        datasets: [{ label: 'Meals', data: [40.5, 42.0, /* ... */], borderColor: '#059669', tension: 0.3 }],
    },
    options: { responsive: true, maintainAspectRatio: false },
});

// Bar — Expense Trend (D-02)
new Chart(document.getElementById('expense-trend').getContext('2d'), {
    type: 'bar',
    data: {
        labels: ['Jan', 'Feb', /* ... */],
        datasets: [{ label: 'Bazar', data: [12000, 11500, /* ... */], backgroundColor: '#059669' }],
    },
    options: { responsive: true, maintainAspectRatio: false },
});
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `dompdf/dompdf` 2.x | `dompdf/dompdf` 3.x (via `barryvdh/laravel-dompdf` v3.x) | 2024 | v3 dropped PHP 7, added stricter filename checks, HTML5 parser improvements. v3.1.1+ adds Laravel 13. |
| `$casts` property | `casts()` method (Laravel 11+) | Laravel 11 | Already enforced in this project. |
| `Maatwebsite\Excel` 2.x | 3.1.x | 2019 | Different API (`FromQuery`/`FromCollection` instead of `Excel::create()`); the 3.x line is what supports Laravel 13. |
| `Chart.js` 3.x auto-register | `chart.js/auto` (Chart.js 4) | 2022 | `import Chart from 'chart.js/auto'` registers all controllers; no manual `registerables` import. |
| `bdt()` helper from `app/helpers.php` | **DOES NOT EXIST** in this codebase | — | Use `App\Support\Money::taka()` (14 callsites already) OR create the helper (Gap 1). |

**Deprecated/outdated:**
- `Maatwebsite\Excel` 2.x API (`Excel::create`, `Excel::load`) — not present in 3.1.x; do not copy from old tutorials.
- `barryvdh/laravel-dompdf` 2.x — lacks Laravel 13 support; pin to `^3.1`.
- `$casts` property — replaced by `casts()` method (Laravel 11+).
- Dompdf `counter(pages)` for total page count — **never worked**; still doesn't in v3. Use only `counter(page)` for "Page N".

## Assumptions Log

> Claims tagged `[ASSUMED]` — planner must confirm or have the user validate.

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | "Monthly Expenses" card (DASH-01) means total monthly expenses (bazar + fixed), not bazar-only. CONTEXT explicitly leaves this to Claude's discretion; defaulted to total. | Dashboard Cache Strategy | If the planner/user wants bazar-only, change one query (drop the fixed-category filter) — low impact. |
| A2 | The ~60-day auto-bucket threshold (D-08) is correct. CONTEXT leaves the exact threshold to Claude's discretion. | Pattern 7 | If users want daily to extend to 90 days, just adjust the constant — low impact. |
| A3 | `DB::raw('SUM(breakfast * 0.5 + lunch * 1.0 + dinner * 1.0)')` for `today_meals` uses the default meal values. If a mess overrides meal values in settings, this is wrong. | Dashboard Cache Strategy | Use `MealType::value(BREAKFAST)` etc. (read from settings) instead of hard-coded 0.5/1/1. The codebase pattern (`BillPreviewService::mealTotals`) uses `MealType::value()` — follow that. |
| A4 | Route shape `monthly.pdf` / `monthly.xlsx` (separate routes) is preferred over `?format=pdf` query switching. | Pattern 3 | Either works; if the user prefers one endpoint, refactor. |
| A5 | `chart.js/auto` (auto-register all controllers) is acceptable vs importing only `LineController` + `BarController` (tree-shaking). Discretion area. | Pattern 6 | `auto` adds ~20KB; fine for 3 charts on one page. Switch to explicit imports only if bundle size becomes a Phase 5 perf concern. |
| A6 | Chart.js wires into the existing global `app.js` (not a per-page chunk). Discretion area. | Pattern 6 | If perf audit (Phase 5) flags it, refactor to a `resources/js/dashboard.js` chunk loaded only on `/home`. |

## Open Questions

1. **Money helper resolution (Gap 1).**
   - What we know: `App\Support\Money::taka()` exists, is used in 14 views; `bdt()` does not exist anywhere despite CONTEXT claiming it.
   - What's unclear: Should Plan 4.1 Wave 0 (a) standardize on `Money::taka()` everywhere (and update CONTEXT's `bdt()` references), or (b) actually create `app/helpers.php` with a `bdt()` wrapper that calls `Money::taka()` + a `mess_date()` helper, and add `app/helpers.php` to composer `autoload.files`?
   - Recommendation: **(a) adopt `Money::taka()`** — it's already the de facto standard, avoids a global-function layer, and matches the project's class-based `App\Support\*` organization. Add a `mess_date(Carbon $date)` helper as a thin static on `Money` (or a new `App\Support\DateFormat` class) for D-34.

2. **Does the manager Member Statement need a member-picker?**
   - What we know: RPT-02 says "any member, any month". The route is `/mess/reports/member-statement/{member}`.
   - What's unclear: How does the manager pick the member? A dropdown in the filter bar? A search (like `mess.members.search`)? Default to first member?
   - Recommendation: a `<select>` of active members in the report's filter bar (small list — v1 is single mess with a manageable member count). If the list grows, reuse the existing AJAX member search.

3. **Does "Today's Meals" card count guest meals?**
   - What we know: D-05 defines Meal Trend as "total meals consumed across the mess that day, e.g. 42.5". The `MealEntry` table has separate `guest_breakfast/lunch/dinner` decimal columns.
   - What's unclear: Should the card include guest meal counts?
   - Recommendation: **exclude guest meals from the trend** (they're charged separately via `charge_amount`, not counted in `total_meals`). Match `BillPreviewService::mealTotals()` exactly.

4. **Does the closed-month path need a banner on the report?**
   - What we know: D-24 says label changes to "for {Month Year}" for closed months; D-30 says no gating banner on the dashboard.
   - What's unclear: Does a *report* for a closed month show a small "Closed month" badge?
   - Recommendation: yes, a small badge next to the period label — matches the "MONTH CLOSED" banner pattern on `/home` (already shipped in Phase 3).

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP | All PHP code | ✓ | 8.4 runtime / ^8.3 declared | — |
| MySQL | DB queries (test + dev) | ✓ | 8.x (per taste) | — |
| Composer | Install dompdf + maatwebsite/excel | ✓ | (present — `composer show` worked) | — |
| npm + Node 24 | Install chart.js | ✓ | node v24.15.0 | — |
| Vite build (`npm run build`) | Bundle chart.js | ✓ | 7.0.7 | — |
| `ext-dom` (PHP) | Dompdf XML parsing | ✓ (Laravel default) | bundled | — |
| `ext-mbstring` (PHP) | Dompdf + Maatwebsite | ✓ (Laravel default) | bundled | — |
| `ext-zip` (PHP) | PhpSpreadsheet `.xlsx` read/write | ⚠ verify | — | If missing, Excel export fails at runtime. Check `php -m | grep zip` in Wave 0. |
| `ext-gd` or `ext-imagick` | Receipt images in PDF (remote images) | Not needed (`isRemoteEnabled=false`) | — | — |

**Missing dependencies with no fallback:** None identified (assuming `ext-zip` is present, which is standard).

**Missing dependencies with fallback:** None.

**Action for Wave 0:** `php -m | grep -i zip` — if absent, install via the PHP package manager before Plan 4.3 (Excel export).

## Validation Architecture

> `workflow.nyquist_validation: false` in `.planning/config.json` — the formal Nyquist test-map section is **not required**. This section is included because PHPUnit feature tests are the project's primary validation approach (success criterion #13, PERF-12) and the planner benefits from a concrete test map.

### Test Framework

| Property | Value |
|----------|-------|
| Framework | PHPUnit 12.5.30 |
| Config file | `phpunit.xml` (MySQL `devsroom_mess_management_testing`, `CACHE_STORE=array` in test) |
| Quick run command | `php artisan test --filter=Report` |
| Full suite command | `php artisan test` (or `composer run test`) |
| Style gate | `vendor/bin/pint --test app/ tests/` (must be clean — locked by all prior phases) |

### Phase Requirements → Test Map (recommended)

| Req ID | Behavior | Test Type | File (to create) | Key assertions |
|--------|----------|-----------|------------------|----------------|
| RPT-01 | Monthly report renders totals | feature | `tests/Feature/Report/MonthlyReportTest.php` | 200, sees meal_rate + total_bazar; closed month reads snapshot |
| RPT-02 | Manager member statement (any member) | feature | `tests/Feature/Report/MemberStatementTest.php` | 200 for active mess member; 404 for cross-mess member |
| RPT-03 | Expense report filters (date/category) | feature | `tests/Feature/Report/ExpenseReportTest.php` | Sticky query string; preset redirects to right range |
| RPT-04 | Payment report filters (member/method) | feature | `tests/Feature/Report/PaymentReportTest.php` | Sticky query string; method filter respected |
| RPT-05 | Member own statement | feature | `tests/Feature/Report/MyStatementTest.php` | `role:user` sees own only; cannot view another's |
| RPT-06 | Member aggregates-only monthly | feature | `tests/Feature/Report/MyMonthlyReportTest.php` | Sees totals; does NOT see per-member table (assert absence of member name in another row) |
| RPT-07 | PDF download (all 4) | feature | `tests/Feature/Report/PdfExportTest.php` | `Content-Type: application/pdf`, `Content-Disposition: attachment; filename=...` — discretion area says assert response + filename, not body |
| RPT-08 | Excel download (all 4) | feature | `tests/Feature/Report/ExcelExportTest.php` | `Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`, filename ends `.xlsx` |
| DASH-01 | 6 manager cards render | feature | `tests/Feature/Dashboard/ManagerDashboardTest.php` | 200, sees all 6 card labels |
| DASH-02 | 3 charts render with `@json` data | feature | same as above | 200, response contains `initDashboardChart` call with non-empty labels |
| DASH-03 | Pending meal-off banner | feature | same as above | Banner present when pending count > 0; absent when 0 |
| DASH-04 | Member 4 cards | feature | `tests/Feature/Dashboard/MyDashboardTest.php` | 200, sees My Meals + My Bill + My Advance + My Payment History |
| DASH-05 | Cache invalidation < 2s | feature | `tests/Feature/Dashboard/CacheInvalidationTest.php` | Write an expense → `Cache::has('bill-preview:1:YYYY-MM')` is false; next read recomputes |
| DASH-06 | Chart range filter | feature | `tests/Feature/Dashboard/ChartRangeTest.php` | `?from=&to=` query respected; auto-bucket picks daily for ≤60d |

### Sampling rate (recommended)
- **Per task commit:** `php artisan test --filter=Report` + `--filter=Dashboard` (fast subset).
- **Per wave merge:** full `php artisan test` (154 existing tests + new Report/Dashboard tests).
- **Phase gate:** `php artisan test` green + `vendor/bin/pint --test` clean before `/gsd-verify-work`.

### Wave 0 gaps
- `tests/Feature/Report/` directory does not exist yet — create in Wave 0.
- `tests/Feature/Dashboard/` directory does not exist yet — create in Wave 0.
- Factory coverage: `Member`, `Expense`, `Payment`, `MealEntry`, `GuestMeal`, `MonthlyClosing`, `MonthlyMemberSummary` factories must exist (most ship from Phase 2/3 — verify in Wave 0 by listing `database/factories/`).
- No new shared test fixture needed — the existing `RefreshDatabase` + factory pattern suffices.

## Security Domain

> `security_enforcement` is absent from `.planning/config.json` (defaults to enabled per the role rules), but Phase 4 is overwhelmingly read-only with no new auth surface. The ASVS-relevant controls are inherited from Phase 1 (Tyro roles) and Phase 3 (`EnsureMonthIsOpen`). The phase-specific check is member-vs-manager scoping.

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|------------------|
| V2 Authentication | inherited | Tyro Login (Phase 1) — no change |
| V3 Session Management | inherited | database session driver (Phase 1) — no change |
| V4 Access Control | **YES** | Route middleware: `role:admin` (manager reports) / `role:user` (member reports) + `EnsureMessExists`. **Member report endpoints must NOT accept a `{member}` URL param** — derive member from `$request->user()->getMemberOrNull()`. Manager's `{member}` param is scope-filtered by `BelongsToActiveMess` / `MessScope` (404 on cross-mess). |
| V5 Input Validation | yes | `ReportFiltersRequest`, `MonthNavigationRequest`, `ExpenseReportRequest`, `PaymentReportRequest` Form Requests — validate all GET params (`from`, `to`, `category_id`, `member_id`, `method`, `year`, `month`). |
| V6 Cryptography | no | No new crypto in Phase 4 (cache keys are not sensitive). |

### Known Threat Patterns for this stack

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Cross-mess data access (manager views another mess's member) | Elevation of privilege | Global `MessScope` + route-model binding returns 404. Test with explicit cross-mess member fixture. |
| Member views another member's statement | Information disclosure | Member routes derive member from session, not URL. No `{member}` param on `role:user` routes. |
| Export endpoint abuse (DoS via huge date range) | Denial of service | Validate date-range span in the Form Request (cap at, say, 2 years); Maatwebsite `FromQuery` chunks; Dompdf has a default render-time limit. |
| Stale-cache data leak after role change | Information disclosure | On role change (Tyro admin action), the session regenerates (Tyro built-in); caches are mess-scoped, not user-scoped, so no leak. |
| CSV/Excel formula injection (CSV injection) | Tampering | For `.xlsx` via PhpSpreadsheet, the risk is low (it's not raw CSV). If a CSV fallback is added later (deferred), prefix cells starting with `=`, `+`, `-`, `@` with `'`. **Not needed for v1 .xlsx-only export.** `[CITED: OWASP CSV Injection]` |

## Sources

### Primary (HIGH confidence)
- `app/Services/BillPreviewService.php` — read source verbatim (lines 1-330) for return shape, cache key, formulas
- `app/Services/BillPreviewInvalidator.php` — read source verbatim for `forDate()`/`forToday()` signatures
- `app/Support/Money.php` — read source; confirmed `taka()` is the actual helper, `bdt()` does not exist
- `app/Providers/AppServiceProvider.php` — read source for cache-invalidation hook pattern
- `app/Http/Controllers/Mess/BillPreviewController.php`, `app/Http/Controllers/My/MyBillPreviewController.php` — read source for controller pattern
- `resources/views/components/mess-date-nav.blade.php`, `tab-nav.blade.php` — read source for reusable component patterns
- `resources/views/layouts/app.blade.php` lines 1-170 — read for sidebar structure, Vite directives
- `database/migrations/2026_06_16_220{700..1900}_*.php` — read column names + indexes for trend queries
- `composer.json` — confirmed no `autoload.files`, no dompdf/maatwebsite/chart.js installed
- `package.json` — confirmed no chart.js installed
- `vite.config.js` + `resources/js/app.js` — confirmed Vite entry structure
- Packagist `p2/barryvdh/laravel-dompdf.json` — v3.1.2 requires `illuminate/support ^9|^10|^11|^12|^13.0`, `dompdf/dompdf ^3.0`, `php ^8.1` `[VERIFIED via composer show -a + curl packagist]`
- Packagist `p2/maatwebsite/excel.json` — 3.1.69 requires `illuminate/support ...||^12.0||^13.0`, `phpoffice/phpspreadsheet ^1.30.4`, `php ^7.0||^8.0` `[VERIFIED via composer show -a + curl packagist]`
- npm registry — `chart.js` latest 4.5.1 `[VERIFIED via npm view chart.js version]`

### Secondary (MEDIUM confidence — verified with multiple sources)
- [github.com/barryvdh/laravel-dompdf/releases](https://github.com/barryvdh/laravel-dompdf/releases) — confirms v3.1.1 added Laravel 13 compatibility (PR #1083 by Laravel Shift)
- [github.com/dompdf/dompdf/issues/1190](https://github.com/dompdf/dompdf/issues/1190) — confirms `position: fixed` repeats header/footer on every page
- [groups.google.com/g/dompdf](https://groups.google.com/g/dompdf) — confirms `counter(page)` works, `counter(pages)` (total) does NOT
- [chartjs.org/docs/latest/configuration/responsive.html](https://www.chartjs.org/docs/latest/configuration/responsive.html) — confirms `responsive: true` + `maintainAspectRatio: false` pattern; parent must have fixed height
- [github.com/chartjs/Chart.js/issues/813](https://github.com/chartjs/Chart.js/issues/813) — confirms `.destroy()` is the correct way to free a canvas before re-render
- [laravel-excel.com/3.1/exports/mapping.html](https://docs.laravel-excel.com/3.1/exports/mapping.html) — `WithMapping` + `WithHeadings` API
- [laravel-excel.com/3.1/exports/column-formatting.html](https://docs.laravel-excel.com/3.1/exports/column-formatting.html) — `WithColumnFormatting` + `NumberFormat::FORMAT_NUMBER_00`
- `.planning/research/PITFALLS.md` #11 (cache staleness), #15 (cache stampede), #13 (currency/date format)

### Tertiary (LOW confidence — single source, marked for validation)
- None. All package claims were cross-verified against the Packagist registry metadata directly.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — versions verified against Packagist + npm this session, not training data
- Architecture: HIGH — patterns derived from reading the actual codebase (`BillPreviewService`, `BillPreviewController`, layout, components)
- Pitfalls: HIGH — 3 of 7 are direct readings of the source code (advance_applied naming, chart destroy, dompdf CSS); 4 are standard Laravel/reporting pitfalls cross-verified
- BillPreviewService return shape: HIGH — read the source line-by-line; field map is exact

**Research date:** 2026-06-17
**Valid until:** 2026-07-17 (30 days — package versions are stable; the laravel-dompdf v3.1.2 and maatwebsite/excel 3.1.69 are unlikely to change in 30 days)

## RESEARCH COMPLETE

**Phase:** 4 - Reports + Dashboard
**Confidence:** HIGH

### Key Findings
- **3 packages verified compatible with Laravel 13.15:** `barryvdh/laravel-dompdf` v3.1.2 (Laravel 13 added in v3.1.1), `maatwebsite/excel` 3.1.69 (Laravel 13 added in 3.1.68), `chart.js` 4.5.1. All installable via `composer require` / `npm install` with no manual provider registration.
- **`BillPreviewService` is fully reusable** — its `preview()` returns exact totals (`total_bazar`, `total_meals`, `meal_rate`, `total_fixed`, `days_in_month`) and per-member rows (15 fields documented above). Phase 4 wraps it, doesn't re-derive. The "DBG debug throw" was already fixed in commit `b4ce6ee` — confirmed by reading the source.
- **Gap 1 (must address in Wave 0):** `bdt()` helper does NOT exist anywhere in the codebase despite CONTEXT D-33 + canonical_refs claiming it ships from `app/helpers.php`. The real helper is `App\Support\Money::taka()` (14 callsites). Planner must standardize on `Money::taka()` (recommended) or create the missing helper — every money-display call in Phase 4 depends on this decision.
- **Cache strategy is concrete:** 4 bill-derived dashboard cards reuse `bill-preview:{mess_id}:{YYYY}-{MM}` (no new key); 3 count cards get one new composite key `dash:counts:{mess_id}:{YYYY}-{MM}` (1h TTL, database driver). Invalidation extends the existing `AppServiceProvider::boot()` listener body (one extra `Cache::forget`) — preserves the `< 2s` refresh contract (success #12).
- **Two Dompdf gotchas to bake into the plan:** (a) PDF views MUST use plain CSS, not Tailwind utilities (Dompdf can't load the Vite stylesheet) — separate `layouts/pdf.blade.php`; (b) only `counter(page)` works for page numbers, not `counter(pages)` — "Page N" not "Page N of M" (D-13 footer).

### File Created
`D:\Devsroom-Work\devsroom-mess-management\.planning\phases\04-reports-dashboard\04-RESEARCH.md`

### Confidence Assessment
| Area | Level | Reason |
|------|-------|--------|
| Standard Stack | HIGH | Versions verified against Packagist p2 JSON + npm registry this session; Laravel 13 compatibility confirmed in actual `illuminate/support` constraints |
| Architecture | HIGH | Patterns read directly from existing controllers/services/views in the repo |
| BillPreviewService Return Shape | HIGH | Read source line-by-line; field map is exact |
| Pitfalls | HIGH | 3 of 7 are direct source-code readings; 4 are standard pitfalls cross-verified with official docs |
| Money Helper Gap | HIGH | Verified by `grep -rln "bdt("` (0 results) + `grep -rln "Money::taka"` (14 results) + reading `composer.json` (no `autoload.files`) |

### Open Questions
1. **Money helper** — standardize on `Money::taka()` vs create `bdt()`? (recommendation: `Money::taka()`)
2. **Member-picker UX** for manager Member Statement (recommendation: `<select>` of active members)
3. **Guest meals in "Today's Meals" card** (recommendation: exclude — match `BillPreviewService::mealTotals()`)
4. **"Monthly Expenses" card scope** (recommendation: total = bazar + fixed, per default in CONTEXT discretion)
5. **Closed-month badge on reports** (recommendation: small badge next to period label, matches existing `/home` banner pattern)

### Ready for Planning
Research complete. Planner can create PLAN.md files for Plans 4.1, 4.2, 4.3.

**Recommended Wave 0 (Plan 4.1) prerequisite tasks:**
1. Install the 3 packages + verify `ext-zip` is loaded
2. Resolve the money-helper decision (Gap 1) before any view work begins
3. Add the "Reports" sidebar group to `layouts/app.blade.php` (D-31)
4. Create the `tests/Feature/Report/` and `tests/Feature/Dashboard/` directories
