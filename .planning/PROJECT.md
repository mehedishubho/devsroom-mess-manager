# Devsroom Mess Management

## What This Is

A web-based mess management system designed for Bangladesh messes — bachelor hostels, student hostels, and shared accommodations. The mess manager enters daily meals and bazar expenses on mobile; members log in to view their bills and submit meal-off requests. The system automates the Bangladesh-specific monthly close: meal rate derived from bazar only, fixed expenses split equally, advance balance carry-forward, and immutable monthly snapshots.

The v1 scope is **one mess, fully working for one real monthly cycle**.

## Core Value

A mess manager can run a full month end-to-end on a phone — enter meals, log bazar, take payments, close the month, and produce a correct member bill — without spreadsheets and without arguing about who owes what.

## Requirements

### Validated

Validated in Phase 3: Payments + Month-Close — manager records bill/advance payments; queued, idempotent, hard-locked month-close; immutable snapshot + corrections; live bill preview (manager + member); advance/due carry-forward in exact BC-math decimal; in-app notifications; 1-hour cached aggregates:

- [x] **R-PAY**: Manager can record member payments (date, amount, method, reference #, notes) — methods: Cash, bKash, Nagad, Rocket, Bank
- [x] **R-PAY-TYPE**: Payment type is either "bill payment" or "advance deposit" — kept distinct in the schema, not conflated
- [x] **R-ADV**: Advance balance carries forward month-to-month; auto-adjusts against next month's bill
- [x] **R-PREVIEW**: Member can view their own running bill (current month) any time
- [x] **R-PREVIEW-MGR**: Manager sees live "if we closed today, meal rate would be ৳X" preview
- [x] **R-CLOSE**: Manager can run month-close: computes meal rate, fixed cost share, individual bills, persists immutable snapshot
- [x] **R-CLOSE-LOCK**: Once closed, month is hard-locked — no edits. Corrections require adjustment entries in next month
- [x] **R-CLOSE-IDEMP**: Month-close is idempotent — unique index on (mess_id, year, month); second close attempt is refused
- [x] **R-CLOSE-Q**: Month-close runs as a queued job, not synchronously
- [x] **R-NOTIF**: In-app notifications (monthly closing, due reminder, payment received, meal off approval)
- [x] **R-CACHE**: Current-month aggregates (meal rate, total bazar, member bills) cached with 1-hour TTL, invalidated on write

### Active

- [ ] R-MESS: Manager can configure one mess (name, address, monthly rent, manager contact)
- [ ] R-MEM: Manager can CRUD members with full profile (name, mobile, email, NID, profession, room/seat, joining/leaving dates, status, emergency contact, photo)
- [ ] R-MEM-A: Members can self-register or are created by manager and can log in to view their own data only
- [ ] R-MEAL: Manager can enter daily meals via bulk grid (rows=members, columns=breakfast/lunch/dinner) with "apply to all" preset
- [ ] R-MEAL-V: Breakfast=0.5, Lunch=1, Dinner=1 by default; configurable per mess in settings
- [ ] R-MEAL-OFF: Member can request meal off for a date range (vacation/outside tour); manager approves/rejects
- [ ] R-MEAL-OFF-A: Approved meal off auto-deducts from that member's meal count for the date range
- [ ] R-GUEST: Manager can add guest meals (guest name, member, date, meal type, qty) — charged to that member's bill
- [ ] R-BAZAR: Manager can record daily bazar purchases (date, purchased by, vendor, description, amount, optional receipt image)
- [ ] R-EXP-FIX: Manager can record fixed monthly expenses (rent, cook salary, internet, electricity, water, gas, security)
- [ ] R-EXP-CAT: Default expense categories (Bazar, Rent, Cook, Internet, Electricity, Water, Gas, Maintenance, Cleaning, Others); admin can add custom
- [ ] R-RPT-M: Monthly report (total members, total meals, meal rate, total bazar, fixed expenses)
- [ ] R-RPT-S: Member statement (meals, guest meals, payments, due, advance)
- [ ] R-RPT-E: Expense report (filter by date, category, month)
- [ ] R-RPT-P: Payment report (filter by member, method, date)
- [ ] R-DASH: Dashboard cards (total members, today's meals, current meal rate, monthly expenses, total due, total advance) + trend charts
- [ ] R-SET: Mess settings (meal values, currency BDT, date format, auto-close toggle)
- [ ] R-AUDIT-M: Domain audit log (meal edits, expense edits, payment edits, member updates) via Auditable trait
- [ ] R-MULTI-PREP: All domain tables carry `mess_id` from day one — multi-mess is out of scope for v1 but the schema is ready
- [ ] R-I18N: All user-facing strings wrapped in `__()` from day one (English only shipped, Bengali-ready)
- [ ] R-MOBILE: All manager-facing screens are mobile-first (375px baseline)
- [ ] R-AUTH-MGR: Manager + super admin use Tyro Login (email/password, role-based)
- [ ] R-AUTH-MEM: Members log in via Tyro Login; role `member` restricts them to their own data only

> **Current State (2026-06-17):** Phases 1 (Foundation), 2 (Members + Daily Ops), and 3 (Payments + Month-Close) are complete and verified — 162 PHPUnit tests green. Phase 4 (Reports + Dashboard) is next, then Phase 5 (Polish + Pilot). The Phase 1/2 items still listed under Active above are built and passing but retained here for now; they will be re-classified to Validated on their next review pass.

### Out of Scope

- Multi-mess / multi-tenant — schema-ready, but a single mess is v1. Adding multi-mess = add mess-switcher, scope all queries by mess_id. Defer to v2.
- PWA / offline mode / service workers — separate project. v1 is online-only.
- Real bKash/Nagad/Rocket payment API integration — manager records payments with reference numbers. Real gateway = regulatory overhead, defer to v2.
- Public API or external integrations — Sanctum is installed but unused. Dashboard is the only consumer.
- Bengali language translations — strings wrapped in `__()` for v2 readiness, but no bn.json shipped.
- SMS / WhatsApp notifications — in-app only in v1.
- Real-time updates (websockets) — dashboard refresh is manual or simple page reload in v1.
- Mobile app (native) — responsive web only.

## Context

- **Target market**: Bangladesh messes (bachelor hostels, student hostels, shared accommodations). The workflows are culturally specific: bazar management, meal off/vacation/outside tour, guest meals, bKash/Nagad payments, advance deposits, monthly closing.
- **Currency**: BDT (৳)
- **Date format**: English-default but should support DD-MM-YYYY (Bangladesh convention)
- **Primary user (manager)**: Uses the app daily on a phone. Speed and one-handed operation matter more than visual polish.
- **Secondary user (member)**: Read-mostly. Submits meal off requests, views own bill and payment history.
- **Scale**: One mess in v1 (5-30 members typical). Schema scales to 100+ members per mess.
- **Working environment**: Windows, Laravel 13, PHP 8.4, MySQL, Tyro Dashboard + Tyro Login, Tailwind v4 + Vite.

## Constraints

- **Tech stack**: Laravel 13, MySQL 8+, Tyro Dashboard, Tyro Login, Tailwind v4, Vite 7. Fixed by taste preference.
- **Database naming**: snake_case (e.g. `devsroom_mess_management`, not hyphens) — per taste preference.
- **DB driver**: MySQL in dev AND prod — do NOT use sqlite locally. Per taste preference and to avoid dev/prod parity bugs.
- **DB credentials**: Verify with user before assuming defaults — per taste preference.
- **Code style**: Laravel Pint (Laravel preset). Run before commits.
- **Tests**: PHPUnit 12 (NOT Pest, despite plugin allowance). Use `RefreshDatabase` for feature tests.
- **No inline CSS, no Bootstrap** — Tailwind only.
- **All user-facing strings use `__()`** — even if only English is shipped.
- **Single mess in v1** — every domain table has `mess_id` but only one mess exists.

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Laravel 13 + MySQL | Per taste preference; modern, stable, MySQL-specific features (JSON, fulltext) available | — Pending |
| Tyro Dashboard for admin UI | Saves building user/role/privilege CRUD; already installed | — Pending |
| Tyro Login for auth | Built-in lockout, 2FA-ready, registration, password reset, magic links, social login (deferred) | — Pending |
| Single mess v1 | Simplifies auth, scoping, and reporting. Multi-mess is a v2 feature, schema is forward-compatible | — Pending |
| Hard-lock on month close | Bangladesh messes have trust disputes; "we edited last month's bill" is a problem. Corrections via adjustment entries | — Pending |
| Meal rate = bazar only | Bangladesh convention: fixed expenses (rent, utilities, salary) split equally, NOT folded into meal rate | — Pending |
| Manager-records payments (no bKash API) | Real gateway integration = 2-4 weeks, regulatory overhead, not the v1 bottleneck. Reference numbers give members a way to verify | — Pending |
| Bulk grid for daily meals | Manager enters 30+ meals/day; one-by-one forms are 10x slower. Grid + "apply to all" preset fits the workflow | — Pending |
| Approval workflow on meal off | Trust + audit trail. Members can request, manager approves, deduction only after approval | — Pending |
| MySQL in dev (not sqlite) | Surfaces fulltext, JSON, transaction issues early. Avoids the "worked in dev, broke in prod" class of bugs | — Pending |
| Queued month-close | 50+ members × multiple lookups = not sub-second. Background job with progress + notification | — Pending |
| Idempotent month-close (unique index) | Manager may double-click. Enforce at schema, not application | — Pending |
| Domain audit log separate from Tyro audit | Tyro covers user/role/privilege changes; domain events (meal edits, payment edits) are a different concern | — Pending |
| `mess_id` on all domain tables | Cheap insurance. Multi-mess in v2 = no backfill | — Pending |
| Translation-ready strings (`__()`) | v2 Bengali = translator job, not code refactor | — Pending |
| Cached current-month aggregates | Read 10x more than write. 1-hour cache, invalidate on write | — Pending |
| No PWA / no real bKash / no public API in v1 | Scope discipline. These are each their own project | — Pending |

## Recommendations (adopted from discussion)

These are not strict requirements but inform the design:

1. **Treat meal consumption as a derived ledger**, not just daily entry. Meal off auto-deducts; guest meal auto-adds. Month-close math becomes a single query.
2. **Daily meal grid should support "apply to all active members" preset.** Mark all 3 meals, then uncheck the 2 on meal off. 60 clicks → 4.
3. **Show live meal rate preview** mid-month ("if we closed today, ৳45.20"). Cheap to compute, builds trust with members.
4. **Separate `bill payment` and `advance deposit` payment types** at the schema level. Different semantics for the ledger.
5. **Receipt image upload is optional in v1**, not required. Best-effort with a "add receipt later" reminder.
6. **Member auth is read-only + meal off submit only.** Don't expose edit anything. Minimizes authorization surface.
7. **MySQL from day 1** in dev. Don't use sqlite locally.
8. **Queued job for month-close** with progress indicator and completion notification.
9. **Cache current-month aggregates** (meal rate, total bazar, member bills) with 1-hour TTL, invalidate on write.
10. **Idempotent month-close** via unique index on `(mess_id, year, month)`.
11. **Dual audit logs** — Tyro's for user/role/privilege, domain `Auditable` trait for meal/expense/payment/member events.
12. **`mess_id` on every domain table** from day 1, even though only one mess exists in v1.
13. **Phase 1 spine**: mess + members + settings + one full monthly cycle end-to-end. Don't build all 16 modules in v1.1.
14. **Translation-ready strings from day 1** via `__()` everywhere. English shipped, Bengali deferred.

## Anti-recommendations (do NOT do in v1)

- **No PWA / service workers / offline mode** — separate project.
- **No real bKash/Nagad/Rocket API** — manager records payments with reference numbers.
- **No public API** — Sanctum installed but unused.
- **No Bengali translations** — strings wrapped but no bn.json shipped.
- **No real-time websockets** — simple page reload is fine.

---
*Last updated: 2026-06-17 after Phase 3 (Payments + Month-Close) completion*
