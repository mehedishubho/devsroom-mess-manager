# Phase 3: Payments + Month-Close - Context

**Gathered:** 2026-06-17
**Status:** Ready for planning

<domain>
## Phase Boundary

Manager can take payments (bill payment + advance deposit, with method + reference + notes), each member carries an `advance_balance` (credit) and `due_balance` (debt) forward month-to-month, manager and member can both see a live "if we closed today" bill preview (cached 1-hour, invalidated on write), and the system can run a queued, idempotent, hard-locked month-close that produces an immutable snapshot, supports corrections via a separate table, and emits in-app notifications.

This is the financial core. The 4 plans in the roadmap deliver this slice end-to-end (PAY, ADV, PREVIEW, CLOSE, NOTIF requirement groups). Reports, dashboard cards, PDF/Excel export are Phase 4+ and out of scope.

</domain>

<decisions>
## Implementation Decisions

### Payment recording (PAY-01 to PAY-06)

- **D-01:** **Single form, segmented "Payment type" control at top.** Manager toggles between `Bill payment` and `Advance deposit` on the same form; the rest of the fields are identical. Faster to build, fewer screens. Default type is `bill_payment` (matches schema default).
- **D-02:** **Form defaults:** `date` defaults to today (`Asia/Dhaka`), `method` defaults to `Cash`. `reference` is required only when `method` is non-cash (bKash/Nagad/Rocket/Bank Transfer). `notes` is always optional. Matches Bangladesh mess reality: cash payments have no reference number.
- **D-03:** **Hard rule — no edits or deletes after month-close.** PAY-06 is enforced strictly. The `EnsureMonthIsOpen` middleware (see D-12) refuses any PATCH/DELETE/POST on a payment whose month is closed. Corrections go through `monthly_corrections`, NOT edits to the original payment.
- **D-04:** **Both manager and member see payment history, full detail.** Manager has a filterable list at `/mess/payments` (filters: member, method, date range per PAY-04). Member sees their own complete payment history on `/my/payments` (PAY-05). Same data shape for both views, no PII concerns in v1.
- **D-05:** **Payment methods are locked at the Phase 1 set:** `cash`, `bkash`, `nagad`, `rocket`, `bank` (PAY-02). Use an enum class `App\Support\PaymentMethod` mirroring the `ExpenseKind` / `MealType` pattern.
- **D-06:** **Manager-only writes; member is read-only on payments.** Member cannot submit payments themselves — manager records everything. Matches PROJECT.md "Manager-records payments" key decision. Member sees their history on `/my` but cannot record/edit/delete.

### Advance balance math (ADV-01 to ADV-07)

- **D-07:** **`bill_payment` does NOT touch `advance_balance`.** A bill payment is treated as cash paid for THIS month's bill. `advance_balance` only changes on (a) `advance_deposit` payment (immediate increase) and (b) month-close (decrease when bill < advance). Cleanest mental model: bill_payment reduces what they owe, advance_deposit increases what they have stored.
- **D-08:** **Add a separate `due_balance` column to `advance_balances`.** Two fields per member row: `advance_balance` (credit, never negative, default 0) and `due_balance` (debt, never negative, default 0). Matches Bangladesh mess vocabulary: "I owe ৳500" is one concept, "I have ৳2000 in advance" is another. New migration adds `due_balance` + `(mess_id, member_id)` unique is already there.
- **D-09:** **Carry-forward direction at month-close:**
  - If `net_bill > 0` → add to member's `due_balance` for next month
  - If `net_bill < 0` (advance > bill) → add the absolute value to member's `advance_balance` for next month
  - The MonthlyMemberSummary's `balance_due` is informational for the closed month; the actual carry-forward is written to `advance_balances` at close time.
