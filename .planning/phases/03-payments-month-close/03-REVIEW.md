---
phase: 03-payments-month-close
reviewed: 2026-06-17T00:00:00Z
depth: standard
files_reviewed: 27
files_reviewed_list:
  - app/Http/Controllers/Mess/BillPreviewController.php
  - app/Http/Controllers/Mess/DueReminderController.php
  - app/Http/Controllers/Mess/MonthCloseController.php
  - app/Http/Controllers/Mess/MonthlyClosingController.php
  - app/Http/Controllers/Mess/MonthlyCorrectionController.php
  - app/Http/Controllers/My/MyBillPreviewController.php
  - app/Http/Controllers/NotificationController.php
  - app/Http/Middleware/EnsureMonthIsOpen.php
  - app/Http/Requests/Mess/StoreMonthlyCorrectionRequest.php
  - app/Http/Requests/Mess/TriggerMonthCloseRequest.php
  - app/Jobs/CloseMonthJob.php
  - app/Models/MonthlyClosing.php
  - app/Models/MonthlyCorrection.php
  - app/Models/MonthlyMemberSummary.php
  - app/Providers/AppServiceProvider.php
  - app/Services/BillPreviewInvalidator.php
  - app/Services/BillPreviewService.php
  - app/Services/MealOffApprovalService.php
  - app/Services/MonthCloseService.php
  - app/Services/MonthlyCorrectionService.php
  - app/Services/NotificationService.php
  - app/Services/PaymentService.php
  - app/Support/NotificationType.php
  - app/View/Components/NotificationBell.php
  - bootstrap/app.php
  - database/factories/ExpenseCategoryFactory.php
  - database/factories/ExpenseFactory.php
  - phpunit.xml
  - routes/web.php
findings:
  critical: 3
  warning: 9
  info: 6
  total: 18
status: issues_found
---

# Phase 03: Code Review Report

**Reviewed:** 2026-06-17
**Depth:** standard
**Files Reviewed:** 27 (+ phpunit.xml)
**Stack:** Laravel 13 / MySQL 8 / PHPUnit 12 / decimal money / `Mess::activeId()` scoping / Tyro roles
**Status:** issues_found

## Summary

The phase implements money calculation (`BillPreviewService`), hard month-close locking (`EnsureMonthIsOpen` + `MonthCloseService`), corrections, payments, and notifications. The overall design is sound: idempotent `firstOrCreate` on `(mess_id, year, month)`, atomic `DB::transaction`, signed corrections applied to live balance but never to the closed snapshot, proper role-based route groups, and `BelongsToActiveMess` global scoping on every relevant model.

However, three **critical** issues must be fixed before release:

1. **`AppServiceProvider::registerBillPreviewInvalidation` registers broken event listeners** — Laravel passes the model directly to `eloquent.saved`/`eloquent.deleted` listeners, but the closures read `$event->model`, which throws a TypeError on every write to MealEntry/GuestMeal/MealOffRequest/Expense/Payment. All 5 invalidation hooks are dead, so the bill-preview cache is permanently stale after the first write.
2. **`phpunit.xml` commits a real DB password** (`125524`) — credential leak in VCS.
3. **`MonthCloseService` carries forward float-decomposed money** — the net bill is split into `balance` / `due_balance` by sign of `(float)` casts; combined with `BillPreviewService` mixing `bill_payments` into `advance_applied`, the carry-forward direction can invert for edge cases, and the snapshot loses decimal precision relative to the source columns.

The remaining findings are correctness/quality issues (missing mess scoping in a raw query, missing `applyPayment` reversal on `PaymentService::update`, double-application risk between `advance_balance` column and `bill_payments`, cache invalidation inside a transaction, etc.).

---

## Critical Issues

### CR-01: Bill-preview cache invalidation hooks are broken (TypeError on every model write)

