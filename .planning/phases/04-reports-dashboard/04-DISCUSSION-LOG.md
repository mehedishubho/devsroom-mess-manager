# Phase 4: Reports + Dashboard - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-06-17
**Phase:** 04-reports-dashboard
**Areas discussed:** Charts, Exports (PDF/Excel), Dashboard layout, Filters & member visibility, Chart data semantics, Member Statement content, Empty & first-run states, Report navigation & export permissions

---

## Area Selection (round 1)

| Option | Description | Selected |
|--------|-------------|----------|
| Charts | DASH-02 trend charts — lib + data flow. No chart lib installed. | ✓ |
| Exports (PDF/Excel) | RPT-07/08 — Dompdf + Excel/CSV choice, neither installed. | ✓ |
| Dashboard layout | DASH-01/04 — manager cards+charts on /home; member dashboard. | ✓ |
| Filters & member visibility | RPT-03/04/06 — filter UX + whether members see the full monthly report. | ✓ |

**User's choice:** All four.
**Notes:** User selected every gray area and continued through all of them, then opted to explore additional areas.

---

## 1. Charts

| Option (library) | Description | Selected |
|--------|-------------|----------|
| Chart.js via Vite | Lightweight, named in research/STACK, Tailwind-friendly, vanilla JS bundled via Vite. | ✓ |
| ApexCharts | More features (zoom, annotations), heavier, more setup. | |
| Server-rendered SVG | No JS; limited interactivity, more view code. | |

| Option (chart types) | Description | Selected |
|--------|-------------|----------|
| All line charts | Consistent; best for time series. | |
| Line + bar mix | Line for daily Meal (30d), bar for monthly Expense + Payment (6mo). | ✓ |
| Mixed/manager-picks | Most flexible, least consistent. | |

| Option (range behavior — DASH-06) | Description | Selected |
|--------|-------------|----------|
| Fixed defaults + override | Default DASH-02 windows, optional override. | |
| Fully selectable | Range picker on every chart, no fixed defaults. | ✓ |
| Always trailing, no filter | Simplest, loses DASH-06. | |

| Option (mobile behavior) | Description | Selected |
|--------|-------------|----------|
| Full chart, responsive | One implementation, scales to 375px. | ✓ |
| Sparkline + expand | Compact on mobile, expand on tap. | |
| Cards-only on mobile | Hide charts under a breakpoint. | |

**User's choices:** Chart.js via Vite · Line + bar mix · Fully selectable range · Full responsive chart on mobile.

---

## 2. Exports (PDF/Excel)

| Option (Excel dep) | Description | Selected |
|--------|-------------|----------|
| Maatwebsite/Excel (.xlsx) | Real .xlsx, named in RPT-08, adds a dependency. | ✓ |
| CSV-only (no dep) | Lighter, universal, plain. | |
| Both .xlsx + .csv | Most flexible, most code + the dependency. | |

| Option (PDF scope) | Description | Selected |
|--------|-------------|----------|
| Statement + Monthly | The human-readable, printable reports. | |
| All 4 reports | Every report gets a PDF button. | ✓ |
| Statement only | Minimal PDF surface. | |

| Option (Excel scope) | Description | Selected |
|--------|-------------|----------|
| Expense + Payment | Dense tabular reports where spreadsheets shine. | |
| All 4 reports | Every report gets Excel/CSV. | ✓ |
| Same as PDF scope | Mirror the PDF choice. | |

| Option (PDF layout) | Description | Selected |
|--------|-------------|----------|
| Portrait + branded | A4, mess name + period header, generated-date + page number footer. | ✓ |
| Mixed orientation | Landscape for wide monthly table, portrait for statement. | |
| Minimal | No header/footer, raw content. | |

**User's choices:** Maatwebsite/Excel (.xlsx) · All 4 reports get PDF · All 4 reports get Excel · Portrait A4 + branded.
**Notes:** Planner flagged that the wide Monthly Report per-member table needs column compaction in portrait.

---

## 3. Dashboard layout

| Option (manager /home) | Description | Selected |
|--------|-------------|----------|
| Replace links with dashboard | /home = 6 stat cards + 3 charts; quick-nav moves to sidebar. | ✓ |
| Augment (cards+links) | Keep link-cards, add stat cards + charts above. | |
| Separate /mess/dashboard route | Leave /home as links, build dashboard separately. | |

