# Architecture Research — Bangladesh Mess Management System

## Project: Devsroom Mess Management
**Date:** 2026-06-16
**Milestone:** v1

## Component Boundaries

```
┌─────────────────────────────────────────────────────────────┐
│                         Browser                             │
│  (Manager phone, Member phone, Admin desktop)                │
└────────────┬────────────────────────────────────────────────┘
             │ HTTP (session cookies)
             ▼
┌─────────────────────────────────────────────────────────────┐
│              Laravel 13 + Tyro Dashboard                    │
│  ┌──────────────────────────────────────────────────────┐   │
│  │  Routes (web.php, api.php)                            │   │
│  │  ├─ /dashboard/*  → Tyro (users, roles, settings)     │   │
│  │  ├─ /login, /register → Tyro Login                    │   │
│  │  ├─ /meals, /bazar, /expenses, /payments → domain     │   │
│  │  ├─ /members, /reports, /settings → domain            │   │
│  │  └─ /monthly-close, /notifications → domain           │   │
│  └──────────────────────────────────────────────────────┘   │
│  ┌──────────────────────────────────────────────────────┐   │
│  │  Controllers (HTTP layer)                              │   │
│  │  ├─ Resource controllers (CRUD)                        │   │
│  │  ├─ Form requests (validation)                        │   │
│  │  └─ API resources (JSON shaping, if needed)            │   │
│  └──────────────────────────────────────────────────────┘   │
│  ┌──────────────────────────────────────────────────────┐   │
│  │  Domain Layer                                          │   │
│  │  ├─ Services (business logic)                          │   │
│  │  │   ├─ MealService, BazarService, ExpenseService      │   │
│  │  │   ├─ PaymentService, AdvanceService                 │   │
│  │  │   └─ MonthCloseService (the big one)                │   │
│  │  ├─ Actions (single-purpose classes)                   │   │
│  │  └─ Events / Listeners                                 │   │
│  └──────────────────────────────────────────────────────┘   │
│  ┌──────────────────────────────────────────────────────┐   │
│  │  Data Layer                                            │   │
│  │  ├─ Eloquent Models (with Auditable trait)             │   │
│  │  ├─ Query Builders / Scopes (per-mess scoping)         │   │
│  │  └─ Migrations (all tables have mess_id)               │   │
│  └──────────────────────────────────────────────────────┘   │
│  ┌──────────────────────────────────────────────────────┐   │
│  │  Cross-cutting                                          │   │
│  │  ├─ Tyro (roles, privileges, auth)                      │   │
│  │  ├─ Middleware (auth, role, mess-scope)                 │   │
│  │  ├─ Auditable trait (domain audit log)                  │   │
│  │  └─ Notifications (in-app)                              │   │
│  └──────────────────────────────────────────────────────┘   │
└────────────┬────────────────────────────────────────────────┘
             │
             │ Eloquent ORM
             ▼
┌─────────────────────────────────────────────────────────────┐
│                    MySQL 8+                                 │
│  ├─ users, roles, privileges (Tyro tables)                   │
│  ├─ messes (v1: 1 row)                                       │
│  ├─ members (5-30 rows typical, 100+ capacity)               │
│  ├─ meal_entries, meal_off_requests, guest_meals             │
│  ├─ expenses, expense_categories                             │
│  ├─ payments, advance_balances                               │
│  ├─ monthly_closings, monthly_member_summaries               │
│  ├─ notifications, audit_logs                                │
│  ├─ settings                                                 │
│  └─ (cache, sessions, jobs, personal_access_tokens — Laravel)│
└─────────────────────────────────────────────────────────────┘
```

## Data Flow

### Daily Workflow (Manager)

```
1. Manager opens /meals/today
   → Controller: MealController@today
   → Service: MealService::getDailyGrid(date)
   → Query: members + meal_entries + meal_off_requests for date
   → Returns: Blade view with grid

2. Manager checks/unchecks meals
   → POST /meals/bulk
   → Form Request: BulkMealRequest (validates member_ids, meal_types, date)
   → Service: MealService::recordBulk(input)
   → Transaction: upsert meal_entries
   → Event: MealRecorded → invalidate cache, audit log
   → Redirect: back to grid with flash success

3. Manager records bazar
   → POST /bazar
   → Form Request: BazarRequest
   → Service: BazarService::record(input, optional receipt image)
   → Storage: put receipt on public disk
   → Event: ExpenseRecorded → invalidate cache, audit log
```

### Month-End Close (Queued)