**File:** `app/Providers/AppServiceProvider.php:62-67`
**Issue:**
```php
Event::listen("eloquent.saved: {$modelClass}", function ($event) use ($invalidator) {
    $this->invalidateForModel($invalidator, $event->model);   // <-- $event is the MODEL
});
Event::listen("eloquent.deleted: {$modelClass}", function ($event) use ($invalidator) {
    $this->invalidateForModel($invalidator, $event->model);
});
```
In Laravel, the `eloquent.saved: App\Models\X` and `eloquent.deleted: App\Models\X` events deliver the **model instance** itself as the first argument to the listener — there is no event object with a `->model` property. Calling `$event->model` on a model that has no attribute named `model` returns `null` (or, with strict mode/property hooks in L13, raises `Error: Access to undeclared property`). Either way `invalidateForModel($invalidator, null)` throws inside `BillPreviewInvalidator::forDate` (null `$date`), so the listener errors out silently in production (events swallow listener exceptions by default) or, in tests with `WithoutModelEvents`, never runs.

Result: the cached bill preview is **never invalidated** when meals/expenses/payments/meal-off/guest-meals change. Stale money numbers are served until the 1-hour TTL expires — exactly the bug class this phase was meant to prevent.

**Fix:**
```php
Event::listen("eloquent.saved: {$modelClass}", function ($model) use ($invalidator) {
    $this->invalidateForModel($invalidator, $model);
});
Event::listen("eloquent.deleted: {$modelClass}", function ($model) use ($invalidator) {
    $this->invalidateForModel($invalidator, $model);
});
```
Additionally harden `BillPreviewInvalidator::forDate` against null/empty dates:
```php
public function forDate(?string $date): void
{
    if ($date === null || $date === '') {
        return;
    }
    // ...existing logic
}
```
Add a feature test that mutates a `MealEntry`, then asserts `Cache::has($cacheKey)` is false.

---

### CR-02: Real database password committed in `phpunit.xml`

**File:** `phpunit.xml:35`
**Issue:**
```xml
<env name="DB_USERNAME" value="root"/>
<env name="DB_PASSWORD" value="125524"/>
```
A non-empty password (`125524`) is checked into VCS. Even if it is only a local dev MySQL password, the convention for `phpunit.xml` (which is committed, unlike `.env`) is to leave the password blank and let `.env.testing` / developer-local `.env` supply real credentials. Committing any non-empty password normalizes secret leakage and risks reuse on production infrastructure.

**Fix:**
```xml
<env name="DB_PASSWORD" value=""/>
```
Move the real testing password to `.env.testing` (gitignored) or to the developer's local `.env`. If `125524` has been used anywhere else (CI, staging), rotate it.

---

### CR-03: Money carry-forward decomposes float-rounded net bill — loses precision & can invert sign

**File:** `app/Services/MonthCloseService.php:66-96`
**Issue:**
```php
$netBill = (float) ($row['due'] ?? 0.0);          // float from BillPreviewService::due
...
foreach ($summaries as $summary) {
    $net = (float) $summary->net_bill;            // re-cast from decimal:2 column
    if ($net > 0) {
        $balanceService->carryForward($summary->member_id, -1 * $net);   // due
    } elseif ($net < 0) {
        $balanceService->carryForward($summary->member_id, abs($net));   // advance
    }
}
```
Three compounding problems:

1. **Float decomposition of decimal money.** The project convention (per stack note) is "decimal money, never float". `BillPreviewService` already does `round(..., 2)` which returns a `float` — fine for display, but routing that float through `(float)$summary->net_bill` (after Laravel has re-rounded it on save to DECIMAL(10,2)) introduces representation error around `.005` boundaries, which is then carried into `AdvanceBalance.balance`/`due_balance` (also DECIMAL columns). For a single close this is microscopic, but corrections + future closes accumulate.