- **D-10:** **`advance_balance` updates on two events only:** (a) `advance_deposit` payment (immediate), (b) month-close (decrease when bill < advance). Mid-month `bill_payment` does not touch advance balance. Matches ADV-03 exactly and avoids write contention on the shared cache.
- **D-11:** **Advance/due adjustments are logged in the audit trail.** Per ADV-07. The source events (payments, corrections, close) are already audited via `Auditable` trait on those models. `advance_balances` itself is NOT individually audited — its changes are derived from audited source events.

### Live bill preview + caching (PREVIEW-01 to PREVIEW-05, CLOSE-04/05/06)

- **D-12:** **Meal rate calculation excludes mid-month joiners/leavers from the meal count.** Denominator = sum of `meal_value` for active meals of members whose `joining_date <= month_start` AND (`leaving_date IS NULL` OR `leaving_date >= month_end`). A member who joined on day 15 still has their meals counted, but only if they were active for the whole month. The numerator is `total_bazar` (sum of `expenses` where `category.kind = 'bazar'`).
- **D-13:** **Fixed cost share is prorated by days in month.** Each member's fixed share = `total_fixed * (days_member_was_active_this_month / total_days_in_month)`. Member who joined day 15 of a 30-day month pays 50% of fixed. Matches CLOSE-05 exactly.
- **D-14:** **Single shared cache key per (mess, year, month).** Key pattern: `bill-preview:{mess_id}:{year}-{month}`. Cached for 1 hour (PREVIEW-05 TTL). All members see identical numbers at identical times. Stored in the `database` cache driver (already configured in `.env`).
- **D-15:** **Invalidate cache on every relevant write.** Any write to `meal_entries`, `meal_off_requests`, `guest_meals`, `expenses`, or `payments` for that `(mess_id, year, month)` immediately invalidates the cache key. Next reader recomputes. Matches PREVIEW-04 (updates within 2s).
- **D-16:** **Preview is "as of today" for the current month, "as of last day of month" otherwise.** Current month = compute against meals/expenses/payments up to today. Past month with a close = use the `MonthlyMemberSummary` row directly (no live compute). Past month without a close (manager never closed it) = compute against last day of that month using whatever data exists.
- **D-17:** **Preview uses the configured `meal_value` setting for guest meals + meal_value for regular meals.** Per-meal-type cost = `meal_rate * meal_value`. Regular meal cost = `meal_rate * total_meal_count` (where breakfast=0.5, lunch=1, dinner=1 already). Guest meal charges are already locked at entry time per Phase 2 D-17 (do not re-compute at preview or close).

### Month-close job (CLOSE-01 to CLOSE-12)

- **D-18:** **`firstOrCreate` on `(mess_id, year, month)` + unique index backstop.** Job calls `MonthlyClosing::firstOrCreate([...], [...])`, inspects `wasRecentlyCreated`. If false, abort with a "month already closed" notification. The unique index on `monthly_closings (mess_id, year, month)` is the source of truth. No `SELECT FOR UPDATE` lock needed.
- **D-19:** **Hard-lock via `EnsureMonthIsOpen` middleware on manager write routes.** Middleware reads the record's `date` (or the closest date column on the route's model), computes `(year, month)`, checks if `monthly_closings` has a row. If yes → 403 with "MONTH CLOSED — corrections only via monthly_corrections". Applied to routes that write to `meal_entries`, `meal_off_requests`, `guest_meals`, `expenses`, `payments`. Read routes are unaffected.
- **D-20:** **Queued job runs on `database` driver (already configured).** `QUEUE_CONNECTION=database` in `.env`. Job class `CloseMonthJob` implements `ShouldQueue`. Dispatched from `MonthCloseController::close()`. Tests use `QUEUE_CONNECTION=sync` so assertions are immediate (already in `phpunit.xml`).
- **D-21:** **Manager trigger UX: dedicated `/mess/close` page with confirmation modal.** Page shows current month + live bill preview + "Close {Month Year}" button. Click → confirmation modal with same preview + "Yes, close now" / "Cancel". On confirm, dispatches job. After dispatch, manager sees "Closing…" spinner; notification arrives on completion via the in-app bell.
- **D-22:** **No AJAX polling for progress.** Small mess (30 members) closes in < 2 seconds. Spinner shows; notification fires when job completes. If the close fails, the notification includes the error message. Adding a `status` column on `MonthlyClosing` for progress tracking is deferred to Phase 5 if real-mess testing shows > 5s close times.
- **D-23:** **Document `php artisan queue:work` in README and the dev workflow.** Phase 3 README section: "Open a second terminal and run `php artisan queue:work` to process close jobs." Production deployment with supervisor/systemd is Phase 5 polish.

