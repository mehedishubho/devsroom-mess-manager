# Setup · Roles & Usage · Deployment Guide

Everything you need to **install**, **use** (as admin / super-admin / manager / user), and **deploy** the Devsroom Mess Management app — on a **VPS** or on **shared hosting (cPanel / Plesk / DirectAdmin)**.

> **Companion docs:** [README.md](./README.md) has the feature list + local-dev detail; [DEPLOYMENT.md](./DEPLOYMENT.md) is the deep VPS/Forge hardening runbook. This guide consolidates setup + daily usage by role, and adds a **shared-hosting path** (the others assume a VPS).

---

## 1. Roles at a glance

The app uses the **Tyro** role system. Four roles:

| Role | Slug | What they do | Where they land after login |
|------|------|--------------|------------------------------|
| **Super-admin** | `super-admin` | Everything an admin/manager can do **+ backups/restore + onboarding**. The installation owner. | `/dashboard/backups` (or `/home`) |
| **Admin** | `admin` | Full daily mess management (members, meals, expenses, payments, reports, month-close). | `/home` (manager dashboard) |
| **Manager** | `manager` | Same mess operations as admin (intended for delegated day-to-day managers). | `/home` (manager dashboard) |
| **User (Member)** | `user` | Their own portal: bills, meals, payments, meal-off requests, own reports. | `/my` (member dashboard) |

> Admin, super-admin, and manager share the **same mess menu** (Home → Due reminders). Super-admin additionally sees **Backups**. The enforcement is role-based via the `roles:admin,super-admin,manager` route middleware; the `mess.*` privilege records (documented in the `create_manager_role_and_mess_privileges` migration) are attached to all three.

---

## 2. Local setup (quickstart)

```bash
git clone <repo-url> devsroom-mess-management
cd devsroom-mess-management

# PHP deps (Laravel 13, PHP 8.3+)
composer install

# Frontend (Tailwind v4 + Chart.js via Vite)
npm install
npm run build          # use `npm run dev` for HMR while developing

# Environment
cp .env.example .env
php artisan key:generate

# Edit .env — MySQL block (MySQL only; do NOT use sqlite):
#   DB_DATABASE=devsroom_mess_management
#   DB_USERNAME=root  DB_PASSWORD=<yours>
# Keep APP_TIMEZONE=Asia/Dhaka (default).

php artisan migrate         # creates all tables (additive, never wipes data)
php artisan storage:link    # symlink public/storage -> storage/app/public
```

Run all servers at once (app + queue + log tail + Vite):

```bash
composer run dev            # http://localhost:8000
```

> **Do not run** `php artisan migrate:fresh` or `db:seed` against a live database — they destroy hand-created accounts. See `composer run dev` for the safe local loop.

---

## 3. First-run: create users & assign roles

### 3.1 Create the first super-admin (the install owner)

```bash
php artisan mess:create-super-admin owner@example.com "Owner Name" --password=secret
```

Log in at `/login` with that email/password. You'll land on the backups/dashboard.

### 3.2 Assign roles to existing users

```bash
php artisan mess:assign-role manager@mess.com manager       # make a manager
php artisan mess:assign-role admin@mess.com admin           # make an admin
php artisan mess:assign-role member@mess.com user           # make a member
```

`--sync` replaces the user's roles with only the given one; without it the role is added.

### 3.3 Invite members (the everyday flow)

As admin/super-admin/manager: **Members → Add member (invite)** → enter the member's email. They get an email with a set-password link and join as the `user` role. No CLI needed for day-to-day onboarding.

> Tyro also exposes a built-in admin UI at `/dashboard` (users / roles / privileges / settings / audit) for super-admins who prefer a GUI over the CLI commands above.

---

## 4. Using the app — by role

### 4.1 Super-admin (`super-admin`)
Everything in §4.2 **plus**:
- **Backups** (`/dashboard/backups`): see the **Configuration card** (Local destination + DigitalOcean Spaces mirror status, schedule, retention), **Configure** the schedule (off/daily/weekly/monthly + time) and retention (keep-days + storage cap), run **Backup now** / **Restore-test**, and **Download / Restore / Delete** individual backups.
- **Onboarding** (only when no mess exists yet — creating the first mess).

### 4.2 Admin & Manager (`admin`, `manager`)
The full mess-management menu (left sidebar):

