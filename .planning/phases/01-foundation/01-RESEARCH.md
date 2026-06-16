# Phase 1: Foundation — Research

**Phase:** 01-foundation
**Date:** 2026-06-16
**Status:** Verification research complete — ready to plan
**Mode:** YOLO / coarse granularity / 3 plans

This document verifies the technical surfaces that the Phase 1 plan will write tasks against. Every claim is cited against a local file, package version, or documentation URL. CONTEXT.md's 24 locked decisions (D-01..D-24) remain the source of truth; this research confirms they are implementable against the installed stack.

---

## 1. Tyro Login — 2FA on `admin` role (D-05)

### Verdict: CONFIRMED — but D-05 needs two env keys, not one

The CONTEXT D-05 decision states `TYRO_LOGIN_2FA_ENABLED=true`. That alone makes 2FA *globally* required for every role. To confine it to `admin` (manager) only, the *additional* gate `TYRO_LOGIN_2FA_FORCED_ROLES` must be set, and `TYRO_LOGIN_2FA_ALLOW_SKIP` must be `false`.

### Citations

- `vendor/hasinhayder/tyro-login/config/tyro-login.php` lines 297-329 (the `two_factor` config block) — the actual env keys are:
  - `'enabled' => env('TYRO_LOGIN_2FA_ENABLED', false)` — global on/off (line 298)
  - `'allow_skip' => env('TYRO_LOGIN_2FA_ALLOW_SKIP', false)` — non-forced roles can dismiss setup (line 311)
  - `'forced_roles' => env('TYRO_LOGIN_2FA_FORCED_ROLES', '')` — comma-separated slugs that must set up 2FA (line 316)
- `vendor/hasinhayder/tyro-login/src/Http/Controllers/LoginController.php` lines 178-216 — the 2FA gate logic reads `config('tyro-login.two_factor.forced_roles')`, splits on `,`, then calls `$user->hasRole($role)` for each. If any match → setup is **mandatory** (no skip, no ignore cookie honored).
- `vendor/hasinhayder/tyro-login/src/Http/Controllers/LoginController.php` lines 213-216 — after a successful login, the user is logged back out, `login.id` is stored in session, and the user is redirected to `tyro-login.two-factor.challenge` (if `two_factor_confirmed_at` is filled) or `tyro-login.two-factor.setup` (if not).
- `vendor/hasinhayder/tyro-login/src/Traits/HasTwoFactorAuth.php` lines 11-26 — the trait only registers casts/hidden columns; it does **not** itself enforce 2FA. Enforcement is in the `LoginController` redirect path.
- `app/Models/User.php` lines 25-26 — `HasTwoFactorAuth` trait is already applied to the User model. No change needed.

### Required `.env` keys for D-05

```
TYRO_LOGIN_2FA_ENABLED=true
TYRO_LOGIN_2FA_FORCED_ROLES=admin,super-admin
TYRO_LOGIN_2FA_ALLOW_SKIP=false
```