2. **`advance_applied` is semantically wrong.** In `BillPreviewService:152-153`, `$advanceApplied = $billPayments` — i.e., the "advance applied" against the bill is set equal to the sum of `bill_payment`-type payments, **not** advance deposits. `PaymentType::ADVANCE_DEPOSIT` is excluded from `bill_payments` (line 255-258). So `due = bill − bill_payments`, and the `advance_applied` snapshot column is mislabeled. A reader (or future auditor) will assume advance deposits were applied here; they were not. Advance deposits live only in `AdvanceBalance.balance`, which is also displayed as `advance_balance` (line 171). This is double-bookkeeping waiting to happen: the same advance deposit appears both in `advance_balance` and (implicitly) not in `advance_applied`.

3. **Carry-forward applies the full `net_bill` to `balance`/`due_balance`, but the member may already have a pre-existing `AdvanceBalance.balance` or `due_balance` from prior months.** `carryForward` uses `+=` (line 101/103 of AdvanceBalanceService), so it correctly accumulates — but `BillPreviewService::compute` line 156-157 reads those same `balance`/`due_balance` columns *into* the preview as `advance_balance`/`due_balance`. When `MonthCloseService` then carry-forwards `-1 * net_bill` for a positive due, it is adding the new due on top of any pre-existing due_balance that was *already* displayed in the preview. If the preview's `due` was computed *without* subtracting the pre-existing `due_balance`, the close will double-count prior dues.

   Tracing: `due = bill − bill_payments` (line 154). The pre-existing `due_balance` (line 157) is **not** added to `due`. So the closing carry-forward correctly adds only this month's new due. This is internally consistent **only** because `due_balance` is carried in a separate column. But the `bill` itself is computed without considering outstanding dues, which means a member who owes from last month sees only this month's bill — acceptable design, but worth documenting.

**Fix (minimum):**
- Preserve decimal precision end-to-end: store `BillPreviewService` numbers as strings (e.g. `number_format($value, 2, '.', '')`) or use `Math::decimal()` / `brick/math` if available, and pass through to the snapshot and carry-forward without `(float)` casts.
- Rename `advance_applied` to `bill_payments_applied` (or compute actual advance application from `AdvanceBalance.balance` capped at `bill`). The current naming implies advance deposits are consumed, which they are not.
- Add an integration test: close a month, then close the next month, assert `AdvanceBalance` end-state equals `prior_balance + new_advance − new_due`.

---

## Warnings

### WR-01: `PaymentService::update` mutates a payment without reversing the prior balance impact

**File:** `app/Services/PaymentService.php:87-100`
**Issue:** `create()` calls `$this->balances->applyPayment($payment)` (advances the advance balance for `ADVANCE_DEPOSIT`). `update()` changes `amount`, `type`, and `date` but never reverses the original payment's effect on `AdvanceBalance.balance`. If an `ADVANCE_DEPOSIT` of 1000 is edited down to 500, the member's advance balance stays at +1000 — permanently wrong.

Also, the route `mess.payments.update` is protected by `month.open`, so the *new* date cannot fall in a closed month — but editing the amount/type on a payment whose *current* date is in the current open month is allowed and will not re-apply.

**Fix:** Reverse the original, then apply the new:
```php
public function update(Payment $payment, array $data): Payment
{
    DB::transaction(function () use ($payment, $data) {
        $original = clone $payment;
        $this->balances->reversePayment($original); // new method: -$amount for ADVANCE_DEPOSIT
        $payment->update([...]);
        $this->balances->applyPayment($payment->refresh());
    });
    return $payment->refresh();
}
```
Add `AdvanceBalanceService::reversePayment(Payment $p)` mirroring `applyPayment`.

---

### WR-02: `BillPreviewInvalidator` may receive a `created_at` string but treats it as a parseable date

**File:** `app/Providers/AppServiceProvider.php:71-76`
**Issue:**
```php
$date = $model->date ?? $model->created_at ?? now();
$dateStr = $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : (string) $date;
$invalidator->forDate($dateStr);
```
For models without a `date` column (none in the current list, but `MealOffRequest` uses `from_date` not `date`), this falls back to `created_at`. `MealOffRequest` is in the registered list (`AppServiceProvider:60`) but has no `date` attribute — it has `from_date` and `to_date`. So invalidation for meal-off changes uses `created_at`, which is the timestamp of creation, not the affected meal month. A meal-off request created today for next month invalidates the **current** month's cache, not next month's.

