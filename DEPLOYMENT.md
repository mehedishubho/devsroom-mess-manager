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

### Phase 6 — Backup & restore keys (REQUIRED once Phase 6 ships)

These 11 keys wire the spatie/laravel-backup engine (Plan 06-01) and the bespoke restore services (Plans 06-02 / 06-03). Leave them empty ONLY if you are deliberately not running Phase 6 backups; spatie's scheduler entries are `class_exists`-guarded so an empty config degrades to "no backups" rather than crashing.

| Key | Value | Why |
|-----|-------|-----|
| `DO_SPACES_KEY` | (Spaces access key from DO control panel) | Phase 6 backup destination authentication. (D-02) |
| `DO_SPACES_SECRET` | (Spaces secret from DO control panel) | Same. |
| `DO_SPACES_REGION` | (matches endpoint subdomain, e.g. `nyc3`) | DO Spaces validates region against the endpoint; mismatch = signature error. (Pitfall 5) |
| `DO_SPACES_BUCKET` | (e.g. `devsroom-mess-backups`) | The bucket you created in DO. |
| `DO_SPACES_ENDPOINT` | `https://<region>.digitaloceanspaces.com` | The S3-compatible API endpoint. |
| `BACKUP_DISK` | `backups` | The filesystem disk (declared in `config/filesystems.php`) that `backup:run` writes to. |
| `BACKUP_MAX_MB` | `5000` (starting point) | Size cap; `backup:monitor` flags if exceeded. Tunable. |
| `BACKUP_NOTIFICATION_EMAIL` | `ops@your-domain.com` | Where spatie sends failure emails. Requires `MAIL_MAILER=smtp` (see §11.7). |
| `BACKUP_ARCHIVE_PASSWORD` | (a strong password) | Optional AES-256 zip encryption (spatie v10). Leave empty to disable client-side encryption; DO Spaces provides server-side encryption at rest as the base layer. |
| `DB_RESTORE_TEST_DATABASE` | `devsroom_mess_restore_test` | The scratch DB the restore-test job loads + wipes. Created on the same MySQL host if missing. |
| `DUMP_BINARY_PATH` | `/usr/bin` (prod) — unset defaults to `/usr/bin` | Directory containing `mysqldump`. Prod Linux `/usr/bin/mysqldump` is correct. (Pitfall 2) |

Provisioning walkthrough: see §11.6 below.

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

- Off-server in a **DigitalOcean Spaces bucket** (S3-compatible) via the configured `backups` s3 disk. Region must match the endpoint subdomain (e.g. `nyc3` + `https://nyc3.digitaloceanspaces.com`). Provisioning: see §11.6. The env keys are listed in §5 above.
- **Retention ladder** (D-02): `keep_all=7d`, `keep_daily=14d`, `keep_weekly=8w`, `keep_monthly=12mo`, `keep_yearly=2y`, plus a 5000 MB growth guard. The long monthly retention exists because `monthly_closings` snapshots are immutable financial records — a corruption discovered months later must still be recoverable.
- **There is NO local copy.** `spatie/laravel-backup` writes the zip directly to object storage. `storage/app/laravel-backup` and `storage/app/backup-temp` are transient working areas only.

### 11.3 Schedule

The backup schedule lives in `routes/console.php` and runs on the existing Laravel scheduler (which fires every minute per §4.3/§4.4). The nightly cadence:

| Time | Command | Purpose |
|------|---------|---------|
| 01:00 | `backup:clean` | Prune old backups per the retention ladder (§11.2). |
| 01:30 | `backup:run` | The actual DB dump + files zip + upload. `withoutOverlapping()->onOneServer()`. |
| 02:00 | `backup:monitor` | Health-check the latest backup (not too old, not too big). Emits `UnhealthyBackupWasFound` on failure → in-app bell + email (§11.7). |
| 03:00 | `backup:restore-test` | Loads the latest dump into the scratch DB + asserts per-table COUNT(*) parity. Result surfaced as a health badge in the super-admin Backups UI. `withoutOverlapping()->onOneServer()`. |

All four commands are `class_exists`-guarded so an unconfigured Phase 6 degrades to "no backups" rather than crashing the scheduler.

**On-demand** (in addition to the schedule):

- **Via the UI**: super-admin → `/dashboard/backups` → "Backup now" button.
- **Via the CLI** on the VPS: `php artisan backup:run`.
- **Via the CLI**, DB-only (faster): `php artisan backup:run --only-db`.

