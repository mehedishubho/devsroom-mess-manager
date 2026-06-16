# Phase 1: Foundation - Context

**Gathered:** 2026-06-16
**Status:** Ready for planning

<domain>
## Phase Boundary

A working Laravel 13 app on MySQL where:
- Super admin can configure **one mess** (name, address, monthly rent, meal values, currency, date format) via Tyro
- Manager and member can log in via Tyro Login with role-based access
- The database schema is in place for **all** domain models (members, meals, expenses, payments, etc.) with `mess_id` and audit log support
- The `Auditable` trait (via `owen-it/laravel-auditing`) is wired and writing entries on a sample model
- `Asia/Dhaka` time zone, decimal money, `__()` everywhere, MySQL connection

This is the **scaffold + spine** phase. Members, meals, expenses, payments, etc. are out of scope — they ship empty tables now and get features in later phases.

</domain>

<decisions>
## Implementation Decisions

### Roles & Auth (Tyro)

- **D-01:** Use Tyro's **built-in roles verbatim** — `super-admin` (protected), `admin` (the manager in this app), `user` (the member). Code uses `'admin'` and `'user'`; UI labels them "Manager" and "Member" for user-facing copy.
- **D-02:** Manager **skips `/dashboard` entirely**. Manager logs in via Tyro Login (`/login`), lands on a custom `/home` shell, accesses mess-domain routes (`/mess/*`). The `/dashboard` route is super-admin only.
- **D-03:** Member data scope enforced via **middleware + Laravel Policy/Gate**. Middleware checks auth user is `'user'` role AND the resource owner matches `auth()->id()`. Policy `view/update` methods do the per-resource check.
- **D-04:** **Manager creates member accounts** via a custom invite flow. Manager submits member email → Tyro invitation email (magic link) is sent → member clicks, sets password, lands on `/my`. Magic link is the default; manager can toggle to **set password manually** if email fails.
- **D-05:** **2FA on for `admin` role** (manager). `TYRO_LOGIN_2FA_ENABLED=true`. Members (`user`) are 2FA-off. Tyro's built-in lockout (5 attempts / 15 min) stays on.
- **D-06:** Member home is `/my` — a placeholder shell with "Welcome, {name}" + a "Profile" link. Real member dashboard (meals, bill, advance) is built in Phase 4.
- **D-07:** Manager home is `/home` — a placeholder shell with "Welcome, {name}" + a "Settings" link to mess config. Real dashboard (cards, charts) is built in Phase 4.

### Mess Configuration UI

- **D-08:** Mess config is a **Tyro resource** declared in `config/tyro-dashboard.php` `resources` array. Tyro provides the auto-CRUD form. Fields: name, address, monthly_rent, manager_contact, status (active/inactive), plus a settings sub-form for meal values / currency / date format.

### Audit Log

