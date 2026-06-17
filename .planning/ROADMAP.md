# Roadmap: Devsroom Mess Management

**Created:** 2026-06-16
**Phases:** 5
**Granularity:** Coarse (3-5 plans per phase)
**Mode:** YOLO (auto-approve, just execute)

## Overview

The roadmap is structured to deliver **one mess running a full monthly cycle in production** as the v1 milestone. Each phase produces a working slice of the system, not a collection of unused abstractions.

```
Phase 1: Foundation       → Mess is configured, auth works, schema is in place
Phase 2: Daily Operations  → Manager runs a day: meals, meal off, bazar, fixed expenses
Phase 3: Payments + Close  → Manager takes payments, closes month, member sees bill
Phase 4: Reports + Dashboard → Manager sees trends, member sees statements
Phase 5: Polish           → PDF, Excel, mobile polish, performance, real-mess pilot
```

## Phase 1: Foundation

**Goal:** A working Laravel 13 app where a super admin can configure a mess, log in via Tyro, and the database schema is in place for all domain models with `mess_id` and audit log support.

**Why this phase first:** Nothing else makes sense without auth, a mess, and a schema. The audit log and settings abstractions are used by every other phase, so they must exist before any domain write.

**Requirements covered:** AUTH-01 to AUTH-10, MESS-01 to MESS-04, SET-01 to SET-05, AUDIT-01 to AUDIT-05, PERF-01, PERF-02, PERF-03, PERF-05, PERF-07, PERF-08, PERF-09, PERF-10

**Success criteria:**
1. Super admin can log in to `/dashboard` (Tyro) and see Tyro's user management
2. Super admin can configure the one mess (name, address, monthly rent, meal values, currency, date format)
3. All domain migrations have run successfully on MySQL with `mess_id` on every table
4. The `Auditable` trait is defined and used on a sample model (e.g., a test `Setting` model)
5. PHPUnit tests for: login, mess configuration, audit log writes, Form Request validation
6. PHP time zone is set to `Asia/Dhaka`
7. Laravel Pint runs clean
8. Smoke test: a fresh `composer run setup` produces a working app on MySQL

**Out of scope for this phase:** Members, meals, expenses, payments, reports, dashboard cards.

**Estimated plans:** 3
- Plan 1.1: MySQL setup, env config, time zone, base migrations (messes, settings, audit_logs)
- Plan 1.2: Tyro integration, role/permission setup, login flow, super admin user
- Plan 1.3: Mess configuration UI, settings persistence, Auditable trait, Form Requests, tests

**UI hint:** yes (login pages, mess configuration form)

---

## Phase 2: Members + Daily Operations

**Goal:** Manager can run a full day of mess operations on a phone — manage members, enter meals via bulk grid, approve meal off, log bazar, record fixed expenses.

**Why this phase second:** These are the manager's daily touchpoints. Get them right and everything else (payments, close) is arithmetic.

**Requirements covered:** MEM-01 to MEM-09, MEAL-01 to MEAL-11, OFF-01 to OFF-07, GUEST-01 to GUEST-04, BAZAR-01 to BAZAR-06, FIXED-01 to FIXED-04, CAT-01 to CAT-04, PERF-04, PERF-11, PERF-12

**Success criteria:**
1. Manager can CRUD members (with profile photo, room/seat, joining date, etc.)
2. Member can log in and see their own profile (read-only for most fields)
3. Manager can view today's meal grid with all active members
4. Manager can check/uncheck meals in bulk; "Mark all 3 meals" preset works
5. Manager can navigate to any date and edit that day's meals
6. Member can request meal off for a date range
7. Manager can approve/reject meal off requests
8. Approved meal off auto-deducts from grid
9. Manager can record guest meals
10. Manager can record bazar expenses (with optional receipt)
11. Manager can record fixed expenses
12. Manager can manage expense categories
13. Daily grid loads in < 100ms (N+1 prevented)
14. Mobile-first UI works at 375px width
15. PHPUnit feature tests for each controller action
16. PDF and Excel export NOT required yet

**Out of scope for this phase:** Payments, advance balance, month-close, reports, dashboard cards.

