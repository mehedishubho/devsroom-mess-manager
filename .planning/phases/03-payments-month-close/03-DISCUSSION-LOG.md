# Phase 3: Payments + Month-Close - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-06-17
**Phase:** 03-payments-month-close
**Areas discussed:** payment recording, advance balance math, live bill preview, month-close job, manager close UX, audit + tests strategy, currency/date display

---

## Payment recording + deletion rules

| Option | Description | Selected |
|--------|-------------|----------|
| Single form + type toggle | One form, segmented control at top (Bill / Advance) | ✓ |
| Two separate forms/routes | Distinct pages for bill vs advance | |
| Auto-pick by current member state | Smart default from current balance | |

| Option | Description | Selected |
|--------|-------------|----------|
| Default today/Cash, reference required for non-cash | Matches Bangladesh reality | ✓ |
| Reference always required | Strict but friction-heavy | |
| Only member/amount/method required | Too loose | |

| Option | Description | Selected |
|--------|-------------|----------|
| Hard rule: no edits/deletes after month-close | PAY-06 enforced strictly | ✓ |
| Soft edit: warning only | Manager-friendly but unsafe | |
| Permanent record: reversal payments | Complex, add later if needed | |

| Option | Description | Selected |
|--------|-------------|----------|
| Both manager + member see full history | Simple, no PII in v1 | ✓ |
| Manager only; member sees summary | Less detail for member | |
| Member sees own + mess-wide totals | Transparency but noise | |

**Decisions locked:** D-01, D-02, D-03, D-04 in CONTEXT.md. D-05 (methods enum) + D-06 (manager-only writes) follow from Phase 1 decisions and PAY-02.
**Notes:** Segmented control + cash-default reference rule mirrors Bangladesh mess behavior. Hard-lock aligns with PAY-06 + PITFALLS #4.

---

## Advance balance math

| Option | Description | Selected |
|--------|-------------|----------|
| bill_payment only reduces due; advance_balance only changes on deposit + close | Cleanest mental model | ✓ |
| bill_payment consumes advance first | ADV-03 wording match, but ambiguous cash | |
| Manager picks per payment | Flexible, complex | |

| Option | Description | Selected |
|--------|-------------|----------|
| Add due_balance column to advance_balances | Matches Bangladesh vocabulary | ✓ |
| Signed advance_balance (one field) | Simpler schema, unnatural wording | |
| Recompute due at query time | No write contention, slower reads | |

| Option | Description | Selected |
|--------|-------------|----------|
| Excess bill → next month's due; excess advance → next month's advance | Matches ADV-04 + ADV-05 exactly | ✓ |
| Net to single signed advance_balance | One number, two concepts | |
| Two always-positive balances (due, advance) | More natural |  |

| Option | Description | Selected |
|--------|-------------|----------|
| Update on month-close + on advance_deposit only | Minimal writes | ✓ |
| Update on every payment | More invalidation churn | |
| Update on every event including recompute | Most accurate, expensive | |

**Decisions locked:** D-07, D-08, D-09, D-10 in CONTEXT.md. D-11 (audit source events, not derived balances) follows from PITFALLS #10.
**Notes:** Separate due_balance column avoids signed-number confusion. Settled-at-month-end semantics match Bangladesh practice.

---

## Live bill preview + caching

| Option | Description | Selected |
|--------|-------------|----------|
| Exclude mid-month joiners/leavers from meal count | Cleanest math | ✓ |
| Include all active meals | Simpler but unfair | |
| Prorated meal_value in denominator | Most fair, hardest to explain | |

| Option | Description | Selected |
|--------|-------------|----------|
| Prorate fixed cost share by days_in_month | CLOSE-05 exact match | ✓ |
| Equal split among active members | Simpler but unfair | |
| Equal split full-month members only | Mid-month pays 0 | |

| Option | Description | Selected |
|--------|-------------|----------|
| Single shared cache key per (mess, year, month) | Matches PREVIEW-04 + PREVIEW-05 | ✓ |
| Per-member cache keys | More granular invalidation | |
| No cache, recompute on every request | Simplest, slowest | |

| Option | Description | Selected |
|--------|-------------|----------|
| Invalidate on every relevant write | Matches PREVIEW-04 | ✓ |
| Cache::tags (needs Redis) | Future optimization | |
| Lazy: never invalidate | Violates PREVIEW-04 | |

**Decisions locked:** D-12, D-13, D-14, D-15, D-16, D-17 in CONTEXT.md.
**Notes:** Database cache driver doesn't support tags, so the single-key + manual forget pattern is the natural choice. Mid-month exclusion matches Bangladesh mess math conventions.

---

## Month-close job + corrections + notifications

| Option | Description | Selected |
|--------|-------------|----------|
| firstOrCreate on (mess, year, month), check wasRecentlyCreated | Unique index is source of truth | ✓ |
| Pessimistic SELECT FOR UPDATE lock on messes row | Belt + suspenders, more complex | |
| WithoutOverlapping middleware | Double-defense, more moving parts | |

| Option | Description | Selected |
|--------|-------------|----------|
| EnsureMonthIsOpen middleware on all relevant write routes | Single enforcement point | ✓ |
| Check inside each service | Duplicated logic | |
| MySQL triggers | Hard to test, bypassed by soft-deletes | |

