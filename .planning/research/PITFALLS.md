# Pitfalls Research — Bangladesh Mess Management System

## Project: Devsroom Mess Management
**Date:** 2026-06-16
**Milestone:** v1

## Critical Mistakes to Avoid

### 1. Conflating "Bill Payment" and "Advance Deposit" as One Type

**The mistake**: Storing all payments in one table with a `method` column (Cash, bKash, etc.) and using a `type` column to distinguish bill payment from advance deposit. Or worse, no `type` column at all.

**Why it breaks**: When computing "member advance balance", you have to filter `WHERE type = 'advance_deposit'`. Easy to forget, leads to wrong balances. Members dispute bills: "I paid ৳5000, why is my bill ৳5000?" Because the system treated the deposit as a bill payment.

**Prevention**:
- Either: separate `payments` and `advance_deposits` tables (different semantics, different fields)
- Or: single `transactions` table with explicit `type` enum (`bill_payment`, `advance_deposit`, `refund`), with the schema enforcing NOT NULL type
- **Recommended**: single table with explicit type, plus a derived `advance_balances` view or computed accessor. Less migration overhead.

**Phase to address**: Phase 3 (Payments + Month-Close)

### 2. Floating-Point Money Math

**The mistake**: Using `float` for money. `0.1 + 0.2 != 0.3` in PHP floats. A member's bill is ৳0.01 off, and the mess manager gets a complaint at 11pm.

**Why it breaks**: SQL floats lose precision. `DECIMAL(10,2)` is the right type for money.

**Prevention**:
- Migration: `$table->decimal('amount', 10, 2)`
- Eloquent cast: `protected $casts = ['amount' => 'decimal:2']`
- For sums: `bcadd()`, `bcsub()`, `bcmul()` — OR use MySQL's `DECIMAL` arithmetic which is exact
- Format for display: `NumberFormatter('bn_BD', NumberFormatter::CURRENCY)` — never `number_format()` with hardcoded symbols

**Phase to address**: Phase 1 (Foundation — establish the cast in initial migrations)

### 3. Month-Close Race Condition (Double Close)

**The mistake**: Manager clicks "Close Month" twice. Two transactions both run, both write a snapshot, last one wins. Members see different bills on different screens.

**Why it breaks**: No idempotency guard. Application-level "check if already closed" is racy.

**Prevention**:
- DB-level: `UNIQUE INDEX (mess_id, year, month)` on `monthly_closings`
- Application: wrap in transaction, attempt insert, catch duplicate key, return friendly error
- Better: use `firstOrCreate(['mess_id' => X, 'year' => Y, 'month' => M], [...])` and check `wasRecentlyCreated`

**Phase to address**: Phase 3 (Payments + Month-Close)

### 4. Editing a Closed Month

**The mistake**: "Oh, I forgot to add Saturday's bazar. Let me just add it now." Member bill changes. Other members see stale data. Disputes.

**Why it breaks**: No lock, or soft lock with override that bypasses the lock.

**Prevention**:
- Closed month = NO writes to `expenses`, `meal_entries`, `payments`, `meal_off_requests` for that (mess, year, month)
- Enforce in middleware: check if month is closed before allowing write
- For corrections: use a separate `monthly_corrections` table that records "applied to month X" without modifying month X's data
- UI: closed month view is read-only, big banner says "MONTH CLOSED — corrections only via new adjustment"

**Phase to address**: Phase 3 (Payments + Month-Close)

### 5. Meal Off Without Approval Workflow

**The mistake**: Member marks "I'm on vacation" → system auto-deducts meals → member "comes back" but doesn't turn it off → meals are off when they shouldn't be → bill is wrong.

**Why it breaks**: Trust + audit. Member claims they were home, manager claims they were eating. No record of who approved.

**Prevention**:
- Member submits request (state: `pending`)
- Manager approves/rejects (state: `approved` / `rejected`)
- Only `approved` requests affect meal count
- Approval is logged in audit trail with timestamp + manager_id
- Rejecting requires a reason (visible to member)

**Phase to address**: Phase 2 (Members + Daily Operations)