**Fix:** Whitelist per-model date columns, or check `from_date` fallback:
```php
$date = $model->date
    ?? $model->from_date
    ?? $model->created_at
    ?? now();
```
Better: a per-model resolver map.

---

### WR-03: `EnsureMonthIsOpen::resolveContext` only inspects `date` / `from_date` inputs on writes — bypassable via mass assignment

**File:** `app/Http/Middleware/EnsureMonthIsOpen.php:60-90`
**Issue:** The middleware reads `date` and `from_date` from `$request->input()`. Any controller that accepts the date under a different input key (e.g. `paid_on`, `occurred_at`, an array `payments[*][date]`, or a JSON body with `{"data": {"date": ...}}` not flattened) will bypass the lock. Additionally, the route-model fallback (line 75) only fires for routes whose binding param is literally `payment|expense|guestMeal|mealEntry|mealOffRequest`; any *new* month-scoped resource added later will silently skip the lock unless the developer remembers to update this list.

**Fix:**
- Centralize a list of "month-scoped write routes" with their date input key (config or attribute on the request class).
- Consider a `BelongsToMonth` interface implemented by month-scoped models, then check `$obj instanceof MonthScoped` and read a `monthDateKey()` method.
- Add a test: POST a `Payment` with `date` set to a closed month → expect 422/redirect with error.

---

### WR-04: `EnsureMonthIsOpen` uses `strtotime` for input parsing — accepts surprising inputs

**File:** `app/Http/Middleware/EnsureMonthIsOpen.php:66-70`
**Issue:** `strtotime((string) $value)` will happily parse `"2026-13-45"`, `"next Thursday"`, `"@1700000000"`, `"2026.5"`, etc. The request validator may not yet have run (middleware runs before form-request validation in some flows). A user submitting `date=2026-02-31` for a closed January would have `strtotime` interpret it as March 3, escaping the January lock.

**Fix:** Validate the date strictly first, e.g. `Carbon::createFromFormat('Y-m-d', $value)` and bail if it throws, deferring to form-request validation:
```php
try {
    $carbon = Carbon::createFromFormat('!Y-m-d', (string) $value);
} catch (\Throwable) {
    return $next($request); // let validation reject it
}
return [$carbon->year, $carbon->month];
```

---

### WR-05: `MonthlyClosingController::show` and `MonthlyCorrectionController` rely solely on global scope — no explicit policy

**File:** `app/Http/Controllers/Mess/MonthlyClosingController.php:22` and `app/Http/Controllers/Mess/MonthlyCorrectionController.php:18,25,35`
**Issue:** Route model binding for `MonthlyClosing` uses `BelongsToActiveMess` global scope, so a manager from mess A cannot view mess B's closing via `/mess/closings/{id}` — good. However, there is **no explicit authorization** (no `authorize()` call, no policy). If the global scope is ever bypassed (e.g. `withoutGlobalScope`, an admin "super-admin" cross-mess context, or a future `from` query param), the controller silently exposes data. Defense in depth is missing.

Also, the route group for closings/corrections/due-reminder is under `role:admin` — but **not** `EnsureMessExists::class` — wait, it is (the parent group at line 44 has `EnsureMessExists`). OK on that front.

**Fix:** Add `Gate`/policy:
```php
$this->authorize('view', $closing);
```
or inline `abort_unless($closing->mess_id === Mess::activeId(), 403);` as a belt-and-suspenders.

---

### WR-06: `MonthCloseController::trigger` pre-check is racy with `CloseMonthJob`

**File:** `app/Http/Controllers/Mess/MonthCloseController.php:38-49`
**Issue:** The controller checks for an existing closing and dispatches the job if none exists. Between the check and the job's `firstOrCreate`, two managers clicking "close" simultaneously will both pass the pre-check and both dispatch the job. The `firstOrCreate` + UNIQUE index will save one and return the existing for the other (idempotent), so no double-close — but the second manager's `redirect()->route('mess.close.index')->with('success', 'Closing dispatched...')` lies (it was a no-op). Minor UX/correctness issue.