| Option (cards layout) | Description | Selected |
|--------|-------------|----------|
| 6 cards + alert banner | 6 stat cards + pending meal-off as a highlighted alert/banner. | ✓ |
| 7 equal cards | 6 stats + pending meal-off as a 7th card. | |
| Compact summary row | Inline numbers, max room for charts. | |

| Option (member dash) | Description | Selected |
|--------|-------------|----------|
| Overview landing + tabs | New Overview landing with 4 cards; tabs drill in. | ✓ |
| New 'Dashboard' tab | Add a 5th tab, leave current default. | |
| Replace tabs with cards | Single card-based dashboard; detail on separate pages. | |

| Option (dash caching — DASH-05) | Description | Selected |
|--------|-------------|----------|
| Reuse + targeted keys | Bill-preview key for bill cards; short-TTL keys for counts; invalidate on write. | ✓ |
| Single dashboard key | One key per (mess, year, month) for all cards + chart aggregates. | |
| No new cache | Compute on page load, rely on query optimization. | |

**User's choices:** Replace /home with dashboard · 6 cards + alert banner · Overview landing + tabs · Reuse + targeted keys.

---

## 4. Filters & member visibility

| Option (filter UX) | Description | Selected |
|--------|-------------|----------|
| GET + sticky + presets | Filters in URL, shareable, This/Last month presets. | ✓ |
| POST + session filters | Server-side session, clean URLs, not shareable. | |
| Minimal (range + dropdown) | Single range + dropdown, no presets. | |

| Option (member monthly report — RPT-06) | Description | Selected |
|--------|-------------|----------|
| Aggregates only | Totals + meal rate; no per-member table (privacy). | ✓ |
| Full per-member report | Names + each member's bill/due/advance (full transparency). | |
| Aggregates + own row | Totals + the member's own line. | |

| Option (period nav) | Description | Selected |
|--------|-------------|----------|
| Month picker (◀ ▶) | Arrows + dropdown; matches Phase 2 meal-grid nav. | ✓ |
| Free date-range picker | Arbitrary from–to on every report. | |
| Current + closed-months list | Default current; pick from past closed months. | |

| Option (member history) | Description | Selected |
|--------|-------------|----------|
| Full history | Current + any past month. | ✓ |
| Current + last month | Limit to recent. | |
| Current month only | Simplest, can't review past bills. | |

**User's choices:** GET + sticky + presets · Aggregates only (privacy) · Month picker ◀ ▶ · Full history.
**Notes:** The member monthly-report visibility was the key privacy/trust decision — members see mess totals only, never other members' dues/advances.

---

## Area Selection (round 2 — additional)

| Option | Description | Selected |
|--------|-------------|----------|
| Chart data semantics | What each of the 3 charts actually plots. | ✓ |
| Member Statement content | Line items/sections on the statement. | ✓ |
| Empty & first-run states | New mess / before first close / no history. | ✓ |
| Report nav & export perms | Sidebar structure + member export permissions. | ✓ |

**User's choice:** All four again.

---

## 5. Chart data semantics

| Option (Meal metric) | Description | Selected |
|--------|-------------|----------|
| Daily meal count | Total meals across the mess per day. | ✓ |
| Daily meal rate | ৳/meal per day (noisy mid-month). | |
| Daily active eaters | Headcount per day. | |

| Option (Expense metric) | Description | Selected |
|--------|-------------|----------|
| Bazar total | Monthly bazar only (matches "meal rate = bazar only"). | ✓ |
| Bazar + fixed (total) | Total mess spend per month. | |
| Stacked by category | Per-category detail. | |

| Option (Payment metric) | Description | Selected |
|--------|-------------|----------|
| Total collected | Monthly total, all methods. | ✓ |
| Split by method | Stacked cash/bKash/Nagad/etc. | |
| Split by type | Bill payment vs advance deposit. | |

| Option (range × granularity) | Description | Selected |
|--------|-------------|----------|
| Auto-bucket by range | Daily ≤ ~60d, weekly/monthly wider. | ✓ |
| Always daily | Simple, dense at wide ranges. | |
| Fixed grain per chart | Range changes window only, not grain. | |

**User's choices:** Daily meal count · Bazar total · Total collected · Auto-bucket by range.

---

## 6. Member Statement content

