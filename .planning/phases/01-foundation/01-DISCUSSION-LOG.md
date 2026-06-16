# Phase 1: Foundation - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-06-16
**Phase:** 01-foundation
**Areas discussed:** Role model (Tyro), Test DB strategy, Auditable trait, Settings storage, Migration scope, Mess config UI, Seeding, Time zone, Money handling, DB credentials

---

## Role model (Tyro)

| Option | Description | Selected |
|--------|-------------|----------|
| Use built-in verbatim | Tyro's `super-admin`, `admin`, `user`. Manager=`admin`, member=`user`. | ✓ |
| Add manager + member, keep built-ins | New Tyro roles `manager`, `member`; keep `admin`/`super-admin` for /dashboard. | |
| Manager+member, no built-ins | Map `super-admin`→system admin, `manager`/`member` for domain. `super-admin` stays protected. | |
| Roles + privileges | High-level roles + fine-grained Tyro privileges (manage-meals, view-bills). | |

**User's choice:** Use built-in verbatim
**Notes:** Code uses 'admin'/'user'; UI labels them Manager/Member. Manager skips /dashboard entirely.

---

| Option | Description | Selected |
|--------|-------------|----------|
| Skip /dashboard entirely | Manager uses custom /home and /mess/*. | ✓ |
| Manager sees /dashboard too | Manager can access user mgmt, roles. | |
| Custom sidebar only | Use dashboard layout shell, custom sidebar. | |

**User's choice:** Skip /dashboard entirely

---

| Option | Description | Selected |
|--------|-------------|----------|
| Middleware checks member.user_id | Laravel Policy + Gate for per-resource ownership. | ✓ |
| Global scope + policy | Eloquent global scope on Member model filters by auth id. | |
| Per-controller checks | $this->authorize() in every method. | |
| Skip — v1 is single user type | Trust Tyro role middleware only. | |

**User's choice:** Middleware checks member.user_id

---

| Option | Description | Selected |
|--------|-------------|----------|
| Manager creates, member invited | Custom invite flow with magic link + manual password toggle. | ✓ |
| Public registration + manager assigns | Open /register, role 'user', manager reviews. | |
| Public reg requires invite code | Manager generates one-time code. | |
| You decide | | |

**User's choice:** Manager creates, member invited

---

| Option | Description | Selected |
|--------|-------------|----------|
| Yes, 2FA on for admin | TOTP for manager; members off. | ✓ |
| Off for v1 | Default, defer to v2. | |
| You decide | | |

**User's choice:** Yes, 2FA on for admin

---

| Option | Description | Selected |
|--------|-------------|----------|
| Email magic link | Manager sends magic link. | |
| Manager sets password | Manager picks initial password. | |
| Both (toggle) | Magic link default, manager can override. | ✓ |

**User's choice:** Both (toggle)

---

| Option | Description | Selected |
|--------|-------------|----------|
| Empty shell, build in Phase 4 | Placeholder /home with Welcome + Settings link. | ✓ |
| Direct to mess config | Manager lands on mess config page. | |
| Redirect to /dashboard | Contradicts "skip /dashboard" decision. | |

**User's choice:** Empty shell, build in Phase 4

---

| Option | Description | Selected |
|--------|-------------|----------|
| /my shell, build in Phase 2/4 | Placeholder /my with Welcome + Profile link. | ✓ |
| Profile page first | Land on /my/profile directly. | |

**User's choice:** /my shell, build in Phase 2/4

---

| Option | Description | Selected |
|--------|-------------|----------|
| Custom route, skip Tyro resource | Custom /mess/settings Blade page. | |
| Tyro resource (auto CRUD) | Configure mess in config/tyro-dashboard.php resources array. | ✓ |
| Custom route + Tyro layout | Custom route, Tyro chrome. | |

**User's choice:** Tyro resource (auto CRUD)

---

| Option | Description | Selected |
|--------|-------------|----------|
| Keep 'admin' label | Code uses 'admin' role, UI says "Manager". | ✓ |
| Rename to 'manager' | Create Tyro role literally named 'manager'. | |
| Code stays 'admin', UI says 'manager' | Same as keep 'admin' label. | |

**User's choice:** Keep 'admin' label

---

| Option | Description | Selected |
|--------|-------------|----------|
| Custom invite flow | Tyro invitation email → /accept-invitation → set password → /my. | ✓ |
| Manual create + email send | Custom signed-URL 'set password' email. | |

**User's choice:** Custom invite flow

---

## Test DB strategy

| Option | Description | Selected |
|--------|-------------|----------|
| Keep sqlite :memory: | Standard Laravel, fast, isolated. | ✓ |
| Switch to MySQL test DB | Parity, slower, catches MySQL-specific bugs. | |
| MySQL on demand, sqlite default | TEST_DB_CONNECTION env flag. | |
| You decide | | |

**User's choice:** Keep sqlite :memory:

---

| Option | Description | Selected |
|--------|-------------|----------|
| Document the test coverage gap | Add 'verify MySQL-specific behavior' to Phase 5 checklist. | ✓ |
| Switch to MySQL now | Remove the parity gap. | |
| You decide | | |

**User's choice:** Document the test coverage gap

---

## Auditable trait

| Option | Description | Selected |
|--------|-------------|----------|
| Use owen-it/laravel-auditing | Production-grade, full-featured. | ✓ |
| Roll our own minimal trait | Zero deps, full control. | |
| Hybrid | DIY with compatible schema. | |
| You decide | | |

**User's choice:** Use owen-it/laravel-auditing

---

| Option | Description | Selected |
|--------|-------------|----------|
| Keep forever (append-only) | Per REQUIREMENTS AUDIT-03. | ✓ |
| Prune > 2 years | Reduces storage. | |
| You decide | | |

**User's choice:** Keep forever (append-only)

---

| Option | Description | Selected |
|--------|-------------|----------|
| Full diff (before/after) | owen-it default. JSON of old + new values. | ✓ |
| Diff for writes, summary for reads | Lean, loses 'before' value. | |
| Minimal: action + user + timestamp + IP | Smallest, no diff. | |

**User's choice:** Full diff (before/after)

---

| Option | Description | Selected |
|--------|-------------|----------|
| Manager views via Tyro | Custom /mess/audit Blade with filters. | ✓ |
| Tyro's built-in audit | Use Tyro's audit log viewer in /dashboard. | |
| Both | Two surfaces. | |

**User's choice:** Manager views via Tyro

---

## Settings storage

| Option | Description | Selected |
|--------|-------------|----------|
| Separate settings table (EAV) | id, mess_id, key, value (json), type, group. | ✓ |
| JSON column on messes | One JSON column, schema migrations per shape change. | |
| Typed columns on messes | Direct columns (meal_breakfast_value, currency, etc.). | |
| You decide | | |

**User's choice:** Separate settings table (EAV)

---

| Option | Description | Selected |
|--------|-------------|----------|
| Scoped to mess_id (1 row now, many later) | Per-mess, mess_id=1 in v1. | ✓ |
| Single global row | No mess_id, v2 needs migration. | |
| Both — global + per-mess override | Per-mess wins. | |

**User's choice:** Scoped to mess_id (1 row now, many later)

---

| Option | Description | Selected |
|--------|-------------|----------|
| Admin only | Manager + super admin. | |
| Admin + super admin | Standard role split. | ✓ |
| Super admin only | Tightest control. | |

**User's choice:** Admin + super admin

---

## Mess config UI (covered in Role model section)

Decided: Tyro resource (auto CRUD).

---

## Migration scope

| Option | Description | Selected |
|--------|-------------|----------|
| All tables, empty | All domain tables now, empty. | ✓ |
| Only foundation tables | Foundation now, rest per phase. | |
| Phase 1 core + future placeholders | Foundation + nullable mess_id placeholders. | |

**User's choice:** All tables, empty

---

| Option | Description | Selected |
|--------|-------------|----------|
| Global scope reads config('app.active_mess_id') | Eloquent global scope on all domain models. | ✓ |
| Middleware sets the mess_id | Per-request binding. | |
| Hardcode mess_id=1 in seeders + checks | Quick and dirty, contradicts 'mess_id on every table'. | |
| You decide | | |

**User's choice:** Global scope reads config('app.active_mess_id')

---

## Seeding

| Option | Description | Selected |
|--------|-------------|----------|
| Yes, with idempotency | DatabaseSeeder creates mess + users + settings. | |
| Seeded via tinker/onboarding UI | Super admin via tinker; mess via onboarding form. | ✓ |
| Seeder + artisan command | Fresh setup seeder + devsroom:create-user. | |

**User's choice:** Seeded via tinker/onboarding UI

---

## Time zone, locale, money

| Option | Description | Selected |
|--------|-------------|----------|
| BDT + DD-MM-YYYY + en locale | Asia/Dhaka, English, DD-MM-YYYY default. | ✓ |
| BDT + DD-MM-YYYY + bn locale | Bengali UI from day 1 (translations deferred). | |
| BDT + per-mess date format | Per-mess format, defaults DD-MM-YYYY. | |

**User's choice:** BDT + DD-MM-YYYY + en locale

---

| Option | Description | Selected |
|--------|-------------|----------|
| DECIMAL(10,2) + ৳ + half-up rounding | MySQL DECIMAL arithmetic, NumberFormatter. | ✓ |
| DECIMAL(12,2) + BCMath + ৳ | Extra headroom, BCMath. | |
| DECIMAL(10,2) + ৳ + store as integer paisa | No float drift, integer internally. | |
| You decide | | |

**User's choice:** DECIMAL(10,2) + ৳ + half-up rounding

---

## DB credentials check

| Option | Description | Selected |
|--------|-------------|----------|
| Confirmed | root / 125524 is correct. | ✓ |
| Not sure — verify | Connect to MySQL first. | |
| Different — I'll tell you | User provides alternate. | |

**User's choice:** Confirmed

---

## the agent's Discretion

- Exact schema column types and indexes
- How to register the global mess scope (Bootable trait vs. observer vs. service provider)
- How to wire the `Auditable` trait on a sample model in Phase 1
- The exact Tyro config keys for enabling 2FA

## Deferred Ideas

- **Year-over-year reporting** (RPT-ADV) — v2
- **2FA enforcement for `user` (member) role** — v2
- **Audit log viewer in Tyro chrome** — v2
- **Member-initiated mess creation** — v2
- **Bengali translations** — v2 (strings are wrapped in `__()` for v2 readiness)
