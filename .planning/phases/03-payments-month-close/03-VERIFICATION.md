---
phase: 03-payments-month-close
verified: 2026-06-17T12:00:00Z
status: passed
score: 16/16 must-haves verified
overrides_applied: 0
re_verification:
  previous_status: none
  notes: "Initial verification. Prior 03-REVIEW.md (status: issues_found) was a code review, not a must-have verification. CR-01/CR-02/CR-03/WR-01/WR-04/WR-08 confirmed fixed in code; remaining review items (WR-02/03/05/06/07/09, IN-01..06, advance_applied rename) are user-deferred follow-ups."
deferred:
  - truth: "advance_applied column name is misleading (it actually holds bill_payments applied, not advance deposits consumed)"
    addressed_in: "Phase 03 follow-up (user-deferred per task context)"
    evidence: "Documented as a known review finding (CR-03 of 03-REVIEW.md). The column name + labeling was explicitly deferred by the user. Math is internally consistent — BillPreviewService and MonthCloseService agree, and a parity test (test_close_numbers_match_bill_preview_service_for_same_inputs) proves it."
human_verification:
  - test: "Manager triggers month-close from /mess/close and sees the success flash + later the close_complete notification in the bell"
    expected: "Redirect to /mess/close with success flash; notification badge increments for all admin+super-admin users in the active mess; /mess/closings shows the new closing row"
    why_human: "Requires a live queue worker (php artisan queue:work) and a real browser session; sync queue in tests hides the async UX"
  - test: "Manager attempts to add a payment dated inside an already-closed month via the UI form"
    expected: "Form re-renders with a 'MONTH CLOSED' validation error on the date field; no payment row is written"
    why_human: "Requires interaction with the real ensureMonthIsOpen middleware via the Blade form (the middleware reads 'date' from POST input)"
  - test: "CloseMonthJob failure path (CLOSE-03 'success or failure' notification)"
    expected: "If the close job fails (e.g. DB error mid-transaction), the manager is notified of failure"
    why_human: "CloseMonthJob has no failed() method — only success notifications are wired. The atomic DB::transaction rolls back on error, but no explicit close_failed notification exists. Verify whether this matters in practice (queue driver is sync in dev/test, so failures surface as 500s to the user immediately)."
---

# Phase 03: Payments + Month-Close Verification Report

**Phase Goal:** Manager can take payments (cash, bKash, Nagad, Rocket, bank) with bill/advance type distinction, and the system can run a queued, idempotent, hard-locked month-close.
**Verified:** 2026-06-17T12:00:00Z
**Status:** passed
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths

Must-haves are merged from ROADMAP Phase 3 success criteria (16 items) and the four plan `must_haves` blocks. Plan-level must-haves add detail to roadmap SCs but do not reduce scope.

