# Deployment Guide — Devsroom Mess Management

Production hardening checklist for shipping the v1 pilot. Covers the Laravel Forge path (primary — faster to ship), self-hosted Docker panels ([Dokploy](https://dokploy.com/) / [Coolify](https://coolify.io/) — §12), cPanel shared hosting (§13), and a manual VPS appendix. The v1 pilot is **one mess, one monthly cycle** — a single small VPS is plenty.

> **Bare shared hosting is RULED OUT for the worker.** The month-close runs as a queued job (`app/Jobs/CloseMonthJob.php`) and requires a persistent queue worker via `supervisor` (or a container-native worker service — §12). Shared hosts cannot reliably run a persistent worker (the worker is killed on every request boundary / cron tick / panel restart), and there is no way to recover from that class of failure without shell access. **You need a VPS** — or shared hosting with `QUEUE_CONNECTION=sync` and its trade-offs accepted (§13). A $5/month DigitalOcean/Hetzner droplet is sufficient for the pilot.

---

## 1. Deployment target

| Path | When to pick | Cost |
|------|--------------|------|
| **Laravel Forge (primary)** | You want managed supervisor + cron + deploy-on-git-push + log UI. Fastest to ship. | ~$12/mo Forge + ~$5/mo VPS |
| **Dokploy / Coolify (§12)** | You want Forge-style UX (git-push deploys, TLS, logs, rollbacks) without the Forge bill — both panels are free/open-source and run on your own VPS. You commit a Dockerfile + compose file to the repo; the panel builds and runs web + queue + scheduler + MySQL. | ~$5–10/mo VPS only |
| **cPanel shared hosting (§13)** | Cheapest possible single-mess install; jobs run inline (`sync`), month-close blocks the browser for a few seconds. | Existing shared-hosting plan |
| **Manual VPS (appendix §4)** | You want full control / lower cost / already have a VPS. | ~$5/mo VPS only |

The Forge / manual-VPS paths require:
- A VPS (DigitalOcean / Hetzner / Linode / AWS Lightsail / etc.) running a recent Ubuntu LTS
- PHP 8.4 + extensions, MySQL 8+, Nginx, supervisor, Composer
- Node.js 24+ **only if** you are building assets on the server (otherwise build locally and commit `public/build/`)

The Dokploy / Coolify path requires none of that on the host — the panel runs everything in Docker. You only commit the three files from §12.1 to the repo.

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
| `QUEUE_CONNECTION` | `database` | A persistent worker runs the jobs — supervisor (§3.2 / §4.3) or a container worker service (§12.1 compose). Without this, `CloseMonthJob` never runs (T-05-03-05). |
| `CACHE_STORE` | `database` | Single-mess pilot: database cache is fine. (array/file also acceptable.) |
| `SESSION_DRIVER` | `database` | |
| `TELESCOPE_ENABLED` | `false` | Default in `.env.example` — verify. Telescope is require-dev so `--no-dev` won't load it anyway, but the flag is the second gate. |
| `DEBUGBAR_ENABLED` | `false` | Default in `.env.example` — verify. Three-layer gate: require-dev + this flag + config closure. |
| `AUDIT_ENABLED` | `true` | Keep the domain audit log on in prod. |

Do NOT copy dev `.env` to prod. Create fresh via `cp .env.example .env` + edits, or via Forge's Environment UI.

### Phase 6 — Backup & restore keys

These keys wire the spatie/laravel-backup engine and the bespoke restore services. Leave them empty ONLY if you are deliberately not running backups; spatie's scheduler entries are `class_exists`-guarded so an empty config degrades to "no backups" rather than crashing.

| Key | Value | Why |
|-----|-------|-----|
| `BACKUP_MAX_MB` | `5000` (starting point) | Size cap; `backup:purge` enforces it. Tunable. |
| `BACKUP_NOTIFICATION_EMAIL` | `ops@your-domain.com` | Where spatie sends failure emails (an email saved on the Backups page wins over this). Requires `MAIL_MAILER=smtp` (see §11.7). |
| `BACKUP_ARCHIVE_PASSWORD` | (a strong password) | Optional AES-256 zip encryption. Leave empty to disable client-side encryption (an encryption password saved on the Backups page wins over this). |
| `BACKUP_LOG_KEEP_DAYS` | `90` | Activity-log retention enforced by `backup:prune-logs`. |
| `DUMP_BINARY_PATH` | `/usr/bin` (prod) — unset defaults to `/usr/bin` | Directory containing `mysqldump`. (Pitfall 2) |

**Backup destinations need no env keys.** Local is always on; Google Drive and Cloudflare R2 are optional mirrors enabled + configured from the Backups page (`/dashboard/backups`) and stored encrypted in the database. See §11.6.

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
2. Log in as the production admin (the super-admin you set up — **NOT** `manager@demo.test`, that's dev only). If no super-admin exists yet, visit the app root — the **one-time setup wizard** at `/setup` will guide you through creating the first super-admin account. Alternatively, create one via CLI:

    ```bash
    php artisan mess:create-super-admin admin@yourdomain.com "Admin Name" --password=<strong-password>
    ```

    Then log in at `/login` with that email/password.
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
| Assets/links render as `http://` behind Dokploy/Coolify (mixed content) | App is behind the panel's Traefik/Caddy proxy | Set `APP_URL=https://…` and redeploy (Laravel generates URLs from `APP_URL`). If any absolute URLs are still `http`, also trust the proxy: add `$middleware->trustProxies(at: '*')` in `bootstrap/app.php`. |
| `backup:run` in a Dokploy/Coolify container fails with "dump process failed" | The image is missing a `mysqldump` binary | Use the §12.1 Dockerfile (it installs `mariadb-client`); keep `DUMP_BINARY_PATH=/usr/bin` (default in `.env.example`). |

---

---

## 11. Backup & restore runbook

This section documents how an operator uses the backup-and-restore system shipped in Phase 6 (Plans 06-01 → 06-04). The system is built on `spatie/laravel-backup` v10 (backup-only by design — D-06) plus a bespoke restore service layer (`BackupRestoreService`, `RestoreTestService` in `app/Services/`) surfaced through a `role:super-admin` UI at `/dashboard/backups`.

**When to open this section:** the moment something has destroyed or corrupted live data — a VPS loss, a bad migration, a botched month-close, an accidental table drop. The crown jewels are the immutable financial snapshots (`monthly_closings` + `monthly_member_summaries`) and the append-only `audit_logs`; this runbook exists so they are always recoverable.

### 11.1 What gets backed up

Each backup is a single zip that contains:

- A **full `mysqldump` of the `mysql` connection** — every domain table (all 26+: `messes`, `members`, `meal_entries`, `payments`, `monthly_closings`, `monthly_member_summaries`, `audit_logs`, etc.). The dump uses `--single-transaction --quick` for a consistent, lock-free snapshot.
- Everything under **`storage/app/public/`** — profile photos and bazar receipts (the only file uploads in v1). Spatie's `follow_links` is `false` so the `public/storage` symlink is never followed into the live dir (Pitfall 4).

**`.env` is deliberately EXCLUDED from backups (D-07).** Secrets must not live in object storage. The consequence: after any restore you MUST regenerate `APP_KEY` + rotate credentials — see §11.5 step 7.

### 11.2 Where backups live

- **Local, always** — `storage/app/backups/` (the `backups-local` disk). Every backup lands here first; this is what the Backups page lists, downloads, verifies and restores from.
- **Off-server mirrors (optional)** — Google Drive and/or Cloudflare R2, enabled + configured from the Backups page (§11.6). Each backup row on the page shows which destinations actually hold the archive.
- **Retention ladder** (D-02): `keep_all=7d`, `keep_daily=14d`, `keep_weekly=8w`, `keep_monthly=12mo`, `keep_yearly=2y`, plus a configurable MB growth guard. The long monthly retention exists because `monthly_closings` snapshots are immutable financial records — a corruption discovered months later must still be recoverable.
- `storage/app/laravel-backup` and `storage/app/backup-temp` are transient working areas only.

### 11.3 Schedule

The backup schedule lives in `routes/console.php` and runs on the existing Laravel scheduler (which fires every minute per §4.3/§4.4). The nightly cadence:

| Time | Command | Purpose |
|------|---------|---------|
| 01:00 | `backup:purge` | Prune old backups per the retention ladder (§11.2) across every active destination. |
| (configured) | `backup:run` | The actual DB dump + files zip + mirror upload. `withoutOverlapping()->onOneServer()`. |
| 02:00 | `backup:monitor` | Health-check the latest backup (not too old, not too big). Emits `UnhealthyBackupWasFound` on failure → in-app bell + email (§11.7). |
| 03:00 | `backup:prune-logs` | Drop activity-log rows older than `BACKUP_LOG_KEEP_DAYS` so the table can't grow without bound. `onOneServer()`. |

All commands are `class_exists`-guarded so an unconfigured Phase 6 degrades to "no backups" rather than crashing the scheduler.

**On-demand** (in addition to the schedule):

- **Via the UI**: super-admin → `/dashboard/backups` → **Backup now**. This is **queued** (a `running` row appears in the Activity log immediately and is updated in place when the worker finishes), so it never blocks the browser or dies on a gateway timeout. **The queue worker must be running** (§4) — otherwise the run sits in `jobs` until one starts.
- **Via the CLI** on the VPS: `php artisan backup:run`.
- **Via the CLI**, DB-only (faster): `php artisan backup:run --only-db`.

**Post-close hook (D-05)**: a successful `CloseMonthJob` calls `Artisan::call('backup:run', ['--only-db' => true])` from its `after()` lifecycle hook. The close produces the highest-value immutable data of the month (`monthly_closings` + `monthly_member_summaries`); capturing it now beats waiting up to 24h for the nightly run. The hook is wrapped in try/catch so a backup failure can NEVER break the close path (T-06-02-07). The `failed()` hook is an explicit no-op — a half-closed state is never backed up.

### 11.4 Restore procedure (PRIMARY path — via the super-admin UI)

Use this path when the app itself is healthy but you need to roll the data back (e.g. a bad migration was applied + reverted, a manager corrupted `monthly_closings` and you need to restore yesterday's snapshot). If the app itself is unreachable, skip to §11.5.

1. Log in to **`/dashboard/backups`** as a super-admin. This is the only role with access (T-06-03-01 — `role:super-admin` middleware on every route in the group; admins and users get 403).
2. Review the **stats header** at the top of the page — last successful backup, archive count, total size on disk and the next scheduled run — plus the **per-destination ticks** on each row. Confirm the archive you are about to restore is the one you want, and that it is present where you expect it.
3. Find the backup zip you want to restore in the list. Use **Verify archive** to confirm the stored checksum matches before you trust it, and **Download** if you want an offline copy (both write an audit row — T-06-03-05, every access is tamper-evident). Then click **Restore** on the chosen zip.
4. The restore form (`resources/views/dashboard/backups/restore.blade.php`) renders a prominent red destructive warning + asks you to type the **active mess's name EXACTLY**. The expected value is `Mess::find(Mess::activeId())->name` — the typed-confirm second factor (D-03, Open Question #3 LOCKED). The restore POST is throttled at `5,1` (5 attempts/minute per IP — T-06-03-04).
5. Type the mess name. Submit. `RestoreRequest` validates `mess_name in:<active mess name>`; a wrong value redirects back with a validation error and NO service call is made (T-06-03-02). If no active mess exists (pre-onboarding), the validator degrades to an unmatchable sentinel so a restore can NEVER proceed.
6. `BackupRestoreService` first takes a **pre-restore safety backup** of the current state (so a bad restore is reversible), then flips the app into **maintenance mode** (web requests now hit `errors/maintenance-backup-restore.blade.php`; `queue:restart` is called so no `CloseMonthJob` runs mid-restore — T-06-02-01), then: **reads** the zip from the backup disk → **extracts** it → **locates** the dump at `db-dumps/<dbname>.sql` via the Finder-based `BackupPathResolver` (handles both flat and nested layouts — Pitfall 1) → **restores the DB** via `mysql` CLI (Symfony Process with array args, never string-concat — Pattern 4a) → **copies files** back into `storage/app/public/` (NEVER `public/storage` — Pitfall 4, T-06-02-03) → **spot-checks** row counts. The `up` call is in a `finally {}` so the app ALWAYS returns to live even if an exception is thrown mid-restore.
7. The restore is **queued** (`RestoreBackupJob`, `$tries = 1`) — a `running` row appears in the Activity log immediately and is updated in place with the outcome. Nothing runs on the request thread, so a gateway/PHP timeout cannot hard-kill the restore half-way.
8. On success the job writes a manual `Audit` row (`event='backup.restore'`) with the path + the restore tag (T-06-03-07) and marks the activity row `success`.
9. On failure the job writes `event='backup.restore.failed'` with the exception message and marks the row `failed` (T-06-03-07). The live DB was NOT modified if the exception fired before the DB-restore step. **The app is still returned to live** — the service's `finally` calls `up`, the job calls `up` again on both the success and failure paths, and `failed()` calls it once more if the worker killed the job.
10. If every one of those was skipped (a hard kill on a hostile host) and the site is serving the maintenance page, use **Stuck in maintenance mode? → "Bring the app back online"** on the Backups page, or `php artisan up` over SSH.
11. **Restoring from an archive you have off-server** (fresh VPS, or the local archive list is gone): use **Restore from an uploaded archive** on the Backups page. Upload the `.zip`, type the active mess name, submit. The file is validated (zip only, ≤ 500 MB), stored on the backups disk, and then restored through the exact same queued flow.

Confirm every restore under **`/mess/audit`** (filter `tags=backup`).

### 11.5 Restore procedure (FALLBACK — via CLI, when the UI is unreachable)

If the app itself is down (white screen, fatal error before Laravel boots, bad migration that broke the schema the UI depends on, lost VPS), restore via SSH on the VPS. This path requires shell access — keep your Forge SSH key or VPS credentials somewhere OUTSIDE the VPS (the runbook in your password manager is a good place).

1. SSH into the VPS. `cd /var/www/mess` (manual setup) or `cd /home/forge/your-domain.com` (Forge).
2. `php artisan down` — maintenance mode on.
3. Download the backup zip from the backups disk (local by default, or your Drive/R2 mirror). From inside the Laravel app:
   ```bash
   php artisan tinker
   >>> $disk = Storage::disk(config('backup.backup.destination.disks.0', 'backups-local'));
   >>> $latest = collect($disk->allFiles())->filter(fn ($p) => str_ends_with($p, '.zip'))->sortDesc()->first();
   >>> $disk->download($latest)->send();
   // Save the streamed body to /tmp/restore.zip.
   ```
   If Laravel cannot boot at all, copy the zip straight off disk (`/var/www/mess/storage/app/backups/...`) or download it from your Drive/R2 mirror with its own client.
4. Unzip the backup:
   ```bash
   unzip /tmp/restore.zip -d /tmp/restore-extracted
   find /tmp/restore-extracted -path '*/db-dumps/*.sql'   # locate the dump
   ```
   The dump may be at the flat path `db-dumps/<dbname>.sql` (legacy layout) or nested one level deeper (spatie v8+ layout) — both are handled.
5. Restore the DB:
   ```bash
   mysql --host=127.0.0.1 --user=root --password=<prod-password> devsroom_mess_management \
       < /tmp/restore-extracted/db-dumps/devsroom_mess_management.sql
   ```
   (If you moved to DO Managed MySQL, add `--set-gtid-purged=OFF` to the original `mysqldump` config — Pitfall 10. Self-managed VPS MySQL per §2/§4 does NOT hit GTID errors.)
6. Restore files:
   ```bash
   cp -R /tmp/restore-extracted/storage/app/public/. /var/www/mess/storage/app/public/
   php artisan storage:link   # belt-and-suspenders; see Pitfall 4 / §11.9
   ```
7. **Regenerate `APP_KEY` + rotate credentials (REQUIRED — `.env` is NOT in the backup per D-07).**
   - `php artisan key:generate` — generates a new `APP_KEY`. In v1 no domain columns are encrypted via `APP_KEY` (money is plain `DECIMAL`, audit rows are plaintext JSON); the impact is that all sessions + signed cookies are invalidated, so every user will be logged out once — expected, harmless. If you suspect compromise of the prior key, this also rotates the encryption key for any future encrypted-at-rest columns.
   - Generate fresh DB passwords + any cloud-mirror keys if you suspect compromise. Update `.env` (Forge Environment UI or `nano /var/www/mess/.env`) and re-enter the Drive/R2 credentials on the Backups page (they are encrypted columns, so a new `APP_KEY` makes the old ciphertext unreadable).
   - `php artisan config:cache` (if your deploy uses it — note the pre-existing tyro-login Closure blocker logged in Plan 06-01's `deferred-items.md`; otherwise leave `config:clear`).
8. `php artisan up` — maintenance mode off.
9. Smoke-test: visit the app in a browser, log in as super-admin, walk to `/dashboard/backups` (the stats header should now reflect the post-restore state) and `/mess/audit` (the most recent writes should be the pre-disaster state).

### 11.6 Configure off-site mirrors (optional)

Local backups need **zero configuration**. To survive the loss of the VPS, add a mirror. Both providers are configured from the Backups page — credentials are stored encrypted in the database (never in `.env`, and never inside a backup archive).

1. Log in as **super-admin** → **Backups** → **Storage providers**.
2. Enable the provider for **Use for backups** (and/or **Use for uploads mirror**) and paste its credentials:
   - **Google Drive**: OAuth Client ID, Client secret, Refresh token, Folder ID.
   - **Cloudflare R2**: Access key ID, Secret access key, Region (`auto`), Bucket, S3 endpoint.
3. **Save configuration**, then click **Test connection**. It writes, reads and deletes a tiny probe file and reports the real error (auth, wrong bucket, network) instead of failing silently.
4. Click **Backup now**. The backup list then shows a per-destination tick so you can confirm the archive actually landed on the mirror.

> Secrets saved here are encrypted with `APP_KEY` at rest and are deliberately kept OUT of `.env` so they never travel inside a backup archive. Consequence: after restoring onto a fresh server you must re-enter them (the encrypted columns decrypt only with the original `APP_KEY`).

### 11.7 Enable failure notifications (REQUIRED for prod)

spatie emits `BackupHasFailed` and `UnhealthyBackupWasFound` events. The app wires these (via `AppServiceProvider::registerBackupFailureListeners()`, `class_exists`-guarded) to the `NotifyOnBackupFailure` listener, which calls `NotificationService::broadcastToManagers('backup_failed', ...)`. That fan-outs to:

- An **in-app notification** row in the manager/super-admin bell (`notifications` table) — always works, no extra config.
- An **email** (via the Email notification channel, which uses the Laravel mail driver). The default `MAIL_MAILER=log` writes the email to a log file — to actually RECEIVE the email, set:
  ```env
  MAIL_MAILER=smtp
  MAIL_HOST=<your-smtp-host>
  MAIL_PORT=587
  MAIL_SCHEME=tls
  MAIL_USERNAME=<smtp-user>
  MAIL_PASSWORD=<smtp-password>
  MAIL_FROM_ADDRESS=ops@your-domain.com
  BACKUP_NOTIFICATION_EMAIL=ops@your-domain.com
  ```
- **WhatsApp / Telegram / SMS** — if the admin enables any of these for the `backup_failed` notification type at `/mess/notifications`, the same failure also fires on those channels. Credentials for those live in the dashboard, not `.env`. (Each channel fails open — a down provider never blocks the in-app record.)

Recommended SMTP providers: AWS SES, Postmark, SendGrid, or your VPS's mail relay.

> **Do NOT ship prod with `MAIL_MAILER=log` — you will not receive failure emails.** The in-app bell still works, but it requires a human to log in and look. T-06-04-03.

### 11.8 Optional: host-level snapshot (defense-in-depth)

The Local + optional off-site mirror setup is the PRIMARY backup. As a second decoupled copy (so a provider outage does not take both the app AND the backups), enable host-level snapshots:

- **Forge**: site → Backups → enable (Forge writes a daily snapshot of the VPS disk).
- Any VPS provider (Hetzner / Linode / AWS Lightsail / DigitalOcean): enable scheduled snapshots in its control panel.

> These snapshots **include `.env`** (so they are sensitive — restrict access in the provider's control panel). They are a full-system restore primitive, NOT a per-table restore — use them only when the application restore is insufficient (e.g. filesystem corruption outside `storage/app/public/`, a boot failure, a lost `/etc` config). T-06-04-04.

This is **OPTIONAL** per the project decisions (CONTEXT.md deferred note) — Phase 6 ships "spatie + runbook", not "spatie + runbook + host snapshot". The operator can enable it independently of any code change.

### 11.9 Troubleshooting

| Symptom | Likely cause | Fix |
|---------|--------------|-----|
| `backup:run` fails with `The dump process failed` (dev only) | `mysqldump` not on PATH on Windows dev | Spatie v10 is NOT Windows-compatible (per its requirements doc). Set `DUMP_BINARY_PATH` in dev `.env` to the MySQL bin dir (e.g. `C:\Program Files\MySQL\MySQL Server 8.0\bin`). Prod Linux `/usr/bin/mysqldump` is fine. (Pitfall 2 — see `06-01-SMOKE.md` §4) |
| A cloud mirror never receives the archive | Invalid credentials, the provider toggle is off, or that mirror disk errored | Open **Backups → Storage providers → Test connection**; it reports the real error. Each backup row also shows a per-destination tick, so a missing tick means that mirror did not receive the archive. |
| `backup:monitor` reports `UnhealthyBackupWasFound` | Backup too old OR too big | Check the scheduler is running (`sudo crontab -l \| grep schedule:run`). Check `BACKUP_MAX_MB` — increase if the mess genuinely has more data. Run `php artisan backup:run` manually to refresh. The notification lands in the super-admin bell + email (§11.7). |
| Restore shows "Restore failed. App is back online" but the app looks fine | The restore service caught an exception but called `up` in `finally` | Check `storage/logs/laravel.log` for the exception. The audit-log row (`event='backup.restore.failed'`) has the error message under `/mess/audit` (filter `tags=backup`). The live DB was NOT modified if the exception fired before the DB-restore step. |
| Restore ran but files 404 on the web (broken image links) | The `public/storage` symlink was clobbered | `php artisan storage:link`. Verify with `ls -la public/storage` → points to `../storage/app/public`. (Pitfall 4 — §11.5 step 6) |
| GTID error restoring a managed-MySQL dump (`ERROR 3546`) | `mysqldump` emitted a `SET @@GLOBAL.GTID_PURGED` line (only happens when moving to a managed/hosted MySQL) | Self-managed VPS MySQL (per §2/§4) does NOT hit this. If you later move to a managed MySQL, add `--set-gtid-purged=OFF` to the dump config in `config/database.php` → `connections.mysql.dump`. (Pitfall 10) |
| Month-close completed but no immediate backup landed | Post-close `after()` hook's `Artisan::call('backup:run', ['--only-db' => true])` threw | The hook is wrapped in try/catch so the close itself succeeded (T-06-02-07). Check `storage/logs/laravel.log`. The nightly 01:30 run will still capture the close. |

---

## 12. Self-hosting with Docker panels — Dokploy & Coolify

[Dokploy](https://dokploy.com/) and [Coolify](https://coolify.io/) are free, open-source "Forge for Docker" panels you install on your own VPS. Both give you, through a web UI: build-and-deploy from a Git repo (auto-deploy on push), automatic Let's Encrypt TLS behind a built-in reverse proxy (Traefik/Caddy), log viewers, health checks, restarts, and one-click redeploys. Coolify also has a per-app **Scheduled Tasks** feature; Dokploy has a **Schedules** UI plus an **Advanced → Run Command** box for one-off `artisan` calls.

What changes vs. Forge: the app ships as a **container image**, so the repo carries a `Dockerfile` + `docker-compose.yml` instead of relying on a panel-written supervisor config. The compose file runs **four services**: `app` (nginx + PHP-FPM), `queue` (the worker), `scheduler` (Laravel's `schedule:work`), and `db` (MariaDB). Everything in §5 (the `.env` hard requirements) applies unchanged — only the process management differs.

### 12.1 Commit these three files to the repo root

The panels build from your cloned repo, so the files must be committed (not pasted). Copy them verbatim; they encode this project's specifics — PHP 8.4, the required extensions, `mariadb-client` for the spatie backup dumps (§11.1), `mysqldump`-on-`/usr/bin`, 512M `memory_limit` for the PDF/Excel exports (§10), and the exact worker command (§4.3).

**`.dockerignore`**

```
.git
.env
.claude
node_modules
vendor
public/build
public/storage
storage/app
storage/logs
storage/framework/cache
storage/framework/sessions
storage/framework/views
tests
```

**`Dockerfile`** — nginx + PHP-FPM 8.4 in one container (supervised), Vite assets built in a Node stage:

```dockerfile
# syntax=docker/dockerfile:1
FROM node:24-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

FROM php:8.4-fpm-alpine
RUN apk add --no-cache nginx mariadb-client unzip supervisor \
 && apk add --no-cache --virtual .build-deps libzip-dev icu-dev freetype-dev libjpeg-turbo-dev libpng-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql gd zip bcmath intl opcache \
 && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .
COPY --from=assets /app/public/build ./public/build
RUN composer install --no-dev --optimize-autoloader --no-interaction

RUN { \
      echo "memory_limit=512M"; \
      echo "upload_max_filesize=20M"; \
      echo "post_max_size=20M"; \
      echo "opcache.enable=1"; \
      echo "opcache.validate_timestamps=0"; \
    } > /usr/local/etc/php/conf.d/zz-app.ini

RUN mkdir -p storage/app/public storage/app/backups \
      storage/framework/cache/data storage/framework/sessions \
      storage/framework/views storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache

COPY <<'EOF' /etc/nginx/http.d/default.conf
server {
    listen 80;
    index index.php;
    root /var/www/html/public;
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
    location ~ /\.(?!well-known).* { deny all; }
}
EOF

COPY <<'EOF' /etc/supervisord.conf
[supervisord]
nodaemon=true
logfile=/dev/null
logfile_maxbytes=0
[program:php-fpm]
command=php-fpm
[program:nginx]
command=nginx -g "daemon off;"
EOF

COPY <<'EOF' /entrypoint.sh
#!/bin/sh
set -e
mkdir -p storage/app/public storage/app/backups \
         storage/framework/cache/data storage/framework/sessions \
         storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  php artisan migrate --force
  php artisan storage:link || true
  if [ "${OPTIMIZE:-true}" = "true" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
  fi
fi
exec "$@"
EOF
RUN chmod +x /entrypoint.sh

ENV RUN_MIGRATIONS=false OPTIMIZE=true
EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
```

**`docker-compose.yml`** — the four services. Only the `<<` YAML anchors are structural; every `${VAR}` is filled from the panel's environment UI (§12.2), so secrets never live in the file:

```yaml
x-app-env: &app-env
  APP_ENV: ${APP_ENV:-production}
  APP_DEBUG: ${APP_DEBUG:-false}
  APP_KEY: ${APP_KEY:?set APP_KEY in the panel environment}
  APP_URL: ${APP_URL:?set APP_URL in the panel environment}
  APP_TIMEZONE: ${APP_TIMEZONE:-Asia/Dhaka}
  DB_CONNECTION: mysql
  DB_HOST: db
  DB_PORT: "3306"
  DB_DATABASE: ${DB_DATABASE:?set DB_DATABASE in the panel environment}
  DB_USERNAME: ${DB_USERNAME:?set DB_USERNAME in the panel environment}
  DB_PASSWORD: ${DB_PASSWORD:?set DB_PASSWORD in the panel environment}
  QUEUE_CONNECTION: database
  CACHE_STORE: ${CACHE_STORE:-database}
  SESSION_DRIVER: database
  SESSION_SECURE_COOKIE: "true"
  LOG_CHANNEL: ${LOG_CHANNEL:-stack}
  MAIL_MAILER: ${MAIL_MAILER:-log}
  MAIL_HOST: ${MAIL_HOST:-}
  MAIL_PORT: ${MAIL_PORT:-587}
  MAIL_USERNAME: ${MAIL_USERNAME:-}
  MAIL_PASSWORD: ${MAIL_PASSWORD:-}
  MAIL_FROM_ADDRESS: ${MAIL_FROM_ADDRESS:-}
  DUMP_BINARY_PATH: /usr/bin
  BACKUP_MAX_MB: ${BACKUP_MAX_MB:-5000}
  BACKUP_NOTIFICATION_EMAIL: ${BACKUP_NOTIFICATION_EMAIL:-}
  BACKUP_ARCHIVE_PASSWORD: ${BACKUP_ARCHIVE_PASSWORD:-}
  BACKUP_LOG_KEEP_DAYS: ${BACKUP_LOG_KEEP_DAYS:-90}
  TELESCOPE_ENABLED: "false"
  DEBUGBAR_ENABLED: "false"

x-app-base: &app-base
  build: .
  restart: unless-stopped
  depends_on:
    db:
      condition: service_healthy
  volumes:
    - app-storage:/var/www/html/storage/app
  environment:
    <<: *app-env
    RUN_MIGRATIONS: "false"

services:
  app:
    <<: *app-base
    environment:
      <<: *app-env
      RUN_MIGRATIONS: "true"   # only the web container migrates (additive-only, safe on redeploy)
    healthcheck:
      test: ["CMD-SHELL", "wget -q -O /dev/null http://127.0.0.1/up || exit 1"]
      interval: 30s
      timeout: 5s
      retries: 5
      start_period: 40s

  queue:
    <<: *app-base
    command: ["php", "artisan", "queue:work", "database", "--sleep=3", "--tries=3", "--max-time=3600"]

  scheduler:
    <<: *app-base
    command: ["php", "artisan", "schedule:work"]   # fires schedule:run every minute — no cron needed in a container

  db:
    image: mariadb:11.4
    restart: unless-stopped
    environment:
      MARIADB_DATABASE: ${DB_DATABASE}
      MARIADB_USER: ${DB_USERNAME}
      MARIADB_PASSWORD: ${DB_PASSWORD}
      MARIADB_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD:?set MYSQL_ROOT_PASSWORD in the panel environment}
    volumes:
      - db-data:/var/lib/mysql
    healthcheck:
      test: ["CMD", "healthcheck.sh", "--connect", "--innodb_initialized"]
      interval: 10s
      timeout: 5s
      retries: 10

volumes:
  app-storage:
  db-data:
```

Why MariaDB instead of `mysql:8.4`: the app image's `mysqldump`/`mysql` binaries come from `mariadb-client`, which talks to a MariaDB server without the `caching_sha2_password` auth wrinkle MySQL 8 introduces (and MySQL 8.4 disables `mysql_native_password` by default). MariaDB is a drop-in for this app — Laravel's `mysql` driver and spatie's dump/restore both work unchanged. (If you insist on `mysql:8.4`, swap the image and add `--set-gtid-purged=OFF` to the dump config per §11.9 Pitfall 10.)

No `ports:` are published — the panel's reverse proxy reaches `app:80` over the internal Docker network.

### 12.2 Environment variables to set in the panel UI

Set these once in the panel's Environment tab (Dokploy: service → **Environment**; Coolify: resource → **Environment Variables**). They are interpolated into the compose file — nothing else to configure.

| Key | Value | Notes |
|-----|-------|-------|
| `APP_KEY` | base64 key from `php artisan key:generate --show` (run locally) | **Required.** Never reuse the dev key. |
| `APP_URL` | `https://mess.your-domain.com` | **Required.** HTTPS — invites, exports, signed routes. |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | e.g. `mess` / `mess` / strong password | **Required.** The same three values create the MariaDB database/user (compose maps them to `MARIADB_*`). |
| `MYSQL_ROOT_PASSWORD` | another strong password | **Required.** Root on the `db` container only. |
| `APP_DEBUG` | `false` (default) | §5 gate. |
| `MAIL_MAILER` / `MAIL_HOST` / `MAIL_PORT` / `MAIL_USERNAME` / `MAIL_PASSWORD` / `MAIL_FROM_ADDRESS` | your SMTP relay | §11.7 — needed for invites + backup-failure email. `log` (default) swallows mail. |
| `BACKUP_*` | per §5 | **Strongly recommended** (`BACKUP_MAX_MB`, `BACKUP_NOTIFICATION_EMAIL`, `BACKUP_ARCHIVE_PASSWORD`) — local backup zips live in the `app-storage` volume, which dies with the VPS. Add an off-site mirror from the Backups page (§11.6). |
| `LOG_CHANNEL` | `stack` (default) or `stderr` | `stderr` streams Laravel logs into the panel's log viewer (else read `storage/logs/laravel.log` via a shell). |

### 12.3 Deploy on Dokploy

Reference: [docs.dokploy.com](https://docs.dokploy.com/).

1. **Install Dokploy** on a fresh VPS (≥ 2 GB RAM — you're hosting the panel *and* the app). Follow the docs' one-line install script, then open the panel on port 3000 and create the admin account.
2. **Create a Project** (e.g. `mess`) → **Create Service** → **Docker Compose**.
3. **Source**: pick your Git provider (GitHub/GitLab) → the repo → branch `master` → compose path `./docker-compose.yml`. Keep the compose type as **Docker Compose** (default) — Dokploy runs `docker compose up -d --build` in this mode, which builds the Dockerfile. (The "Swarm" compose type ignores `build:` — don't use it here.)
4. **Environment** tab: add the §12.2 variables.
5. **Deploy**. First build runs `npm ci && npm run build` + `composer install` — expect a few minutes. The entrypoint runs `migrate --force` + caches on the `app` container start.
6. **Domain** tab: add your domain (service `app`, port 80), enable **Let's Encrypt** and HTTPS redirect. Panel-managed TLS — no certbot.
7. **First run**: visit `https://your-domain` → the one-time **`/setup` wizard** creates the super-admin (§8 step 2). CLI alternative: Dokploy's **Advanced → Run Command** (shell into the `app` container) → `php artisan mess:create-super-admin admin@yourdomain.com "Admin Name" --password=...`.
8. **Verify** with the §8 checklist. Worker/scheduler health = the `queue`/`scheduler` services showing running in the panel; `queue:failed` via Run Command.
9. **Updates**: enable auto-deploy (Git watch/webhook) or hit **Deploy** — every deploy rebuilds the image and recreates all four containers, which is the container-native equivalent of Forge's restart-worker-on-deploy. Rollback = redeploy an older commit from the deployments list.

Optional extras: Dokploy's **Schedules** UI (service → Schedules) can run `php artisan schedule:run` on a `* * * * *` cron as an alternative to the `scheduler` service; Dokploy's built-in **database backup** on the `db` service (drop to S3) is a fine second copy alongside the app's spatie pipeline (§11).

### 12.4 Deploy on Coolify

Reference: [Coolify Laravel docs](https://coolify.io/docs/applications/framework-examples/php/laravel).

1. **Install Coolify** on a fresh VPS (≥ 2 GB RAM) per [coolify.io/docs](https://coolify.io/docs) install script; create the admin account.
2. Create a **Project** → **New Resource** → **Docker Compose** → source = your Git repo + branch `master`. Coolify detects `docker-compose.yml` at the root and loads the four services.
3. **Environment Variables**: add the §12.2 variables.
4. **Deploy** (first build takes a few minutes). The `app` service reports healthy via the compose healthcheck (`/up` — §6 step 1).
5. **Domains**: in the resource's service settings, point your domain at service `app`, port `80`. Coolify terminates TLS with Let's Encrypt via its proxy automatically.
6. **First run**: visit `https://your-domain` → the `/setup` wizard (§8 step 2). For artisan one-offs use the resource's **Terminal** tab.
7. **Scheduler** — already covered by the `scheduler` service (`schedule:work`). Alternative: delete that service and use Coolify's **Scheduled Tasks** (resource → Scheduled Tasks) with command `php artisan schedule:run` and cron `* * * * *`.
8. **Verify** with the §8 checklist (worker = `queue` service running; `php artisan queue:failed` in the Terminal).
9. **Updates**: enable **Auto Deploy** (webhook on push) or hit **Deploy** — containers are recreated, so the worker always restarts onto fresh code. **Rollback**: Deployments list → Redeploy a previous build.

### 12.5 Panel-specific gotchas

| Symptom / risk | Cause | Fix |
|----------------|-------|-----|
| `config:cache` fails at container start (tyro-login Closure blocker, §11.5 step 7) | A config Closure can't be serialized to the cache | Set `OPTIMIZE=false` in the panel environment — the entrypoint then skips the cache commands (§12.1). |
| `app` scaled to >1 replica → migration errors | Two containers run `migrate --force` concurrently | Keep `app` at **1** replica (Dokploy: Advanced → Cluster). One mess needs one. |
| Panel log viewer is empty | Laravel logs to `storage/logs/laravel.log` (a file), not stdout | Set `LOG_CHANNEL=stderr` to stream into the viewer, or read the file via the panel's terminal. |
| Local backups "disappear" after a redeploy | They live in the `app-storage` volume — volumes persist across redeploys but not across VPS loss | Add an off-site mirror (Google Drive / Cloudflare R2) from the Backups page (§11.6). Optionally add the panel's own DB-backup-to-S3 as a second copy. |
| 500 on PDF/Excel export | Container memory cap | The image sets `memory_limit=512M`; if you added a Dokploy/Coolify **resource limit** below ~512 MB, raise it. |
| Month-close never completes | The `queue` service is stopped | Panel → services → restart `queue`; check its logs for `CloseMonthJob` exceptions. |

---

## 13. cPanel (shared hosting)

The full step-by-step cPanel walkthrough lives in [SETUP-USAGE-DEPLOY.md §5.3](./SETUP-USAGE-DEPLOY.md) (upload, docroot → `/public`, MySQL databases, `.env`, migrate, storage symlink, cron). Summary + the hard rules:

- **`QUEUE_CONNECTION=sync`** — no persistent worker exists on shared hosting; `CloseMonthJob` runs inline in the HTTP request (a few seconds for one small mess). No supervisor, no retries.
- **Per-minute scheduler cron** (cPanel → Cron Jobs): `* * * * * cd /home/USER/mess && /usr/bin/php artisan schedule:run >> /dev/null 2>&1` — this drives backups, `backup:monitor`, the restore-test, and `telescope:prune`. Without it the backup cadence configured in the Backups UI never fires.
- **PHP 8.3+ with `pdo_mysql, gd, zip, mbstring, curl, bcmath, intl`** via MultiPHP Manager / Select PHP Version. Build assets locally and upload `public/build/` — shared hosts have no Node.
- **Create a second empty database** for the nightly restore-test, and expect `mysqldump` to already exist (set `DUMP_BINARY_PATH` if `backup:run` fails).
- All §5 `.env` gates (`APP_DEBUG=false`, fresh DB creds, `APP_TIMEZONE=Asia/Dhaka`, SMTP) apply identically.
- Trade-offs recap: month-close blocks the browser; no async retries; CPU/`max_execution_time` caps are the host's, not yours. For anything beyond a single small mess, move to a VPS path (§1).

---

*Last updated: 2026-09-14. §12 (Dokploy / Coolify) is new — commit its three files to the repo root and the panels build everything else themselves. The supervisor block in §4.3 + the schedule cron in §4.4 remain the verbatim artifacts Forge would write — copy them as-is for manual setups. §11 (Backup & restore runbook) documents the Phase 6 backup-and-restore system (Plans 06-01 → 06-04) — read it before you need it.*