**Fix:** Either:
- After dispatch, check `was_recently_created` via a synchronous `MonthCloseService::close()` (skip the job for the trivial case), or
- Re-fetch after dispatch and branch the message.

---

### WR-07: `MonthCloseService::invalidate` runs inside the DB transaction

**File:** `app/Services/MonthCloseService.php:99`
**Issue:** `app(BillPreviewService::class)->invalidate($year, $month)` runs `Cache::forget` *inside* `DB::transaction`. If the transaction later rolls back (e.g. carry-forward throws), the cache is already cleared but the DB state is unchanged — next read recomputes and re-caches the pre-close state (acceptable), but in the window between `Cache::forget` and rollback, concurrent readers see an empty cache and recompute against uncommitted in-transaction data (depending on isolation). Better to invalidate after commit.

**Fix:** Use `DB::afterCommit()`:
```php
DB::afterCommit(fn () => app(BillPreviewService::class)->invalidate($year, $month));
```
Same for `NotificationService::broadcastToManagers` (notifications should not be sent if the transaction rolls back).

---

### WR-08: `NotificationService::broadcastToManagers` queries users without mess scoping

**File:** `app/Services/NotificationService.php:36-38`
**Issue:**
```php
$recipients = User::query()
    ->whereHas('roles', fn ($q) => $q->whereIn('slug', ['admin', 'super-admin']))
    ->get();
```
This selects **all** admin/super-admin users across **all** messes. Then `send()` stamps the notification with `mess_id => Mess::activeId()` (line 20), but the `user_id` recipients include managers of unrelated messes. A month-close in mess A will notify mess B's admin users.

**Fix:** Scope by the active mess's user membership. Assuming `User` belongs to a mess or `Member` links them:
```php
$recipients = User::query()
    ->whereHas('roles', fn ($q) => $q->whereIn('slug', ['admin', 'super-admin']))
    ->whereHas('members', fn ($q) => $q->where('members.mess_id', Mess::activeId()))
    ->get();
```
(Adjust to your actual user→mess relationship.)

---

### WR-09: `MonthlyCorrectionService` invalidates the **applied-to** month's preview, but corrections target a closed month

**File:** `app/Services/MonthlyCorrectionService.php:48-49`
**Issue:** The correction applies to `applied_to_year`/`applied_to_month` (the live balance month, often the current month), so invalidating that month's preview is correct. But `BillPreviewService::forMember` and `compute` do not actually incorporate `MonthlyCorrection` amounts — they read `AdvanceBalance.balance`/`due_balance` (lines 271-273), which `carryForward` updates. So the invalidation is necessary and sufficient. **However**, the snapshot for the closed month (the `MonthlyClosing` the correction is attached to) is never updated, and the preview for *that* closed month is also never invalidated, so the historical preview remains consistent. Good.

Minor concern: the `reason` for the correction is stored only on `MonthlyCorrection`, not surfaced in the preview — so a member viewing their bill for the applied-to month sees a changed `due_balance` with no explanation. UI/UX gap, not a bug. Flagging as info-level.

**Fix:** None required for correctness. Consider surfacing recent corrections in the bill-preview view.

---

## Info

### IN-01: `BillPreviewService::mealTotals` and `guestTotals` fetch all columns then iterate in PHP

**File:** `app/Services/BillPreviewService.php:196-217, 225-236`
**Issue:** Each helper selects specific columns but then loops in PHP to sum. For messes with many members × many days, this is O(members × days) in PHP memory. Could be a single `selectRaw('member_id, sum(...) as total')->groupBy('member_id')` query. Out of scope for v1 (performance), but worth noting.

**Fix:** Optional — convert to grouped aggregate when scale demands.

---

