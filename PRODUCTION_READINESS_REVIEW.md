# Production Readiness Review

Date: 2026-07-06

## Security

- Root traffic now goes through controller-based install/auth routing instead of a public welcome page.
- The one-time setup wizard is blocked after installation and only creates the initial `super-admin` account.
- Dashboard access remains controlled by Tyro Dashboard middleware and role checks; member routes remain separate under `/my`.
- Sensitive deployment values remain environment-driven. `.env.example` now calls out production `APP_DEBUG=false`, HTTPS `APP_URL`, encrypted sessions, mail, queue, scheduler, and Tyro production flags.
- Existing CSRF protection, Form Request validation, escaped Blade output, hashed password casts, and route middleware are preserved.

## Performance

- The new install-state lookup uses a single keyed `app_settings` row and does not touch mess-scoped settings.
- Existing cached bill preview and dashboard count strategy remains unchanged.
- Route-cache compatibility is improved by replacing the `/` route closure with `RootController`.
- Existing pagination, queued month close, Vite build, and database-backed cache/session/queue configuration remain intact.

## Laravel And Tyro Integration

- Changes are app-level only; no `vendor/` or Tyro package internals were edited.
- The first setup user receives the existing `super-admin` role expected by Tyro Dashboard, backups, and mess onboarding.
- Existing mess onboarding is preserved: after initial account setup, a super-admin still creates the first mess through `/onboarding` when no mess exists.
- Composer no longer creates a skeleton SQLite file during project creation, preserving the MySQL-only project constraint.

## Deployment Follow-Ups

- Confirm production `.env` uses real MySQL credentials, `APP_ENV=production`, `APP_DEBUG=false`, HTTPS `APP_URL`, SMTP/API mail, and `BACKUP_*` settings. Cloud backup mirrors (Google Drive / Cloudflare R2) are configured on the Backups page, not in `.env`.
- Ensure the web server can write `storage/` and `bootstrap/cache/`, and run `php artisan storage:link` when public uploads are enabled.
- Run migrations once during deploy, then cache config/routes/views and start a persistent queue worker plus scheduler.
- Confirm the scheduler is actually driving backups (the Backups page shows a scheduler-health banner + the exact cron line when the per-minute `schedule:run` cron is missing) and that the queue worker is running, since UI-triggered **Backup now** and restores are queued jobs. There is no restore-test subsystem and no scratch database to maintain (removed in quick task 260724-pm2).