**Post-close hook (D-05)**: a successful `CloseMonthJob` calls `Artisan::call('backup:run', ['--only-db' => true])` from its `after()` lifecycle hook. The close produces the highest-value immutable data of the month (`monthly_closings` + `monthly_member_summaries`); capturing it now beats waiting up to 24h for the nightly run. The hook is wrapped in try/catch so a backup failure can NEVER break the close path (T-06-02-07). The `failed()` hook is an explicit no-op — a half-closed state is never backed up.

### 11.4 Restore procedure (PRIMARY path — via the super-admin UI)

Use this path when the app itself is healthy but you need to roll the data back (e.g. a bad migration was applied + reverted, a manager corrupted `monthly_closings` and you need to restore yesterday's snapshot). If the app itself is unreachable, skip to §11.5.

1. Log in to **`/dashboard/backups`** as a super-admin. This is the only role with access (T-06-03-01 — `role:super-admin` middleware on every route in the group; admins and users get 403).
2. Review the **restore-test health badge** at the top of the page. It reads the latest `restore_tests` row. Do NOT proceed with a restore if the badge is `failed` or `error` — fix the test first (§11.9), because a restore from a known-bad backup wastes the maintenance window.
3. Find the backup zip you want to restore in the list. Click **Download** first if you want an offline copy (this writes an `event='backup.download'` audit row — T-06-03-05, every download is tamper-evident). Then click **Restore** on the chosen zip.
4. The restore form (`resources/views/dashboard/backups/restore.blade.php`) renders a prominent red destructive warning + asks you to type the **active mess's name EXACTLY**. The expected value is `Mess::find(Mess::activeId())->name` — the typed-confirm second factor (D-03, Open Question #3 LOCKED). The restore POST is throttled at `5,1` (5 attempts/minute per IP — T-06-03-04).
5. Type the mess name. Submit. `RestoreRequest` validates `mess_name in:<active mess name>`; a wrong value redirects back with a validation error and NO service call is made (T-06-03-02). If no active mess exists (pre-onboarding), the validator degrades to an unmatchable sentinel so a restore can NEVER proceed.
6. The `BackupRestoreService` flips the app into **maintenance mode** (web requests now hit `errors/maintenance-backup-restore.blade.php`; `queue:restart` is called so no `CloseMonthJob` runs mid-restore — T-06-02-01), then: **downloads** the zip from DO Spaces → **extracts** it → **locates** the dump at `db-dumps/<dbname>.sql` via the Finder-based `BackupPathResolver` (handles both flat and nested layouts — Pitfall 1) → **restores the DB** via `mysql` CLI (Symfony Process with array args, never string-concat — Pattern 4a) → **copies files** back into `storage/app/public/` (NEVER `public/storage` — Pitfall 4, T-06-02-03) → **spot-checks** row counts. The `up` call is in a `finally {}` so the app ALWAYS returns to live even if an exception is thrown mid-restore.
7. On success, `RestoreController::store()` writes a manual `Audit` row (`event='backup.restore'`) with the path + the restore tag (T-06-03-07), and redirects back to `dashboard.backups.index` with a success flash.
8. On failure, the controller's try/catch writes `event='backup.restore.failed'` with the exception message and the exception NEVER escapes (T-06-03-07). The user sees "Restore failed. App is back online" — the live DB was NOT modified if the exception fired before the DB-restore step.

Confirm every restore under **`/mess/audit`** (filter `tags=backup`).

### 11.5 Restore procedure (FALLBACK — via CLI, when the UI is unreachable)

If the app itself is down (white screen, fatal error before Laravel boots, bad migration that broke the schema the UI depends on, lost VPS), restore via SSH on the VPS. This path requires shell access — keep your Forge SSH key or VPS credentials somewhere OUTSIDE the VPS (the runbook in your password manager is a good place).

1. SSH into the VPS. `cd /var/www/mess` (manual setup) or `cd /home/forge/your-domain.com` (Forge).
2. `php artisan down` — maintenance mode on.
3. Download the latest backup zip from DO Spaces. From inside the Laravel app:
   ```bash
   php artisan tinker
   >>> $disk = Storage::disk(config('backup.backup.destination.disks.0', 'backups'));
   >>> $latest = collect($disk->allFiles())->sortDesc()->first();
   >>> $disk->download($latest)->send();
   // Save the streamed body to /tmp/restore.zip (or use s3cmd / aws-cli / rclone outside Laravel
   // if Laravel itself can't boot — the bucket/keys are in .env).
   ```
   If Laravel cannot boot at all, use `s3cmd` / `aws s3 cp` / `rclone` directly against DO Spaces with the keys from a known-good `.env` copy.
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
   - Generate fresh DB passwords + DO Spaces keys if you suspect compromise. Update `.env` (Forge Environment UI or `nano /var/www/mess/.env`).
   - `php artisan config:cache` (if your deploy uses it — note the pre-existing tyro-login Closure blocker logged in Plan 06-01's `deferred-items.md`; otherwise leave `config:clear`).
8. `php artisan up` — maintenance mode off.
9. Smoke-test: visit the app in a browser, log in as super-admin, walk to `/dashboard/backups` (badge should now reflect the post-restore state) and `/mess/audit` (the most recent writes should be the pre-disaster state). Run `php artisan backup:restore-test` once to confirm the restored data passes the parity check.

### 11.6 Configure DO Spaces (one-time setup)

Follow these steps once, at first deploy:

1. **Create the Space**: DigitalOcean control panel → Spaces → Create Space. Suggested name: `devsroom-mess-backups`. Pick a region close to your VPS (e.g. `nyc3` if your VPS is in NYC).
2. **Generate Spaces access keys**: Spaces → Settings → Spaces Keys → Generate new key. Copy the **Key** and the **Secret** (the Secret is shown ONCE — record it somewhere durable, e.g. your password manager).
3. **Set the prod `.env` keys** (via Forge's Environment UI or `nano /var/www/mess/.env`):
   ```env
   DO_SPACES_KEY=<access-key>
   DO_SPACES_SECRET=<secret>
   DO_SPACES_REGION=nyc3                                              # MUST match the endpoint subdomain
   DO_SPACES_BUCKET=devsroom-mess-backups
   DO_SPACES_ENDPOINT=https://nyc3.digitaloceanspaces.com
   BACKUP_DISK=backups
   BACKUP_MAX_MB=5000
   BACKUP_NOTIFICATION_EMAIL=ops@your-domain.com
   ```
4. `php artisan config:cache` (or `config:clear` per the pre-existing tyro-login note).
5. **Trigger the first backup**:
   ```bash
   php artisan backup:run --only-db     # DB-only smoke (faster — proves mysqldump + s3 upload)
   php artisan backup:run               # full backup (DB + storage/app/public)
   php artisan backup:list              # confirm the zip landed
   php artisan backup:monitor           # confirm the health checks pass
   ```
   A successful first `backup:run` is the green light that Plan 06-01's plumbing works end-to-end.
6. **Verify the restore-test works on the prod VPS**: `php artisan backup:restore-test`. The scratch DB (`devsroom_mess_restore_test` per `DB_RESTORE_TEST_DATABASE`) is created automatically on the same MySQL host; the test wipes it at the start of every run.
7. **Verify region/endpoint match (Pitfall 5)**. `DO_SPACES_REGION` MUST match the subdomain of `DO_SPACES_ENDPOINT`:
   - `nyc3` + `https://nyc3.digitaloceanspaces.com` ✓
   - `sfo3` + `https://sfo3.digitaloceanspaces.com` ✓
   - `nyc3` + `https://sfo3.digitaloceanspaces.com` ✗ → "authorization signature invalid" errors.

### 11.7 Enable failure notifications (REQUIRED for prod)

spatie emits `BackupHasFailed` and `UnhealthyBackupWasFound` events. The app wires these (via `AppServiceProvider::registerBackupFailureListeners()`, `class_exists`-guarded) to the `NotifyOnBackupFailure` listener, which calls `NotificationService::broadcastToManagers('backup_failed', ...)`. That fan-outs to:

- An **in-app notification** row in the manager/super-admin bell (`notifications` table) — always works, no extra config.
- An **email** via spatie's mail channel. The default `MAIL_MAILER=log` writes the email to a log file — to actually RECEIVE the email, set:
  ```env
  MAIL_MAILER=smtp
  MAIL_HOST=<your-smtp-host>
  MAIL_PORT=587
  MAIL_USERNAME=<smtp-user>
  MAIL_PASSWORD=<smtp-password>
  MAIL_FROM_ADDRESS=ops@your-domain.com
  BACKUP_NOTIFICATION_EMAIL=ops@your-domain.com
  ```

Recommended SMTP providers: AWS SES, Postmark, SendGrid, or your VPS's mail relay.

> **Do NOT ship prod with `MAIL_MAILER=log` — you will not receive failure emails.** The in-app bell still works, but it requires a human to log in and look. T-06-04-03.

### 11.8 Optional: host-level snapshot (defense-in-depth)

The spatie + DO Spaces setup is the PRIMARY backup. As a second decoupled copy (so a DO-region outage does not take both the app AND the backups), enable host-level snapshots:

- **Forge**: site → Backups → enable (Forge writes a daily snapshot of the VPS disk).
- **DigitalOcean**: Droplet → Snapshots → enable scheduled snapshots (daily or weekly).

> These snapshots **include `.env`** (so they are sensitive — restrict access in the DO/Forge control panel). They are a full-system restore primitive, NOT a per-table restore — use them only when the spatie restore is insufficient (e.g. filesystem corruption outside `storage/app/public/`, a boot failure, a lost `/etc` config). T-06-04-04.

This is **OPTIONAL** per the project decisions (CONTEXT.md deferred note) — Phase 6 ships "spatie + runbook", not "spatie + runbook + host snapshot". The operator can enable it independently of any code change.

### 11.9 Troubleshooting

| Symptom | Likely cause | Fix |
|---------|--------------|-----|
| `backup:run` fails with `The dump process failed` (dev only) | `mysqldump` not on PATH on Windows dev | Spatie v10 is NOT Windows-compatible (per its requirements doc). Set `DUMP_BINARY_PATH` in dev `.env` to the MySQL bin dir (e.g. `C:\Program Files\MySQL\MySQL Server 8.0\bin`). Prod Linux `/usr/bin/mysqldump` is fine. (Pitfall 2 — see `06-01-SMOKE.md` §4) |
| `backup:run` fails with "authorization signature invalid" | DO Spaces region/endpoint mismatch | Confirm `DO_SPACES_REGION` matches the endpoint subdomain (e.g. `nyc3` + `https://nyc3.digitaloceanspaces.com`). (Pitfall 5 — §11.6 step 7) |
| `backup:monitor` reports `UnhealthyBackupWasFound` | Backup too old OR too big | Check the scheduler is running (`sudo crontab -l \| grep schedule:run`). Check `BACKUP_MAX_MB` — increase if the mess genuinely has more data. Run `php artisan backup:run` manually to refresh. The notification lands in the super-admin bell + email (§11.7). |
| restore-test reports FAILED but the backup looks fine | Scratch DB (`devsroom_mess_restore_test`) is stale or contaminated | The test wipes it every run. If still failing, manually `DROP DATABASE devsroom_mess_restore_test; CREATE DATABASE devsroom_mess_restore_test;` and re-run `php artisan backup:restore-test`. Also check the `restore_tests` row's `message` column for the per-table divergence detail. |
| Restore shows "Restore failed. App is back online" but the app looks fine | The restore service caught an exception but called `up` in `finally` | Check `storage/logs/laravel.log` for the exception. The audit-log row (`event='backup.restore.failed'`) has the error message under `/mess/audit` (filter `tags=backup`). The live DB was NOT modified if the exception fired before the DB-restore step. |
| Restore ran but files 404 on the web (broken image links) | The `public/storage` symlink was clobbered | `php artisan storage:link`. Verify with `ls -la public/storage` → points to `../storage/app/public`. (Pitfall 4 — §11.5 step 6) |
| GTID error restoring a DO-managed MySQL dump (`ERROR 3546`) | (only if you moved to DO Managed DB) `mysqldump` emitted a `SET @@GLOBAL.GTID_PURGED` line | Self-managed VPS MySQL (per §2/§4) does NOT hit this. If you later move to DO Managed MySQL, add `--set-gtid-purged=OFF` to the dump config in `config/database.php` → `connections.mysql.dump`. (Pitfall 10) |
| Month-close completed but no immediate backup landed | Post-close `after()` hook's `Artisan::call('backup:run', ['--only-db' => true])` threw | The hook is wrapped in try/catch so the close itself succeeded (T-06-02-07). Check `storage/logs/laravel.log`. The nightly 01:30 run will still capture the close. |

---

*Last updated: 2026-06-19 (Plan 06-04). The supervisor block in §4.3 + the schedule cron in §4.4 are the verbatim artifacts Forge would write for you — copy them as-is if doing manual setup. §11 (Backup & restore runbook) documents the Phase 6 backup-and-restore system (Plans 06-01 → 06-04) — read it before you need it.*