| Menu item | What you do there |
|-----------|-------------------|
| **Home** | Dashboard: 6 stat cards + 3 charts + pending-meal-off alert. |
| **Mess settings** | Edit mess name, address, rent, meal values, currency, manager contact. |
| **Audit log** | Append-only history of every write (filter by model/user/date). |
| **Members** | CRUD members + invite; view profile, recent meals, request meal-off on their behalf, deactivate. |
| **Daily meals** | The meal grid (rows=members, cols=B/L/D). Mark presets, bulk save. Date nav. |
| **Guest meals** | Record guest meals charged to a host member. |
| **Meal off approval** | Approve/reject member meal-off requests (rejection needs a reason). |
| **Expenses** | Add **bazar** (amber) and **fixed** (sky) expenses; list with filters. |
| **Categories** | Manage bazar/fixed/other expense categories (defaults locked). |
| **Payments** | Record bill payments & advance deposits (Cash/bKash/Nagad/Rocket/Bank). |
| **Advance balances** | Adjust a member's advance/due with a reason. |
| **Bill preview** | Live "if we closed today" calculation for the selected month. |
| **Reports → Monthly / Member Statement / Expense / Payment** | 4 reports, each with PDF + Excel export + date/category/member/method filters. |
| **Close month** | Snapshot + lock the month (idempotent; runs as a queued job). |
| **Closings** | View closed months + post **corrections** (snapshot stays immutable). |
| **Due reminders** | Send in-app reminders to members who owe money. |

**Month-close flow:** once a month, review the **Bill preview**, then **Close month**. This writes an immutable snapshot, locks writes for that month (the 11 write routes are guarded), and lets you post corrections afterward if needed. Members keep viewing their finalized bill on `/my`.

### 4.3 Member (`user`)
The `/my` portal (tabs):
- **Overview** — My Meals, My Bill, My Advance, My Payment History cards.
- **Profile** — update photo + emergency contact.
- **My meals** — read-only recent meals.
- **Meal off** — request meal-off for a date range + reason; see approval status.
- **Payments** — own payment history.
- **My reports** — own Member Statement (PDF/Excel) + the mess Monthly Report (aggregates only).

Members can **never** see another member's data — there's no `{member}` URL param on member routes (IDOR-structurally-impossible).

---

## 5. Deployment

### 5.1 Production `.env` checklist (both VPS & shared)

| Key | Value | Why |
|-----|-------|-----|
| `APP_ENV` | `production` | |
| `APP_DEBUG` | `false` | **Hard requirement** — never expose stack traces. |
| `APP_URL` | `https://yourdomain.com` | Used for emails, signed invite links, exports. |
| `APP_TIMEZONE` | `Asia/Dhaka` | Keep consistent. |
| `DB_*` | prod MySQL creds | MySQL only. |
| `DB_RESTORE_TEST_DATABASE` | `<db>_restore_test` | Create a 2nd empty MySQL DB for the nightly restore-test. |
| `MAIL_MAILER` | `smtp` (+ host/user/pass/from) | Needed for invite emails + backup-failure alerts (`log` only catches mail in dev). |
| `DO_SPACES_*` | (optional) | Set `KEY`/`SECRET`/`BUCKET` to mirror backups off-server. Leave blank for local-only backups. |
| `BACKUP_NOTIFICATION_EMAIL` | `you@…` | Where spatie sends backup failure emails. |
| `TELESCOPE_ENABLED` / `DEBUGBAR_ENABLED` | `false` | Dev tooling — must be off in prod. |

Always finish with:
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

---

### 5.2 Deploy on a VPS (recommended)

The app's month-close runs as a queued job, so a **persistent queue worker** + a per-minute **scheduler** cron are required. This is why a VPS (or Forge) is the primary target.

**Short version** (full detail in [DEPLOYMENT.md](./DEPLOYMENT.md)):

```bash
# On the server
git clone <repo> /var/www/mess && cd /var/www/mess
composer install --no-dev --optimize-autoloader
npm ci && npm run build        # or build locally and commit public/build
cp .env.example .env           # then edit per §5.1
php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Then three infrastructure pieces:

1. **Nginx** vhost with document root = `/var/www/mess/public` (point `fastcgi` to PHP-FPM). See DEPLOYMENT.md §4.2.
2. **Supervisor** keeps the queue worker alive:
   ```ini
   [program:mess-queue]
   command=php /var/www/mess/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
   autostart=true autorestart=true user=www-data
   ```
   (DEPLOYMENT.md §4.3 — verbatim.)
3. **Cron** for the scheduler (runs backups/purge/monitor/restore-test/telescope:prune on schedule):
   ```
   * * * * * cd /var/www/mess && php artisan schedule:run >> /dev/null 2>&1
   ```

**HTTPS:** a TLS cert (Let's Encrypt / Forge) is required — invite links and exports assume `https://`.