### 6. Performance: N+1 on Daily Meal Grid

**The mistake**: Loading the daily meal grid as 1 query for members, then 1 query per member for their meal count for the day, then 1 query per member for their meal off status, etc. 30 members = 91 queries.

**Why it breaks**: Page takes 3+ seconds on mobile. Manager gets frustrated, goes back to spreadsheet.

**Prevention**:
- Eager load: `Member::with(['mealEntries' => fn($q) => $q->where('date', $today), 'mealOffRequests' => fn($q) => $q->approved()->covering($today)])->active()->get()`
- Or: 2-3 explicit queries (members, today's meals, today's meal offs) and merge in PHP
- Profile in dev with Laravel Debugbar
- Target: < 100ms for the daily grid

**Phase to address**: Phase 2 (Members + Daily Operations)

### 7. Timezone Bugs

**The mistake**: Server timezone is UTC. Bangladesh is UTC+6. Manager enters meals at 11pm Bangladesh time = 5pm UTC. The meal is recorded with the wrong date.

**Why it breaks**: "Why is today's meal on yesterday's date?"

**Prevention**:
- Set `APP_TIMEZONE=Asia/Dhaka` in `config/app.php`
- All `date` columns store date only (no time)
- Manager's "today" = their local "today" (Asia/Dhaka)
- For audit timestamps, use `now()` in app timezone

**Phase to address**: Phase 1 (Foundation)

### 8. Bazar vs Fixed Expense Confusion

**The mistake**: Treating all expenses the same way. Meal rate accidentally includes rent. Members' bills are ৳5000 each when they should be ৳1500.

**Why it breaks**: The whole point of mess accounting is "meal rate" = bazar only. Fixed costs are split equally. Conflating the two means the meal rate is wrong.

**Prevention**:
- `expense_categories` table has a `kind` column: `bazar`, `fixed`, `other`
- Default categories: Bazar (kind=bazar), Rent/Cook/Internet/Electricity/Water/Gas/Security/Maintenance/Cleaning (kind=fixed), Others (kind=other)
- Meal rate calculation explicitly filters `WHERE kind = 'bazar'`
- Fixed cost share explicitly filters `WHERE kind = 'fixed'`
- "Other" is neither — admin-only, excluded from member bills
- UI: when creating expense category, require `kind` to be selected

**Phase to address**: Phase 2 (Bazar + Fixed Expenses)

### 9. Missing Member on a Closed Month

**The mistake**: Member joins on the 15th. They have 15 days of meals. The month-close formula gives them 50% of the fixed cost share. Correct. But the manager entered 30 days of meals for them by mistake. Member's bill is wrong.

**Why it breaks**: Proration assumes meal data is correct. If meal data is wrong, bill is wrong.

