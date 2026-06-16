<!-- GSD:project-start source:PROJECT.md -->
## Project

**Devsroom Mess Management**

A web-based mess management system designed for Bangladesh messes — bachelor hostels, student hostels, and shared accommodations. The mess manager enters daily meals and bazar expenses on mobile; members log in to view their bills and submit meal-off requests. The system automates the Bangladesh-specific monthly close: meal rate derived from bazar only, fixed expenses split equally, advance balance carry-forward, and immutable monthly snapshots.

The v1 scope is **one mess, fully working for one real monthly cycle**.

**Core Value:** A mess manager can run a full month end-to-end on a phone — enter meals, log bazar, take payments, close the month, and produce a correct member bill — without spreadsheets and without arguing about who owes what.

### Constraints

- **Tech stack**: Laravel 13, MySQL 8+, Tyro Dashboard, Tyro Login, Tailwind v4, Vite 7. Fixed by taste preference.
- **Database naming**: snake_case (e.g. `devsroom_mess_management`, not hyphens) — per taste preference.
- **DB driver**: MySQL in dev AND prod — do NOT use sqlite locally. Per taste preference and to avoid dev/prod parity bugs.
- **DB credentials**: Verify with user before assuming defaults — per taste preference.
- **Code style**: Laravel Pint (Laravel preset). Run before commits.
- **Tests**: PHPUnit 12 (NOT Pest, despite plugin allowance). Use `RefreshDatabase` for feature tests.
- **No inline CSS, no Bootstrap** — Tailwind only.
- **All user-facing strings use `__()`** — even if only English is shipped.
- **Single mess in v1** — every domain table has `mess_id` but only one mess exists.
<!-- GSD:project-end -->

<!-- GSD:stack-start source:codebase/STACK.md -->
## Technology Stack

## Runtime
- **PHP**: `^8.3` (declared in `composer.json`); runtime is `8.4`
- **Node.js**: `v24.15.0` (development tooling only)
## Framework
- **Laravel**: `^13.0` (installed: `13.15.0`)
- **Project type**: Laravel application skeleton (Composer name `laravel/laravel`)
- **Conventions**: PSR-4 autoload, Laravel 13 directory structure
## Frontend
- **Vite**: `^7.0.7` — build tool and dev server (`vite.config.js`)
- **Tailwind CSS**: `^4.0.0` via `@tailwindcss/vite` plugin
- **Axios**: `^1.11.0` — HTTP client
- **Concurrently**: `^9.0.1` — runs server, queue, pail, vite in dev
- **No SPA framework**: This is a server-rendered Blade app, not Inertia/Livewire/React
## Authentication & Authorization Packages
- **`laravel/sanctum`**: `^4.0` (installed: `4.3.2`) — API token auth, `personal_access_tokens` migration present
- **`hasinhayder/tyro-dashboard`**: `^1.36` — admin dashboard (users, roles, privileges, settings, invitations, audit logs, dynamic CRUD resources)
- **`hasinhayder/tyro-login`**: pulled in as a dependency; provides auth UI (login, register, email verification, magic links, OTP, 2FA, social login, lockout)
- **`hasinhayder/tyro`**: roles/privileges package — `User` model uses `HasTyroRoles` trait
- **`HasinHayder\TyroLogin\Traits\HasTwoFactorAuth`**: applied to `User`
## Developer Tooling
- **`laravel/boost`**: `^2.4` (installed: `2.4.10`) — AI agent integration, exposes MCP tools
- **`laravel/pint`**: `^1.27` — code style fixer (Laravel preset)
- **`laravel/pail`**: `^1.2.5` — error tailing in dev (`php artisan pail`)
- **`laravel/mcp`**: `0.8.1` — Model Context Protocol server
- **`laravel/prompts`**: `0.3.18` — CLI prompts library
- **`nunomaduro/collision`**: `^8.6` — pretty test errors
- **`mockery/mockery`**: `^1.6` — test doubles
- **`fakerphp/faker`**: `^1.23` — test data
- **`phpunit/phpunit`**: `^12.5.12` (installed: `12.5.30`) — test runner
## Database
- **Default connection**: `sqlite` (`.env.example`)
- **Production target**: MySQL (per `.commandcode/taste/taste.md` — "Use MySQL as the database")
- **Cache / Sessions / Queue**: `database` driver (requires `cache`, `jobs`, `sessions` tables — already migrated)
- **Naming convention**: `snake_case` for database names (per taste preferences)
## Configuration
- `boost.json` enables skills: `laravel-best-practices`, `tyro-dashboard`
- `.agents/skills/` directory contains project-local skill definitions
- `phpunit.xml` uses sqlite `:memory:` for tests with array cache/session, sync queue
## Tailwind/Vite Assets
- `resources/css/app.css` and `resources/js/app.js` are the build entry points
- `resources/views/` contains Blade templates (welcome view default only)
- HMR enabled in dev via Vite `refresh: true`
## Notable Gaps (no code yet)
- No `app/Http/Controllers` beyond the base `Controller.php`
- No `app/Models` beyond `User.php`
- No `routes/web.php` content beyond `/` → welcome view
- No `routes/api.php` content
- No feature-specific migrations (only Laravel defaults + Sanctum)
- No policies, form requests, jobs, or events
<!-- GSD:stack-end -->

