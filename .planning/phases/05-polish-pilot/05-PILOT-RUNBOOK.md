# Pilot Runbook — One-Mess Pilot (Phase 5 Plan 05-03, Tasks 2 & 3)

**Status:** Drafted by Claude (Plan 05-03 Task 2 autonomous portion). Awaiting human review alongside the 4 Phase 4 HUMAN-UAT items.
**Scope:** The v1 pilot (D-01..D-05) — ONE real mess, ONE clean monthly cycle.
**Source rules:** `.planning/phases/05-polish-pilot/05-CONTEXT.md` decisions D-01 through D-05.

This runbook is the playbook the dev follows to onboard + run + close the pilot mess. It is paired with:
- [DEPLOYMENT.md](../../../DEPLOYMENT.md) — the production deploy checklist (provision VPS, supervisor, cron, `.env`)
- [04-HUMAN-UAT.md](../04-reports-dashboard/04-HUMAN-UAT.md) — the 4 browser-verifications that must clear before the pilot starts
- `05-PILOT-RESULTS.md` (drafted in Task 3) — the blank template the dev fills in as the pilot runs

---

## Section 1: Hybrid Onboarding (D-03)

**Principle:** The dev configures the mess + members + settings; the manager runs daily ops. The manager is **NOT** expected to self-serve setup. The dev does this via screen-share or in person with the manager watching, so the manager understands what was set up.

