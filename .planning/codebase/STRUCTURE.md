# Structure

Directory layout and file organization.

## Top-Level

```
devsroom-mess-management/
├── app/                  # Application code (PSR-4: App\)
├── bootstrap/            # Framework bootstrap (app.php, providers.php)
├── config/               # Configuration files (one per package/area)
├── database/             # Migrations, factories, seeders
├── public/               # Web root (index.php, build assets)
├── resources/            # Views, raw CSS, raw JS
├── routes/               # Route definitions
├── storage/              # Logs, cache, uploads (gitignored)
├── tests/                # PHPUnit tests (Feature + Unit)
├── vendor/               # Composer packages (gitignored)
├── node_modules/         # npm packages (gitignored)
├── .agents/              # Agent skill definitions
├── .ai/, .claude/, .codex/, .gemini/, .kilo/  # Multi-agent config
├── .commandcode/         # Command Code CLI config + taste preferences
├── .github/              # GitHub-specific config
├── .editorconfig
├── .env, .env.example    # Environment
├── .gitignore
├── .gitattributes
├── .mcp.json             # MCP server config
├── artisan               # Laravel CLI entrypoint
├── boost.json            # Laravel Boost agent config
├── composer.json, composer.lock
├── opencode.json         # OpenCode agent config
├── package.json          # Frontend deps
├── phpunit.xml           # PHPUnit config
├── README.md             # Default Laravel README
└── vite.config.js        # Vite config
```

## `app/`

```
app/
├── Http/
│   └── Controllers/
│       └── Controller.php        # Abstract base
├── Models/
│   └── User.php                  # Only model
└── Providers/
    └── AppServiceProvider.php    # Empty service provider
```

## `database/`

```
database/
├── factories/
│   └── UserFactory.php
├── migrations/
│   ├── 0001_01_01_000000_create_users_table.php        # users, password_reset_tokens, sessions
│   ├── 0001_01_01_000001_create_cache_table.php        # cache, cache_locks
│   ├── 0001_01_01_000002_create_jobs_table.php         # jobs, job_batches, failed_jobs
│   └── 2026_06_15_225413_create_personal_access_tokens_table.php
├── seeders/
│   └── DatabaseSeeder.php
└── .gitignore
```

## `routes/`

- `web.php` — Web routes (1 route, `/` → welcome)
- `api.php` — API routes (empty)
- `console.php` — Artisan closures (empty)

## `resources/`

- `css/app.css` — Tailwind entry
- `js/app.js` — JS entry
- `views/` — Blade templates (welcome.blade.php default only)

## `config/`

- `app.php`, `auth.php`, `cache.php`, `database.php`, `filesystems.php`, `logging.php`
- `mail.php`, `queue.php`, `sanctum.php`, `services.php`, `session.php`
- `tyro-dashboard.php`, `tyro-login.php` — Tyro packages

## `tests/`

- `TestCase.php` — Base test class
- `Feature/ExampleTest.php` — Asserts `/` returns 200
- `Unit/ExampleTest.php` — Empty unit test stub

## `.agents/skills/`

Project-local skills available to AI agents:
- `laravel-best-practices/SKILL.md`
- `tyro-dashboard/SKILL.md`

## Naming Conventions

- **Classes**: PascalCase (`User`, `AppServiceProvider`)
- **Methods/Properties**: camelCase (`getKey`, `remember_token`)
- **Database columns**: snake_case (`email_verified_at`, `personal_access_tokens`)
- **Database names**: snake_case per taste preference (`devsroom_mess_management`, not hyphens)
- **Routes**: kebab-case paths, dot-notation names (`tyro-dashboard.users.index`)
- **Migrations**: `YYYY_MM_DD_HHMMSS_description.php`

## Where Things Go (When Adding Code)

| Concern | Location |
|---|---|
| HTTP controllers | `app/Http/Controllers/` |
| Eloquent models | `app/Models/` |
| Service classes | `app/Services/` (or `app/Actions/`) |
| Form requests | `app/Http/Requests/` |
| Resources (API) | `app/Http/Resources/` |
| Middleware | `app/Http/Middleware/` |
| Policies | `app/Policies/` |
| Jobs | `app/Jobs/` |
| Listeners | `app/Listeners/` |
| Mailables | `app/Mail/` |
| Notifications | `app/Notifications/` |
| Blade views | `resources/views/` (subdirectories per feature) |
| Vue/React/JS | `resources/js/` |
| Migrations | `database/migrations/` |
| Seeders | `database/seeders/` |
| Factories | `database/factories/` |
| Feature tests | `tests/Feature/` |
| Unit tests | `tests/Unit/` |