(Without `ALLOW_SKIP=false` and `FORCED_ROLES=admin,...`, the `user` role would be prompted for 2FA setup but could dismiss it via a cookie — that violates D-05's "Members 2FA-off".)

### Lockout (AUTH-04)

- `vendor/hasinhayder/tyro-login/config/tyro-login.php` lines 568-602 — the `lockout` config block has the defaults that satisfy AUTH-04:
  - `'max_attempts' => env('TYRO_LOGIN_LOCKOUT_MAX_ATTEMPTS', 5)` (line 575)
  - `'duration_minutes' => env('TYRO_LOGIN_LOCKOUT_DURATION', 15)` (line 580)
  - `'enabled' => env('TYRO_LOGIN_LOCKOUT_ENABLED', true)` (line 570)
- No `.env` change required; the defaults already match D-05/AUTH-04 ("5 attempts / 15 min").
- `LoginController::isLockedOut()` (lines 779-795) and `lockoutUser()` (lines 832-836) use the `tyro-login:lockout:{ip}` cache key. Cache driver in dev is `database` per `.env` — lockout data persists across requests, which is correct.

---

## 2. Tyro Login — Invitations (D-04 manager invite flow)

### Verdict: PARTIALLY MATCHES — the literal "invitation" in Tyro is a **referral** system, not a manager→member email invite. The D-04 flow must be built as a custom controller on top of the **magic link** feature, not Tyro's `InvitationController`.

### What the existing `InvitationController` actually does

- `vendor/hasinhayder/tyro-dashboard/src/Http/Controllers/InvitationController.php` lines 25-160 — this controller manages a **referral** system: an *existing* user creates an `InvitationLink` row (with a random 32-char hash, see lines 130-136), shares the URL `/register?invite={hash}`, and tracks who registered through it via `InvitationReferral`.
- `vendor/hasinhayder/tyro-login/src/Models/InvitationLink.php` lines 39-43 — the link points to `/register?invite=...`, not a "set your password" page.
- The receiving flow (`RegisterController::register()` lines 60-117) creates a *new* account (with whatever the user types) and tracks the referral via `InvitationHelper::trackReferral($invitationHash, $user->id)`. The new user is then auto-logged-in (line 115) with the default `user` role.
- This is **not** a "manager creates a member account and member sets their password" flow. The manager does not choose the password; the invitee does at register time.

### The actual mechanism to use for D-04: Magic Links + a custom invite controller

D-04 ("Manager creates member accounts via a custom invite flow. Manager submits member email → Tyro invitation email (magic link) is sent → member clicks, sets password, lands on `/my`") is best implemented as:

1. **Manager UI** — a custom `/mess/members/invite` Blade form (or custom route under `/mess/*`) that takes a member email. Submitting it:
   - Creates (or finds) a `User` row with a random temp password (e.g. `Str::random(32)`) so the user record exists.
   - Assigns the `user` role via `$user->assignRole($role)`.
   - Triggers a **magic link** to the email (or a custom mailable with a signed URL pointing to `/set-password/{token}`).

2. **Magic link mechanism** — `vendor/hasinhayder/tyro-login/src/Http/Controllers/LoginController.php` lines 681-734 (`magicLogin()`) handles the click: it validates a hash stored in the cache (`tyro_magic_link_{$hash}`), then `Auth::login($user)`. **However**, the magic link logs the user straight in without setting a password**, which doesn't match D-04's "set password".

3. **Recommended D-04 implementation** — Build a small custom controller (e.g. `App\Http\Controllers\Mess\MemberInviteController`):
   - `GET /mess/members/invite` — manager-only form
   - `POST /mess/members/invite` — creates user with random temp password, sends a `SetPasswordMail` containing a signed URL `/set-password?token={hash}&email={email}` (use `URL::temporarySignedRoute('password.set', now()->addHour(), ['user' => $user->id])`)
   - `GET /set-password` — public route (not in `/mess/*`), shows a password form
   - `POST /set-password` — Form Request validates + sets the password, then `Auth::login()` and redirect to `/my` (D-06)

4. **Manager-only gate** — apply middleware `['auth', 'role:admin']` (the `role` alias is registered at `vendor/hasinhayder/tyro/src/Providers/TyroServiceProvider.php` line 159; backed by `EnsureTyroRole::handle()` at `vendor/hasinhayder/tyro/src/Http/Middleware/EnsureTyroRole.php` lines 13-21).

### Citations

- D-04 mentions "Magic link is the default" — Tyro Login's `magic_links_enabled` config exists at `vendor/hasinhayder/tyro-login/config/tyro-login.php` line 197: `'magic_links_enabled' => env('TYRO_LOGIN_ENABLE_MAGIC_LINKS', false)`. Note: as built, magic links **log the user in** — they don't *set a password* — so a custom route is the right approach for D-04's "sets password" requirement.
- D-04 also says "manager can toggle to set password manually if email fails" — that is just a second manager-side action: re-send the invite OR a "set password" form that writes the password directly to the User. Both go through the same custom controller.

### Required `.env` for D-04 (only if we use the built-in magic link as a backup)

```
TYRO_LOGIN_ENABLE_MAGIC_LINKS=true
```

But the primary D-04 path is the custom controller + signed URL — it does **not** rely on Tyro's magic link.

---

## 3. Tyro Dashboard — declaring a CRUD resource for Mess (D-08)

### Verdict: CONFIRMED — config-based declaration in `config/tyro-dashboard.php` `resources` array. Auto-registers routes and sidebar entry.

### Exact shape of a resource entry

`vendor/hasinhayder/tyro-dashboard/src/Concerns/HasCrud.php` lines 24-44 (and the comment block at `vendor/hasinhayder/tyro-dashboard/config/tyro-dashboard.php` lines 224-246) define the shape:

```php
// config/tyro-dashboard.php
'resources' => [
    'messes' => [
        'model' => 'App\Models\Mess',          // required, FQCN
        'title' => 'Messes',                    // optional, defaults to plural snake_case
        'icon' => '<svg>...</svg>',             // optional, sidebar icon
        'fields' => [                           // optional, auto-detected from $fillable
            'name' => ['type' => 'text', 'label' => 'Name', 'rules' => 'required|string|max:255'],
            'address' => ['type' => 'textarea', 'rules' => 'nullable|string'],
            'monthly_rent' => ['type' => 'number', 'rules' => 'required|numeric|min:0'],
            'manager_contact' => ['type' => 'text', 'rules' => 'nullable|string|max:255'],
            'status' => ['type' => 'select', 'options' => ['active' => 'Active', 'inactive' => 'Inactive'], 'rules' => 'required|in:active,inactive'],
            // The settings sub-form (D-08 + D-13) is best done as a separate `settings` resource,
            // linked by mess_id, declared the same way.
        ],
        'roles' => ['admin', 'super-admin'],    // optional: roles allowed to see+edit (otherwise admin-only)
        'readonly' => [],                       // optional: roles that can see but not edit
        'search' => ['name', 'address'],        // optional: explicit searchable columns
        'upload_disk' => 'public',              // optional
        'upload_directory' => 'uploads/messes', // optional
    ],
],
```

### Citations for how resources work

- `vendor/hasinhayder/tyro-dashboard/routes/web.php` lines 165-174 — the `/dashboard/resources/{resource}/*` routes are auto-registered with the `ResourceController`. No additional route file edits needed.
- `vendor/hasinhayder/tyro-dashboard/src/Http/Controllers/ResourceController.php` lines 7-31 — `getResourceConfig($key)` reads the config first, then falls back to scanning `app/Models` for classes using the `HasCrud` trait.
- `vendor/hasinhayder/tyro-dashboard/src/Providers/TyroDashboardServiceProvider.php` lines 117-123 — `getAllResources()` is called by the view composer and shares `$allResources` with the sidebar views, which auto-generates a sidebar entry per resource.
- `vendor/hasinhayder/tyro-dashboard/src/Http/Controllers/ResourceController.php` lines 159-185 — the `hasAccess($config)` check: if no `roles` are defined, **only admins can access** (`tyro-dashboard.admin_roles` = `['admin', 'super-admin']`). This is the "super-admin only" gate for free.

### Restricting to super-admin only

Two ways:

1. **Omit `roles`** — defaults to admin-only (which includes `super-admin` per `EnsureIsAdmin` middleware at `vendor/hasinhayder/tyro-dashboard/src/Http/Middleware/EnsureIsAdmin.php` line 35).
2. **Explicit: `'roles' => ['super-admin']`** — strict super-admin-only, excludes `admin`. (Manager cannot see/edit; only super-admin can.)

For D-08 (mess config editable by both `admin` and `super-admin` — D-15), use: `'roles' => ['admin', 'super-admin']`.

### Overriding form fields / validation / views

- **Field config** — add to `'fields'` array in the resource config. Per-field keys: `type`, `label`, `rules`, `options`, `relationship`, `option_label`, `multiple`, `help_text`, `placeholder`, `hide_in_index`, `hide_in_form`, `hide_in_create`, `hide_in_edit`, `searchable`, `sortable`, `readonly`, `attributes` (see `vendor/hasinhayder/tyro-dashboard/.agents/skills/tyro-dashboard/rules/crud-resources.md` lines 65-83 for the full shape).
- **Validation** — pass `rules` per field; the `ResourceController::store()` and `update()` methods (lines 252-258 and 412-419) collect `rules` from the config and call `$request->validate($rules)`.
- **View overrides** — publish the package views: `php artisan vendor:publish --tag=tyro-dashboard-views-admin` (publishes 12 admin views, see `vendor/hasinhayder/tyro-dashboard/src/Providers/TyroDashboardServiceProvider.php` lines 200-217). Override `resources/views/vendor/tyro-dashboard/resources/create.blade.php` etc. for field-level custom rendering.

### Sidebar entry

- Auto-registered. The `getAllResources()` (line 117 in the service provider) is called by the view composer at lines 105-110 and shared with `tyro-dashboard::partials.admin-sidebar` and `tyro-dashboard::partials.user-sidebar`. The sidebar iterates over the resources and renders a link per resource, filtered by the user's role.
- No manual sidebar edit needed for the basic case. Only override the sidebar if you want custom ordering, icons, or to hide specific resources from non-super-admins.

### The settings sub-form (D-13)

D-08 mentions "sub-form for meal values / currency / date format". This is best implemented as a **second resource** called `settings` (not as a sub-form of `messes`), because:

1. The EAV pattern in D-13 (`settings` table with `mess_id`, `key`, `value`, `type`, `group`) doesn't map naturally to Tyro's single-model CRUD.
2. A separate `settings` resource keeps the mess fields clean and lets us use `relationship` (`'mess_id' => ['type' => 'select', 'relationship' => 'mess', ...]`) to link a setting to its mess.

For Phase 1, declare a `settings` resource with the same `['admin', 'super-admin']` roles.

---

## 4. `owen-it/laravel-auditing` (D-09, D-10, D-11)

### Verdict: NOT YET INSTALLED. The package must be added via `composer require`.

### Version compatibility (Laravel 13)

- The latest stable of `owen-it/laravel-auditing` is **v13.x** (the v13 line supports Laravel 10-13, the v14 line dropped older Laravel; verify exact version via `composer show owen-it/laravel-auditing --available` after `composer require`).
- The composer.json file does **not** currently require it (verified — `vendor/owen-it/laravel-auditing/` does not exist; only `bacon/bacon-qr-code`, `brick/math`, etc. are present in `composer.lock`).
- Packagist page: <https://packagist.org/packages/owen-it/laravel-auditing>
- GitHub: <https://github.com/owen-it/laravel-auditing>

### Install command

```bash
composer require owen-it/laravel-auditing
```

This will install the package, register its service provider (auto-discovered), and add it to `composer.json` `require`.

### Publish the audits migration

```bash
php artisan vendor:publish --provider="OwenIt\Auditing\AuditingServiceProvider" --tag="migrations"
# or simpler:
php artisan vendor:publish --provider="OwenIt\Auditing\AuditingServiceProvider"
```

The published migration lands at `database/migrations/{timestamp}_create_audits_table.php` (the package default is `2020_01_01_000000_create_audits_table.php` or similar; check the published filename after running the command).

### Audits table schema

The published migration creates a `audits` table with these columns (per the package's canonical `2014_01_01_000000_create_audits_table.php` migration):

| Column | Type | Notes |
|---|---|---|
| `id` | `bigIncrements` | PK |
| `user_type` | `string` nullable | morph type of the actor (e.g. `App\Models\User`) |
| `user_id` | `unsignedBigInteger` nullable | actor FK |
| `event` | `string` | e.g. `created`, `updated`, `deleted`, `restored` |
| `auditable_type` | `string` | morph type of the audited model |
| `auditable_id` | `unsignedBigInteger` | morph FK |
| `old_values` | `json` nullable | full JSON of dirty attributes before |
| `new_values` | `json` nullable | full JSON of dirty attributes after |
| `url` | `text` nullable | request URL (from `Request::url()`) |
| `ip_address` | `string(45)` nullable | request IP |
| `user_agent` | `string(1023)` nullable | user agent (note: 1023 not 255) |
| `tags` | `string` nullable | comma-separated tags |
| `created_at` | `timestamp` | uses `useCurrent()` |
| `updated_at` | `timestamp` nullable | optional |

Indexed: `user_type`+`user_id`, `auditable_type`+`auditable_id`, and `created_at`.

### Apply the `Auditable` trait to a model

```php
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['name', 'address', 'monthly_rent', 'manager_contact', 'status'])]
class Mess extends Model implements AuditableContract
{
    use Auditable; // or use HasFactory, Auditable
    // ... casts(), etc.
}
```

D-09 says: "Phase 1 only needs to prove the trait works on one model — `Mess` is a good fit." Apply it to `App\Models\Mess` (new model created in Phase 1).

### Querying audit logs (morph relation)

The trait adds a polymorphic relation:

```php
$mess = Mess::find(1);
foreach ($mess->audits as $audit) {     // `audits` is the relation name
    echo $audit->event;                  // 'created' | 'updated' | 'deleted' | 'restored'
    echo json_encode($audit->old_values);
    echo json_encode($audit->new_values);
    echo $audit->user->name ?? 'system'; // null if no auth user
    echo $audit->created_at->format('Y-m-d H:i:s');
}
```

(Relation name is `audits` in current versions; older versions used `auditLogs`. Confirm by reading `vendor/owen-it/laravel-auditing/src/Auditable.php` after install.)

### Before/After JSON access

`$audit->old_values` and `$audit->new_values` are cast to `array` (or `object`) via Eloquent's built-in JSON cast. Access examples:

```php
$audit->old_values['name'] ?? null;   // array access
$audit->new_values['status'] ?? null; // null if the field was unchanged
$audit->getModified() // helper that returns array of changed keys
```

### Performance: synchronous or queued?

- **Default**: writes are **synchronous** inside the same DB transaction as the model save. This is **safe** (the audit row only exists if the model change committed) but can be slow under high write load.
- **Queue support**: the package supports `auditing.queue = true` in `config/audit.php`. When enabled, the audit row is dispatched to a job (`OwenIt\Auditing\Jobs\AuditShipped`) on the default queue. **Caveat**: queue mode means the audit can land *after* the model change commits, so a crash mid-job will leave missing audit rows. For Phase 1 (low write volume — only Mess updates + role/privilege changes), keep the default synchronous.
- Recommendation: leave it synchronous in Phase 1. Add `auditing.queue = true` in Phase 5 if perf warrants it.

### Enforce append-only (D-11)

D-11 says audit entries are kept forever, never edited, never deleted. The package does **not** enforce append-only by itself — it just writes rows. To enforce:

1. **No `audit.prune_days` set** (or set to `0` / unset in `config/audit.php`).
2. **No scheduled prune job** — skip the `auditing:prune` artisan command; don't add a `Console\Kernel` schedule.
3. **Application code** — never call `Audit::where(...)->delete()` or `Audit::where(...)->update(...)`. The domain `AuditController` for our custom `/mess/audit` page is **read-only** (no destroy or edit routes).
4. **DB-level** (optional defense-in-depth) — add a MySQL trigger on `audits` that rejects `UPDATE` and `DELETE`. This is optional; the application code is enough.
5. **Tyro's built-in audit** (`tyro_audit_logs` table, migration at `vendor/hasinhayder/tyro/database/migrations/2026_02_15_000000_create_tyro_audit_logs_table.php`) is separate and tracks user/role changes. Per D-11 / AUDIT-05, "Domain audit log is separate from Tyro's user/role audit log" — leave Tyro's table alone; use `audits` (owen-it) for Mess (and later members/meals/etc.).

### Configuration publish

```bash
php artisan vendor:publish --provider="OwenIt\Auditing\AuditingServiceProvider" --tag="config"
# Publishes config/audit.php (optional — defaults are fine)
```

### Sources

- <https://packagist.org/packages/owen-it/laravel-auditing> (latest version)
- <https://github.com/owen-it/laravel-auditing> (README + docs)
- Local: `composer.json` (currently does not list `owen-it/laravel-auditing` — needs adding)
- Local: `composer.lock` (no `owen-it` entry — confirmed not installed)

---

## 5. Global Eloquent scope for `mess_id` (D-20)

### Verdict: CONFIRMED — use the modern **`Scope` class registered in a service provider's `boot()`** approach. This is the cleanest, most testable pattern in Laravel 13.

### Three approaches compared

| Approach | Pros | Cons | Verdict |
|---|---|---|---|
| **A. Bootable trait** (`static::boot()` override on each model) | Self-contained, no provider changes | Repeats the same `static::addGlobalScope` call in 12+ models; can't be unit-tested in isolation; risky for v2 swap-out | ❌ |
| **B. Observer** (event-based) | Familiar pattern | Observers are for *side effects on events*, not query constraints; you can't add a global query scope from an observer without hacks; this is misuse of the observer pattern | ❌ |
| **C. Dedicated `Scope` class** registered via `Model::booted()` callback or a trait applied to all models | Single source of truth; testable; clean swap-out for v2; uses the framework's intended extension point | Requires one extra class + a provider entry | ✅ |

### Recommended: `App\Models\Scopes\MessScope` + a small trait

```php
// app/Models/Concerns/BelongsToActiveMess.php
namespace App\Models\Concerns;

use App\Models\Scopes\MessScope;
use Illuminate\Support\Facades\Config;

trait BelongsToActiveMess {
    public static function bootBelongsToActiveMess(): void {
        static::addGlobalScope(new MessScope());
    }
}
```

```php
// app/Models/Scopes/MessScope.php
namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class MessScope implements Scope {
    public function apply(Builder $builder, Model $model): void {
        $messId = config('app.active_mess_id');
        if ($messId !== null) {
            $builder->where($model->getTable() . '.mess_id', $messId);
        }
    }
}
```

Then every domain model:

```php
use App\Models\Concerns\BelongsToActiveMess;

#[Fillable(['mess_id', /* ... */])]
class MealEntry extends Model {
    use BelongsToActiveMess;
}
```

### Reading from `config('app.active_mess_id')`

- Add to `config/app.php`:
  ```php
  'active_mess_id' => env('ACTIVE_MESS_ID', 1),
  ```
- Add to `.env`:
  ```
  ACTIVE_MESS_ID=1
  ```
- The scope reads it on every query — v1 has a static value; v2 can swap to a session/middleware-driven value without changing the scope class.

### Bypass the scope

Use `withoutGlobalScope(MessScope::class)` or `withoutGlobalScopes()`:

```php
Mess::withoutGlobalScope(MessScope::class)->find($id);
Mess::withoutGlobalScopes()->get(); // bypass all
```

Use cases in v1: super-admin "view all messes" dashboard (when multi-mess is added), the mess-creation flow itself (we can't query `WHERE mess_id = 1` to see if any mess exists before any mess exists), seeders.

**Edge case to handle**: the scope will silently return empty results if `config('app.active_mess_id')` is `null` and the model has no rows. The scope should only apply the WHERE if the value is non-null (as shown above). This lets the onboarding flow (D-22, mess doesn't exist yet) bootstrap.

### Tests bootstrap `active_mess_id=1`

In the `TestCase` (`tests/TestCase.php`) or in test setUp methods:

```php
config(['app.active_mess_id' => 1]);
```

Or globally in `phpunit.xml` (no env var — use a TestCase base class `setUp`):
```php
// tests/TestCase.php
protected function setUp(): void {
    parent::setUp();
    config(['app.active_mess_id' => 1]);
}
```

### Citations

- Laravel 13 Eloquent Global Scopes docs: <https://laravel.com/docs/13.x/eloquent#global-scopes>
- Local: `vendor/hasinhayder/tyro-dashboard/.agents/skills/laravel-best-practices/SKILL.md` (line: "Global scopes sparingly — document their existence")
- Local: `vendor/hasinhayder/tyro-dashboard/.agents/skills/tyro-best-practices/SKILL.md` (mentions local scopes for reusable query constraints)

---

## 6. Decimal money casts (D-24)

### Verdict: CONFIRMED — `decimal:2` cast in the `casts()` method. No DBAL needed for Laravel 11+.

### Cast syntax

```php
protected function casts(): array {
    return [
        'monthly_rent' => 'decimal:2',
        'amount' => 'decimal:2',
        // ... other casts
    ];
}
```

The `decimal:N` cast is built into Eloquent's cast system (no `use` import needed). It formats the value as a string with N decimal places on read (e.g. `"1234.56"`), and on set it accepts both numeric strings and floats, rounding to N decimal places.

### DBAL requirement for Laravel 13

- **Pre-Laravel 11**: `decimal` columns required `doctrine/dbal` to read the column schema (for column changes in migrations). That dependency is no longer needed.
- **Laravel 11+ / 13.x**: built-in schema methods (`Schema::getColumnType()`, `Schema::getColumns()`) return `decimal` directly without DBAL. Verified by `vendor/hasinhayder/tyro-dashboard/src/Concerns/HasCrud.php` line 277-278 (calls `Schema::getColumnType()` and handles `'decimal'` as a `number` field type without any DBAL-specific code).
- The `composer.json` of the project does **not** require `doctrine/dbal`, and the installed `composer.lock` does not include it. Confirmed.

### Migration syntax

```php
$table->decimal('monthly_rent', 10, 2);          // DECIMAL(10, 2)
$table->decimal('amount', 10, 2)->default(0);    // with default
$table->decimal('meal_value_breakfast', 4, 2);    // smaller scale (e.g. 0.50)
```

D-24 specifies `DECIMAL(10,2)` for money. Settings fields (meal values, e.g. 0.50) can use `DECIMAL(4,2)`.

### Edge cases

- **String input** — `"1234.5"` → stored as `1234.50`, retrieved as `"1234.50"`.
- **Float input** — `1234.5` (PHP float) → stored as `1234.50`, retrieved as `"1234.50"`. No precision loss for normal money values within `DECIMAL(10,2)`'s 8-digit integer range (up to 99,999,999.99).
- **Empty/null** — stored as `NULL` (column is nullable) or `0.00` (if not nullable + default 0). For Eloquent, the cast is applied on retrieve; on save, `null` is preserved if the column is nullable.
- **Sums** — MySQL's `DECIMAL` arithmetic is exact: `SELECT SUM(amount) FROM payments` returns a `DECIMAL` result, no PHP float drift. PITFALLS #2 (research) is fully addressed by this.
- **Display** — D-24 specifies `NumberFormatter('bn_BD', NumberFormatter::CURRENCY)` for BDT formatting, not `number_format()`. Phase 1 should add a `bdt()` helper in `App\Providers\AppServiceProvider::boot()` or as a Blade directive:
  ```php
  // app/helpers.php (registered via composer.json "autoload.files")
  function bdt(string|float|null $amount): string {
      $f = new \NumberFormatter('bn_BD', \NumberFormatter::CURRENCY);
      return $f->formatCurrency((float) $amount, 'BDT');
  }
  ```

### Sources

- Laravel 13 Eloquent custom casts: <https://laravel.com/docs/13.x/eloquent-mutators#attribute-casting>
- `vendor/hasinhayder/tyro-dashboard/.planning/research/PITFALLS.md` (PITFALLS #2 — "Float money math", addressed here)
- `vendor/hasinhayder/tyro-dashboard/.planning/research/SUMMARY.md` (anti-feature: "Floating-point money — Strictly decimal. Float is a bug.")

---

## 7. Form Requests in Laravel 13 (D-07, D-15)

### Verdict: CONFIRMED — store in `app/Http/Requests/`, authorize via `authorize()`, type-hint in controller.

### Convention

- **Path**: `app/Http/Requests/{Resource}/{Action}Request.php` (e.g. `app/Http/Requests/Mess/UpdateMessRequest.php`).
- **Base class**: extends `Illuminate\Foundation\Http\FormRequest`.
- **Methods**:
  - `authorize(): bool` — return `true` for always-allowed, or check `$this->user()->can('update', $this->route('mess'))`.
  - `rules(): array` — return the validation rules array.
  - `messages(): array` (optional) — custom error messages.
  - `attributes(): array` (optional) — custom attribute names.
  - `withValidator($validator)` or `after($validator)` (Laravel 13 prefers `after()`) — additional checks.

### Citation — Laravel 13 docs

<https://laravel.com/docs/13.x/validation#form-request-validation>

### Citation — conventions file

`.planning/codebase/CONVENTIONS.md` line: "Use Form Requests for validation (`app/Http/Requests/`)"

### Example for D-08 mess config

```php
// app/Http/Requests/Mess/UpdateMessRequest.php
namespace App\Http\Requests\Mess;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMessRequest extends FormRequest {
    public function authorize(): bool {
        $mess = $this->route('mess');
        return $this->user()->hasRole('admin') || $this->user()->hasRole('super-admin');
    }

    public function rules(): array {
        $messId = $this->route('mess')?->id;
        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'monthly_rent' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'manager_contact' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
```

### Injection into controller (D-07, D-15)

```php
// app/Http/Controllers/Mess/MessConfigController.php
public function update(UpdateMessRequest $request, Mess $mess) {
    $mess->update($request->validated());
    return redirect()->route('home')->with('success', __('Mess settings updated.'));
}
```

Validation runs **before** the controller method body, automatically. The `authorize()` check also runs automatically — if it returns `false`, a 403 is thrown.

### Citations

- `vendor/hasinhayder/tyro-dashboard/.agents/skills/tyro-dashboard/SKILL.md` line: "Type-hint Form Requests for auto-validation" (§10 Routing & Controllers)
- `vendor/hasinhayder/tyro-dashboard/.agents/skills/laravel-best-practices/SKILL.md` §6 Validation & Forms
- `.planning/codebase/CONVENTIONS.md` "Form Requests for all user input"

---

## 8. Tyro's auto-registered routes

### Verdict: CONFIRMED — full route list below; `/home` and `/my` (D-06, D-07) are app routes, NOT registered by Tyro.

### Tyro Login routes (no prefix by default; `prefix` from `tyro-login.php` line 116)

Source: `vendor/hasinhayder/tyro-login/routes/web.php`

| Method | Path | Name | Notes |
|---|---|---|---|
| GET | `/login` | `tyro-login.login` | Login form |
| POST | `/login` | `tyro-login.login.submit` | Login submit |
| GET | `/mlogin` | `tyro-login.magic-link` | Magic link consumption |
| POST | `/magic-link/request` | `tyro-login.magic-link.request` | Request a magic link |
| GET | `/lockout` | `tyro-login.lockout` | Lockout page |
| GET | `/register` | `tyro-login.register` | Register form (if `registration.enabled`) |
| POST | `/register` | `tyro-login.register.submit` | Register submit |
| GET | `/email/verify` | `tyro-login.verification.notice` | Email verify notice |
| GET | `/email/not-verified` | `tyro-login.verification.not-verified` | |
| GET | `/email/verify/{token}` | `tyro-login.verification.verify` | |
| POST | `/email/resend` | `tyro-login.verification.resend` | |
| GET | `/forgot-password` | `tyro-login.password.request` | |
| POST | `/forgot-password` | `tyro-login.password.email` | |
| GET | `/reset-password/{token}` | `tyro-login.password.reset` | |
| POST | `/reset-password` | `tyro-login.password.update` | |
| GET | `/otp/verify` | `tyro-login.otp.verify` | If OTP enabled |
| POST | `/otp/verify` | `tyro-login.otp.submit` | |
| POST | `/otp/resend` | `tyro-login.otp.resend` | |
| GET | `/otp/cancel` | `tyro-login.otp.cancel` | |
| GET | `/auth/{provider}/redirect` | `tyro-login.social.redirect` | If social enabled |
| GET | `/auth/{provider}/callback` | `tyro-login.social.callback` | |
| GET | `/two-factor/challenge` | `tyro-login.two-factor.challenge` | 2FA challenge page |
| POST | `/two-factor/verify` | `tyro-login.two-factor.verify` | |
| GET | `/two-factor/setup` | `tyro-login.two-factor.setup` | 2FA setup |
| POST | `/two-factor/confirm` | `tyro-login.two-factor.confirm` | |
| POST | `/two-factor/skip` | `tyro-login.two-factor.skip` | |
| POST | `/two-factor/ignore` | `tyro-login.two-factor.ignore` | |
| GET | `/two-factor/recovery-codes` | `tyro-login.two-factor.recovery-codes` | |
| GET/POST | `/logout` | `tyro-login.logout` | |

### Tyro Dashboard routes (prefix `/dashboard` by default)

Source: `vendor/hasinhayder/tyro-dashboard/routes/web.php`. All routes have name prefix `tyro-dashboard.`.

| Method | Path | Name | Notes |
|---|---|---|---|
| GET | `/dashboard` | `tyro-dashboard.index` | Dashboard home |
| GET | `/dashboard/components` | `tyro-dashboard.components` | Examples (dev only) |
| GET | `/dashboard/widgets` | `tyro-dashboard.widgets` | Examples (dev only) |
| GET | `/dashboard/profile` | `tyro-dashboard.profile` | Profile mgmt |
| PUT | `/dashboard/profile/update` | `tyro-dashboard.profile.update` | |
| PUT | `/dashboard/profile/password` | `tyro-dashboard.profile.password` | |
| DELETE | `/dashboard/profile/photo` | `tyro-dashboard.profile.photo.delete` | |
| POST | `/dashboard/profile/2fa/setup` | `tyro-dashboard.profile.2fa.setup` | |
| DELETE | `/dashboard/profile/2fa/reset` | `tyro-dashboard.profile.2fa.reset` | |
| GET | `/dashboard/invitations` | `tyro-dashboard.invitations.index` | User's own referrals |
| POST | `/dashboard/invitations/create` | `tyro-dashboard.invitations.create` | Create referral link |
| POST | `/dashboard/leave-impersonation` | `tyro-dashboard.leave-impersonation` | |
| GET | `/dashboard/media` | `tyro-dashboard.media` | Media library |
| POST | `/dashboard/media/upload` | `tyro-dashboard.media.upload` | |
| (more media routes) | | | |
| GET | `/dashboard/users` | `tyro-dashboard.users.index` | **Admin only** (uses `tyro-dashboard.admin` middleware) |
| GET/POST/PUT/DELETE | `/dashboard/users/{id}/*` | `tyro-dashboard.users.*` | Admin only |
| GET/POST/PUT/DELETE | `/dashboard/roles/*` | `tyro-dashboard.roles.*` | Admin only |
| GET/POST/PUT/DELETE | `/dashboard/privileges/*` | `tyro-dashboard.privileges.*` | Admin only |
| GET/POST/DELETE | `/dashboard/invitations/admin/*` | `tyro-dashboard.invitations.admin.*` | Admin only |
| GET | `/dashboard/audits` | `tyro-dashboard.audits.index` | Admin only — Tyro's own audit log |
| GET | `/dashboard/audits/export` | `tyro-dashboard.audits.export` | Admin only |
| GET | `/dashboard/settings/system` | `tyro-dashboard.settings.system.index` | Admin only — .env editor |
| GET | `/dashboard/resources/{resource}` | `tyro-dashboard.resources.index` | Dynamic CRUD |
| GET/POST | `/dashboard/resources/{resource}/create` | `tyro-dashboard.resources.create` | |
| POST | `/dashboard/resources/{resource}` | `tyro-dashboard.resources.store` | |
| GET/PUT/DELETE | `/dashboard/resources/{resource}/{id}/*` | `tyro-dashboard.resources.*` | |

### Citations for the admin-only gate

- `vendor/hasinhayder/tyro-dashboard/routes/web.php` line 78 — `Route::middleware('tyro-dashboard.admin')->group(...)` wraps all user/role/privilege/audit/settings/invitation-admin routes.
- `vendor/hasinhayder/tyro-dashboard/src/Http/Middleware/EnsureIsAdmin.php` lines 33-44 — checks `tyro-dashboard.admin_roles` config (default `['admin', 'super-admin']`).
- `vendor/hasinhayder/tyro-dashboard/config/tyro-dashboard.php` line 23 — `'admin_roles' => ['admin', 'super-admin']`.

### App routes (D-06, D-07) — `routes/web.php`

The project needs to add (NOT yet present in `routes/web.php`):

```php
// routes/web.php
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MyController;
use App\Http\Controllers\Mess\MessConfigController;
use App\Http\Controllers\Mess\MemberInviteController;
use App\Http\Controllers\Mess\AuditController;
use App\Http\Controllers\SetPasswordController;

Route::get('/', fn () => view('welcome'));

// Manager (admin role) home
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/home', [HomeController::class, 'index'])->name('home');
    Route::get('/mess/settings', [MessConfigController::class, 'edit'])->name('mess.settings.edit');
    Route::patch('/mess/settings', [MessConfigController::class, 'update'])->name('mess.settings.update');
    Route::get('/mess/audit', [AuditController::class, 'index'])->name('mess.audit');
    Route::get('/mess/members/invite', [MemberInviteController::class, 'create'])->name('mess.members.invite.create');
    Route::post('/mess/members/invite', [MemberInviteController::class, 'store'])->name('mess.members.invite.store');
});

// Member (user role) home
Route::middleware(['auth', 'role:user'])->group(function () {
    Route::get('/my', [MyController::class, 'index'])->name('my');
});

// Public set-password (from invite link)
Route::get('/set-password', [SetPasswordController::class, 'show'])->name('password.set.show')->middleware('signed');
Route::post('/set-password', [SetPasswordController::class, 'update'])->name('password.set.update')->middleware('signed');

// After-login redirect (D-02, D-06, D-07)
// /home is for admin, /my is for user, /dashboard is super-admin only
// Set in tyro-login config: TYRO_LOGIN_REDIRECT_AFTER_LOGIN=/home (admin) — but Tyro doesn't differentiate by role, so use a small middleware below.
```

**Conflict avoidance with Tyro's routes** — Tyro owns: `/login`, `/register`, `/logout`, `/password/*`, `/email/*`, `/otp/*`, `/auth/*`, `/two-factor/*`, `/mlogin`, `/lockout`, `/dashboard/*`, `/mess/*` is free. The `/set-password` route name has no conflict with Tyro's `/reset-password` (different path).

**After-login redirect for D-02 (admin→/home, user→/my, super-admin→/dashboard)**: Tyro's `redirects.after_login` env (`TYRO_LOGIN_REDIRECT_AFTER_LOGIN`) is a single value. To route by role, override it with a small closure in `routes/web.php`:

```php
// In a service provider's boot() method
use Illuminate\Support\Facades\Auth;
config(['tyro-login.redirects.after_login' => function () {
    $user = Auth::user();
    if ($user?->hasRole('super-admin')) return '/dashboard';
    if ($user?->hasRole('admin')) return '/home';
    if ($user?->hasRole('user')) return '/my';
    return '/';
}]);
```

(Note: `config()` accepts a closure for the value; Tyro Login's `LoginController::login()` and `RegisterController::register()` call `redirect()->intended(config('tyro-login.redirects.after_login', '/'))`, which supports closures via `intended()`.)

---

## 9. Composer require / dev workflow

### Verdict: CONFIRMED — `composer require owen-it/laravel-auditing` is the correct command for Laravel 13. Publish step is below.

### Install + publish sequence

```bash
# 1. Install
composer require owen-it/laravel-auditing

# 2. Publish the audits migration
php artisan vendor:publish --provider="OwenIt\Auditing\AuditingServiceProvider" --tag="migrations"
# This creates: database/migrations/{timestamp}_create_audits_table.php

# 3. (Optional) Publish the config for customization
php artisan vendor:publish --provider="OwenIt\Auditing\AuditingServiceProvider" --tag="config"
# This creates: config/audit.php

# 4. Run migrations
php artisan migrate
```

### Why `composer require` works for Laravel 13

- The package's `composer.json` declares `illuminate/database` and `php` constraints compatible with Laravel 13 (verify exact version on Packagist: <https://packagist.org/packages/owen-it/laravel-auditing>).
- The package auto-registers its service provider via Laravel's package discovery (the package's composer.json has the `extra.laravel.providers` key).

### Sources

- <https://packagist.org/packages/owen-it/laravel-auditing>
- <https://laravel-auditing.com/> (official docs)

---

## 10. PHPUnit 12 with `RefreshDatabase` for feature tests

### Verdict: CONFIRMED — `RefreshDatabase` is in the standard Laravel 12/13 test skeleton. Use it in feature tests that touch the DB.

### Test base class

Local: `tests/TestCase.php` is the standard `Illuminate\Foundation\Testing\TestCase` wrapper. Extend it for both Unit and Feature tests.

### Setting up a super-admin / mess in `setUp()`

```php
// tests/Feature/Auth/LoginTest.php
namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\Mess;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        config(['app.active_mess_id' => 1]); // for global mess scope

        // Create Tyro roles (the package's TyroSeeder does this, but tests are isolated)
        Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
        Role::firstOrCreate(['slug' => 'user'], ['name' => 'User']);

        // Create a mess
        Mess::factory()->create(['id' => 1, 'name' => 'Test Mess', 'status' => 'active']);
    }

    public function test_super_admin_can_log_in(): void {
        $user = User::factory()->create(['email' => 'admin@test.com']);
        $user->assignRole(Role::where('slug', 'super-admin')->first());

        $response = $this->post('/login', [
            'email' => 'admin@test.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('/dashboard'); // or intended URL
        $this->assertAuthenticatedAs($user);
    }
}
```

### Best practice: actingAs vs. going through the login route

- **Going through the login route** is the right approach for **auth-flow tests** (login redirects, 2FA gating, lockout, role-based post-login redirects). This is what the user actually experiences.
- **Using `actingAs($user)`** is the right approach for **functional tests** of protected endpoints (e.g. `GET /home` as a manager — just verify it returns 200 and the right view). It bypasses login and tests the downstream behavior in isolation.
- For Phase 1, the recommended test set:
  1. `test_super_admin_can_log_in` — go through the route, verify redirect to `/dashboard`.
  2. `test_manager_is_redirected_to_home_after_login` — go through route, verify redirect to `/home` and 200.
  3. `test_member_is_redirected_to_my_after_login` — go through route, verify redirect to `/my`.
  4. `test_login_is_locked_after_5_failed_attempts` — go through route 5 times with bad password, verify lockout.
  5. `test_home_page_returns_200_for_manager` — `actingAs($manager)`, hit `/home`, assert 200.
  6. `test_audit_entry_is_written_when_mess_is_updated` — `actingAs($admin)`, hit `PATCH /mess/settings`, assert `Audit::where(...)` row exists.

### Tyro's TyroSeeder in tests

- The Tyro seeder (`vendor/hasinhayder/tyro/database/seeders/TyroSeeder.php`) calls `RoleSeeder`, `PrivilegeSeeder`, `UsersSeeder`. Tests should NOT call this seeder directly — instead, `Role::firstOrCreate(...)` in setUp is faster and more controlled.

### Note on D-16 (sqlite for tests)

D-16 says tests use `sqlite :memory:` (see `phpunit.xml` lines 22-23). The MySQL-specific gotchas (fulltext, JSON column types, transaction isolation) are deferred to manual UAT in Phase 5. For Phase 1, the only MySQL-specific concern is the `DECIMAL(10,2)` column type — both sqlite and MySQL handle this identically, so the cast works in tests.

### Citations

- Laravel 12/13 testing docs: <https://laravel.com/docs/13.x/testing>
- Local: `phpunit.xml` (already has `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:` for tests)
- Local: `composer.json` line 23: `"phpunit/phpunit": "^12.5.12"` (installed: `12.5.30`)
- Local: `tests/Feature/ExampleTest.php` (uses `Tests\TestCase` base class)

---

## 11. Asia/Dhaka timezone in Laravel 13 (D-23)

### Verdict: PARTIALLY CONFIRMED — `APP_TIMEZONE` is **not** read by `config/app.php` by default. The current value is hardcoded to `'UTC'` at `config/app.php` line 64. The env-based override must be added.

### The problem

`config/app.php` line 64:
```php
'timezone' => 'UTC',
```

This is hardcoded. D-23 says `APP_TIMEZONE=Asia/Dhaka` in `.env` should drive the value. The fix is one line in `config/app.php`:

```php
'timezone' => env('APP_TIMEZONE', 'UTC'),
```

### Why it works

Laravel's `Date::now()` and `Carbon::now()` both honor the app timezone, which is set at runtime from `config('app.timezone')` during the framework bootstrap. The framework calls `date_default_timezone_set()` with this value. The default `UTC` was the pre-Laravel-11 convention; the `env()` override is the modern pattern (recommended since Laravel 9+).

### Carbon defaults

Carbon's `now()` is timezone-aware and uses PHP's default timezone, which is set by Laravel from `config('app.timezone')`. So once `config/app.php` reads `env('APP_TIMEZONE', 'UTC')`, all `Carbon::now()`, `now()`, and `Date::now()` calls return Asia/Dhaka time.

To set Carbon's default explicitly (optional, for clarity):

```php
// In AppServiceProvider::boot()
\Carbon\Carbon::setLocale('en'); // Carbon locale, not app locale
\Carbon\Carbon::now();           // Triggers PHP default timezone
```

### Citations

- `config/app.php` line 64: `'timezone' => 'UTC'` (current hardcoded value)
- `vendor/hasinhayder/tyro-dashboard/.planning/research/PITFALLS.md` PITFALLS #5 (Timezone — "set `APP_TIMEZONE=Asia/Dhaka` in `config/app.php`")
- Laravel 13 docs: <https://laravel.com/docs/13.x/configuration#timezone-configuration>
- Carbon docs: <https://carbon.nesbot.com/docs/#api-carbon>

### .env change

```
APP_TIMEZONE=Asia/Dhaka
```

### Code change

```php
// config/app.php line 64
'timezone' => env('APP_TIMEZONE', 'UTC'),
```

---

## 12. MySQL-specific gotchas for the schema (D-18, D-19)

### Verdict: CONFIRMED — all three syntaxes are Laravel 13 compatible and MySQL 8 compatible.

### `foreignId('mess_id')->constrained('messes')->cascadeOnDelete()`

- This is the canonical Laravel 11+ / 12 / 13 syntax. Translates to:
  ```sql
  ALTER TABLE members
  ADD COLUMN mess_id BIGINT UNSIGNED NOT NULL,
  ADD CONSTRAINT fk_members_mess_id FOREIGN KEY (mess_id) REFERENCES messes(id) ON DELETE CASCADE;
  ```
- For nullable: `foreignId('mess_id')->nullable()->constrained('messes')->nullOnDelete()`.
- Both work on MySQL 8.0+.

### `DECIMAL(10,2)` migration syntax

```php
$table->decimal('monthly_rent', 10, 2);
$table->decimal('monthly_rent', 10, 2)->default(0);
$table->decimal('meal_value_breakfast', 4, 2);  // for small decimals
```

Laravel's `decimal()` method is a direct passthrough to MySQL's `DECIMAL(M, D)` syntax. Compatible with MySQL 8+.

### `id()` primary key behavior with MySQL 8

`$table->id()` resolves to:
```sql
`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
```

- MySQL 8 default storage engine is **InnoDB** (since MySQL 5.7).
- BIGINT UNSIGNED supports up to 2^64 - 1 rows — effectively unlimited for our use case.
- The `personal_access_tokens` migration in the project (`database/migrations/2026_06_15_225413_create_personal_access_tokens_table.php`) already uses `id()` — confirms the syntax works.

### `mess_id` index requirement (D-19)

D-19 says: "Every domain table has `mess_id` (foreign key to `messes.id`, indexed)."

- `foreignId('mess_id')` automatically creates an index when paired with `constrained()` (the index is the FK constraint itself).
- For a non-FK index (e.g. when you want a non-constrained index for performance), add `->index()` explicitly: `foreignId('mess_id')->index()`.

### Citations

- Laravel 13 schema docs: <https://laravel.com/docs/13.x/migrations#creating-columns>
- Local: `database/migrations/0001_01_01_000000_create_users_table.php` lines 15-22 (uses `$table->id()`)
- Local: `database/migrations/0001_01_01_000000_create_users_table.php` line 33 (`foreignId('user_id')->nullable()->index()`) — confirms the syntax is in use in this project.

---

## Validation Architecture

The Phase 1 plans (1.1 base/foundation, 1.2 auth/Tyro, 1.3 mess config/auditable/Form Requests) can use the following measurable checks as UAT/test criteria. These are scoped to Phase 1: auth, mess config, settings, audit log, migrations, time zone, decimal money.

### Input

1. **`StoreMessConfigRequest` rejects invalid data**: POSTing to mess config with `monthly_rent = "not-a-number"` returns 422 with `monthly_rent` field error (Form Request validation).
2. **`StoreMessConfigRequest` enforces `mess_id` on the resource**: A `Setting` row without a `mess_id` fails `required` validation when stored via the Tyro `settings` resource form.
3. **`UpdateMessRequest` authorize() returns false for member role**: Acting as a `user`-role member, PATCH to `/mess/settings` returns 403 (not 200, not redirect).

### State

1. **`Mess` model uses the `Auditable` trait**: `Mess::first()->audits` returns a `MorphMany` relation; updating the mess's `name` writes an `audits` row with non-null `old_values` and `new_values` JSON.
2. **Global mess scope applies on all domain models**: With `config('app.active_mess_id') = 1`, `Member::all()` (when `Member` model exists in Phase 2) returns only rows with `mess_id = 1`; setting `config('app.active_mess_id') = null` returns all rows.
3. **`Mess::withoutGlobalScope(MessScope::class)->count()` bypasses the scope**: The count differs from `Mess::count()` when multi-mess data is seeded.

### Output

1. **Mess config edit page renders for manager**: GET `/mess/settings` as a `user` with `admin` role returns 200 and contains the form fields `name`, `address`, `monthly_rent`, `manager_contact`, `status`.
2. **BDT formatting helper**: `bdt(1234.5)` returns a string containing `৳` and `1,234.50` (or similar locale-formatted output).
3. **Audit log page renders**: GET `/mess/audit` as `admin` returns 200 with a table of audit rows (old_values, new_values, user, timestamp).

### Integration

1. **Tyro 2FA blocks manager login until verified**: After `TYRO_LOGIN_2FA_ENABLED=true` and `TYRO_LOGIN_2FA_FORCED_ROLES=admin,super-admin`, logging in as a `admin` user without `two_factor_confirmed_at` set redirects to `/two-factor/setup`, not `/home`.
2. **Tyro lockout triggers after 5 failed attempts**: 5 wrong-password POSTs to `/login` from the same IP results in the 6th request returning 302 to `/lockout`.
3. **Migration runs on real MySQL without errors**: `php artisan migrate` against `devsroom_mess_management` MySQL DB creates all 14+ tables (Tyro's 6 + Laravel's 4 + Sanctum 1 + Phase 1's messes/settings/audits = ~14 tables) with `mess_id` on every domain table.

### Security

1. **Audit entries are append-only**: `Audit::find(1)->update(['old_values' => 'hacked'])` is not called anywhere in app code (grep verification: `grep -r "Audit::" app/` returns no update/delete calls).
2. **Routes are gated by role middleware**: GET `/home` as a `user`-role user returns 403; as a `admin`-role user returns 200.
3. **`password` field is `hashed` cast**: `User::find(1)->password` does not equal the plaintext password (cast applied); DB column stores a bcrypt hash.

### Performance

1. **Single-mess scope prevents N+1 on mess lookups**: A controller action that lists 50 members completes in < 100ms locally (single mess_id, no extra query).
2. **Audit trait writes are fast enough for Phase 1**: A `Mess::update(['name' => 'New'])` call completes in < 50ms locally (single audit row insert).
3. **No queries in Blade templates**: `grep -r "::query" resources/views/` returns no matches.

### Accessibility

1. **Login form has proper labels**: The Tyro Login Blade (`vendor/hasinhayder/tyro-login/resources/views/login.blade.php`) renders `<label for="email">` and `<input id="email">` pairs (verify in published view).
2. **Mess config form has visible field labels**: Each input has a `<label>` element associated via `for`/`id` (auto-generated by Tyro's field renderer from `'label' => '...'` config).
3. **Tab order is logical**: Manager can reach all form fields with keyboard-only navigation (Tyro's default form rendering preserves source-order tab order).

### Recovery

1. **Lockout auto-clears after 15 minutes**: After triggering lockout, waiting 15 minutes (or manually clearing the `tyro-login:lockout:{ip}` cache key) allows login attempts to resume.
2. **Forgot password flow works end-to-end**: POST `/forgot-password` with a valid email sends a reset link (visible in `storage/logs/laravel.log` since `MAIL_MAILER=log`); GET `/reset-password/{token}` renders the reset form; POST `/reset-password` with a new password logs the user in.
3. **Forgot super-admin password**: The artisan command `php artisan tyro:create-user --super-admin` (registered by `TyroServiceProvider`, see `vendor/hasinhayder/tyro/src/Providers/TyroServiceProvider.php` line 197) allows the user to bootstrap a super-admin if locked out. **Caveat**: this command requires the user to be on the server, not a user-facing recovery — but it's a valid "break-glass" recovery for ops.

---

## Open Questions

1. **D-22 super-admin creation** — CONTEXT says "no automated seeder, create via `php artisan tinker` after fresh install." Is that the only path, or should Phase 1 add a `tyro:create-super-admin` artisan command? **Recommendation**: add it as a Plan 1.2 deliverable (the `Tyro` package ships a `CreateUserCommand`, see `vendor/hasinhayder/tyro/src/Providers/TyroServiceProvider.php` line 197, but no super-admin-specific one). Open question for the planner: ship a custom command, or rely on `tinker`?

2. **D-04 invite flow** — Is the manager-only invite gate implemented as `role:admin` middleware (D-01 super-admin + admin both can invite?) or should it be `role:admin` only (not super-admin)? CONTEXT D-04 says "Manager creates member accounts" (D-01 admin = manager), and D-08 + D-15 say both `admin` AND `super-admin` can edit mess config. Recommended: invite route uses `role:admin` (Tyro's EnsureTyroRole with `*` wildcard via `super-admin`'s protected slug is allowed if explicitly listed; safer to use `['admin', 'super-admin']`).

3. **D-13 Settings storage — Tyro resource vs. custom controller** — Should the `settings` table CRUD be a Tyro resource (declared in `config/tyro-dashboard.php` `resources`) or a custom controller at `/mess/settings`? D-08 says "sub-resource" but D-13 says "scoped to mess_id" which is awkward in Tyro's flat config. Recommendation: declare a `settings` resource with `mess_id` as a `select` relationship field, filtered by the global scope; or use a custom controller. Open for planner to decide.

4. **D-04 set-password URL** — Should the `set-password` link go through the `signed` middleware (recommended for security) or a custom signed-token check? The `signed` middleware uses Laravel's built-in URL signing — works fine. Recommendation: use `URL::temporarySignedRoute(...)` + `->middleware('signed')`.

5. **`active_mess_id` config location** — `config('app.active_mess_id')` is a project extension to the default `config/app.php`. Acceptable, but the `config/app.php` file ships with framework keys only. Recommendation: add a `config/mess.php` config file for cleanliness. Open for planner to decide.

6. **MySQL JSON column support for `value` (D-13 settings EAV)** — CONTEXT D-13 says `value` is `json`. Laravel's `$table->json('value')` maps to MySQL's `JSON` type on MySQL 5.7+ (which MySQL 8 is). SQLite (for tests) stores JSON as TEXT, but the cast handles it transparently. No issue, but worth noting in the planner that tests won't exercise MySQL's JSON-path functions (e.g. `JSON_EXTRACT`).

7. **Tyro's built-in `tyro_audit_logs` table vs. `audits` (owen-it)** — D-09 + AUDIT-05 say "Domain audit log is separate from Tyro's user/role audit log." Tyro writes to `tyro_audit_logs` automatically on Login/Logout events (see `vendor/hasinhayder/tyro-dashboard/src/Providers/TyroDashboardServiceProvider.php` lines 76-96). Phase 1 should leave this enabled (for user/role audit) and use `audits` (owen-it) for Mess/Setting domain audit. Open for planner to confirm: keep Tyro's auto-audit on, don't disable it.

8. **`owen-it/laravel-auditing` exact version for Laravel 13** — The research verified the package exists and supports Laravel 11+/12+ via Packagist-style info, but the **exact pinned version** needs to be captured during `composer require`. The planner should let `composer require owen-it/laravel-auditing` resolve to the latest compatible version (likely v13.x) and pin in composer.json. No manual version-pinning needed unless v14+ breaks Laravel 13 compat.

9. **D-22 onboarding form on first `/dashboard` visit** — How is "onboarding" detected? `Mess::count() === 0`? If yes, the dashboard controller (`Tyro Dashboard`'s `DashboardController::index()`) needs an override or a redirect. Since Tyro's controller is package-internal, the cleanest path is a middleware on the `/dashboard` route group that checks `Mess::count() === 0 && auth()->user()->hasRole('super-admin')` and redirects to `/onboarding`. Open for planner to decide where to add the middleware.

---

## RESEARCH COMPLETE
