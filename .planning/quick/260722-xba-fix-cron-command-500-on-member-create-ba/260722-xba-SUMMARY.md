---
quick_id: 260722-xba
title: Fix cron command, 500 on member create, backup scheduling, user image display
status: complete
date: 2026-07-22
commits: [7104804, 989e5d8, 1a30b60]
---

# Quick Task 260722-xba — Summary

## Root causes (all 4 confirmed)

| # | Symptom | Root cause |
|---|---------|-----------|
| 1 | Cron not working | Server crontab malformed: `cd` aimed at the PHP binary, target `script.php` doesn't exist, never calls `artisan schedule:run`. Bare-`php` line from `backup:install` isn't on the CloudPanel cron user's PATH. |
| 2 | 500 on member create | `MemberController::store` calls `assignRole()` → `TyroAudit::log()` (writes `tyro_audit_logs`) uncaught. On the server that table/cache is unavailable → throws AFTER `User::create` committed → 500 while the user shows under `/dashboard/users`. |
| 3 | Backup not scheduled | Same as #1 — `backup_configs` row 1 = daily/01:30 (correct); the scheduler just never runs because cron doesn't call `schedule:run`. |
| 4 | Image upload not showing | (a) Photo dropped in `store()` when `create_account` checked (early return before `storePhoto`). (b) `public/storage` symlink missing on prod → uploaded files 404 in the browser. |

## Changes

**`app/Http/Controllers/Mess/MemberController.php`** (commit 7104804)
- Moved `storePhoto()` above the `create_account` early-return so every member keeps its photo (fixes #4-photo-drop).
- Wrapped `$user->assignRole(...)` in try/catch + `Log::error('member.create.role_assign_failed', ...)`. The role attaches before the audit call, so login still works; the audit/cache failure is logged instead of surfacing as a 500 (fixes #2).

**`app/Console/Commands/BackupInstall.php`** (commit 989e5d8)
- Printed cron line now uses `PHP_BINARY` (full path) instead of bare `php` — copy-paste correct on CloudPanel (fixes #1, unblocks #3).
- Added a `public/storage` symlink check reporting LINKED vs MISSING with the `storage:link` fix command (fixes #4-symlink diagnostic).

**`tests/Feature/Mess/MemberCrudTest.php`** (commit 1a30b60)
- `test_photo_is_stored_when_account_created_too` — regression for the photo-drop fix.

## Verification
- `php -l` clean on both edited files.
- Member suite green: `MemberCrudTest`, `MemberAuditTest`, `InviteMemberTest` — 13 tests / 29 assertions pass (separate `devsroom_mess_management_testing` DB; no real data touched).

## Operator actions still required (cannot be done from code)
1. **Cron** — replace the server crontab line with the exact line `php artisan backup:install` now prints, e.g.:
   `* * * * * cd /home/wpmhs-mess/htdocs/mess.wpmhs.com && /usr/bin/php8.4 artisan schedule:run >> /dev/null 2>&1`
2. **Symlink** — on prod run `php artisan storage:link` so uploaded images are served.
3. **#2 follow-up (optional)** — if the resilient handler logs `member.create.role_assign_failed` entries, the underlying cause (likely the `tyro_audit_logs` migration not run on prod) can be fixed permanently by running `php artisan migrate` on the server.