### IN-02: `MonthCloseController::index` always closes the **current** calendar month

**File:** `app/Http/Controllers/Mess/MonthCloseController.php:18-30`
**Issue:** `index()` uses `Carbon::now()` to pick year/month. On March 1, this shows March (an open month with ~0 data) and not February (the month a manager actually wants to close on the 1st). UX gap.

**Fix:** Default to the previous month if `now()->day <= 5`, or accept `?year=&month=` from the query string like `BillPreviewController::resolveYearMonth` does.

---

### IN-03: `DueReminderController::send` does not validate the members belong to the active mess

**File:** `app/Http/Controllers/Mess/DueReminderController.php:32-47`
**Issue:** The validation rule `'member_ids.*' => ['integer', 'exists:members,id']` checks global existence, not mess-scoped. The subsequent `Member::query()->whereIn('id', $data['member_ids'])->get()` *is* scoped by the global `BelongsToActiveMess` scope, so non-mess IDs are silently dropped — but the validation passes any integer that exists in `members`, producing a confusing count. Defense in depth.

**Fix:** Use a custom rule: `Rule::exists('members', 'id')->where(fn ($q) => $q->where('mess_id', Mess::activeId()))`.

---

### IN-04: `NotificationBell` component queries the DB on every render

**File:** `app/View/Components/NotificationBell.php:13-21`
**Issue:** `unreadCount($user->id)` runs on every page that renders the bell (likely every authenticated page). For active messes this is a per-request COUNT query — acceptable, but cacheable.

**Fix:** Optional — `Cache::remember("unread:{$user->id}", now()->addMinutes(1), ...)` and bust on `NotificationService::send` / `markRead`.

---

### IN-05: `ExpenseFactory` and `ExpenseCategoryFactory` call `Mess::activeId()` at definition-time

**File:** `database/factories/ExpenseFactory.php:17`, `database/factories/ExpenseCategoryFactory.php:19`
**Issue:** `Mess::activeId() ?? Mess::factory()` is evaluated when `definition()` runs, which is fine, but `Mess::activeId()` caches statically — if a test sets the active mess after the first factory call, later factories use the stale cached ID. Tests must call `Mess::forgetActiveIdCache()` between cases.

**Fix:** Document this in the test bootstrap, or prefer `Mess::factory()` unconditionally and let the test bind the active mess explicitly.

---

### IN-06: `BillPreviewController::resolveYearMonth` silently coerces out-of-range year/month to "now"

**File:** `app/Http/Controllers/Mess/BillPreviewController.php:31-36`
**Issue:** `month=13` becomes the current month without any error. A typo in a URL silently shows the wrong data. Not a security issue, but a correctness footgun for shared links.

**Fix:** Return a 404 for blatantly out-of-range values, or redirect to the canonical URL.

---

## Verdict

**Status: issues_found — do not ship without fixes.**

The architecture is sound: idempotent close, atomic transaction, global mess scoping, role-separated routes, signed corrections, audit hooks on every model. The three **critical** issues, however, undermine the phase's core guarantees:

- **CR-01** silently disables all bill-preview cache invalidation — the headline feature of this phase is broken in production.
- **CR-02** is a credential leak that should be rotated immediately.
- **CR-03** is a money-correctness concern at the precision/sign boundary — exactly the class of bug the "decimal money, never float" convention exists to prevent.

After CR-01/02/03 are fixed, address WR-01 (balance not reversed on payment update — silent money corruption), WR-08 (cross-mess notification leak — privacy), and WR-04 (lock bypass via permissive `strtotime`) before release. The remaining warnings and infos can be batched into a follow-up.

Recommended priority:
1. CR-02 (rotate password, blank in phpunit.xml) — today.
2. CR-01 (fix event listener signatures, add test) — blocks release.
3. WR-01, WR-08 (correctness + privacy) — blocks release.
4. CR-03, WR-02..07, WR-09 — same release, can be parallelized.
5. Info items — follow-up PR.

---

_Reviewed: 2026-06-17_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