```
1. Manager clicks "Close Month"
   → POST /monthly-close
   → Controller dispatches: CloseMonthJob(mess_id, year, month)
   → Response: "Close job started, you'll be notified when done"

2. CloseMonthJob runs in queue
   → Begin transaction
   → Lock mess row (SELECT ... FOR UPDATE)
   → Check idempotency: unique (mess_id, year, month) — refuse if exists
   → Calculate:
     - total_bazar = SUM(expenses where category=bazar, in month)
     - total_meals = SUM(member_total_meals) for active members
     - meal_rate = total_bazar / total_meals
     - total_fixed = SUM(expenses where category=fixed, in month)
     - fixed_per_member = total_fixed / active_member_count
   → For each active member:
     - member_meal_cost = member_total_meals × meal_rate
     - fixed_share = fixed_per_member (prorated by days if joined/left mid-month)
     - payments_in_month = SUM(payments where type=bill_payment)
     - advance_applied = MIN(previous_advance_balance, remaining_due)
     - final_bill = meal_cost + fixed_share - payments_in_month - advance_applied
     - new_advance = MAX(0, previous_advance - applied)
   → Insert: monthly_closings row, monthly_member_summaries rows
   → Commit transaction
   → Event: MonthClosed → notify manager, invalidate all caches

3. Manager receives notification
   → "Month {month} {year} closed. {N} members billed. {X} carry-forward advance."
```

## Layer Responsibilities

### Controllers (HTTP Layer)
- Receive HTTP request, validate via Form Request, dispatch to Service or Action
- Return view or JSON
- No business logic
- Use Resource Controllers for CRUD
- Inject Services via constructor

### Services (Domain Layer)
- Encapsulate business logic
- Example: `MonthCloseService::close($mess, $year, $month)`
- Stateless (one instance per request/job)
- Use Eloquent directly OR use Repositories (TBD — see below)

### Actions (Single-purpose)
- For complex one-off operations
- Example: `CalculateMemberBillAction`
- Preferred over fat Services

### Models (Data Layer)
- Eloquent models with relationships
- Use `Auditable` trait for domain events
- Use Scopes for per-mess queries: `Member::forCurrentMess()`
- Casts: dates, decimals (money), enums

### Middleware
- `auth` — Tyro (or Laravel default)
- `role:manager` — manager-only routes
- `role:member` — member-only routes
- `scope.mess` — ensures mess_id is in scope (v1 = trivially true, but the middleware exists for v2)

## Build Order

```
Phase 1: Foundation
  ├─ Mess settings + audit trait
  ├─ Migrations (all tables, all with mess_id)
  ├─ Base models
  └─ Tyro integration verification

Phase 2: Members + Daily Operations
  ├─ Member CRUD
  ├─ Daily meal entry (bulk grid)
  ├─ Meal off (request + approve)
  ├─ Guest meal
  ├─ Bazar expense
  └─ Fixed expense

Phase 3: Payments + Month-Close
  ├─ Payment recording
  ├─ Advance balance
  ├─ Month-close (queued, idempotent, hard-locked)
  └─ Notifications

Phase 4: Reports + Dashboard
  ├─ 4 reports
  ├─ Dashboard (cards + charts)
  └─ Member self-view

Phase 5: Polish
  ├─ PDF export
  ├─ Excel export
  ├─ Mobile polish
  └─ Performance tuning
```

## Key Architectural Decisions

### 1. Service Layer, No Repositories (for v1)

- Eloquent is already a repository pattern. Adding a `MemberRepository` over `Member::query()` is ceremony.
- For complex queries, use **Query Scopes** on models: `Member::active()->forMess($id)->get()`
- For complex write logic, use **Services** or **Actions**
- If a Service needs to abstract Eloquent for testing, use `Bus::fake()` / `Event::fake()` instead of mocking repos

### 2. Form Requests for All Input

- Every controller method that accepts user input has a Form Request
- Validation rules are the contract
- No `validate()` calls in controllers

### 3. Auditable Trait on All Domain Models

- Members, meals, bazar, expenses, payments, advance_balances
- Not on `users` (Tyro handles user audit), not on `monthly_closings` (immutable, no edits)
- Trait writes to a single `audit_logs` table

### 4. Mess Scoping via Trait or Scope

- All domain models have `mess_id` column
- Global scope `MessScope` automatically filters by current mess (set via middleware or `auth()->user()->mess_id`)
- v1: scope is trivially `mess_id = 1` (the one mess)
- v2: scope is dynamic per request

### 5. Money as Decimal, Not Float

- Use `decimal:2` cast on all money fields
- Never use `float` for money (rounding errors)
- Format with `NumberFormatter` for BDT display

### 6. Date as Carbon

- All date fields use Carbon
- `meal_date`, `purchase_date`, `payment_date` are `date` type, not `datetime`
- Time zone: Asia/Dhaka (config/app.php)

### 7. Immutable Monthly Closing

- `monthly_closings` table has unique `(mess_id, year, month)` — enforces idempotency
- `monthly_member_summaries` references the closing — no orphan rows
- Once written, never updated (use a separate `monthly_corrections` table for adjustments if needed)

## Quality Gates

- [x] Components clearly defined with boundaries
- [x] Data flow direction explicit
- [x] Build order implications noted
