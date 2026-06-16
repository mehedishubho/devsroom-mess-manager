# Concerns

Technical debt, known issues, fragile areas, and risks.

## Current State Assessment

This is a **fresh Laravel skeleton** — there is no domain code yet. Concerns below are mostly **forward-looking** (things to watch as the mess management app is built) plus a few observations about the skeleton itself.

## Skeleton-Level Concerns

### 1. No Domain Code Exists

- No controllers beyond the base `Controller` class
- No models beyond `User`
- No feature-specific migrations
- No policies, jobs, listeners, mailables
- `routes/web.php` only routes `/` to the welcome view
- `routes/api.php` is empty

**Risk**: When adding the first feature, there's no precedent in the codebase to follow — every decision is fresh. Recommend establishing patterns in Phase 1 (e.g., the first feature module).

### 2. Database Driver Mismatch

- `.env.example` defaults to **sqlite** for development
- Taste preference says **MySQL** is the target
- Migration `create_personal_access_tokens_table` uses `morphs('tokenable')` which behaves identically on both, but session/cache/queue tables may have subtle differences

**Risk**: Dev-vs-prod parity issues. MySQL-specific features (fulltext indexes, JSON column behaviors) may not surface in sqlite-based tests. **Recommendation**: use MySQL in dev too, or at least ensure CI runs against MySQL.

### 3. Session/Cache/Queue on Database Driver

- `SESSION_DRIVER=database` — requires `sessions` table (migrated ✓)
- `CACHE_STORE=database` — requires `cache` + `cache_locks` tables (migrated ✓)
- `QUEUE_CONNECTION=database` — requires `jobs` + `failed_jobs` tables (migrated ✓)

**Risk**: Database-driven sessions, cache, and queues add load to the DB. For production, consider Redis. For dev, fine.

### 4. No `.env` File Committed

- `.gitignore` excludes `.env`
- `.env.example` exists but real `.env` must be created locally
- `APP_KEY` is empty in `.env.example` — must be generated with `php artisan key:generate`

### 5. No Git Commits Yet

- Repo was just initialized in this session
- No `.gitignore`-protected content committed yet
- **Recommendation**: commit the skeleton state immediately to lock in the baseline

## Tyro-Specific Concerns

### 6. Tightly Coupled to Tyro Roles

- `User` model directly uses `HasTyroRoles` trait
- If we ever want to decouple from Tyro, it's a refactor of the User model
- For now, this is a feature — saves building custom RBAC

### 7. Tyro Default Roles May Need Adjustment

- `admin` and `super-admin` are protected (cannot be deleted via dashboard)
- `user` is the default role for new registrations
- Make sure these align with the mess management business model (likely roles: `member`, `manager`, `admin`)

### 8. Tyro 2FA Default Off

- `TYRO_LOGIN_2FA_ENABLED=false`
- Mess management may not need 2FA, but admins handling payments should — consider forcing 2FA for `admin` role

## Security Concerns (Skeleton-Level)

### 9. `APP_DEBUG=true` Default in `.env.example`

- Expected for local dev
- **Must be set to `false` in production** — verify deployment process enforces this

### 10. `MAIL_MAILER=log`

- Mails are written to log, not actually sent
- For dev this is fine, but you cannot test real email flows without configuring a mailtrap/local SMTP

### 11. No Rate Limiting Beyond Tyro Lockout

- Tyro Login has built-in lockout (5 attempts / 15 min) — good
- No global rate limiting on API or other routes
- Consider Laravel's `throttle` middleware for sensitive endpoints

### 12. No CSP / Security Headers

- No custom middleware for Content-Security-Policy, X-Frame-Options, etc.
- Tailwind app is server-rendered, so risk is lower, but worth adding

## Performance Concerns (Future)

### 13. Tyro Dashboard Loads All Modules by Default

- All Tyro features (users, roles, privileges, settings, audit logs) load on dashboard init
- For small teams this is fine; for large user counts, audit log table can grow fast — plan for pruning

### 14. No Caching Strategy Defined

- No `php artisan response:cache` or route caching expected yet
- Database cache means every cache miss is a DB query
- Recommend Redis when scaling

## Testing Concerns

### 15. Tests Use SQLite In-Memory

- Fast, but not MySQL — fulltext indexes, JSON queries, and MySQL-specific features won't be tested
- **Recommendation**: add a MySQL test environment for integration tests

### 16. No CI Pipeline

- No `.github/workflows` test runner
- Tests not enforced on PR
- **Recommendation**: add GitHub Actions workflow running `composer test` against MySQL

## Documentation Concerns

### 17. Default Laravel README

- `README.md` is the Laravel framework README, not project-specific
- No project description, no setup instructions specific to mess management

## Decisions Pending

| Question | Default | Needs Decision When |
|---|---|---|
| MySQL vs sqlite in dev | sqlite | Phase 1 setup |
| Roles/privileges model | Tyro default | Phase 1 / Phase 2 |
| Payment integration | none | Phase 3+ |
| Currency / locale | en_US | When adding billing |
| Multi-tenant (multiple messes)? | unknown | When defining domain |
| Public-facing UI vs admin-only? | unknown | When defining core value |