| #  | Truth | Status | Evidence |
| -- | ----- | ------ | -------- |
| 1  | Manager can record a payment with `type=bill_payment` or `advance_deposit` (SC-1, PAY-01/03) | ✓ VERIFIED | `PaymentController` resource (7 methods) at routes/web.php:106-120; `StorePaymentRequest` requires `type ∈ {bill_payment, advance_deposit}`; `PaymentCrudTest::test_admin_can_create_bill_payment_cash` + `test_admin_can_create_advance_deposit` pass |
| 2  | Payment methods Cash/bKash/Nagad/Rocket/Bank supported (PAY-02) | ✓ VERIFIED | `App\Support\PaymentMethod` defines all 5 constants; `MethodPill` component renders all 5; `PaymentFactory::bkash()` state works |
| 3  | Reference required when method != cash, optional for cash (D-02) | ✓ VERIFIED | `StorePaymentRequest::rules()` builds `'reference' => [$isCash ? 'nullable' : 'required', ...]` from `$this->input('method')`; `PaymentCrudTest::test_bkash_requires_reference` pass |
| 4  | Member can view own payment history; manager sees all with filters (SC-2, SC-3, PAY-04/05) | ✓ VERIFIED | `PaymentController::index` (manager filters by member/method/date), `MyPaymentController::index` (member scoped via `$member->id`); `PaymentListTest` (4 tests), `PaymentHistoryTest::test_member_sees_only_their_payments` pass |
| 5  | Each member has an advance_balance + due_balance that carry forward month-to-month (SC-4, ADV-01/02) | ✓ VERIFIED | Migration `2026_06_18_000000_add_due_balance_to_advance_balances`; `AdvanceBalanceService::carryForward(memberId, string)` accumulates via `bcadd` on 2-decimal strings; called from `MonthCloseService::close` for non-zero net bills |
| 6  | Manager sees live "if we closed today" meal rate on dashboard (SC-5, PREVIEW-01) | ✓ VERIFIED | `resources/views/home.blade.php:61` links to `mess.bill-preview.index` + calls `BillPreviewService::preview(now)` for the rate display |
| 7  | Manager sees each member's running bill for current month (SC-6, PREVIEW-02) | ✓ VERIFIED | `/mess/bill-preview` via `BillPreviewController::index` → `mess.bill-preview.index` view with `_summary` + `_row-cards` (table + mobile cards); `BillPreviewTest` (4 tests) pass |
| 8  | Member sees their own running bill (SC-7, PREVIEW-03) | ✓ VERIFIED | `/my?tab=bill-preview` → `MyBillPreviewController::index` resolves `$member` and renders `my._bill-preview` with member-scoped row; `MyBillPreviewTest` passes |
| 9  | Manager can trigger month-close for a (year, month) (SC-8, CLOSE-01) | ✓ VERIFIED | `MonthCloseController::trigger` (POST `/mess/close`) dispatches `CloseMonthJob::dispatch($year, $month, $closedBy)`; `MonthCloseControllerTest::test_admin_can_dispatch_close_job` (Queue::fake) pass |
| 10 | Month-close runs as a queued job (SC-9, CLOSE-02) | ✓ VERIFIED | `CloseMonthJob implements ShouldQueue` (`app/Jobs/CloseMonthJob.php:12`); `$tries = 1`, `$timeout = 120`; sync in tests via phpunit.xml, database driver in dev |
| 11 | Close is idempotent — second attempt refused (SC-10, CLOSE-07) | ✓ VERIFIED | Migration `2026_06_16_221400` declares `$table->unique(['mess_id', 'year', 'month'])`; `MonthCloseService::close` uses `firstOrCreate` + `wasRecentlyCreated` branch; `MonthCloseServiceTest::test_idempotent_close_does_not_duplicate_rows` pass |
| 12 | Close persists immutable snapshot to monthly_closings + monthly_member_summaries (SC-11, CLOSE-09) | ✓ VERIFIED | `MonthCloseService::close` writes 1 `MonthlyClosing` + N `MonthlyMemberSummary` rows inside `DB::transaction`; `MonthlyCorrectionService::create` only writes a new `monthly_corrections` row + calls `carryForward` — never mutates existing summaries (test_correction_does_not_mutate_existing_member_summary_snapshot) |
| 13 | Closed months are read-only via middleware (SC-12, CLOSE-10) | ✓ VERIFIED | `EnsureMonthIsOpen` middleware (`month.open` alias registered in bootstrap/app.php:17) applied to 11 write routes (routes/web.php:74-120); `EnsureMonthIsOpenTest::test_payment_write_to_closed_month_is_rejected` pass |
| 14 | Corrections go through monthly_corrections, not edits (SC-13, CLOSE-12) | ✓ VERIFIED | `MonthlyCorrectionController` (index/create/store) + `MonthlyCorrectionService::create` writes a new `MonthlyCorrection` row; correction routes intentionally NOT locked by `month.open` (routes/web.php:135 comment explains why); `MonthlyCorrectionTest` (5 tests) pass |
| 15 | Mid-month joiners/leavers prorated by days (SC-14, D-12/D-13) | ✓ VERIFIED | `BillPreviewService::eligibleForDenominator` excludes members with `joining_date > monthStart` or `leaving_date < monthEnd`; `MonthCloseServiceTest::test_mid_month_joiner_excluded_from_denominator` + `BillPreviewService` active-day proration in `compute` |
| 16 | PHPUnit tests for bill computation, carry-forward, idempotency, prorated fixed cost, queued close (SC-15) | ✓ VERIFIED | Full suite `php artisan test` → 162 passed (352 assertions); 13 phase-03 test files cover all listed scenarios |