**Estimated plans:** 5
- Plan 2.1: Member CRUD (manager side, mobile-first UI)
- Plan 2.2: Member self-view + meal off request
- Plan 2.3: Daily meal grid (bulk entry, presets, navigation)
- Plan 2.4: Meal off approval workflow
- Plan 2.5: Bazar + fixed expenses + expense categories

**UI hint:** yes (member list, member form, daily grid, meal off requests, expense forms)

---

## Phase 3: Payments + Month-Close

**Goal:** Manager can take payments (cash, bKash, Nagad, Rocket, bank) with bill/advance type distinction, and the system can run a queued, idempotent, hard-locked month-close.

**Why this phase third:** Once meals and expenses are recorded, payments and closing are arithmetic — but the arithmetic must be exactly right.

**Requirements covered:** PAY-01 to PAY-06, ADV-01 to ADV-07, PREVIEW-01 to PREVIEW-05, CLOSE-01 to CLOSE-12, NOTIF-01 to NOTIF-05

**Success criteria:**
1. Manager can record a payment (member, date, amount, method, reference, notes) with `type=bill_payment` or `type=advance_deposit`
2. Member can view their own payment history
3. Manager can view all payments (filter by member, method, date)
4. Each member has an `advance_balance` that carries forward month-to-month
5. Manager can see live "if we closed today, meal rate would be ৳X"
6. Manager can see each member's running bill for the current month
7. Member can see their own running bill
8. Manager can trigger month-close for a (year, month)
9. Month-close runs as a queued job with progress notification
10. Close is idempotent: second attempt for same (mess, year, month) is refused with a clear error
11. Close persists immutable snapshot to `monthly_closings` and `monthly_member_summaries`
12. Closed months are read-only (middleware enforces)
13. Corrections to closed months go through `monthly_corrections` (not edits to original)
14. Mid-month joiners/leavers are prorated by days
15. PHPUnit tests for: bill computation, advance carry-forward, idempotency, prorated fixed cost, queued close
16. In-app notification on close completion

**Out of scope for this phase:** Reports, dashboard charts, PDF/Excel export, performance tuning.

**Estimated plans:** 4
- Plan 3.1: Payment recording + payment history
- Plan 3.2: Advance balance + carry-forward
- Plan 3.3: Live bill preview (caching, manager + member views)
- Plan 3.4: Month-close job (queued, idempotent, hard-locked) + corrections + notifications

**UI hint:** yes (payment form, payment history, advance balance card, close button, close progress)

---

## Phase 4: Reports + Dashboard

**Goal:** Manager can see trends and member statements. Member can see their own statement. Both sides have meaningful dashboards.

**Why this phase fourth:** Reporting and dashboard are read-mostly. By this phase, all the data is being written correctly (phases 1-3), so the reports reflect reality.

**Requirements covered:** RPT-01 to RPT-08, DASH-01 to DASH-06

**Success criteria:**
1. Manager can view Monthly Report (totals, meal rate, due, advance)
2. Manager can view Member Statement for any member, any month
3. Manager can view Expense Report with filters (date, category, month)
4. Manager can view Payment Report with filters (member, method, date)
5. Member can view their own Member Statement
6. Member can view the mess's Monthly Report
7. Manager dashboard shows cards: Total Members, Today's Meals, Current Meal Rate, Monthly Expenses, Total Due, Total Advance
8. Manager dashboard shows charts: Expense Trend, Meal Trend, Payment Trend
9. Member dashboard shows: My Meals, My Bill, My Advance, My Payment History
10. Reports support PDF export (Dompdf)
11. Reports support Excel/CSV export (Maatwebsite/Excel)
12. Dashboard cache invalidation works (changes reflect within 2 seconds)
13. PHPUnit feature tests for all report endpoints

**Out of scope for this phase:** Performance tuning, advanced reports, year-over-year, SMS/WhatsApp.

**Plans:** 4 plans