<!-- GSD:conventions-start source:CONVENTIONS.md -->
## Conventions

## Code Style
- **Laravel Pint** is configured (Laravel preset). Run `vendor/bin/pint` before commits.
- **PSR-12** is the base standard.
- **EditorConfig** present (`.editorconfig`) — uses 4-space indent, LF line endings, UTF-8.
## PHP
### Attribute-Based Model Configuration
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
- New models should follow the same pattern (use `#[Fillable]`, `#[Hidden]` attributes)
- Avoid the older `$fillable` / `$hidden` property arrays when starting fresh
### Casts Method (Laravel 11+ style)
- Use the `casts()` method, not the deprecated `$casts` property
### Imports
- One `use` per import, alphabetically grouped
- Example from `User.php`:
### Migration Style
- Use anonymous classes, not named classes
- Always implement both `up()` and `down()`
- Use `Blueprint` typed parameter
- Foreign keys use `foreignId('user_id')` + `constrained()` pattern
### Test Style
- Extends `Tests\TestCase`
- Test methods prefixed with `test_` (PHPUnit snake_case, not Pest)
- `void` return type on test methods
## Database
- **snake_case** for all column and table names
- **Plural** table names (`users`, `personal_access_tokens`)
- **Timestamps** (`$table->timestamps()`) on all domain tables
- **Soft deletes** preferred for user-facing entities (not seen in skeleton, but standard)
- **Foreign keys**: `foreignId('user_id')->constrained()->cascadeOnDelete()` pattern
## Authorization
- Use Tyro roles/privileges, not Laravel Gates/Policies (initially)
- Check role: `$user->hasRole('admin')` (Tyro API)
- Use middleware `role:admin` for route protection
## Frontend
- **Tailwind CSS v4** with `@tailwindcss/vite` plugin
- **No JavaScript framework** — vanilla JS in `resources/js/app.js`
- **Axios** for AJAX (configured but no usage yet)
- All Blade, no Inertia/Livewire
## Error Handling
- Skeleton has no custom error handling
- Follow Laravel defaults: throw `ValidationException`, `ModelNotFoundException`, `AuthorizationException` from actions
- Use Form Requests for validation (`app/Http/Requests/`)
- Use Policies for authorization
## Service Registration
- Register bindings in `AppServiceProvider::register()` for app-wide singletons
- Use `boot()` for view composers, gates, observer registration
## Git
- Initialized in this session
- No commits yet
- `.gitignore` covers vendor, node_modules, .env, storage logs/cache, IDE folders, build artifacts
## PHPStan / Static Analysis
- **Not configured** — consider adding `larastan` (`nunomaduro/larastan`) for type safety
## Things To Avoid
- Don't add `$fillable` / `$hidden` property arrays to new models (use attributes)
- Don't use the deprecated `$casts` property (use the `casts()` method)
- Don't create named migration classes (use anonymous)
- Don't bypass Tyro for auth/roles (don't roll custom auth when Tyro provides it)
- Don't use hyphens in database names (per taste preference: `devsroom_mess_management`)
- Don't assume default DB credentials (per taste preference — verify with user)
<!-- GSD:conventions-end -->

<!-- GSD:architecture-start source:ARCHITECTURE.md -->
## Architecture