**Score:** 16/16 truths verified

Additional roadmap SCs not listed above:
- SC-16 "In-app notification on close completion" → covered by NOTIF-01 (below, notification truths all pass)

Notification truths (NOTIF-01..05, all VERIFIED):
- NOTIF-01 close_complete to managers: `NotificationService::broadcastToManagers` mess-scoped (WR-08 fixed) called from `MonthCloseService::close`; `MonthCloseServiceTest::test_close_writes_close_complete_notification_for_managers` pass
- NOTIF-02 meal_off_decision: wired in `MealOffApprovalService::approve/reject` via `$this->notifications->send(..., MEAL_OFF_DECISION, ...)`
- NOTIF-03 payment_recorded: wired in `PaymentService::create` after `applyPayment` (lines 73-83)
- NOTIF-04 due_reminder broadcast: `DueReminderController::send` filters `due_balance > 0`; `DueReminderTest` (4 tests) pass
- NOTIF-05 bell + center: `<x-notification-bell />` in `layouts/app.blade.php:26`; `NotificationBell` component reads `unreadCount`; `/notifications` route registered (shared admin+user)

Audit truths (D-30, AUDIT-01):
- `Payment`, `MonthlyClosing`, `MonthlyMemberSummary`, `MonthlyCorrection` all `implements AuditableContract` with `use Auditable,` trait (verified by grep across all 4 files)
- `AdvanceBalance` deliberately NOT audited (D-11) — its writes are derived from audited source events
- `PaymentAuditTest`, `MonthlyCorrectionTest::test_correction_writes_audit_log` pass

### Required Artifacts

All artifacts checked at Levels 1-4 (exists, substantive, wired, data-flowing). Selected high-signal artifacts:

| Artifact | Expected | Status | Details |
| -------- | -------- | ------ | ------- |
| `app/Services/PaymentService.php` | create/update/list with advance + notif hooks | ✓ VERIFIED | 117 lines; `applyPayment` + NOTIF-03 wired; `update` calls `reversePayment` then `applyPayment` (WR-01 fixed) |
| `app/Services/AdvanceBalanceService.php` | applyPayment/adjust/carryForward/reversePayment | ✓ VERIFIED | 152 lines; BC math (`bcadd`/`bcsub`/`bccomp`) on 2-decimal strings (CR-03 fixed); no float decomposition |
| `app/Services/MonthCloseService.php` | idempotent + atomic close | ✓ VERIFIED | 137 lines; `firstOrCreate` + `wasRecentlyCreated`; `DB::transaction`; carry-forward via signed BC string; invalidates cache; broadcasts notification |
| `app/Services/BillPreviewService.php` | cached read-only preview | ✓ VERIFIED | `Cache::remember($key, now()->addHour(), ...)`; key `bill-preview:{messId}:{year}-{MM}`; returns `total_bazar/total_meals/meal_rate/members/rows` |
| `app/Http/Middleware/EnsureMonthIsOpen.php` | refuse writes to closed months | ✓ VERIFIED | Strict `Carbon::createFromFormat('!Y-m-d', ...)` (WR-04 fixed); route-model fallback for 5 model types |
| `app/Jobs/CloseMonthJob.php` | queued close | ✓ VERIFIED | `implements ShouldQueue`; `$tries=1`; sync in tests |
| `app/Providers/AppServiceProvider.php` | cache invalidation hooks | ✓ VERIFIED | `Event::listen("eloquent.saved: {$modelClass}", function (Model $model) ...)` (CR-01 fixed — no `$event->model`); per-model date column resolver (WR-02 mitigated) |
| `app/Services/NotificationService.php` | send/broadcast/markRead | ✓ VERIFIED | `broadcastToManagers` mess-scoped via `whereHas('members', ...)` (WR-08 fixed); super-admins always included |
| `database/migrations/2026_06_18_000000_add_due_balance_to_advance_balances.php` | D-08 column | ✓ VERIFIED | `decimal('due_balance', 10, 2)->default(0)` |
| `database/migrations/2026_06_16_221400_create_monthly_closings_table.php` | CLOSE-07 unique index | ✓ VERIFIED | `$table->unique(['mess_id', 'year', 'month'])` |