**Pre-requisites (do these BEFORE meeting the manager):**
- [ ] Production deploy is live per [DEPLOYMENT.md](../../../DEPLOYMENT.md) §8 (smoke test passed, queue worker RUNNING, scheduler RUNNING, `APP_DEBUG=false`, HTTPS).
- [ ] You have a list of the real members: names, mobile numbers, emails (for login), room/seat, joining date. (Email is optional for members who won't log in — the manager can still record their data. But at least the manager needs an email.)
- [ ] You have the mess details: real name, address, monthly rent, manager contact.
- [ ] You have the desired meal values + currency + date format (defaults: B=0.5, L=1, D=1, BDT, DD-MM-YYYY).

**Step-by-step (the dev, screen-sharing with the manager):**

1. **Log in as the production super-admin** (the account you created at deploy time — NOT `manager@demo.test`, that's local-only). You'll land on `/dashboard`.
2. **If no mess exists**, you'll be redirected to `/onboarding`. Fill in the onboarding form with the real mess details (name, address, monthly rent, manager contact). Submit.
3. **Create the manager's user account** via the Tyro dashboard at `/dashboard` (Users → New). Use the manager's real email + a temporary password you communicate verbally. Assign the **`admin`** role. The manager will log in with this account.
   - (Alternatively, use the member invite flow at `/mess/members/invite` to send an email link.)
4. **Log out, log in as the manager** (`admin` role). Land on `/home`.
5. **Configure settings** at `/mess/settings`:
   - Meal values: breakfast / lunch / dinner (default 0.5 / 1 / 1 — change if the mess uses different rates).
   - Currency: BDT (default).
   - Date format: DD-MM-YYYY (default).
6. **Add expense categories** at `/mess/categories` if the mess uses any non-default categories. Default categories ship: Bazar, Rent, Cook Salary, Internet, Electricity, Water, Gas, Maintenance, Cleaning, Others. Add custom ones now (e.g. "Maid Salary", "Generator Fuel").
7. **Create members** at `/mess/members/create` — one per real person. Fill in:
   - Name (real name, correctly spelled — this appears on bills).
   - Mobile, email (email is the login — leave blank only for members who won't log in).
   - Profession, room/seat, joining date (use the **real joining date** even if it's before this month — mid-month joiners are prorated correctly by the math).
   - Emergency contact.
   - Status: `active` for current members. Use `former` (with leaving date) for anyone who left mid-month (they'll still appear in this month's close, prorated). Use `inactive` only for members who should NOT appear at all.
   - (Skip the photo for now — optional, the manager can add later.)
8. **For each member who will log in**, create their user account at `/dashboard` with the `user` role and the same email as the member record. The member's `User` must link to their `Member` row (the system matches by email). Communicate each member's temporary password verbally or via a private channel — NOT in the in-app notification.
9. **Walk the manager through the UI** while screen-sharing:
   - `/home` — the dashboard (6 cards + 3 charts).
   - `/mess/meals` — the daily meal grid. Show the "Mark all 3" / "Mark all 0" presets + per-member quick actions. This is where the manager spends most of their time.
   - `/mess/expenses/bazar/create` + `/mess/expenses/fixed/create` — recording bazar + fixed expenses.
   - `/mess/payments/create` — recording a payment (show the `bill_payment` vs `advance_deposit` toggle — explain the difference).
   - `/mess/bill-preview` — the live "if we closed today" preview.
   - `/mess/close` — where they'll trigger month-close at the end of the month.
10. **Hand off the credentials** to the manager (verbal or private channel). Confirm the manager can log in on their own phone.
11. **Set the WhatsApp / call feedback channel** with the manager (D-05 — see §5).

**Expected outcome of onboarding:** mess configured, members created, manager + member accounts can log in, manager knows where to enter meals/expenses/payments, dev has a direct WhatsApp/call line to the manager for feedback.

---

## Section 2: Daily Ops Runbook (for the manager)

**Plain-language version. Share this with the manager (translate to Bengali if helpful).**

Each day, the manager does these 4 things. Total time: ~5 minutes/day for a 15-member mess.

### Every day (the manager, on their phone):

1. **Log in** at the app URL with your manager email + password. You'll land on `/home`.
2. **Enter today's meals.** Tap **Meals** in the sidebar → `/mess/meals`. Today's date is shown at the top (change it if you're entering for a different day).
   - **Fast path:** tap **"Mark all 3"** to give every active member B+L+D. Then uncheck the meals for anyone who didn't eat (e.g. meal-off, ate outside). Tap **Save**.
   - The grid auto-handles approved meal-off requests — those members are greyed out and their meals are not counted.
   - You can also use the per-member quick actions (All on, All off, Breakfast only, Lunch only, Dinner only).
3. **Log bazar purchases as they happen.** Tap **Expenses → Bazar** → `/mess/expenses/bazar/create`. Fill in: date, purchased by, vendor (optional), description, amount, category. Attach a receipt photo if you have one (optional). Save.
   - Do this same-day if possible — it keeps the meal-rate preview accurate.
4. **Take payments when received.** Tap **Payments → Create** → `/mess/payments/create`. Fill in: member, date, amount, method (Cash / bKash / Nagad / Rocket / Bank), reference number (the bKash/Nagad TrxID), notes.
   - **Important — pick the right type:**
     - **Bill payment** = the member is paying toward this month's bill.
     - **Advance deposit** = the member is depositing money ahead (it carries forward and reduces next month's bill). Use this when the member pays more than they owe.
5. **(Optional) Check the live preview.** Tap **Bill preview** → `/mess/bill-preview`. Shows "if we closed today, the meal rate would be ৳X" and each member's running bill. Use this to answer "how much do I owe right now?" questions.

### Once a month (manager, with dev on standby):

6. **Approve meal-off requests.** When a member requests meal off (via their `/my` page), you'll see a banner on `/home`. Tap it → `/mess/meal-off` → approve or reject (rejection requires a reason). Approved meal-off auto-deducts the member's meals for those dates.

### Things the manager does NOT do:
- ❌ Do **not** edit last month's data after month-close. The system will refuse with "MONTH CLOSED". If something needs correcting, ask the dev (corrections go through a separate closings page).
- ❌ Do **not** create members or change settings — that's the dev's job (hybrid onboarding, D-03). If a new member joins or settings need changing, contact the dev.
- ❌ Do **not** worry about the math. The system computes the meal rate, fixed share, advance carry-forward, and per-member bill. Your job is to enter meals + expenses + payments accurately.

---

## Section 3: Month-Close Runbook

**When:** At the end of the month, after all meals + expenses + payments for the month are entered.
**Who triggers:** The manager (or the dev, if the manager prefers). The dev is on standby.

### Steps:

1. **Verify the month is complete.** Walk through with the manager:
   - All daily meal grids for the month are filled. (Check `/mess/meals` for any missed days.)
   - All bazar + fixed expenses are entered.
   - All payments received this month are recorded.
2. **Trigger the close.** Manager (or dev) goes to **`/mess/close`** → picks the (year, month) → taps **Close month**.
   - This dispatches `CloseMonthJob` to the queue. It does NOT run synchronously.
   - The queue worker (running under supervisor — see [DEPLOYMENT.md](../../../DEPLOYMENT.md) §3.2 or §4.3) picks it up within seconds.
3. **Wait for the notification.** When the job completes, every manager + super-admin receives an in-app `close_complete` notification (the bell icon in the nav). This typically completes in **under 1 second** at pilot scale (Plan 05-02 measured 0.12s at 50 members — well under the 30s budget).
4. **Verify the close.** Browse to **`/mess/closings`** → click the new closing row → `/mess/closings/{id}`. Review:
   - The summary: total bazar, total meals, meal rate, total fixed, member count.
   - Each member's `monthly_member_summaries` row: meals, meal cost, fixed share, bill payments, net bill (due) or advance (balance).
5. **Verify members can view their own bills.** Log in as a member (or ask the manager to ask a member to log in) → `/my` → **My reports** → **Member Statement**. The member should see their closed-month bill with the same numbers as the manager's view. (Members CANNOT see other members' data — verified by `tests/Feature/My/MyStatementTest::test_member_cannot_view_other_member_statement`.)
6. **If something is wrong** (math bug, missing data, wrong member):
   - **Do NOT re-trigger close** — it's idempotent, so re-triggering is a no-op.
   - **Do NOT edit the original data** — the month is hard-locked.
   - **Use the corrections page:** `/mess/closings/{closing_id}/corrections/create`. Corrections append to `monthly_corrections` and apply immediately to the member's balance; the original snapshot stays immutable. Contact the dev if a correction is needed — this is a manual, audited step.

### What "success" looks like at month-close:
- `close_complete` notification arrives for the manager + super-admin.
- `/mess/closings` shows the new closing row with non-zero totals (unless the month genuinely had zero activity).
- Every member with a `user` account can log in and see their own bill on `/my`.
- The numbers on the manager's view match the member's view for the same member.

---

## Section 4: Success Criteria (D-04 — verbatim)

The pilot PASSES when ALL THREE of these hold:

1. ✅ **ONE clean month-close completes.** The `CloseMonthJob` ran without exception, the closing row exists in `monthly_closings`, the summary rows exist in `monthly_member_summaries`.
2. ✅ **Members can view their own bills.** At least one member logs in on their own device, opens `/my`, and sees their closed-month bill (with the correct numbers — matching the manager's view for that member).
3. ✅ **ZERO data-loss or math-wrong bugs.** No member reports an incorrect bill, missing meal, missing payment, or wrong meal rate that cannot be explained by the formulas in [AGENTS.md § Domain Walkthrough](../../../AGENTS.md#bill-math). Any discrepancy that surfaces MUST be documented in `05-PILOT-RESULTS.md` with its root cause + resolution.

**NOT required for pilot PASS:**
- ❌ Two consecutive monthly cycles (v1 = ONE cycle).
- ❌ Formal manager sign-off (D-05 is direct WhatsApp/call feedback, not a signed document).
- ❌ "Prefer the app over their spreadsheet" gate (preference is post-pilot).
- ❌ Real bKash/Nagad/Rocket API integration (manager records payments with reference numbers — v1 scope).
- ❌ Bengali language UI (v1 is English; Bengali is v2).
- ❌ Mobile app (v1 is responsive web — works in the phone browser).

If any of the 3 success criteria fail, the pilot is BLOCKED — describe the blocker in `05-PILOT-RESULTS.md` and Claude will assist via the dev's channel.

---

## Section 5: Feedback Channel (D-05)

**The feedback channel is direct WhatsApp / phone call between the dev and the manager.** There is no in-app feedback feature in v1 (out of scope per REQUIREMENTS.md — SMS/WhatsApp API integration is v2).

**Why direct, not in-app:**
- The pilot manager is someone the dev has direct access to (D-01 — own/family/close contact). A direct line is faster than any in-app channel.
- The dev needs to hear about issues in real-time, with screenshots if possible, to triage.
- No regulatory overhead (no WhatsApp Business API, no consent flow).

**What the manager should report via WhatsApp/call:**
- Any error message they see (screenshot if possible).
- Any "this number looks wrong" — the dev can cross-check against `/mess/bill-preview` or the closed snapshot.
- Any "I can't find X" / "this is confusing" — UX issues for v2 backlog.
- Any "the app is slow" — the dev checks `storage/logs/laravel.log` + Forge/UI.

**What the dev does with reports:**
- If it's a bug (incorrect data, exception, IDOR hole): fix in code, redeploy, document in `05-PILOT-RESULTS.md` under "Bugs found + resolution".
- If it's a UX issue: log in `.planning/` for v2 backlog, do NOT block the pilot.
- If it's a "math looks wrong" report: walk the manager through the formula in [AGENTS.md § Domain Walkthrough](../../../AGENTS.md#bill-math). Most "math is wrong" reports turn out to be a misunderstanding of "fixed expenses don't enter the meal rate" (FIXED-04) or "advance deposit is not auto-applied" (CR-03).

**The dev's commitment:** respond to WhatsApp/call within the agreed SLA (suggest same-day during the pilot month).

---

## Section 6: Fresh-Start Note (D-02)

**The pilot month IS the first recorded month. There is NO historical importer.**

This means:
- The pilot starts with an empty database (after the production deploy). The only seeded data is what Laravel's default `DatabaseSeeder` produces (expense categories + a test user — see `database/seeders/DatabaseSeeder.php`). The `PerfDemoSeeder` (`manager@demo.test` + 50 fake members) is **dev-only** and is NEVER run in production (the seeder is environment-guarded — `app/Console/Commands/SeedPerfDemo.php` refuses to run in production without `--force`, and `--force` is NOT RECOMMENDED).
- Do NOT attempt to import last month's data from the manager's spreadsheet. The pilot starts with THIS month. If the manager wants historical reporting, that is a v2 feature (a historical importer is tracked but out of scope for v1).
- Mid-month joiners: enter the REAL joining date (even if it's months ago). The math prorates the fixed share by `active_days / days_in_month` (FIXED-03) — it does NOT need historical meal data. A member who joined the mess in January but is being tracked in the app for the first time this month simply starts with zero meals recorded until the manager enters them.
- If a SECOND mess later wants to use the app, that is post-pilot scope. v1 is ONE mess. The `mess_id` on every domain table makes v2 multi-mess a schema-ready extension, but the pilot is single-mess only.

**What "fresh start" implies for the first month-close:**
- The first month-close is the system's first real test. The `advance_balances` table starts empty — no member has a carried-forward advance or due from "before". After the first close, `due_balance` and `balance` populate for carry-forward into month 2.
- This is expected and correct. The pilot success bar (D-04) is about ONE clean close + members seeing their bills, NOT about historical continuity.

---

## Quick reference: the demo creds vs. production creds

| Environment | Manager login | Member login | When to use |
|-------------|---------------|--------------|-------------|
| **Local dev** (after `php artisan db:seed:perf-demo`) | `manager@demo.test` / `password` | `member@demo.test` / `password` | Trying things out, screenshots, the 4 HUMAN-UAT items |
| **Production** (the pilot) | Real manager email (set during onboarding §1) | Real member emails (set during onboarding §1) | The actual pilot. NEVER use demo creds here. |

Demo creds are documented in the [README § Demo Dataset](../../../README.md#demo-dataset-optional) and come from `database/seeders/PerfDemoSeeder.php`. They exist ONLY in local dev — the seeder refuses to run in production.

---

*This runbook is the playbook for Plan 05-03 Task 3 (run the pilot). The results of the pilot go in `05-PILOT-RESULTS.md` (a blank template that Task 3 drafts and the dev fills in as the pilot runs).*