### Monthly corrections (CLOSE-12)

- **D-24:** **Correction applies immediately to advance/due balances.** `monthly_corrections` row created with `amount` (signed: + = credit to member, − = debit), `reason` (required), `applied_to_year`, `applied_to_month`. The amount immediately adjusts the member's `advance_balance` (if positive) or `due_balance` (if negative). The closed month's `monthly_member_summaries` is NOT rewritten — the original snapshot stays immutable (CLOSE-09 honored).
- **D-25:** **Manager-only correction UI.** Route `/mess/closings/{closing}/corrections/create`. Shows the closing, lets manager pick member, enter signed amount + reason. List view at `/mess/closings/{closing}/corrections`. No correction deletion in v1 (audit trail is the point — delete would re-open trust disputes).
- **D-26:** **Correction writes also flow through the cache invalidation.** A correction to a closed month's `applied_to_year/month` invalidates the live preview cache for that (year, month) so future previews pick up the adjustment.

### Notifications (NOTIF-01 to NOTIF-05)

- **D-27:** **All 5 NOTIF events fire as in-app `Notification` rows.** Uses the existing `App\Models\Notification` table (custom domain model, NOT `Illuminate\Notifications\Notification`). Use FQCN `\App\Models\Notification` when referencing from app code. NOTIF-01 (close complete), NOTIF-02 (meal off decision), NOTIF-03 (payment recorded for member), NOTIF-04 (manager due reminder broadcast), NOTIF-05 (notification bell in main nav with unread count).
- **D-28:** **Notifications are in-app only.** No email, no SMS, no push. Matches PROJECT.md anti-recommendation "no SMS / WhatsApp in v1" and "no real-time websockets". Polling via page reload (or in-page refresh action). Notification bell on every page in the main nav, with unread count.
- **D-29:** **NOTIF-04 (due reminder) is manager-triggered broadcast.** Manager opens a "Send due reminders" page, sees a list of members with `due_balance > 0`, selects which to notify (default all), clicks "Send". Each selected member gets a `Notification` row of type `due_reminder`. Manual dispatch, not automated. (Auto-monthly reminder is v2 per SET-04.)

### Audit + tests strategy (AUDIT-01 to AUDIT-05, CLOSE-15, ADV-07)

- **D-30:** **Add `Auditable` trait to `Payment`, `MonthlyClosing`, `MonthlyMemberSummary`, `MonthlyCorrection`.** Already on `Mess`, `Member`, `MealEntry`, `MealOffRequest`, `GuestMeal`, `Expense`, `ExpenseCategory` (from Phases 1 + 2). `AdvanceBalance` does NOT get `Auditable` — its changes are derived from audited source events (D-11).
- **D-31:** **Tests use `Queue::fake()` for dispatch + `QUEUE_CONNECTION=sync` for happy-path.** Dispatch tests assert the right job class was pushed with the right args. Happy-path tests of close itself run synchronously so assertions are immediate. Both patterns in the same test class.
- **D-32:** **Comprehensive `MonthCloseService` unit tests, 10+ scenarios.** Cover per PITFALLS #3 risk: idempotency (double-close returns same id, no duplicate rows), mid-month joiner, mid-month leaver, prorated fixed cost, advance carry-forward (positive case), excess advance carry-forward (negative bill case), all-zero edge case, guest meal charge uses locked amount, correction applies + audit trail writes, queue job executes the service and writes notifications. Aim for ≥ 90% coverage on `MonthCloseService` and `BillPreviewService`.