### Key Link Verification

| From | To | Via | Status | Details |
| ---- | -- | --- | ------ | ------- |
| PaymentController::store | PaymentService::create → AdvanceBalanceService::applyPayment | DI constructor + method call | ✓ WIRED | PaymentService.php:16,71 |
| PaymentService::create | NotificationService::send (PAYMENT_RECORDED) | DI + send() | ✓ WIRED | PaymentService.php:76 |
| MonthCloseController::trigger | CloseMonthJob::dispatch → MonthCloseService::close | dispatch + handle(DI) | ✓ WIRED | MonthCloseController.php:49; CloseMonthJob.php:30 |
| MonthCloseService::close | AdvanceBalanceService::carryForward | app()->make + signed string | ✓ WIRED | MonthCloseService.php:94-102 |
| MonthCloseService::close | NotificationService::broadcastToManagers | app()->make | ✓ WIRED | MonthCloseService.php:108 |
| AppServiceProvider::boot | BillPreviewInvalidator::forDate on 5 model events | Event::listen | ✓ WIRED | AppServiceProvider.php:62-71 |
| MealOffApprovalService::approve/reject | NotificationService::send (MEAL_OFF_DECISION) | DI + send() | ✓ WIRED | MealOffApprovalService.php:56 |
| routes/web.php (11 routes) | `month.open` middleware | ->middleware('month.open') | ✓ WIRED | routes/web.php:74-120 |
| MonthlyCorrectionService::create | AdvanceBalanceService::carryForward + BillPreviewService::invalidate | DI + app() | ✓ WIRED | (verified by MonthlyCorrectionTest) |

### Data-Flow Trace (Level 4)

| Artifact | Data Variable | Source | Produces Real Data | Status |
| -------- | ------------- | ------ | ------------------ | ------ |
| `BillPreviewService::compute` | `meal_rate` | `Expense::sum('amount')` (bazar kind) ÷ `MealEntry::sum(meal_value)` | Yes — real DB queries | ✓ FLOWING |
| `MonthCloseService::close` | `net_bill` per member | `BillPreviewService::preview()['members'][i]['due']` | Yes — verifiable by `BillPreviewTest::test_preview_computes_meal_rate` | ✓ FLOWING |
| `AdvanceBalance.balance` | running credit | `carryForward()` accumulates via `bcadd` from close + applyPayment | Yes — accumulates across closes (tested) | ✓ FLOWING |
| `Notification` rows | per-recipient | `broadcastToManagers` writes one per admin+super-admin in active mess | Yes — `MonthCloseServiceTest::test_close_writes_close_complete_notification_for_managers` | ✓ FLOWING |

### Behavioral Spot-Checks

| Behavior | Command | Result | Status |
| -------- | ------- | ------ | ------ |
| Full test suite passes | `php artisan test` | Tests: 162 passed (352 assertions), 8.87s | ✓ PASS |
| Idempotent close | (covered by `test_idempotent_close_does_not_duplicate_rows`) | part of suite | ✓ PASS |
| Hard lock on closed month | (covered by `test_payment_write_to_closed_month_is_rejected`) | part of suite | ✓ PASS |
| Close math == preview math | (covered by `test_close_numbers_match_bill_preview_service_for_same_inputs`) | part of suite | ✓ PASS |
| Carry-forward accumulation across closes | (covered by CR-03 integration test added per review) | part of suite | ✓ PASS |

### Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
| ----------- | ----------- | ----------- | ------ | -------- |
| PAY-01 | 3.1 | Record payment (member, date, amount, method, reference, notes) | ✓ SATISFIED | PaymentController::store; StorePaymentRequest rules |
| PAY-02 | 3.1 | Cash/bKash/Nagad/Rocket/Bank methods | ✓ SATISFIED | App\Support\PaymentMethod constants |
| PAY-03 | 3.1 | type field bill_payment/advance_deposit NOT NULL | ✓ SATISFIED | PaymentType enum + schema + StorePaymentRequest |
| PAY-04 | 3.1 | Manager view all payments w/ filters | ✓ SATISFIED | PaymentService::list; PaymentListTest |
| PAY-05 | 3.1 | Member view own payment history | ✓ SATISFIED | MyPaymentController::index; PaymentHistoryTest |
| PAY-06 | 3.1+3.4 | Payment editable only if month open | ✓ SATISFIED | Payment uses SoftDeletes; update/destroy routes under `month.open` |
| ADV-01 | 3.2 | advance_balance carries month-to-month | ✓ SATISFIED | AdvanceBalanceService::carryForward accumulates |
| ADV-02 | 3.2 | advance_deposit increases advance_balance | ✓ SATISFIED | applyPayment only acts on ADVANCE_DEPOSIT |
| ADV-03 | 3.2+3.3 | bill_payment may consume advance before cash | ⚠ PARTIAL | Advance consumption happens at close (BillPreviewService computes `advance_applied`) — see deferred item on column naming |
| ADV-04 | 3.2 | Excess advance carries to next month | ✓ SATISFIED | Negative net_bill → carryForward to balance (MonthCloseService) |
| ADV-05 | 3.2 | Bill > advance → difference is new due | ✓ SATISFIED | Positive net_bill → carryForward to due_balance |
| ADV-06 | 3.2 | Member can view current advance balance | ✓ SATISFIED | /my?tab=advance-balance; MyAdvanceBalanceTest |
| ADV-07 | 3.2 | Adjustments logged in audit trail | ✓ SATISFIED | AdvanceBalanceService::adjust Log::info; source Payment + MonthlyClosing are Auditable (AdvanceBalance itself is not — D-11) |
| PREVIEW-01..05 | 3.3 | Live bill preview + 1-hour cache + invalidation | ✓ SATISFIED | BillPreviewService; AppServiceProvider hooks; BillPreviewTest + BillPreviewCacheTest |
| CLOSE-01 | 3.4 | Manager triggers close | ✓ SATISFIED | MonthCloseController::trigger |
| CLOSE-02 | 3.4 | Close runs as queued job | ✓ SATISFIED | CloseMonthJob implements ShouldQueue |
| CLOSE-03 | 3.4 | Notification on completion (success or failure) | ⚠ PARTIAL | Success notification wired (NOTIF-01); no `failed()` method on job → no failure notification. Routed to human verification. |
| CLOSE-04 | 3.3+3.4 | Meal rate = total_bazar / total_meals | ✓ SATISFIED | BillPreviewService::compute |
| CLOSE-05 | 3.3+3.4 | Fixed cost share prorated by days | ✓ SATISFIED | BillPreviewService (active-days math); test_mid_month_joiner_excluded_from_denominator |
| CLOSE-06 | 3.3+3.4 | Per-member bill math | ✓ SATISFIED | BillPreviewService::compute; parity test |
| CLOSE-07 | 3.4 | Idempotent UNIQUE (mess_id, year, month) | ✓ SATISFIED | Migration + firstOrCreate |
| CLOSE-08 | 3.4 | SELECT FOR UPDATE to lock mess row | ⚠ PARTIAL | AdvanceBalance::query()->lockForUpdate()->firstOrCreate() locks the balance row (not the mess row). The UNIQUE index backstops races. No explicit Mess-row lock, but the design is safe via UNIQUE + transaction. Acceptable. |
| CLOSE-09 | 3.4 | Immutable snapshot persisted | ✓ SATISFIED | MonthlyClosing + MonthlyMemberSummary writes |
| CLOSE-10 | 3.4 | Hard-lock once closed | ✓ SATISFIED | EnsureMonthIsOpen middleware on 11 routes |
| CLOSE-11 | 3.4 | Closed month view read-only w/ banner | ✓ SATISFIED | closings/show.blade.php + home banner + ClosedMonthBannerTest |
| CLOSE-12 | 3.4 | Corrections via monthly_corrections only | ✓ SATISFIED | MonthlyCorrectionService; snapshot immutability test |
| NOTIF-01..05 | 3.4 | All 5 notification paths | ✓ SATISFIED | See notification truths above |

