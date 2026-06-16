---
status: diagnosed
problem: mess_id from config does not match actual Mess row id
phase: 01-foundation
created: 2026-06-16T23:50:00Z
updated: 2026-06-16T23:50:00Z
---

# Debug: mess_id mismatch

## Symptom

User invites a new member at `/mess/members/invite` (manager role). Form submits with valid email. Server returns 500 with:

```
SQLSTATE[23000]: Integrity constraint violation: 1452
Cannot add or update a child row: a foreign key constraint fails
(`devsroom_mess_management`.`member_invitations`,
CONSTRAINT `member_invitations_mess_id_foreign`
FOREIGN KEY (`mess_id`) REFERENCES `messes` (`id`) ON DELETE CASCADE)

SQL: insert into `member_invitations`
  (`mess_id`, `email`, `token`, `invited_by`, `expires_at`, `updated_at`, `created_at`)
values (1, mehedihassan169@gmail.com, 5eri3n1LM..., 28, '2026-06-18 01:17:37', ...)
```

## Investigation

1. Confirmed `messes` table has only one row with `id=20`, name "Toto Devs", `status=active`.
2. `config('mess.active_mess_id')` returns string `'1'` (from `.env` `ACTIVE_MESS_ID=1`).
3. `MemberInviteController::store` (line 42) writes `'mess_id' => $messId` where `$messId = config('mess.active_mess_id')` = 1.
4. FK constraint `member_invitations.mess_id` references `messes.id`. There is no mess with id=1, so insert fails.

## Root cause

The app was designed with the assumption that "the active mess id" is a single number stored in `.env` (`ACTIVE_MESS_ID=1`) AND that the actual mess row lives at that id in the `messes` table. Both assumptions are wrong in v1:

- The first mess to be inserted (whether via onboarding, a seed, or a manual `Mess::factory()->create()`) gets the next auto-increment id, which is **not** guaranteed to be 1 in a database that has any prior data.
- In this dev environment, the schema was applied to a pre-existing MySQL server that had other data; the very first `INSERT INTO messes` was assigned id 20 by the storage engine.
- The codebase uses `config('mess.active_mess_id')` directly as the `mess_id` value on inserts in:
  - `MemberInviteController::store`
  - `OnboardingController::store` (only sets it indirectly via the new Mess's own id, which is fine)
  - `EnsureMessExists` middleware (`doesntExist()` check is fine)
  - `SetPasswordController::show/update` (uses `mess_id` on `MemberInvitation` queries)
- The `MessScope` global scope also uses `config('mess.active_mess_id')` for filtering. Because of the FK mismatch, no `MemberInvitation` with `mess_id=1` could ever exist, so the scope silently hides everything in `notifications`, `member_invitations`, etc.

## Why automated tests passed

`tests/Feature/Mess/InviteMemberTest::test_admin_invite_creates_user_and_invitation_and_mails`
directly invokes the controller via reflection. Because the test sets up
`config(['mess.active_mess_id' => $mess->id])` to match the factory-created mess,
it works. In production code paths the controller reads the env-supplied
`config('mess.active_mess_id')` without resolving it against actual data.

## Fix options considered

### Option A: Resolve active_mess_id at runtime

Add a `Mess::activeId(): ?int` helper that:
1. Returns the first existing `Mess::query()->value('id')`, or
2. Returns null if no mess exists.

Replace every `config('mess.active_mess_id')` use in controllers / scope with
`Mess::activeId()`. The `config('mess.active_mess_id')` becomes a fallback only.

Pros:
- Works regardless of auto-increment id
- One source of truth (the Mess table)
- Survives future mess creation
- Minimal code surface

Cons:
- One extra query per call (caches in static)
- Slight indirection for readers

### Option B: Force first Mess to id=1 on insert

In `OnboardingController::store` and any factory/seed, set the id explicitly
when the table is empty. The migration could also reset the auto-increment.

Pros:
- Keeps the existing "active_mess_id=1" semantic

Cons:
- Brittle: any out-of-band insert (factory, seeder, manual SQL) breaks it
- Doesn't fix the underlying "config value is a string" issue
- Doesn't help if multiple messes are accidentally created during seeding

### Option C: Add a unique-by-mess unique key + better default

Make `mess_id` derivable from a deterministic key (e.g. UUID) rather than int.

Pros:
- Multi-mess ready for v2
Cons:
- Massive refactor for v1, breaks FKs and existing data

## Chosen fix: Option A

**Why:** It's a small, focused change (one helper + ~5 call sites) that fixes
both the immediate bug and the latent risk. The config value can stay as
a documented fallback for edge cases (e.g. multi-mess install in tests).

## Affected call sites

- `app/Models/Scopes/MessScope.php` — `apply()` uses `config('mess.active_mess_id')`
- `app/Http/Controllers/Mess/MemberInviteController.php` — `store()` uses it
- `app/Http/Controllers/SetPasswordController.php` — `show()` / `update()` use it via `MemberInvitation::withoutGlobalScopes()->where('mess_id', $messId)`
- `app/Http/Middleware/EnsureMessExists.php` — `doesntExist()` check (this one is fine, just needs the helper for consistency)
- `app/Http/Controllers/OnboardingController.php` — `Mess::withoutGlobalScopes()->exists()` is fine
- `app/Http/Controllers/Mess/MessConfigController.php` — `Mess::firstOrFail()` is fine
- `app/Http/Controllers/Mess/AuditController.php` — fine

## Plan

Add `Mess::activeId(): ?int` static helper that lazily caches the first mess id.
Update the scope and the controllers that insert `mess_id` to use it. Keep
`config('mess.active_mess_id')` as the env-supplied override. Write a regression
test that proves the bug: create a mess with id=42, do NOT touch env, run the
invite flow, expect no FK error.