| Option (sections) | Description | Selected |
|--------|-------------|----------|
| Full ledger | Meals + guest + payments + fixed + opening/closing balance. | ✓ |
| Bill-only (bottom line) | No per-day detail. | |
| Meals + payments only | No fixed-share breakdown. | |

| Option (meal detail) | Description | Selected |
|--------|-------------|----------|
| Daily breakdown + totals | Each date's B/L/D + totals. | ✓ |
| Per-type totals | Breakfast/lunch/dinner totals only. | |
| Total only | One line. | |

| Option (as-of label) | Description | Selected |
|--------|-------------|----------|
| As-of today / period | "As of today" for open month; "for Month Year" closed. | ✓ |
| Month label only | No timestamp. | |
| Period + last-updated | Both period and a timestamp. | |

| Option (rate math) | Description | Selected |
|--------|-------------|----------|
| Show rate + math | rate × meals = cost (transparency). | ✓ |
| Hide rate | Just "Meal cost: ৳X". | |
| Rate only, no totals | Rate but not the deriving totals. | |

**User's choices:** Full ledger · Daily breakdown + totals · As-of today / period · Show rate + math.

---

## 7. Empty & first-run states

| Option (empty charts) | Description | Selected |
|--------|-------------|----------|
| Placeholder + hint | "No data yet — charts appear once…". | ✓ |
| Empty axes | Zeroed canvas. | |
| Hide until data | Panel hidden until data exists. | |

| Option (empty report) | Description | Selected |
|--------|-------------|----------|
| Empty state + hint | "No data for {Month Year} yet". | ✓ |
| Report with zeroes | Full structure, all ৳0.00. | |
| Redirect to data | Refuse, jump to a period with data. | |

| Option (zero rate) | Description | Selected |
|--------|-------------|----------|
| ৳0 + hint | "৳0.00 / meal — no bazar recorded yet". | ✓ |
| Em-dash | "—" (not yet meaningful). | |
| Hide card | Hide meal-rate card until a rate exists. | |

| Option (pre-close) | Description | Selected |
|--------|-------------|----------|
| Live compute, no gate | Reports work off live preview; closed-only features just don't appear. | ✓ |
| Banner nudge | "Run your first month-close to unlock historical reports". | |
| Disable until first close | Historical reports locked until first close. | |

**User's choices:** Placeholder + hint · Empty state + hint · ৳0 + hint · Live compute, no gate.

---

## 8. Report navigation & export permissions

| Option (manager nav) | Description | Selected |
|--------|-------------|----------|
| Sidebar group, 4 entries | "Reports" group with 4 sub-entries. | ✓ |
| Reports index page | Single link → hub page. | |
| Top-level entries | Flat nav, no grouping. | |

| Option (member access) | Description | Selected |
|--------|-------------|----------|
| Overview cards | From /my Overview cards. | |
| My reports tab | Dedicated tab on /my alongside the others. | ✓ |
| Member top-bar links | Direct links in a top bar. | |

| Option (member statement export) | Description | Selected |
|--------|-------------|----------|
| PDF + Excel for own statement | RPT-05 + RPT-07/08 apply to members. | ✓ |
| PDF only | No Excel for members. | |
| Manager-only exports | Members view on-screen only. | |

| Option (monthly report export) | Description | Selected |
|--------|-------------|----------|
| Member can export monthly | Aggregates-only monthly as PDF/Excel too. | ✓ |
| View-only, no export | See on-screen, can't export. | |
| Monthly PDF only | No Excel for member monthly. | |

**User's choices:** Sidebar group, 4 entries · My reports tab · PDF + Excel for own statement · Member can export monthly.
**Notes:** The "My reports" tab coexists with the Overview landing cards (D-16) — Overview is at-a-glance; My reports is the detailed/exportable view.

---

## Claude's Discretion

Areas deferred to Claude / researcher / planner (captured in CONTEXT.md under "Claude's Discretion"):
- Chart.js theme/palette/tooltip styling, Vite entry strategy
- Date-range picker component choice
- PDF filename conventions
- Excel raw-number cells vs formatted strings
- Report table pagination thresholds
- Test depth for exports (assert download response, not full content)
- Auto-bucket threshold + bucket labels
- "Monthly Expenses" card semantics (bazar-only vs fixed vs total)

## Deferred Ideas

See CONTEXT.md `<deferred>` section — Bengali PDF, YoY reports, websockets, CSV fallback, sparkline charts, ApexCharts/SVG, stacked charts, dashboard polling, landscape PDF.
