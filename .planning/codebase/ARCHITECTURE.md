# Architecture

System design, patterns, layers, and data flow.

## Pattern

**Laravel MVC** — classic Model-View-Controller with service providers and package-driven extensions.

- Server-rendered Blade templates (no SPA)
- Eloquent ORM for persistence
- Service container for dependency injection
- PSR-4 autoloading under `App\`, `Database\Factories\`, `Database\Seeders\`, `Tests\`

## Layers

```
app/
├── Http/
│   └── Controllers/    # HTTP request handlers (currently empty)
├── Models/             # Eloquent models (only User.php exists)
└── Providers/          # Service providers (only AppServiceProvider)
```

### Request Flow (planned, not implemented)

1. Route (`routes/web.php` or `routes/api.php`) → middleware stack
2. Controller delegates to action (use case / service) — or directly to a Model
3. Model / Eloquent query against MySQL (or sqlite in dev/test)
4. View rendered (Blade) or JSON returned
5. Session flashed via Tyro middleware where applicable

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
Browser → Route (web) → [auth middleware] → Controller → Model (Eloquent) → MySQL
                                ↑                              ↓
                          Tyro guards                   Blade view render
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
