# Stack

Languages, runtime, frameworks, and dependencies.

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
