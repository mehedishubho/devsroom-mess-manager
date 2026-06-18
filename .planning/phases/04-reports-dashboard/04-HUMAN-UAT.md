---
status: partial
phase: 04-reports-dashboard
source: [04-VERIFICATION.md]
started: 2026-06-18T00:00:00Z
updated: 2026-06-18T00:00:00Z
---

## Current Test

[awaiting human testing]

## Tests

### 1. Chart visual rendering (DASH-02, D-01..D-08)
expected: Open `/home` as admin in a browser; the 3 Chart.js charts (Meal Trend line, Expense Trend bar, Payment Trend bar) actually render visually with axes, labels, and data points. 3 charts appear in stacked cards with non-empty axes; canvas height ~280px; changing a chart's date range via its form updates only that chart (hidden inputs preserve the other two).
result: [pending]

### 2. PDF export layout (RPT-07, D-13)
expected: Download a PDF export (e.g. `/mess/reports/monthly.pdf`) and open it in a PDF viewer; branded header with mess name + report title, plain CSS tables with borders, footer reads "Page N" (NOT "Page N of M"), content fits A4 portrait, per-member Monthly Report table uses compact 9px font for the wide column set.
result: [pending]

### 3. Mobile responsive layout (D-04, DASH-04)
expected: Open `/my` on a phone-sized viewport (375px); the 4 Overview cards stack vertically and remain legible. Cards render in single column on mobile; "My Payment History" panel shows amount + method pill + View-all link; no horizontal overflow.
result: [pending]

### 4. End-to-end cache refresh (DASH-05, success #12)
expected: Trigger a write (e.g. POST a new bazar expense as admin) and visually confirm the dashboard refreshes within ~2 seconds on next page load. Revisiting `/home` shows updated "Monthly Expenses" card and "Expense Trend" chart reflecting the new expense; "Today's Meals" updates if meal-entry date is today.
result: [pending]

## Summary

total: 4
passed: 0
issues: 0
pending: 4
skipped: 0
blocked: 0

## Gaps