Plans:
- [ ] 04-00-PLAN.md — Wave 0 prerequisites: install Dompdf + Maatwebsite/Excel + chart.js, expose window.initDashboardChart, add Reports sidebar group (D-31), create test dirs, lock Money::taka() as canonical helper
- [ ] 04-01-PLAN.md — 4 manager-side reports (Monthly, Member Statement, Expense, Payment): routes + ReportService + MemberStatementService + Form Requests + Blade views + month-nav + filter UX + tests (RPT-01..04)
- [ ] 04-02-PLAN.md — Member self-view + member dashboard: own Member Statement, aggregates-only Monthly Report (D-19), /my Overview landing with 4 DASH-04 cards, My reports tab (RPT-05, RPT-06, DASH-04, DASH-06)
- [ ] 04-03-PLAN.md — Manager dashboard + exports: transform /home into dashboard (6 cards + alert banner + 3 Chart.js charts with auto-bucketing), extend cache hook for dash:counts:* key, PDF (Dompdf plain-CSS layout) + Excel (Maatwebsite raw-numeric) exports on all 4 reports both sides (RPT-07, RPT-08, DASH-01, DASH-02, DASH-03, DASH-05)

**UI hint:** yes (report views, dashboard cards, charts, PDF/Excel download buttons)

---

## Phase 5: Polish + Pilot

**Goal:** App is production-ready for one real mess. Performance is acceptable. Mobile UX is polished. Documentation is complete.

**Why this phase last:** Real usage surfaces the issues that test data doesn't. Polish based on actual feedback.

**Requirements covered:** PERF-04, PERF-06, PERF-13 + general polish

**Success criteria:**
1. All pages render correctly at 320px, 375px, 768px, 1024px
2. Daily grid loads in < 100ms with 50 members
3. Dashboard loads in < 500ms
4. Month-close completes in < 30s for a mess with 50 members
5. No N+1 queries in production (Laravel Debugbar or Telescope enabled in dev)
6. Cache hit rate > 80% on dashboard
7. PHP time zone consistently `Asia/Dhaka` everywhere
8. All user-facing strings wrapped in `__()`
9. Pint clean, test coverage > 70%
10. README updated with setup instructions, deployment notes, demo credentials
11. AGENTS.md (or equivalent) documents the architecture for future agents
12. One real mess has been onboarded and used the app for a full monthly cycle without major issues

**Out of scope for this phase:** v2 features (multi-mess, bKash API, PWA, Bengali).

**Estimated plans:** 3
- Plan 5.1: Mobile UX polish (review all manager screens at 375px)
- Plan 5.2: Performance audit (N+1, caching, query optimization)
- Plan 5.3: Documentation, deployment guide, real-mess pilot

**UI hint:** yes (mobile polish review)

---

## Milestone Definition (v1)

**Done means:**
- Phase 1-5 all complete
- One real mess is using the app
- They've run at least one full month-close
- Members are viewing their bills
- No P0/P1 bugs open
- Test coverage > 70%
- Performance criteria from Phase 5 met
- v1 deployed to a public URL

**Not in this milestone:**
- v2 features (multi-mess, bKash API, PWA, Bengali, SMS, real-time, native mobile, public API)
- More than one mess onboarded
- Year-over-year reporting
- Cook/maid management
- Inventory tracking

## Risks & Open Questions

| Risk | Mitigation |
|---|---|
| Meal rate calculation bugs (off-by-one, wrong category filter) | Unit tests for MonthCloseService with 10+ scenarios |
| Mid-month joiner proration bugs | Specific tests for joining day, leaving day, mid-month joins |
| Cache invalidation misses (member sees stale bill) | Manual UAT: make a change, verify member sees it within 2s |
| PDF/Excel export breaks on large data | Test with 100+ meal entries, 50+ members |
| MySQL-specific bugs (fulltext, JSON) that sqlite would hide | Dev on MySQL from day 1 (taste preference) |
| Tyro package updates breaking our config | Pin Tyro version, document upgrade procedure |
| Mobile UX is hard to test in browser-only env | Test on actual mobile devices in Phase 5 pilot |

## v2 Milestone Sketch (NOT in scope for v1, but tracked)

After v1 ships and one real mess is using it:
- Multi-mess support
- bKash/Nagad/Rocket API integration
- PWA / offline support
- Bengali translations
- SMS / WhatsApp notifications
- Public API for member mobile app
- Year-over-year reporting
- 2FA enforcement
- Real-time dashboard updates

These are documented in REQUIREMENTS.md v2 section. NOT part of the v1 roadmap.
