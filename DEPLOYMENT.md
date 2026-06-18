# Deployment Guide — Devsroom Mess Management

Production hardening checklist for shipping the v1 pilot. Covers the Laravel Forge path (primary — faster to ship) and a manual VPS appendix. The v1 pilot is **one mess, one monthly cycle** — a single small VPS is plenty.

> **Shared hosting is RULED OUT.** The month-close runs as a queued job (`app/Jobs/CloseMonthJob.php`) and requires a persistent queue worker via `supervisor`. Shared hosts cannot reliably run a persistent worker (the worker is killed on every request boundary / cron tick / panel restart), and there is no way to recover from that class of failure without shell access. **You need a VPS.** A $5/month DigitalOcean/Hetzner droplet is sufficient for the pilot.

---

## 1. Deployment target

| Path | When to pick | Cost |
|------|--------------|------|
| **Laravel Forge (primary)** | You want managed supervisor + cron + deploy-on-git-push + log UI. Fastest to ship. | ~$12/mo Forge + ~$5/mo VPS |
| **Manual VPS (appendix §4)** | You want full control / lower cost / already have a VPS. | ~$5/mo VPS only |

Either path requires:
- A VPS (DigitalOcean / Hetzner / Linode / AWS Lightsail / etc.) running a recent Ubuntu LTS
- PHP 8.4 + extensions, MySQL 8+, Nginx, supervisor, Composer
- Node.js 24+ **only if** you are building assets on the server (otherwise build locally and commit `public/build/`)

---

## 2. Prerequisites (production VPS)

Install on the VPS:

```bash
# PHP 8.4 + extensions
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.4-fpm php8.4-mysql php8.4-gd php8.4-zip \
                    php8.4-mbstring php8.4-curl php8.4-xml php8.4-bcmath

# MySQL 8
sudo apt install -y mysql-server
sudo mysql_secure_installation

# Nginx + supervisor + Composer + Git
sudo apt install -y nginx supervisor git
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer

# Node.js 24 (ONLY if building assets on the server)
curl -fsSL https://deb.nodesource.com/setup_24.x | sudo -E bash -
sudo apt install -y nodejs
```

PHP extensions required by this project: `pdo_mysql`, `gd`, `zip`, `mbstring`, `curl` (plus the standard `xml`, `bcmath` for Laravel). `pcov` / `xdebug` are NOT needed in production (those are dev-only coverage tools).

---

## 3. Forge path (primary — recommended for the pilot)

Laravel Forge provisions the VPS for you and writes the supervisor config + cron entry automatically.

### 3.1 Provision
1. Create a server in Forge (DigitalOcean / Hetzner / AWS). Pick PHP 8.4, MySQL 8, Nginx.
2. Create a **Site** pointing at your Git repo's `master` branch, deploy-on-push enabled.
3. In the site's **Editor**, set the deployment script. Recommended baseline:

    ```bash
    cd /home/forge/your-domain.com
    git pull origin master
    composer install --no-dev --optimize-autoloader
    # Note: --no-dev is CRITICAL — it excludes debugbar + telescope so they
    # never even load in production. This is layer 1 of the three-layer gate.
    php artisan migrate --force
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    # Only if NOT building assets locally + committing public/build/:
    #   npm ci && npm run build && php artisan filament:assets
    ```

4. In **Environment**, set the production `.env` (see §5 for the full checklist — the hard requirements are `APP_DEBUG=false`, `APP_ENV=production`, `APP_URL=https://...`, MySQL creds, `QUEUE_CONNECTION=database`).

### 3.2 Add the Queue Worker (Forge does this for you)
1. In the site → **Queues** → **Add Worker**. Use these values:
    - Command: `php /home/forge/your-domain.com/artisan queue:work database --sleep=3 --tries=3 --max-time=3600`
    - Daemons: `1`
    - Max Time / Hours: as Forge default
2. Forge writes the supervisor config + enables auto-restart-on-deploy (so the worker picks up new code each deploy).

### 3.3 Add the Scheduler (Forge does this for you)
1. Site → **Scheduler** → add `php /home/forge/your-domain.com/artisan schedule:run` at `* * * * *`. Forge writes the crontab entry.