## Pattern
- Server-rendered Blade templates (no SPA)
- Eloquent ORM for persistence
- Service container for dependency injection
- PSR-4 autoloading under `App\`, `Database\Factories\`, `Database\Seeders\`, `Tests\`
## Layers
```
```
### Request Flow (planned, not implemented)
## Key Components
### `App\Models\User`
- Extends `Illuminate\Foundation\Auth\User as Authenticatable`
- Uses `HasApiTokens` (Sanctum), `HasFactory`, `Notifiable`
- Uses `HasTyroRoles` (Tyro roles/privileges)
- Uses `HasTwoFactorAuth` (Tyro 2FA)
- Fillable: `name`, `email`, `password` (via `#[Fillable]` attribute)
- Hidden: `password`, `remember_token` (via `#[Hidden]` attribute)
- Casts: `email_verified_at` → datetime, `password` → hashed
### `App\Providers\AppServiceProvider`
- Empty `register()` and `boot()` — no custom bindings yet
### `App\Http\Controllers\Controller`
- Abstract base class, no shared logic yet
## Authorization Model
- **Tyro roles**: `admin`, `super-admin` (protected), `user` (default for new registrations)
- **Tyro privileges**: fine-grained, assigned to roles
- **Gate / Policy**: none defined yet — relies on Tyro's role/privilege checks
## Authentication Flow
- Session-based for web (Tyro Login)
- Token-based for API (Sanctum, `personal_access_tokens` table)
- Login → redirect to `TYRO_LOGIN_REDIRECT_AFTER_LOGIN` (defaults `/`)
- Registration auto-assigns `user` role; auto-login enabled by default
- Email verification: optional (`TYRO_LOGIN_REQUIRE_EMAIL_VERIFICATION=false`)
## Data Flow (Typical)
```
```
## Data Model (Current)
- `users` — id, name, email (unique), email_verified_at, password, remember_token, timestamps
- `password_reset_tokens` — email (PK), token, created_at
- `sessions` — id (PK), user_id, ip_address, user_agent, payload, last_activity
- `cache`, `cache_locks` — Laravel cache table
- `jobs`, `job_batches`, `failed_jobs` — Laravel queue tables
- `personal_access_tokens` — Sanctum tokens
- (Tyro tables) — roles, privileges, role_user, privilege_role, invitations, audit_logs (created by package migrations)
## Routing
- `routes/web.php`: only `GET /` → `welcome` view
- `routes/api.php`: empty
- `routes/console.php`: empty
- Tyro dashboard routes auto-registered at `/dashboard/*`
- Tyro login routes auto-registered at `/login`, `/register`, `/logout`, `/password/*`, `/verify-email`, etc.
## Build & Runtime
- **Dev**: `composer run dev` starts `php artisan serve`, `queue:listen`, `pail`, `vite` in parallel
- **Build**: `npm run build` (Vite production build)
- **Test**: `composer run test` → `php artisan test` (PHPUnit 12)
- **Migrate**: `php artisan migrate` (idempotent)
- **Seed**: `php artisan db:seed` (creates `test@example.com` test user)
## Extension Points
- `config/tyro-dashboard.php` — dashboard config, dynamic CRUD resources
- `config/tyro-login.php` — auth flow config, feature flags
- `App\Providers\AppServiceProvider::boot()` — global bindings, view composers, gates
- Custom policies, form requests, jobs, listeners as needed
<!-- GSD:architecture-end -->

<!-- GSD:skills-start source:skills/ -->
## Project Skills

| Skill | Description | Path |
|-------|-------------|------|
| laravel-best-practices | "Apply this skill whenever writing, reviewing, or refactoring Laravel PHP code. This includes creating or modifying controllers, models, migrations, form requests, policies, jobs, scheduled commands, service classes, and Eloquent queries. Triggers for N+1 and query performance issues, caching strategies, authorization and security patterns, validation, error handling, queue and job configuration, route definitions, and architectural decisions. Also use for Laravel code reviews and refactoring existing Laravel code to follow best practices. Covers any task involving Laravel backend PHP code patterns." | `.claude/skills/laravel-best-practices/SKILL.md` |
| tyro-dashboard | "Use for Tyro Dashboard framework or app integration work: admin pages, routes, sidebar/menu changes, settings, CRUD resources, Blade overrides, RBAC, media, plugins, service providers, public APIs, and upgrade-safe framework maintenance for hasinhayder/tyro-dashboard, hasinhayder/tyro, tyro-login, or Laravel apps consuming Tyro Dashboard." | `.claude/skills/tyro-dashboard/SKILL.md` |
<!-- GSD:skills-end -->

<!-- GSD:workflow-start source:GSD defaults -->
## GSD Workflow Enforcement

Before using Edit, Write, or other file-changing tools, start work through a GSD command so planning artifacts and execution context stay in sync.

Use these entry points:
- `/gsd-quick` for small fixes, doc updates, and ad-hoc tasks
- `/gsd-debug` for investigation and bug fixing
- `/gsd-execute-phase` for planned phase work

Do not make direct repo edits outside a GSD workflow unless the user explicitly asks to bypass it.
<!-- GSD:workflow-end -->



<!-- GSD:profile-start -->
## Developer Profile

> Profile not yet configured. Run `/gsd-profile-user` to generate your developer profile.
> This section is managed by `generate-claude-profile` -- do not edit manually.
<!-- GSD:profile-end -->