- **D-09:** Use **`owen-it/laravel-auditing`** (per `.planning/research/SUMMARY.md` decision). Provides model `$auditLogs`, console output, soft-delete support, and a built-in schema for `audits` table.
- **D-10:** Audit entries store **full diff (before + after JSON)**. owen-it default.
- **D-11:** Audit entries are **append-only, kept forever** per REQUIREMENTS AUDIT-03. No prune job. No edits, no deletes from the app.
- **D-12:** Manager views the audit log via a **custom `/mess/audit` Blade page** (not Tyro's built-in audit viewer). Filters: by model, user, date range.

### Settings Storage

- **D-13:** Settings use a **dedicated EAV table** (`settings`: `id`, `mess_id`, `key`, `value` (json), `type`, `group`, `description`). Standard Laravel-settings pattern. Supports typed casts, easy to add new keys without migrations.
- **D-14:** Settings are **scoped to `mess_id`**. v1 has mess_id=1; v2 multi-mess works without schema change.
- **D-15:** Settings editable by **admin OR super admin** (both roles can edit mess config).

### Test Database

- **D-16:** **Keep `sqlite :memory:`** in `phpunit.xml` for tests. Faster, isolated, standard Laravel practice. Document the test coverage gap: `sqlite :memory:` won't catch MySQL fulltext, JSON column quirks, or transaction isolation differences. Validate in dev with a real MySQL run + manual UAT before each release (checklist added to Phase 5 polish).
- **D-17:** All test code uses MySQL-compatible syntax (no fulltext, no MySQL JSON-specific features). Decided at code-review time.

### Migrations & Schema

- **D-18:** Phase 1 ships migrations for **ALL domain tables now** (empty, just schema). Tables: `messes`, `settings`, `members`, `meal_entries`, `meal_off_requests`, `guest_meals`, `expenses`, `expense_categories`, `payments`, `monthly_closings`, `monthly_member_summaries`, `monthly_corrections`, `advance_balances`, `notifications`, `audits` (owen-it). Future phases add features, not schema.
- **D-19:** **Every domain table has `mess_id`** (foreign key to `messes.id`, indexed). Single mess in v1 = `mess_id=1` everywhere. Multi-mess in v2 = no backfill.
- **D-20:** Single-mess enforced via a **global Eloquent scope** on all domain models that reads `config('app.active_mess_id')` (set to `1` in v1 config). v2 multi-mess swaps the config to read from auth user session/cookie.

### Database Credentials

- **D-21:** Dev MySQL credentials are `root` / `125524`, database `devsroom_mess_management`. **Confirmed by user** during discussion. `.env` is correct.

### Seeding

- **D-22:** **No automated seeder** for super admin / mess. Super admin is created via `php artisan tinker` after fresh install. The mess is created on first `/dashboard` visit via an **onboarding form** (or via Tyro's mess resource UI). Forces a real config step.

### Time Zone, Locale, Money

- **D-23:** `APP_TIMEZONE=Asia/Dhaka` in `config/app.php`. `APP_LOCALE=en`. Per-mess `date_format` setting defaults to `DD-MM-YYYY`. Carbon defaults to `Asia/Dhaka`.
- **D-24:** Money uses **`DECIMAL(10,2)` columns + Eloquent `decimal:2` cast**. Use MySQL's `DECIMAL` arithmetic (exact) for sums — no `bcadd`/`bcsub` needed at this scale. Display with ৳ symbol prefix. PHP `NumberFormatter('bn_BD', NumberFormatter::CURRENCY)` for locale-aware formatting. **Half-up rounding** (PHP/MySQL default).

### the agent's Discretion

- Exact schema column types and indexes (within the constraints above)
- How to register the global mess scope (Bootable trait vs. observer vs. service provider)
- How to wire the `Auditable` trait on a sample model in Phase 1 (Phase 1 only needs to prove the trait works on one model — `Mess` is a good fit)
- The exact Tyro config keys for enabling 2FA (refer to `config/tyro-login.php` env mappings)

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Project context
- `.planning/PROJECT.md` — Vision, constraints, key decisions, adopted recommendations
- `.planning/REQUIREMENTS.md` — 154 v1 requirements (AUTH, MESS, SET, AUDIT, PERF sections relevant to Phase 1)
- `.planning/ROADMAP.md` — Phase 1 success criteria and out-of-scope items
- `.planning/STATE.md` — Current progress, open questions

### Codebase maps (already in repo)
- `.planning/codebase/STACK.md` — Installed packages, runtime versions
- `.planning/codebase/CONVENTIONS.md` — Code style, attribute-based model config, migration style, test style
- `.planning/codebase/STRUCTURE.md` — Directory layout, where things go
- `.planning/codebase/INTEGRATIONS.md` — Tyro config, mail, cache, queue, session drivers

### Research
- `.planning/research/SUMMARY.md` — Stack decisions, anti-features, watch-out list
- `.planning/research/PITFALLS.md` — Top 5 critical pitfalls (timezone #5, decimal money #2, month-close race #3)
- `.planning/research/ARCHITECTURE.md` — Service-layer-no-repository, Form Requests, Auditable trait
- `.planning/research/STACK.md` — Why owen-it/laravel-auditing, Chart.js, etc.

### Skills (project-local, used during implementation)
- `.agents/skills/tyro-dashboard/SKILL.md` — Tyro patterns, app integration, CRUD resources, sidebar overrides
- `.agents/skills/laravel-best-practices/SKILL.md` — General Laravel 13 best practices

### Taste preferences
- `.commandcode/taste/taste.md` — Laravel 13, MySQL, snake_case DB names, verify DB creds

### External package docs (to consult during research/planning)
- `owen-it/laravel-auditing` GitHub README — schema, config, model usage (added in Phase 1)
- Tyro Dashboard `config/tyro-dashboard.php` and `config/tyro-login.php` — 2FA, invitations, role middleware

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `App\Models\User` (in `app/Models/User.php`) — Already has `HasTyroRoles`, `HasTwoFactorAuth`, `HasApiTokens`. Use as the base auth model. No changes needed.
- `database/migrations/0001_01_01_000000_create_users_table.php` — Creates `users`, `password_reset_tokens`, `sessions`. Reuse the pattern.
- `database/migrations/2026_06_15_225413_create_personal_access_tokens_table.php` — Sanctum. Reuse the anonymous-class migration pattern.
- `config/tyro-dashboard.php` — Already configured (app name, sidebar colors). Add the `messes` resource to the `resources` array.
- `config/tyro-login.php` — Tyro Login config. Set `TYRO_LOGIN_2FA_ENABLED=true` in `.env`.
- `bootstrap/providers.php` — Add app-level service provider for the global mess scope.
- `App\Providers\AppServiceProvider` — Empty `register()` and `boot()`. Use for global mess scope binding and view composers.

### Established Patterns
- **Attribute-based model config**: `#[Fillable(['...'])]`, `#[Hidden(['...'])]` on models. New domain models follow this.
- **`casts()` method, not `$casts` property**.
- **Anonymous-class migrations** with `up()` and `down()` methods, `Blueprint` typed parameter.
- **Form Requests** for all input validation (`app/Http/Requests/`).
- **Test style**: PHPUnit 12, `test_` prefix, `void` return type, extends `Tests\TestCase`.
- **`__()` everywhere** for user-facing strings.
- **snake_case columns, plural table names, `$table->timestamps()` on all tables**.

### Integration Points
- **Routes**: `routes/web.php` — add `/home` (manager), `/my` (member), `/mess/*` (manager routes), and Tyro auto-routes for `/login`, `/register`, `/password/*`, `/dashboard/*`.
- **Tyro's `/dashboard`**: super-admin only. Manager never lands here.
- **`.env`**: already has `DB_CONNECTION=mysql`, `DB_DATABASE=devsroom_mess_management`, `DB_USERNAME=root`, `DB_PASSWORD=125524`. `TYRO_DASHBOARD_*` keys already set.
- **Tyro's `TYRO_LOGIN_APP_NAME`**: still "Laravel" — should be set to app name.
- **MySQL connection**: dev DB is `devsroom_mess_management`. Need to create the DB on first run (idempotent migration setup).

</code_context>

<specifics>
## Specific Ideas

- **Manager home (`/home`)**: Simple Blade page, plain text. "Welcome, {name}." + a single "Mess Settings" link to `/mess/settings`. No dashboard cards yet (Phase 4).
- **Member home (`/my`)**: Simple Blade page. "Welcome, {name}." + a single "Profile" link to `/my/profile`. No member dashboard yet (Phase 4).
- **Mess config**: A Tyro resource, so the form is auto-generated. Fields visible in the Tyro UI: name, address, monthly_rent, manager_contact, status. Sub-resource: settings (meal values, currency, date format) — likely as a separate Tyro resource linked by `mess_id`.
- **Audit log sample model**: `Mess` model uses the `Auditable` trait in Phase 1 just to prove the trait works. Updates to a mess's name should write an audit entry.
- **Settings defaults**: meal_breakfast=0.5, meal_lunch=1, meal_dinner=1, currency=BDT, date_format=DD-MM-YYYY, auto_monthly_close=false.
- **Phase 5 polish reminder**: Add "verify MySQL-specific behavior (fulltext, JSON, transactions) via manual UAT on real MySQL" to the Phase 5 checklist.

</specifics>

<deferred>
## Deferred Ideas

- **Year-over-year reporting** (RPT-ADV) — out of v1 scope; tracked in REQUIREMENTS.md v2.
- **2FA enforcement for `user` (member) role** — currently only `admin` has 2FA. Defer to v2.
- **Audit log viewer in Tyro chrome** — currently custom `/mess/audit` page. Could expose via Tyro too in v2.
- **Member-initiated mess creation** — currently super-admin + onboarding form only. Defer to v2.
- **Locale fallbacks / Bengali translations** — strings are wrapped in `__()` for v2 readiness, but no `bn.json` ships in v1. Tracked in REQUIREMENTS.md v2 LOC section.

</deferred>

---

*Phase: 01-foundation*
*Context gathered: 2026-06-16*
