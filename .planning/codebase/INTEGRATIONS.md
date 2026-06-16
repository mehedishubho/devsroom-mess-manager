# Integrations

External services, APIs, and third-party system connections.

## Currently Configured

### Mail

- **Driver**: `log` (default in `.env.example`) — writes mails to log, not sent
- **From address**: `hello@example.com` (placeholder)
- **Production**: Needs real SMTP credentials in `.env`

### File Storage

- **Default disk**: `local` (private, `storage/app/`)
- **Public disk**: `public` (for uploaded assets)
- **No S3 / cloud storage configured** — AWS env vars present but empty in `.env.example`

### Cache

- **Default store**: `database` (uses `cache` table)
- **Test store**: `array` (in-memory)
- **No Redis configured** despite env vars in `.env.example`

### Session

- **Driver**: `database` (uses `sessions` table)
- **Lifetime**: 120 minutes
- **Encryption**: disabled by default

### Queue

- **Connection**: `database` (uses `jobs` table)
- **Dev runner**: `php artisan queue:listen` via the `dev` Composer script

### Broadcast

- **Driver**: `log` (broadcasts written to log file only)

## Tyro Ecosystem (Internal Integrations)

The `hasinhayder/tyro-dashboard` and `hasinhayder/tyro-login` packages bundle:

- **Auth flow**: login, register, logout, password reset, email verification
- **2FA**: TOTP (Google Authenticator compatible) — disabled by default (`TYRO_LOGIN_2FA_ENABLED=false`)
- **OTP login**: email-delivered one-time passwords — disabled by default
- **Magic links**: passwordless email login — disabled by default
- **Social login**: Google, Facebook, GitHub, Twitter, LinkedIn, Bitbucket, GitLab, Slack — requires `laravel/socialite` and OAuth credentials in `config/services.php`
- **Lockout**: brute-force protection (5 attempts / 15 min) — enabled by default
- **Captcha**: math-based, available for login/registration — disabled by default

## Dashboard / Admin

- **Route prefix**: `/dashboard` (`TYRO_DASHBOARD_PREFIX` env)
- **Middleware**: `web`, `auth`
- **Built-in resources**: user mgmt, role mgmt, privilege mgmt, settings, profile, invitations, audit logs
- **Dynamic CRUD resources**: configurable via `config/tyro-dashboard.php` `resources` array (empty by default)

## Outbound (Not Yet Configured)

- No payment gateway (Stripe, bKash, Nagad, etc.)
- No SMS gateway
- No analytics service
- No error tracking (Sentry, Bugsnag)
- No cloud storage binding