| Option | Description | Selected |
|--------|-------------|----------|
| Apply immediately to advance/due balances | Snapshot stays immutable | ✓ |
| Apply at next month-close | Queued corrections | |
| Rewrite closed-month summary | Violates CLOSE-09 | |

| Option | Description | Selected |
|--------|-------------|----------|
| All 5 NOTIF events, in-app only, polling | Matches PROJECT.md anti-recommendations | ✓ |
| Drop NOTIF-04 due reminders | Manual reminder only | |
| In-app + log channel for debugging | More noise | |

**Decisions locked:** D-18, D-19, D-20, D-22, D-24, D-25, D-26, D-27, D-28, D-29 in CONTEXT.md. D-21 (close trigger UX) captured separately in manager close UX area.
**Notes:** Single middleware is much easier to audit than per-service checks. firstOrCreate + unique index is the canonical Laravel idempotency pattern.

---

## Manager close UX + progress UI

| Option | Description | Selected |
|--------|-------------|----------|
| One click → confirmation modal with snapshot preview | Best balance | ✓ |
| Single click, no confirmation | Risky | |
| Multi-step wizard | Overkill on mobile | |

| Option | Description | Selected |
|--------|-------------|----------|
| Show 'Closing...' spinner, wait for notification | Simplest, matches scale | ✓ |
| 3-second AJAX polling for status | More moving parts | |
| Synchronous close (no queue) | Violates CLOSE-02 | |

| Option | Description | Selected |
|--------|-------------|----------|
| Document queue:work in README, sync driver in tests | Standard pattern | ✓ |
| Scheduler ticks every minute | Overkill | |
| Sync queue in dev too | Loses async feedback | |

**Decisions locked:** D-21, D-22, D-23 in CONTEXT.md.
**Notes:** With 30 members and 1-month data, sync execution would be fine, but the queued pattern is correct and matches CLOSE-02. Production supervisor config is Phase 5.

---

## Audit + tests strategy

| Option | Description | Selected |
|--------|-------------|----------|
| Audit Payment + MonthlyClosing + MonthlyMemberSummary + MonthlyCorrection; not AdvanceBalance | Source events are audited | ✓ |
| Audit everything including AdvanceBalance | Lots of noise | |
| Audit only payments and corrections | Less coverage | |

| Option | Description | Selected |
|--------|-------------|----------|
| Feature test the dispatch + sync-queue tests the actual close | Best of both worlds | ✓ |
| Always sync queue in tests | Less expressive | |
| Manual queue processing in tests | Slowest | |

| Option | Description | Selected |
|--------|-------------|----------|
| Comprehensive MonthCloseService unit tests, 10+ scenarios | Matches PITFALLS #3 risk | ✓ |
| Feature tests only | Less pinpoint | |
| Light tests | Minimum viable | |

**Decisions locked:** D-30, D-31, D-32 in CONTEXT.md.
**Notes:** PITFALLS #3 ("meal rate calculation bugs") explicitly calls for 10+ scenarios. Auditing source events rather than derived state avoids log noise.

---

## Currency/date settings integration

| Option | Description | Selected |
|--------|-------------|----------|
| bdt() helper for all money display | Existing helper, PITFALLS #13 prevention | ✓ |
| NumberFormatter per locale | Heavier, English-only in v1 | |
| Plain number_format | Hard to change later | |

| Option | Description | Selected |
|--------|-------------|----------|
| Per-mess date_format setting everywhere | Consistent with mess settings | ✓ |
| Hardcoded DD-MM-YYYY | Ignores setting | |
| ISO YYYY-MM-DD | Less familiar for Bangladesh users | |

**Decisions locked:** D-33, D-34 in CONTEXT.md.
**Notes:** Both are no-brainers — Phase 1 already established the helpers, just need to use them.

---

## the agent's Discretion

- Exact cache key string format (as long as it includes mess_id + year-month)
- Whether to add a `status` column on `MonthlyClosing` (default: not in v1)
- Whether the notification bell is a partial refresh via AJAX or full page reload
- How to surface unread count to the layout
- Exact seed for the default test scenarios in `MonthCloseServiceTest`
- Form layout breakpoints (375px baseline)
- Whether to add a payment receipts upload (NOT in scope per PAY-01)
- Whether `/my` shows just last 6 months of payments or all
- Where corrections are listed in nav ("Corrections" or under "Closings")
- How the close job handles edge cases (joining_date > month_end, leaving_date < month_start)
- Whether `monthly_closings.softDeletes()` stays (immutable but trait wired from Phase 1)

## Deferred Ideas

- **Payment receipts upload** — v2.
- **Auto-monthly reminder** — v2 (SET-04 prep).
- **Auto-email notifications** — anti-recommendation.
- **WebSocket real-time push** — anti-recommendation.
- **Correction edit/delete** — audit trail integrity.
- **Multi-month close** — single (year, month) only.
- **Year-end rollover** — defer to v2.
- **Notification preferences** — all-or-nothing in v1.
- **Soft-delete monthly_closings** — could remove; currently never triggered.
- **Member payment self-submission** — manager records in v1.
- **2FA enforcement for member role** — v2.
- **Phase 5 checklist add**: verify MySQL-specific queue + cache behavior on real MySQL.