**Prevention**:
- Validation: when recording meals, member must be `active` and `joining_date <= date <= leaving_date`
- Validation: meal count per day per member cannot exceed meal values (3 for full day, can't eat 4 breakfasts)
- Month-close: verify each member's `joining_date <= month_start` and `leaving_date >= month_end` (or prorate)

**Phase to address**: Phase 2 (Daily Operations) and Phase 3 (Month-Close)

### 10. No Audit Trail for Disputed Edits

**The mistake**: Manager edits yesterday's bazar. Original was ৳500 for rice. Now it's ৳800. Member: "Why did the rice cost double?" Manager: "I don't remember." Disputes go unresolved.

**Why it breaks**: Bangladesh mess culture is built on trust and visibility. Without an audit trail, every change is a potential dispute.

**Prevention**:
- Every write to `expenses`, `meal_entries`, `payments`, `members` writes an audit log entry
- Audit log shows: who, when, what changed (before/after), from which IP
- Audit log is **append-only** — never edited, never deleted
- Members can view audit log for their own data (limited view)
- Managers view full audit log

**Phase to address**: Phase 1 (Foundation — establish the trait)

### 11. Single Source of Truth for "Current Bill"

**The mistake**: Manager edits a meal. Member refreshes their page. Their bill is stale. Member: "You said my bill is ৳3000 but it shows ৳3500."

**Why it breaks**: Cache invalidation is hard. If you cache the current bill, you have to invalidate on every meal edit.

**Prevention**:
- Cache key: `mess:{id}:month:{year}-{month}:member:{id}:bill`
- Invalidate on: any write to meals, meal off, guest meals, expenses, payments for that month
- Use `Cache::tags(['month-'.$year.'-'.$month])` for efficient bulk invalidation (Laravel 8+)
- For very fresh data (after a write), use `Cache::remember()` with a short TTL (1 hour) plus immediate invalidation
- Document: "if you just made a change, give it 2 seconds and refresh"

**Phase to address**: Phase 4 (Reports + Dashboard)

### 12. Mobile UX: 60-Click Daily Entry

**The mistake**: Daily meal entry has 30 members × 3 meals = 90 checkboxes, no presets. Manager gives up, uses spreadsheet.

**Why it breaks**: Mess managers are busy, often on mobile, often in a hurry. Every extra click loses adoption.

**Prevention**:
- "Mark all 3 meals" button: 1 click
- "Mark all 0 meals" button: 1 click
- Per-member row with quick buttons: "All on", "All off", "Breakfast only", "Lunch only", "Dinner only"
- Member on meal off today: show grayed out with a "meal off" badge, prevent editing
- Bulk actions: "Apply to selected members"
- Target: enter a full day's meals in < 30 seconds

**Phase to address**: Phase 2 (Daily Operations)

### 13. Currency / Date Format Hardcoded

**The mistake**: "৳" hardcoded in Blade templates. Date format `m/d/Y` hardcoded.

**Why it breaks**: A future mess in India, Pakistan, or English-speaking Bangladesh wants different format. Refactor every template.

**Prevention**:
- All currency formatting via helper: `bdt($amount)` → `৳1,234.56`
- All date formatting via Laravel's `->format('d-m-Y')` or `__()` with locale
- Currency and date format are settings (per mess)
- v1: defaults to BDT and `d-m-Y`, but the abstraction exists

**Phase to address**: Phase 1 (Foundation — establish the helper)

### 14. Forgetting Receipts

**The mistake**: Receipts are required at bazar entry time. Manager forgets to take photo. They don't go back. Members: "Where's the proof?"

**Why it breaks**: Required fields kill adoption. Especially on mobile, with poor connectivity.

**Prevention**:
- Receipt image is **optional** in v1
- Bazar entries without receipts show a "no receipt" badge in reports
- Manager can edit a bazar entry later to add the receipt
- v2: make receipt required above a threshold (e.g., > ৳500)

**Phase to address**: Phase 2 (Bazar)

### 15. Cache Stampede on Dashboard

**The mistake**: Manager + 10 members all open the dashboard at 9am. The meal rate hasn't been computed yet (cache miss). All 11 requests trigger a recalculation. DB load spikes.

**Why it breaks**: First load of the day is slow.

**Prevention**:
- `Cache::lock('compute-meal-rate', 10)` — only one process computes
- Or: precompute via a scheduled job (every hour), cache the result
- Or: use `Cache::remember()` with a long TTL (1 hour) and accept that the first viewer pays the cost

**Phase to address**: Phase 4 (Dashboard)

## Phase Mapping Summary

| Pitfall | Phase |
|---|---|
| 1. Bill/Advance conflation | 3 |
| 2. Float money | 1 |
| 3. Month-close race | 3 |
| 4. Editing closed month | 3 |
| 5. Meal off no approval | 2 |
| 6. N+1 on daily grid | 2 |
| 7. Timezone | 1 |
| 8. Bazar vs fixed confusion | 2 |
| 9. Missing member on month | 2 + 3 |
| 10. No audit trail | 1 |
| 11. Bill cache staleness | 4 |
| 12. 60-click daily entry | 2 |
| 13. Hardcoded format | 1 |
| 14. Forgetting receipts | 2 |
| 15. Cache stampede | 4 |

## Quality Gates

- [x] Pitfalls are specific to this domain (not generic advice)
- [x] Prevention strategies are actionable
- [x] Phase mapping included for each