### Currency/date display

- **D-33:** **All money uses the `bdt()` helper from `app/helpers.php`.** Helper reads the mess's currency setting (default BDT, symbol ৳). Single source of truth, matches PITFALLS #13 prevention.
- **D-34:** **All dates use the per-mess `date_format` setting.** Default `DD-MM-YYYY` per Phase 1 D-23. Applied uniformly to payment dates, close period labels, correction `applied_to` labels, and audit timestamps in views. Read via the existing settings helper.

### the agent's Discretion

- Exact cache key string format (as long as it includes mess_id + year-month)
- Whether to add a `status` column on `MonthlyClosing` (default: not in v1, deferred to Phase 5)
- Whether the notification bell is a partial refresh via AJAX or full page reload (mobile UX)
- How to surface unread count to the layout (view composer reading the count, passed from controller)
- Exact seed for the default test scenarios in `MonthCloseServiceTest`
- Form layout breakpoints (375px baseline per Phase 5 polish)
- Whether to add a payment receipts upload (NOT in scope per PAY-01, deferred to v2)
- Whether the member `/my` page shows just last 6 months of payment history or all of them
- Whether `corrections` are listed under a separate nav item ("Corrections") or under "Closings"
- How the close job handles a member with `joining_date > month_end` (skip; they weren't a member that month)
- How the close job handles a member with `leaving_date < month_start` (skip; they left before the month)

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Project context
- `.planning/PROJECT.md` — Vision, constraints, key decisions (esp. "Meal rate = bazar only", "Hard-lock on month close", "Queued month-close", "Idempotent month-close", "Cached current-month aggregates")
- `.planning/REQUIREMENTS.md` — 154 v1 requirements (PAY-01 to PAY-06, ADV-01 to ADV-07, PREVIEW-01 to PREVIEW-05, CLOSE-01 to CLOSE-12, NOTIF-01 to NOTIF-05)
- `.planning/ROADMAP.md` — Phase 3 success criteria, out-of-scope items, plan breakdown (4 plans)
- `.planning/STATE.md` — Current progress, validations from Phase 1 + 2

### Prior phase context
- `.planning/phases/01-foundation/01-CONTEXT.md` — 24 locked Phase 1 decisions (especially D-23 timezone, D-24 decimal money, D-19 `mess_id` on all tables, D-09 owen-it Auditable)
- `.planning/phases/01-foundation/01.3-SUMMARY.md` — layout pattern (manager sidebar + mobile drawer)
- `.planning/phases/02-members-daily-operations/02-CONTEXT.md` — 24 locked Phase 2 decisions (especially D-17 guest meal charge locked at entry, D-22 expense category kind)
- `.planning/phases/02-members-daily-operations/02-DISCUSSION-LOG.md` — full Q&A audit trail for Phase 2
- `.planning/phases/02-members-daily-operations/02-RESEARCH.md` — service-layer pattern research
- `.planning/phases/02-members-daily-operations/02-UI-SPEC.md` — mobile-first Blade UI contract

### Codebase maps (already in repo)
- `.planning/codebase/STACK.md` — Installed packages, runtime versions
- `.planning/codebase/CONVENTIONS.md` — Code style, attribute-based model config, migration style, test style, Form Request pattern
- `.planning/codebase/STRUCTURE.md` — Directory layout
- `.planning/codebase/INTEGRATIONS.md` — Tyro config, mail, cache, queue, session drivers
- `.planning/codebase/TESTING.md` — PHPUnit 12 patterns, RefreshDatabase, factory usage

### Research
- `.planning/research/SUMMARY.md` — Stack decisions, anti-features
- `.planning/research/PITFALLS.md` — **Phase 3 pitfalls:** #1 (bill/advance conflation), #3 (month-close race), #4 (editing closed month), #11 (cache staleness), #15 (cache stampede)
- `.planning/research/ARCHITECTURE.md` — Service-layer-no-repository, Form Requests, Auditable trait
- `.planning/research/STACK.md` — Why owen-it/laravel-auditing, queue database driver

### Skills (project-local, used during implementation)
- `.agents/skills/laravel-best-practices/SKILL.md` — Laravel 13 best practices, N+1 detection, queue jobs, validation patterns
- `.agents/skills/tyro-dashboard/SKILL.md` — Tyro patterns, app integration

### Taste preferences
- `.commandcode/taste/taste.md` — Laravel 13, MySQL, snake_case DB names, verify DB creds, **always use `Mess::activeId()`** for `mess_id`

### External package docs (to consult during research/planning)
- `owen-it/laravel-auditing` GitHub README — model usage (already installed)
- Laravel queues docs — `ShouldQueue`, `Queue::fake()`, `database` driver, failed_jobs handling
- Laravel cache docs — `Cache::remember()`, `Cache::forget()`, TTL, the `database` driver
- Laravel testing docs — `Bus::fake()` vs `Queue::fake()` semantics

### Schema references (already in repo)
- `database/migrations/2026_06_16_221300_create_payments_table.php` — `payments` with `type`, `method`, `reference`, `notes`, indexes
- `database/migrations/2026_06_16_221400_create_monthly_closings_table.php` — **already has `unique(['mess_id', 'year', 'month'])`** for idempotency
- `database/migrations/2026_06_16_221500_create_monthly_member_summaries_table.php` — per-member bill snapshot fields
- `database/migrations/2026_06_16_221600_create_monthly_corrections_table.php` — correction rows
- `database/migrations/2026_06_16_221700_create_advance_balances_table.php` — needs `due_balance` column added (D-08)
- `database/migrations/2026_06_16_221800_create_notifications_table.php` — domain notifications table

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `App\Models\Payment` (`app/Models/Payment.php`) — Fillable, casts, soft-deletes, `enteredBy()` relation. **Phase 3 adds `Auditable` trait** (D-30) and validation rules in `StorePaymentRequest` + `UpdatePaymentRequest`.
- `App\Models\AdvanceBalance` (`app/Models/AdvanceBalance.php`) — Fillable, casts, unique `(mess_id, member_id)`. **Phase 3 adds `due_balance` column** (D-08) and a new `App\Support\PaymentType` enum.
- `App\Models\MonthlyClosing` (`app/Models/MonthlyClosing.php`) — Schema complete with `unique(['mess_id', 'year', 'month'])`. **Phase 3 adds `Auditable` trait** + adds relations to `monthlyMemberSummaries()` and `corrections()`.
- `App\Models\MonthlyMemberSummary` (`app/Models/MonthlyMemberSummary.php`) — All per-member bill fields ready. **Phase 3 adds `Auditable` trait**.
- `App\Models\MonthlyCorrection` (`app/Models/MonthlyCorrection.php`) — Fillable, casts, relations ready. **Phase 3 adds `Auditable` trait**.
- `App\Models\Notification` (`app/Models/Notification.php`) — Custom domain model, NOT `Illuminate\Notifications\Notification`. Uses `data` json column for payload. Use FQCN `\App\Models\Notification`.
- `App\Models\Concerns\BelongsToActiveMess` — Already on Payment, AdvanceBalance, MonthlyClosing, etc. via Phase 1 + 2 setup.
- `App\Models\Mess::activeId()` — Use everywhere for `mess_id`. Already memoized.
- `App\Support\ExpenseKind` — Pattern reference for `App\Support\PaymentType` and `App\Support\PaymentMethod` enums.
- `App\Support\MealType` — Already there for breakfast/lunch/dinner.
- `app/helpers.php` — `bdt($amount)` helper for currency display (D-33).
- `App\Services\MealGridService` (`app/Services/MealGridService.php`) — Pattern reference: query → map → return. Phase 3 follows same shape for `BillPreviewService`.
- `App\Services\ExpenseService` (`app/Services/ExpenseService.php`) — Pattern reference for payment service.
- `App\Http\Controllers\Mess\MealGridController` (`app/Http/Controllers/Mess/MealGridController.php`) — Controller pattern: Form Request → service → redirect with success flash.
- `App\Http\Controllers\Mess\ExpenseController` (`app/Http/Controllers/Mess/ExpenseController.php`) — Pattern for create/store forms.
- `App\Http\Middleware\EnsureMessExists` (`app/Http/Middleware/EnsureMessExists.php`) — Pattern reference for the new `EnsureMonthIsOpen` middleware (D-19).
- `resources/views/layouts/app.blade.php` — Manager layout with sidebar + mobile drawer. **Phase 3 adds nav items:** "Payments", "Close month". Notification bell added to top bar (D-28).
- `database/factories/PaymentFactory.php`, `MonthlyClosingFactory.php`, etc. — All factories stubbed. Phase 3 fills states.
- `tests/TestCase.php` — Base class. `setUp()` sets `mess.active_mess_id = 1`, seeds Tyro roles.

### Established Patterns
- **Attribute-based model config**: `#[Fillable(['...'])]`, `#[Hidden(['...'])]` on all models.
- **`casts()` method, not `$casts` property**. All money columns are `decimal:2`, dates are `date`, booleans are `boolean`.
- **Anonymous-class migrations** with `up()` and `down()`, `Blueprint` typed parameter.
- **Form Requests** for all input validation (`app/Http/Requests/Mess/*`).
- **Service layer** in `app/Services/{Domain}Service.php`. Controllers delegate. No Repository pattern.
- **Test style**: PHPUnit 12, `test_` prefix, `void` return type, extends `Tests\TestCase`. Use `RefreshDatabase` for feature tests.
- **Direct controller invocation via Reflection** in tests to bypass CSRF.
- **`__()` everywhere** for user-facing strings.
- **snake_case columns, plural table names, `$table->timestamps()` on all tables**.
- **`Mess::activeId()` for `mess_id`** — never `config('mess.active_mess_id')` directly.
- **`BelongsToActiveMess` trait + `MessScope` global scope** — automatically filters all domain queries.
- **Enum classes in `app/Support/`** for string-typed columns (PaymentMethod, PaymentType).
- **Tyro role checks**: `$user->hasRole('admin')` for manager.

### Integration Points
- **Routes**: `routes/web.php` — Phase 3 adds manager routes under `role:admin` + `EnsureMessExists` + `EnsureMonthIsOpen` middleware (e.g. `/mess/payments/*`, `/mess/close`, `/mess/closings/*`, `/mess/closings/{closing}/corrections/*`). Member routes under `role:user` (e.g. `/my/payments`).
- **Sidebar nav** (`resources/views/layouts/app.blade.php`): Phase 3 adds "Payments" + "Close month" links. Notification bell in top bar with unread count.
- **Member-facing nav**: Members currently have a placeholder `/my`. Phase 3 adds a "Payment history" section to `/my`.
- **`.env`**: already has `DB_CONNECTION=mysql`, `DB_DATABASE=devsroom_mess_management`, `QUEUE_CONNECTION=database`. No new env keys required. Confirm `CACHE_STORE=database` (or default falls through to database) before implementing cache layer.
- **Queue worker**: Document `php artisan queue:work` in README. Production supervisor config is Phase 5.
- **Existing migrations**: All Phase 3 schema is already in place. Only **new migration** is adding `due_balance` to `advance_balances` (D-08).
- **Cache**: Database driver. `Cache::put`/`Cache::forget` with string keys. Keys do NOT support tags in the database driver — that's why D-14/D-15 use a single string key + manual invalidation (no `Cache::tags()`).
- **Notifications**: Custom `App\Models\Notification` model. NOT Laravel's `Illuminate\Notifications\Notification`. Always use FQCN.

</code_context>

<specifics>
## Specific Ideas

- **Payment form** (`/mess/payments/create`): stacked single-column on mobile. Segmented control at top: "Bill payment | Advance deposit" (defaults to Bill payment). Fields in order: member dropdown (searchable, defaults to most recently paid), date (defaults today), amount, method dropdown (Cash/bKash/Nagad/Rocket/Bank), reference (required only when method != Cash), notes (optional textarea). Save + Cancel at bottom.
- **Payment list** (`/mess/payments`): filter bar at top (member, method, date range). Mobile: cards; desktop: table. Each row: member avatar + name, date, amount, method pill (with method-specific color: green=Cash, pink=bKash, etc.), type pill (Bill payment=neutral, Advance deposit=blue), reference (truncated). Click row → detail/edit.
- **Member payment history** (`/my/payments`): simple table or card list, paginated, latest first. No filters needed for member-side.
- **Close month page** (`/mess/close`): card with current month + year label. "Live preview" section: total meals, total bazar, meal rate, total fixed, per-member table (member, meals, meal cost, fixed share, guest charges, gross bill, advance applied, net bill). "Close {Month Year}" button → modal → "Yes, close now" → dispatches job. After dispatch: "Closing…" spinner + dismiss. Notification arrives when done.
- **Live preview widget** (on `/home` manager dashboard): top card showing "If we closed today, meal rate would be ৳X.XX" with a link to the full preview at `/mess/preview` or `/mess/close`. Cached via the same key.
- **Bill preview page** (`/mess/preview`): full per-member breakdown for the current month. Manager + super-admin only. Same data shape as the live preview section of `/mess/close`.
- **Member preview widget** (on `/my`): "If we closed today, your bill would be ৳X.XX" with a breakdown link. Same cache key, different view shape.
- **Closed-month view banner**: when manager navigates to a date in a closed month (meals, expenses, payments for that month), the page renders with a read-only "MONTH CLOSED — {Month Year}" banner. All write controls are hidden.
- **Correction create form** (`/mess/closings/{closing}/corrections/create`): member dropdown, signed amount (positive = credit to advance, negative = add to due), reason (required textarea), applied_to_year/month (defaults to the closing's year/month, can override for "apply this correction to next month"). Save.
- **Correction list** (`/mess/closings/{closing}/corrections`): table — date, member, amount, applied_to, reason, entered_by. Read-only in v1 (no edit/delete).
- **Notification bell** (top nav): bell icon + red badge with unread count. Click → dropdown showing latest 10 unread, each clickable to mark read + go to source. Polled on page load (no AJAX for v1).

</specifics>

<deferred>
## Deferred Ideas

- **Payment receipts upload** — not in PAY-01; defer to v2.
- **Auto-monthly reminder** (cron-driven due reminder) — SET-04 has `auto_monthly_close=false`; v1 is manual only.
- **Auto-email notifications** — anti-recommendation in PROJECT.md ("no SMS / WhatsApp in v1").
- **WebSocket real-time notification push** — anti-recommendation ("no real-time websockets"); polling is fine.
- **Correction edit/delete** — corrections are audit trail; delete would re-open trust disputes.
- **Multi-month close** (close Jan + Feb in one job) — out of scope; close runs per (year, month).
- **Year-end rollover** (December → January accounting reset) — Bangladesh messes typically settle annually; defer to v2.
- **Notification preferences** (member opts out of certain types) — out of scope; v1 all-or-nothing.
- **Soft-delete monthly_closings** — they're immutable; `monthly_closings.softDeletes()` is wired but should never be triggered. Could remove it.
- **Member payment self-submission** (member pays via member portal, manager approves) — manager records all in v1.
- **2FA enforcement for member role** — defer to v2 (already in v2 AUTH-2FA section).
- **Phase 5 polish reminder**: Add "MySQL-specific queue/cache behavior — verify job processing and cache invalidation on real MySQL" to the Phase 5 checklist (PITFALLS #2 + PITFALLS #11).

</deferred>

---

*Phase: 03-payments-month-close*
*Context gathered: 2026-06-17*