---

### 5.3 Deploy on shared hosting (cPanel / Plesk / DirectAdmin)

The official README rules shared hosting out because of the queue-worker requirement. **You can still run it** by making jobs run **inline** (`QUEUE_CONNECTION=sync`) instead of via a background worker. Trade-off: the month-close and backup notifications execute synchronously inside the request that triggers them (a few seconds of wait during **Close month** — fine for one small mess). No `supervisor`, no persistent process.

> ⚠️ Shared hosting is acceptable for a **single small mess**. If you expect many members or background-heavy work, use a VPS (§5.2).

#### A. Prepare the build locally (shared hosts rarely have Node/Composer over SSH)

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build          # produces public/build/
php artisan key:generate         # sets APP_KEY in .env (copy it into the uploaded .env)
```

Pack the whole project (including `vendor/` and `public/build/`, excluding `node_modules/`) into a zip for upload.

#### B. Upload + place files

cPanel **File Manager** or FTP:

- Upload the project zip to your home dir (e.g. `/home/username/`) and extract, **not** inside `public_html`.
- Result: `/home/username/mess/` containing `artisan`, `vendor/`, `public/`, `storage/`, etc.

#### C. Point the web root to `/public`

The cleanest approach (works on most modern cPanels for addon domains / subdomains):

- **cPanel → Domains** (or "Addon Domains" / "Subdomains") → set the domain's **Document Root** to `/home/username/mess/public`.

If your host forces the docroot to `public_html` and won't let you change it, use the fallback:

- Copy everything in `mess/public/*` into `/home/username/public_html/`.
- Edit `public_html/index.php` so the paths point up one level:
  ```php
  require __DIR__.'/../mess/vendor/autoload.php';
  $app = require_once __DIR__.'/../mess/bootstrap/app.php';
  ```
- Keep the rest of the app in `/home/username/mess/` (outside `public_html`), so the `.env`, `vendor`, `storage` are not web-accessible.

#### D. Create the MySQL databases (cPanel → MySQL® Databases)

1. Create database `username_mess` + a user, grant all privileges.
2. Create a second empty database `username_mess_restore` (for the restore-test).
3. Note the DB host (usually `localhost`).

#### E. Configure `.env`

Edit `/home/username/mess/.env`:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com
APP_KEY=<the base64 key from your local php artisan key:generate>

DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=username_mess
DB_USERNAME=username_user
DB_PASSWORD=<db password>
DB_RESTORE_TEST_DATABASE=username_mess_restore

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=sync          # <-- key change: no queue worker needed
FILESYSTEM_DISK=local

MAIL_MAILER=smtp
MAIL_HOST=<your smtp host>
MAIL_PORT=587
MAIL_USERNAME=<…>
MAIL_PASSWORD=<…>
MAIL_FROM_ADDRESS="mess@yourdomain.com"
MAIL_FROM_NAME="${APP_NAME}"

# Optional off-server backup mirror (leave blank for local-only):
DO_SPACES_KEY=
DO_SPACES_SECRET=
DO_SPACES_BUCKET=
BACKUP_NOTIFICATION_EMAIL=you@yourdomain.com

TELESCOPE_ENABLED=false
DEBUGBAR_ENABLED=false
```

#### F. Migrate + cache (over SSH, or via a "Terminal" / cPanel "SSH Access")

If SSH is available:

```bash
cd ~/mess
php artisan migrate --force
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

If **no SSH**: run these once via the cPanel **Cron Jobs** (add a one-off, let it fire, then delete it), or use the "Terminal" tool many panels now offer. Alternatively, run `php artisan migrate` locally against the remote DB before uploading (if your host allows remote MySQL — most cPanel hosts need you to add your IP to "Remote MySQL®" first).

#### G. Storage permissions

```bash
chmod -R 775 ~/mess/storage ~/mess/bootstrap/cache
```

`storage:link`: if you can't run the artisan command, manually create a symlink `public_html/storage -> ../mess/storage/app/public` (or copy the folder). Profile photos + receipt images need it.

#### H. The scheduler via cPanel Cron Jobs

cPanel → **Cron Jobs** → add (runs every minute; drives the backup schedule, purge, monitor, restore-test):

```
* * * * * cd /home/username/mess && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

> Replace `/usr/local/bin/php` with your host's PHP 8.3+ CLI path (cPanel → "MultiPHP Manager" or `which php` over SSH). The backup cadence/retention you set in **Backups → Configure** takes effect through this cron.

#### I. Configure backups after install

Log in as **super-admin** → **Backups → Configure**: set frequency (daily/weekly/monthly), time, keep-days, and storage cap. Backups write to `storage/app/backups/` locally; add `DO_SPACES_*` in `.env` (then re-cache config) to mirror to Spaces.

#### Shared-hosting limitations to know

- **Jobs run inline** (`QUEUE_CONNECTION=sync`) → "Close month" blocks the browser for the duration (seconds for a small mess). No background retries.
- **No long-lived workers** → don't enable features that expect async jobs.
- **`mysqldump`** must be on the host (almost all cPanel hosts have it). If `backup:run` fails, set `DUMP_BINARY_PATH` to its directory.
- **Resource limits** — shared hosts cap CPU/memory; the nightly restore-test loads a dump into a scratch DB, which is fine for small data but watch for `max_execution_time` kills.

---

### 5.4 Other control panels (Plesk / DirectAdmin)

Same recipe, different buttons:

- **Plesk:** *Files* → upload/extract above `httpdocs`; set the domain *Document Root* to `/httpdocs/../mess/public` (or put the app in a subfolder and point docroot to its `public`). *Scheduled Tasks (CRON)* → add the `schedule:run` line. *SSH Access* → run migrate/cache. Set *PHP* to 8.3+ with the `pdo_mysql, gd, zip, mbstring, curl` extensions.
- **DirectAdmin:** *File Manager* → upload; set domain *Document Root* to the `public` folder via *Domain Setup*; *Cron Jobs* for the scheduler; *SSH* (enable per-user) for artisan commands.

The constants everywhere: **docroot → `/public`**, **`QUEUE_CONNECTION=sync`**, **per-minute scheduler cron**, **`APP_DEBUG=false`**, **storage writable**, **`storage:link`**.

---

## 6. Post-deploy verification (do once)

1. Visit `https://yourdomain.com/up` → should return a 200 health page.
2. Log in as super-admin → **Backups** loads (no `UnableToListContents`) and the Configuration card shows "Local — default ✓" (and "Spaces — configured ✓" if you added creds).
3. **Backups → Backup now** → a zip appears in `storage/app/backups/`; **Delete** removes it (audit-logged).
4. Log in as admin/manager → `/home` dashboard renders; open each sidebar page → all 200.
5. Invite a member → they get the email → set-password → land on `/my`.
6. (VPS) `php artisan queue:failed` is empty; `supervisorctl status` shows the queue running.
7. Confirm a backup ran on schedule (check `storage/app/backups/` after the next scheduled time, or the audit log).

---

## 7. Common pitfalls

| Symptom | Cause / fix |
|--------|-------------|
| **403 on `/mess/*`** as super-admin | (Fixed in current code) the mess routes now allow `roles:admin,super-admin,manager`. If it recurs, the user's role isn't one of these — reassign via `mess:assign-role`. |
| **`UnableToListContents` / `169.254.169.254`** on Backups | No Spaces creds + old config. Clear config (`php artisan config:clear`); the `backups-local` disk is always used for listing, so Spaces being absent is fine. |
| **Month-close "hangs" forever** | You're on `sync` queue on shared hosting and hit `max_execution_time` — raise it in `.htaccess` (`php_value max_execution_time 300`) or use a VPS. |
| **Invite emails not sent** | `MAIL_MAILER=log` still set — switch to `smtp` with real credentials. |
| **Profile photos 404** | `storage:link` missing — run `php artisan storage:link` (or create the symlink manually on shared hosting). |
| **Backups fail with mysqldump error** | Set `DUMP_BINARY_PATH` to the dir containing `mysqldump`; ensure the host's PHP user can run it. (Spatie v10 is Linux-only — fine on servers, not Windows dev.) |
| **"Backup schedule didn't run"** | The per-minute `schedule:run` cron isn't set (or wrong PHP path). The Backups → Configure cadence only fires through that cron. |

---

## 8. Updating an existing install

```bash
cd /path/to/app
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build        # if frontend changed
php artisan migrate --force    # additive only — safe, never wipes data
php artisan config:cache && php artisan route:cache && php artisan view:cache
# (VPS) supervisorctl restart mess-queue
```

On shared hosting without SSH: re-upload changed files, then re-run `migrate` + cache commands via the panel Terminal/cron as in §5.3.F.

---

*For the full VPS hardening runbook (HTTPS setup, supervisor verbatim, the restore procedure, DO Spaces provisioning, monitoring), see [DEPLOYMENT.md](./DEPLOYMENT.md). For features and architecture, see [README.md](./README.md).*
