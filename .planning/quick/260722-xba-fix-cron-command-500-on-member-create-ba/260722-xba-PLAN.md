# Quick Task 260722-xba: Fix cron command, 500 on member create, backup scheduling, user image display

**Created:** 2026-07-22
**Mode:** quick (investigation done inline; surgical 2-file fix)

## Root causes (confirmed)

1. **Cron malformed (#1) + backup not scheduled (#3):** server crontab is
   `* * * * * cd /usr/bin/php8.4 .../public/script.php` — `cd` points at the PHP
   binary, target file doesn't exist, never calls `artisan schedule:run`.
   `backup:install` prints the cron line with bare `php`, which is NOT on the
   CloudPanel cron user's PATH — that's why the operator reached for
   `/usr/bin/php8.4` and broke the syntax. `backup_configs` row 1 = daily/01:30,
   so the scheduler is correctly wired; fixing the cron line starts backups.

2. **500 on member create (#2):** `MemberController::store` calls
   `$user->assignRole()` → `TyroAudit::log()` (writes `tyro_audit_logs`) with
   NO try/catch (the Tyro dashboard controller wraps audit in `auditSafely`).
   `User::create` commits first, then the audit/cache write throws → 500 while
   the user row is already visible under `/dashboard/users`. Resilience fix is
   correct regardless of which sub-call actually throws on the server.

3. **Member image not showing (#4):** photos are stored on the `public` disk at
   `photos/{id}.ext` and served via `/storage/...` which needs the
   `public/storage` symlink. Missing on prod → images 404. ALSO: in `store()`,
   the `create_account` branch `return`s BEFORE `storePhoto()`, so a photo
   uploaded with "create account" is silently dropped.

## Tasks

### Task 1 — MemberController::store (fixes #2 + #4-photo-drop)
- File: `app/Http/Controllers/Mess/MemberController.php`
- Move `storePhoto()` ABOVE the `create_account` early return (photo stored for
  every member, including account-created ones).
- Wrap `assignRole(Role::firstOrCreate(...))` in try/catch + `Log::error` so an
  audit/cache failure degrades gracefully instead of 500-ing. Member + user
  still created; role attaches before the audit call, so login still works.
- Verify: `php artisan lint`/route not broken; read-through confirms flow.

### Task 2 — backup:install diagnostics (fixes #1, #3, #4-symlink)
- File: `app/Console/Commands/BackupInstall.php`
- Cron line uses `PHP_BINARY` (full path) instead of bare `php`, so the
  printed copy-paste line works on CloudPanel where `php` isn't on PATH.
- Add a `public/storage` symlink check: report MISSING/LINKED and print
  `php artisan storage:link` when missing.

## Operator actions (communicated to user, not code)
- Paste correct cron line on server (printed by `backup:install`):
  `* * * * * cd /home/wpmhs-mess/htdocs/mess.wpmhs.com && /usr/bin/php8.4 artisan schedule:run >> /dev/null 2>&1`
- Run `php artisan storage:link` on prod.