### 3.4 HTTPS
1. Site → **SSL** → **Let's Encrypt** (one click). Forge provisions + auto-renews.
2. Force HTTPS redirect stays on (Forge default).

---

## 4. Manual VPS path (appendix)

If you are not using Forge, you write the Nginx site, supervisor config, and cron yourself.

### 4.1 Clone + install
```bash
sudo mkdir -p /var/www/mess
sudo chown -R $USER:www-data /var/www/mess
cd /var/www/mess
git clone <repo-url> .
composer install --no-dev --optimize-autoloader
# If NOT committing public/build/:
#   npm ci && npm run build
cp .env.example .env
php artisan key:generate
# Edit .env per §5 checklist
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link
```

### 4.2 Nginx site config
`/etc/nginx/sites-available/mess.conf`:
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/mess/public;
    index index.php index.html;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
```
```bash
sudo ln -s /etc/nginx/sites-available/mess.conf /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

### 4.3 Supervisor config for the queue worker (verbatim)

The `CloseMonthJob` has `$timeout = 120`. Supervisor's `stopwaitsecs` MUST exceed the job timeout so the worker isn't killed mid-close.

`/etc/supervisor/conf.d/mess-worker.conf`:
```ini
; Laravel Forge writes this file automatically if using Forge.
[program:mess-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/mess/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/mess/storage/logs/worker.log
stopwaitsecs=3600            ; MUST exceed job timeout (CloseMonthJob timeout=120s)
```