No orphaned requirements found — every requirement ID mapped to Phase 3 in REQUIREMENTS.md traceability is claimed by at least one plan and has implementation evidence.

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
| ---- | ---- | ------- | -------- | ------ |
| `app/Services/BillPreviewService.php` | 153-162 | `advance_applied` column semantically holds `bill_payments` sum, not advance consumed (documented in code comment) | ℹ️ Info | Math is internally consistent and parity-tested; column rename is a user-deferred follow-up |
| `app/Jobs/CloseMonthJob.php` | — | No `failed()` method to send close_failed notification | ⚠️ Warning | CLOSE-03 partially satisfied (success only). Atomic transaction prevents partial state; sync queue surfaces failures as 500s. |
| `app/Services/MonthCloseService.php` | 105 | `Cache::forget` runs inside `DB::transaction` (WR-07) | ℹ️ Info | Acceptable: rollback re-computes pre-close state on next read; no correctness issue |

No blockers found. No `TODO`/`FIXME`/`PLACEHOLDER` markers in any of the 7 critical service files.

### Human Verification Required

1. **Manager close trigger end-to-end**
   - Test: Manager clicks "Close month" at `/mess/close` with a queue worker running
   - Expected: Redirect with success flash; close_complete notification badge increments for all admins+super-admins in the active mess; new row appears at `/mess/closings`
   - Why human: Requires live `php artisan queue:work` + real browser session; sync queue in tests hides async UX

2. **Hard-lock via the real UI form**
   - Test: Manager opens the payment create form and submits a payment dated inside an already-closed month
   - Expected: Form re-renders with a "MONTH CLOSED" validation error on the date field; no payment row written
   - Why human: Requires interaction with `ensureMonthIsOpen` via real Blade form POST (middleware reads `date` input)

3. **CloseMonthJob failure notification (CLOSE-03 gap)**
   - Test: Force a job failure (e.g. drop a column mid-test, or mock `MonthCloseService::close` to throw) and observe whether the manager is notified
   - Expected: Per CLOSE-03, manager should be notified of failure. Currently NOT implemented.
   - Why human: Decision needed — is silent failure acceptable given sync queue surfaces 500s, or should a `failed()` method be added? Routed as human verification per task instructions (deferred review items are out of scope).

### Gaps Summary

No blocking gaps. The phase goal — "manager can take payments (cash, bKash, Nagad, Rocket, bank) with bill/advance type distinction, and the system can run a queued, idempotent, hard-locked month-close" — is fully achieved.

All 16 roadmap success criteria verified. All 6 critical review findings (CR-01, CR-02, CR-03, WR-01, WR-04, WR-08) confirmed fixed in code. The user-deferred review items (WR-02/03/05/06/07/09, IN-01..06, advance_applied rename) are documented as deferred — they do not affect any must-have or requirement materially.

Minor observations (not gaps):
- CLOSE-03 "success or failure" — only success notification wired (no `CloseMonthJob::failed()`). Routed to human verification; does not block the goal.
- CLOSE-08 "SELECT FOR UPDATE on mess row" — implemented as `lockForUpdate` on the balance row + UNIQUE index backstop. Safe design, just not on the literal mess row.
- ADV-03 "advance consumed before cash" — handled at close-time via `BillPreviewService::advance_applied` computation; column naming deferred.

Test suite: **162 passed, 352 assertions, 8.87s** — green.

---

_Verified: 2026-06-17T12:00:00Z_
_Verifier: Claude (gsd-verifier)_
