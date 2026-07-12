# Quick Task 260712-s5g — Whole-app audit & recommendations

A focused review of the mess management app, grouped into **Add**, **Remove/consolidate**, and **Rearrange**. Priority tags: **P0** (data-integrity / security), **P1** (high-value UX), **P2** (polish).

## What's already strong
- Clean service layer (17 services), thin controllers, money-as-decimal, `mess_id` on every domain table (multi-tenant-ready).
- Month-close is idempotent + immutable + queue-backed; 11 write routes hard-locked post-close.
- Member-side routes carry no `{member}` URL param — IDOR is structurally impossible on `/my`.
- Audit log on every write; PDF + xlsx exports on all 4 reports; perf budgets locked by tests.

## ADD

- **P0 — Restore soft-deleted members + a "trash" view.** This batch added soft-delete + permanent delete but no UI to list/restore soft-deleted members. Add `members?trashed=1` filtering + a `PATCH .../restore` route so accidental deletes are recoverable before the 30-day habit sets in.
- **P0 — Encrypt channel credentials at rest.** `mess.notifications` now stores Telegram bot tokens, Twilio SID/token, Vonage key/secret as plaintext JSON in `settings.value`. Wrap reads/writes in `Crypt::encryptString` / `decryptString` (decrypt in `MessNotificationSettings::read()`), fail soft on legacy plaintext rows.
- **P1 — Per-notification delivery log.** `ChannelManager::dispatch()` returns per-channel outcomes that are only written to `laravel.log`. Add a `notification_deliveries` table (notification_id, channel, ok, detail, created_at) and a small admin view so managers can see *why* a member didn't get a WhatsApp message instead of guessing.
- **P1 — Balance card on the member profile.** `mess/members/show` lists recent meals but not the member's current bill / advance / due. One `MemberStatementService`-backed card would make the profile the single source of truth for a member's standing.
- **P1 — Per-member Telegram linking.** Telegram currently broadcasts to one configured chat. Add a nullable `telegram_chat_id` on `members` (collect via a `/start` bot flow) and have `TelegramChannel` prefer the member's chat id, falling back to the mess default.
- **P2 — Global search.** Member search is good; consider a header search across payments/expenses/members for power users.
- **P2 — Receipt lightbox.** Expense receipts are stored but viewed by URL only; a modal preview would smooth audit workflows.
- **P2 — Tests for the new behavior.** Slug uniqueness, duplicate-prevention 422s, delete vs force-delete guards, channel fail-open on a misconfigured provider. (File-only; safe to add per the no-test-DB-running constraint.)

## REMOVE / consolidate

- **P1 — Two audit tables.** `audits` (owen-it/laravel-auditing) and `tyro_audit_logs` both exist. The `mess/audit` page reads `audits`. Either drop `tyro_audit_logs` or document clearly which is canonical — right now it reads as duplicated infrastructure.
- **P2 — `MemberController::destroy` naming.** It performs *deactivation*, not destruction (now well-commented and the real delete lives in `delete()`). A future rename to `deactivate()` + route key would remove the lingering surprise; safe to defer.
- **P2 — Duplicate sidebar icon SVGs.** The legacy sidebar reused the identical expenses SVG for payments; the new component uses distinct icons, so this is already resolved — keep an eye on copy-paste regressions.

## REARRANGE

- **Sidebar grouping — shipped.** Replaced the flat list with Mess / Finance / Closing / Reports / Settings (+ System for super-admin; focused My-nav for members). If anything, "Meal off approval" could move *above* "Guest meals" since it's checked daily; minor.
- **P1 — Tabbed member profile.** The member show page stacks profile → recent meals → meal-off form vertically. Convert to tabs (Overview / Meals / Meal off / Payments / Statement) for density at the cost of one click.
- **P2 — Landing route by role is already correct** (post-login redirect); no change needed. Confirm `super-admin` lands on `/dashboard` intentionally vs. a mess-aware overview — relevant once multi-mess lands.

## Net recommendation
Ship **P0 restore UI** and **P0 credential encryption** as the immediate follow-up; they close the only data-safety gaps this batch opened. The **P1 delivery log** and **balance card** are the highest-leverage UX wins. Everything else is genuine polish on an already-solid core.