Activate:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start mess-worker:*
sudo supervisorctl status     # verify mess-worker:RUNNING
```

### 4.4 The schedule:run cron (verbatim)

`sudo crontab -e` — add:
```
* * * * * cd /var/www/mess && php artisan schedule:run >> /dev/null 2>&1
```

This fires Laravel's scheduler every minute. The scheduler runs `telescope:prune` daily (class_exists-guarded so prod without telescope doesn't error). Verify: `sudo crontab -l`.

---

## 5. Production `.env` checklist — HARD requirements

Get these wrong and the pilot fails. The first two are non-negotiable security gates (CONCERNS #9 — stack traces leak; T-05-03-01).

| Key | Value | Why |
|-----|-------|-----|
| `APP_ENV` | `production` | Disables dev-only behaviors. |
| **`APP_DEBUG`** | **`false`** | **CRITICAL.** If `true`, any error leaks full stack traces + env vars to the browser. (T-05-03-01) |
| `APP_URL` | `https://your-domain.com` | HTTPS scheme, not HTTP. Used for signed routes + asset URLs. |
| `APP_TIMEZONE` | `Asia/Dhaka` | Carry-forward from Plan 01 D-21. Don't ship UTC. |
| `APP_KEY` | (generated by `php artisan key:generate`) | Never copy dev's key. |
| `DB_CONNECTION` | `mysql` | NEVER sqlite (dev/prod parity constraint). |
| `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | (production MySQL creds — NOT the dev password) | T-05-03-04: set fresh at deploy time, do not copy dev `.env`. |
| `QUEUE_CONNECTION` | `database` | Supervisor runs the worker (§3.2 or §4.3). Without this, `CloseMonthJob` never runs (T-05-03-05). |
| `CACHE_STORE` | `database` | Single-mess pilot: database cache is fine. (array/file also acceptable.) |
| `SESSION_DRIVER` | `database` | |
| `TELESCOPE_ENABLED` | `false` | Default in `.env.example` — verify. Telescope is require-dev so `--no-dev` won't load it anyway, but the flag is the second gate. |
| `DEBUGBAR_ENABLED` | `false` | Default in `.env.example` — verify. Three-layer gate: require-dev + this flag + config closure. |
| `AUDIT_ENABLED` | `true` | Keep the domain audit log on in prod. |

Do NOT copy dev `.env` to prod. Create fresh via `cp .env.example .env` + edits, or via Forge's Environment UI.

---

## 6. HTTPS

- **Let's Encrypt via Forge**: one click in the Forge UI, auto-renews. (Primary path.)
- **Manual**: `sudo certbot --nginx -d your-domain.com -d www.your-domain.com`. Certbot rewrites the Nginx config to redirect HTTP → HTTPS.
- If you serve assets from a CDN, set `ASSET_URL` to the CDN's HTTPS URL.

---

## 7. Storage permissions

Profile photos + bazar receipts land in `storage/app/public/` (symlinked to `public/storage/` via `php artisan storage:link`).

```bash
sudo chown -R www-data:www-data /var/www/mess/storage /var/www/mess/bootstrap/cache
sudo find /var/www/mess/storage -type d -exec chmod 775 {} \;
sudo find /var/www/mess/storage -type f -exec chmod 664 {} \;
php artisan storage:link
```

Forge handles this automatically.

---

## 8. First-deploy verification

After the first deploy, run this smoke test:

1. Visit `https://your-domain.com`. Should redirect to `/login` (Tyro Login).
2. Log in as the production admin (the super-admin you set up via `php artisan tinker` → `User::factory()` + `assignRole(Role::firstOrCreate(['slug'=>'super-admin']))`, OR seeded manually — **NOT** `manager@demo.test`, that's dev only).
3. Walk the onboarding: create the real Mess (name, address, rent, manager contact) → configure settings (meal values, currency BDT, date format DD-MM-YYYY).
4. Smoke-test: create a member, enter a meal on `/mess/meals`, view `/home` (dashboard should populate).
5. Trigger a test month-close: `/mess/close` → POST. Watch the worker log (`storage/logs/worker.log` or Forge's log UI) for `CloseMonthJob` completing without exception. Verify `/mess/closings` shows the new closing.
6. Verify the queue worker is running:
    ```bash
    sudo supervisorctl status
    # expect: mess-worker:RUNNING
    ```
7. Verify the scheduler is running:
    ```bash
    sudo crontab -l | grep schedule:run
    # expect: * * * * * cd /var/www/mess && php artisan schedule:run >> /dev/null 2>&1
    ```
8. Verify Debugbar + Telescope are OFF: view-source on `/home` — there should be NO `phpdebugbar` / `debugbar` substring anywhere, and `/telescope` should 403 or redirect.
9. Force an error (e.g. visit a non-existent route like `/zzz`) and confirm Laravel's production error page shows NO stack trace — just the generic "Server Error" with a log line written to `storage/logs/laravel.log`.

---

## 9. Post-deploy monitoring

- **App logs**: `storage/logs/laravel.log` (Laravel default daily rotation). Forge has a log UI.
- **Worker log**: per the supervisor config (`/var/www/mess/storage/logs/worker.log`). This is where `CloseMonthJob` exceptions surface.
- **Failed jobs**: `php artisan queue:failed` lists failed jobs; `php artisan queue:retry all` retries them.
- **Database size**: `monthly_closings` + `monthly_member_summaries` are immutable and grow one row per member per month. Telescope tables (if installed in non-local) grow fast — the daily `telescope:prune` (24h retention) keeps them bounded.
- **Health check**: a simple `curl -fsS https://your-domain.com/login` in an uptime monitor (UptimeRobot, etc.) covers "is the app responding."

---

## 10. When something breaks

| Symptom | Likely cause | Fix |
|---------|--------------|-----|
| Month-close never completes | Queue worker not running | `sudo supervisorctl status` → if not RUNNING, `sudo supervisorctl start mess-worker:*` (T-05-03-05) |
| Stack traces visible in browser | `APP_DEBUG=true` shipped | Set `APP_DEBUG=false`, `php artisan config:cache`, redeploy (T-05-03-01) |
| `/home` data stale after a write | Cache invalidation not firing | Check `AppServiceProvider::registerBillPreviewInvalidation` listeners; check `CACHE_STORE=database` and that the `cache` table exists |
| "MONTH CLOSED" on a legitimate write | The (year, month) is closed — by design | Use `/mess/closings/{closing}/corrections` for adjustment entries (CLOSE-12) |
| 500 on PDF/Excel export | Dompdf memory or Excel timeout | Increase PHP `memory_limit` / `max_execution_time` for fpm; check `php8.4-fpm` pool config |
| `composer install` tries to load debugbar/telescope | You forgot `--no-dev` | Re-run `composer install --no-dev --optimize-autoloader` |

---

*Last updated: 2026-06-19 (Plan 05-03). The supervisor block in §4.3 + the schedule cron in §4.4 are the verbatim artifacts Forge would write for you — copy them as-is if doing manual setup.*
