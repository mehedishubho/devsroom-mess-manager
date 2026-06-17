# Phase 2: Members + Daily Operations - Research

**Gathered:** 2026-06-17
**Status:** Ready for planning
**Purpose:** Verify the technical approach and surface blockers / pitfalls for the 5 Phase 2 plans.

**Source docs (read first):**
- `.planning/phases/02-members-daily-operations/02-CONTEXT.md` — 24 locked decisions
- `.planning/phases/02-members-daily-operations/02-UI-SPEC.md` — visual & interaction contracts
- `.planning/REQUIREMENTS.md` — MEM, MEAL, OFF, GUEST, BAZAR, FIXED, CAT, PERF sections
- `.planning/codebase/STACK.md` — installed packages, runtime versions
- `.planning/codebase/CONVENTIONS.md` — code style, model config, migrations, Form Requests
- `.planning/codebase/INTEGRATIONS.md` — Tyro, mail, cache, queue, session drivers
- `.planning/research/SUMMARY.md` — stack decisions, anti-features
- `.planning/research/PITFALLS.md` — top 5 critical pitfalls
- `.planning/research/ARCHITECTURE.md` — service-layer-no-repository, Form Requests
- `.agents/skills/tyro-dashboard/SKILL.md` — Tyro patterns (skim for relevant sections)
- `.agents/skills/laravel-best-practices/SKILL.md` — general Laravel 13 best practices

---

## 1. Existing model state (verified by reading the code)

All Phase 2 domain models **already have**:
- `#[Fillable]` attribute with the right columns
- `BelongsToActiveMess` trait (auto-fills `mess_id` from `Mess::activeId()`)
- `MessScope` global scope applied via the trait
- Correct `casts()` method (dates as `date`, money as `decimal:2`, booleans as `boolean`)
- Relations (`mess()`, `member()`, `category()`, etc.) on each model

**Missing in Phase 1 (Phase 2 adds):**
- `Auditable` trait on `Member`, `MealEntry`, `MealOffRequest`, `GuestMeal`, `Expense`, `ExpenseCategory` (per AUDIT-01, PERF-08 — Phase 1 only audited `Mess` and `Setting`; Phase 2 audits the rest).
- Relations from `Member` to its `mealEntries()`, `mealOffRequests()`, `guestMeals()`.
- `kind` column on `expense_categories` table (D-20 — schema reconciliation).
- Drop of `expense_type` column on `expenses` table (D-20).
- `Setting` and `Mess` audit log: verified wired in Phase 1. The `audits` table exists and `MessAuditableTest` passes.

**Schema gap verification** (re-read the migrations):
- `members` table: id, mess_id, user_id (nullable), name, mobile (nullable, 30), email (nullable), nid (50), profession (100), room_or_seat (50), joining_date, leaving_date, status (20, default 'active'), emergency_contact (100), photo_path, timestamps, softDeletes. Unique `(mess_id, email)`. Index `(mess_id, status)`. ✓
- `meal_entries` table: id, mess_id, member_id, date, breakfast, lunch, dinner, guest_breakfast, guest_lunch, guest_dinner, entered_by, timestamps. Unique on what? **Not on `(mess_id, member_id, date)`** — Phase 2 needs to add this for the upsert to work correctly. **PITFALL #1 (below).**
- `meal_off_requests` table: id, mess_id, member_id, from_date, to_date, reason, status (20), requested_at, acted_at, acted_by, timestamps, softDeletes. ✓
- `guest_meals` table: id, mess_id, member_id, guest_name, date, meal_type (single — see PITFALL #2), quantity, meal_value, charge_amount, entered_by, timestamps. ✓ (needs a tiny tweak: meal_type is `string`, not enum, so any value can be stored — fine for v1).
- `expense_categories` table: id, mess_id, name, slug, is_default, sort_order, timestamps. Unique `(mess_id, slug)`. **Missing: `kind` column (D-20).**
- `expenses` table: id, mess_id, expense_category_id (nullable), date, purchased_by (nullable), vendor (nullable), description (nullable), amount, expense_type (20, default 'bazar'), receipt_path (nullable), entered_by (nullable), timestamps. **To drop: `expense_type` column (D-20).** With the drop, `category.kind` carries the distinction.

---

## 2. PITFALLS (the 7 critical things to avoid)

### PITFALL #1: `meal_entries` lacks a unique key for upsert

**Problem:** The migration for `meal_entries` doesn't have a unique constraint on `(mess_id, member_id, date)`. The daily meal grid does an upsert (per MEAL-08 — "Bulk save persists all changes in a single transaction"). Without the unique key, `updateOrCreate` per row can race when 50 members save concurrently.

**Solution:** Add a new migration `2026_06_17_100000_add_unique_member_date_to_meal_entries.php` that adds `$table->unique(['mess_id', 'member_id', 'date'])`. The down() drops it. MySQL supports adding unique indexes via `ALTER TABLE` (online for InnoDB by default in 5.6+).

**Plan placement:** Plan 2.3 (the daily meal grid plan) adds this migration as part of the meal grid work, before the upsert logic.

### PITFALL #2: `guest_meals.meal_type` is a free string, not an enum

**Problem:** `guest_meals.meal_type` is a `string` column, which means the manager could enter "breakfast", "Breakfast", or "BREAKFAST" — the GUEST-02 charge calculation needs to be consistent.

**Solution:** Validate at the Form Request level: `Rule::in(['breakfast', 'lunch', 'dinner'])`. The model casts to a backed enum in v2 (deferred). For v1, the lowercase string is the source of truth. Add a tiny constant set `App\Support\MealType::BREAKFAST = 'breakfast'`, etc., so the views and services use one canonical string.

**Plan placement:** Plan 2.3 (or a small `app/Support/MealType.php` helper created in Plan 2.1) defines the constants. The GuestMeal form request in Plan 2.3 uses `Rule::in([MealType::BREAKFAST, ...])`.

### PITFALL #3: Photo upload on the form can blow up the validation response

**Problem:** Per MEM-07 / D-02, the photo input uses `<input type="file" accept="image/*" capture="environment">` and the photo is **optional** (D-03). If the user picks a 5 MB photo, Laravel's default validation is `'photo' => 'nullable|image|max:2048'` (max 2MB in KB). On failure, the entire form re-renders with the old values — including the photo — which means the photo is held in memory until the request completes.

**Solution:** Use Laravel's `Storage::disk('public')->putFile()` directly in the controller (not `storeAs`). On validation failure, do NOT repopulate the photo input. Form Request: `'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']`. On `store`, if the file is present, save to `photos/{$member->id}.{$ext}` and update `photo_path`. If absent or invalid, fall through (D-03 best-effort pattern). The form's `@error('photo')` only shows if the validation rule is set to hard-fail; if we go best-effort, the rule is `nullable` and the user gets no error — which is correct per D-03.

**Plan placement:** Plan 2.1 (Member CRUD).

### PITFALL #4: `expense_type` column drop will break the migration history

**Problem:** The `expenses` migration creates `expense_type`. If we just `dropColumn` in a new migration, the rollback path of the original migration is incomplete (you can't recreate the column in down() if it was dropped). This is fine for a v1 project, but we should still:
1. Create a new migration that drops `expense_type` AND adds no new columns to `expenses` (the kind lives on the category).
2. Update the original migration's `down()` to be a no-op or wrap the drop in a `Schema::hasColumn` check.
3. Update the `Expense` model: remove `expense_type` from the `#[Fillable]` array, remove the relation/method.

**Solution:** Two clean migrations:
- `2026_06_17_110000_add_kind_to_expense_categories.php` — adds `kind` enum (bazar, fixed, other) to `expense_categories`, default 'bazar', index.
- `2026_06_17_110100_drop_expense_type_from_expenses.php` — drops the column.

Update `Expense` model `#[Fillable]` to drop `expense_type` and the `pivot` index helper.

**Plan placement:** Plan 2.5 (Bazar + fixed expenses + categories).

### PITFALL #5: Default categories seeder needs to run idempotently

**Problem:** Per D-21, default categories ship via seeder. The seeder runs once at install. If it runs twice (e.g., re-running migrations fresh), it should not duplicate. The unique key is `(mess_id, slug)`, so `firstOrCreate` works.

**Solution:** `ExpenseCategorySeeder::run()` loops over the default list, calls `ExpenseCategory::firstOrCreate(['mess_id' => Mess::activeId(), 'slug' => $slug], ['name' => $name, 'kind' => $kind, 'is_default' => true, 'sort_order' => $i])`. Idempotent.

**Plan placement:** Plan 2.5 (the seeder ships in the categories task).

### PITFALL #6: `MealOffRequest` status enum is free-form, not constrained

**Problem:** The `status` column on `meal_off_requests` is `string` (20 chars). The model fillable includes it. D-15 says manager approval sets it to `approved` or `rejected`. Nothing prevents a write of "kinda-maybe".

**Solution:** Add a tiny enum-style PHP class `App\Support\MealOffStatus` with constants `PENDING = 'pending'`, `APPROVED = 'approved'`, `REJECTED = 'rejected'`. The Form Request validates with `Rule::in([...])`. The model can use PHP 8.1 backed enum in v2 (deferred).

**Plan placement:** Plan 2.4 (meal off approval).

### PITFALL #7: Live AJAX member search is a CSRF + JSON endpoint, not a form

**Problem:** Per D-06, member search uses "live debounced AJAX." The search box is a regular `<input>`, but the input fires an AJAX request to a JSON endpoint. CSRF for AJAX: send `X-CSRF-TOKEN` header from the meta tag (already in `layouts/app.blade.php` via `<meta name="csrf-token" content="{{ csrf_token() }}">`).

**Solution:** Endpoint `GET /mess/members/search?q=...` returns a Blade partial via `Response::make(view('mess.members._list', compact('members'))->render(), 200)` OR a JSON array. **Decision: return a Blade partial** (HTML fragment). Why: the rows contain `<x-status-pill>` and other Blade components — building the same HTML in JS would duplicate the markup. The endpoint is auth-protected (`auth` + `role:admin` + `EnsureMessExists`). The JS does `fetch('/mess/members/search?q=' + encodeURIComponent(q))` with `headers: { 'X-Requested-With': 'XMLHttpRequest' }` to get a 200, then `text()` (not `json()`), then `innerHTML` to the list container. 300ms debounce via a small inline script in the members index view.

**Plan placement:** Plan 2.1 (member CRUD).

---

## 3. Implementation patterns to reuse (verified from Phase 1 code)

### 3.1 Controller pattern (verified from `MessConfigController.php`)

```php
public function edit(): View
{
    $mess = Mess::firstOrFail();
    return view('mess.settings.edit', compact('mess'));
}

public function update(UpdateMessRequest $request): RedirectResponse
{
    $mess = Mess::firstOrFail();
    $mess->update($request->validated());
    return redirect()->route('mess.settings.edit')->with('success', __('Mess settings updated.'));
}
```

Phase 2 controllers follow this exactly: `FormRequest` → `validated()` → model update → redirect with flash.

### 3.2 Form Request pattern (verified from `UpdateMessRequest.php`)

```php
public function authorize(): bool
{
    $user = $this->user();
    return $user && ($user->hasRole('admin') || $user->hasRole('super-admin'));
}

public function rules(): array
{
    return [
        'name' => ['required', 'string', 'max:255'],
        // ...
    ];
}
```

Phase 2 form requests: `authorize()` returns true for the appropriate role. Rules use array syntax (Laravel 12+ style). No `messages()` override unless a custom error string is needed (none in Phase 2).

### 3.3 Blade view pattern (verified from `mess/settings/edit.blade.php`)

- `@extends('layouts.app')` for every custom view.
- `@section('content')` for the main content.
- Inline Tailwind classes (no `@apply` custom components in Phase 1).
- `__()` for every user-facing string.
- `@error` blocks for inline error display.

Phase 2 continues this exactly. The new `<x-*>` components (per UI-SPEC §3) are introduced to reduce duplication, but they coexist with inline Tailwind where it's clear.

### 3.4 Test pattern (verified from `InviteMemberTest.php`)

- `extends Tests\TestCase`, `use RefreshDatabase`.
- `$this->seedTyroRoles()` in `setUp()`.
- `User::factory()->create()` + `assignRole(Role::where('slug', 'admin')->first())`.
- For controller-only tests (bypassing HTTP routing): use Reflection to invoke the method directly with a `Request` created via `Request::create()`.
- For HTTP feature tests: `actingAs($user)->get(route(...))->assertOk()`.

Phase 2 tests use the same patterns. **No Pest** — PHPUnit 12 only (per taste and Phase 1 convention).

### 3.5 Audit log pattern (verified from `MessAuditableTest.php`)

`Mess` already has the `Auditable` trait. For new models:

```php
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Member extends Model implements AuditableContract
{
    use BelongsToActiveMess, HasFactory, SoftDeletes, Auditable;
    // ...
}
```

The `audits` table is already migrated. No new audit-related migrations needed.

### 3.6 Routes pattern (verified from `routes/web.php`)

- Manager routes under `Route::middleware(['auth', 'role:admin', EnsureMessExists::class])->group(...)`.
- Member routes under `Route::middleware(['auth', 'role:user'])->group(...)`.
- Resource routes use `Route::resource('mess.members', MemberController::class)->except(['show'])` for CRUD, or explicit `Route::get(...)` for non-resource routes.

Phase 2 follows the same.

---

## 4. File storage patterns

### 4.1 Local storage setup

- `php artisan storage:link` must be run (or already linked) for the public disk to be served. Phase 1 didn't verify this. **Plan 2.1 should run `php artisan storage:link` in its first task** to ensure photos and receipts are servable. Check the existing public/storage symlink exists; if not, create it.
- Photos go to `storage/app/public/photos/{member_id}.{ext}` (one file per member; updates replace the file via `Storage::delete()` then `putFileAs()`).
- Receipts go to `storage/app/public/receipts/{expense_id}.{ext}` (same pattern).

### 4.2 Validation size limits

- Photos: 2 MB max (per MEM-07). `max:2048` in KB.
- Receipts: 5 MB max (per BAZAR-02). `max:5120` in KB.

### 4.3 Image dimension checks (deferred)

- MEM-07 says "max 2MB, JPG/PNG/WEBP" — no dimension constraint. Phase 2 doesn't add an image-manipulation library (Intervention/Image) per the agent's discretion call in CONTEXT. Photos are stored as-uploaded. Phase 5 polish may add thumbnailing.

---

## 5. AJAX and client-side patterns

### 5.1 Live search debounce

Pattern (small inline script in `members/index.blade.php`):

```js
let timer;
const input = document.querySelector('[data-member-search]');
const list = document.querySelector('[data-member-list]');
input.addEventListener('input', () => {
    clearTimeout(timer);
    timer = setTimeout(async () => {
        const q = input.value.trim();
        const res = await fetch(`/mess/members/search?q=${encodeURIComponent(q)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        list.innerHTML = await res.text();
    }, 300);
});
```

No Axios needed for this. Fetch is built into all evergreen browsers. Axios (already installed per `package.json`) can be used elsewhere but Phase 2 doesn't require it.

### 5.2 Quick action dropdowns (meal grid)

Native `<details>`/`<summary>` for the trigger, no JS for the open/close. The action handlers do need JS (to update the row's checkboxes):

```html
<details>
    <summary>⋯</summary>
    <ul role="menu">
        <li><button type="button" data-quick-action="all-on" data-row="<id>">All on</button></li>
        <!-- ... -->
    </ul>
</details>
```

JS: on click of `[data-quick-action]`, find the row's checkboxes, set them per the action.

### 5.3 Preset buttons (meal grid)

JS: on click of `[data-preset="all-3"]`, set all editable checkboxes (skip meal-off rows) to checked. On `[data-preset="all-0"]`, set all to unchecked.

### 5.4 Date nav

Pure HTML: a `<form method="GET">` with the date input. On change (`onchange="this.form.submit()"`), submits. No JS framework needed.

---

## 6. Database considerations

### 6.1 MySQL charset and collation

Default Laravel 13 MySQL charset is `utf8mb4` with `utf8mb4_unicode_ci` collation. The existing migrations use this default. Phase 2 doesn't change it.

### 6.2 Soft deletes

- `Member` already has `SoftDeletes`. ✓
- `MealOffRequest` already has `SoftDeletes`. ✓
- No other Phase 2 models need soft deletes (meal entries, expenses, guest meals are append-only — corrections are via new rows, not deletions).

### 6.3 Indexing

The existing indexes are sufficient for Phase 2. The only new index is the unique `(mess_id, member_id, date)` on `meal_entries` (PITFALL #1). No other new indexes.

### 6.4 Transactions

The meal grid save uses `DB::transaction(function () { ... })` to ensure all rows save or none do (MEAL-08). The expense form save uses a simple save — no transaction needed. The meal off approval uses a single update — no transaction.

---

## 7. Time zone and date handling

- App time zone: `Asia/Dhaka` (validated in Phase 1.1).
- All dates use `Carbon::parse(...)->setTimezone('Asia/Dhaka')` or rely on Laravel's default app time zone.
- The date input on the meal grid is `type="date"` (no time), so time zone is moot for entry, but the saved date is interpreted as midnight Dhaka time.
- **PITFALL #8 (related)**: When the manager on a phone in Dhaka taps "today" at 11pm, the saved date is today (Dhaka). When the manager on a phone in NYC (visiting) taps "today", the saved date is today (NY). The meal grid should always use **the active mess's time zone** (Dhaka), not the user's. For v1, since there's only one mess and the time zone is `Asia/Dhaka` globally, this is fine. The grid uses `Carbon::now('Asia/Dhaka')->toDateString()` for the default date, not `now()->toDateString()`. This is **subtle but important** for the future when multi-time-zone is a thing.

**Plan placement:** Plan 2.3 (meal grid) uses `Carbon::now(config('app.timezone'))` for the default date. Documented in the plan.

---

## 8. Library and package decisions (Phase 2 additions)

| Need | Choice | Why | Plan |
|---|---|---|---|
| Photo upload validation | Laravel built-in (`image`, `mimes`, `max:2048`) | No extra package. | 2.1 |
| Image manipulation | None in v1 | D-23 agent's discretion. Photos are stored as-is. Phase 5 may add Intervention. | 2.1 |
| Date picker | HTML `<input type="date">` | Native, no library. iOS Safari and Android Chrome both support it well. | 2.3 |
| Quick action dropdown | Native `<details>`/`<summary>` | Keyboard-accessible, no JS. | 2.3 |
| File upload (receipts) | Laravel built-in (`file`, `image`, `max:5120`) | Same as photos. | 2.5 |
| AJAX | Native `fetch()` | No Axios needed for one endpoint. | 2.1 |
| CSV/PDF/Excel export | **NOT in Phase 2** | Per REQUIREMENTS RPT-07/RPT-08, Phase 4. | — |

**No new composer/npm packages needed for Phase 2.**

---

## 9. Performance considerations (PERF-04, PERF-11, PERF-12, MEAL-11)

- **MEAL-11 (grid < 100ms)**: use eager loading on `Member::active()->with('mealEntries')` so the grid is one query + one relation preload. The grid fetch should be 2-3 queries total, not 50. Verify with Laravel Debugbar in dev.
- **N+1 prevention**: the member list preloads `mess` and `user`. The meal off queue preloads `member`. The expense list preloads `category` and `purchasedByMember`.
- **No caching in Phase 2**: per CONTEXT, dashboard cards and live previews are Phase 3+. Phase 2 fetches live on every request.
- **No indexing beyond what's already in migrations**: confirmed above.

---

## 10. Security considerations (PERF-07, AUTH-06, OFF-07)

- **Form Requests** for all user input (PERF-07, Phase 1 convention).
- **Role middleware**: `role:admin` on all manager routes, `role:user` on all member routes. The `/my` page works for both, but the `/mess/*` routes are admin-only.
- **Mass assignment**: `#[Fillable]` on every model (Phase 1 convention).
- **CSRF**: `web` middleware group is on by default for web routes. AJAX search uses `X-CSRF-TOKEN` from the meta tag (or `X-Requested-With: XMLHttpRequest`, which Laravel's CSRF middleware also accepts as proof of same-origin).
- **File upload safety**: `mimes:jpg,jpeg,png,webp` restricts to known types. The `image` rule checks MIME. Filenames are sanitized to `member-{id}.{ext}` and `receipt-{id}.{ext}` — no user-controlled filenames stored.
- **Audit log**: all Phase 2 models get `Auditable` trait. Per AUDIT-01, every write creates an entry with user_id, action, before, after, timestamp, IP. Verify with a feature test.
- **Member data isolation**: members can only see their own data on `/my`. The `Member` global scope ensures `mess_id` is filtered. The `/my` controller uses `auth()->user()->member` to fetch the member, then scopes all child queries to that member's id.
- **No SQL injection risk**: all queries use Eloquent or query builder with parameter binding.

---

## 11. Phase 2 plan structure (the 5 plans + dependencies)

| Plan | Title | Wave | Depends on | Plan key tasks |
|---|---|---|---|---|
| 2.1 | Member CRUD (manager side) | 1 | — (Phase 1 done) | Models: add Auditable to Member; routes: `/mess/members` resource + search endpoint; controller: `MemberController` (resource) + `MemberSearchController`; views: index (cards on mobile, table on desktop), create, edit, profile, search partial; components: `<x-member-card>`, `<x-photo-input>`, `<x-status-pill>`; factories: `MemberFactory` add `inactive()`, `former()` states; tests: create, edit, soft-delete (inactive), search debounce, audit log writes. |
| 2.2 | Member self-view + meal off request | 1 | 2.1 (uses Member model) | Routes: `/my` extended with tabs (profile/meal-off/meals); controller: `MyController` (update to handle tabs), `MyMealOffController` (create/list for member); views: `my/index.blade.php` (replaces Phase 1 placeholder), `my/_profile.blade.php`, `my/_meal-off.blade.php`, `my/_meals.blade.php`; components: `<x-tab-nav>`; manager-on-behalf: add `POST /mess/members/{id}/meal-off` (controller action on `MemberController` or new `MemberMealOffController`); tests: member can view own profile, member can submit meal off (D-14, D-16), manager can submit on behalf, member cannot edit name/email/etc. (D-24), audit log writes. |
| 2.3 | Daily meal grid | 2 | 2.1 (uses Member) | Migration: add unique `(mess_id, member_id, date)` on `meal_entries` (PITFALL #1); routes: `/mess/meals` (GET grid, POST bulk save); controller: `MealGridController` (index with `?date=`, store with `entries[]` array); services: `MealGridService` (build grid data: active members + entries for date + meal-off overlap, apply presets); views: `mess/meals/index.blade.php`; components: `<x-mess-date-nav>`, `<x-meal-grid-row>`, `<x-meal-grid-checkbox>`, `<x-quick-action-dropdown>`, `<x-meal-off-badge>`; JS: presets, quick actions, save-all; tests: grid loads, save persists, meal-off rows disabled, presets respect meal-off, audit log writes, performance (N+1 prevention). |
| 2.4 | Meal off approval workflow | 2 | 2.2 (uses MealOffRequest model + member self-submit) | Routes: `/mess/meal-off` (GET queue with `#tab` fragments, POST approve, POST reject); controller: `MealOffApprovalController`; services: `MealOffApprovalService` (validate, set status, set acted_at/acted_by, set rejection reason); views: `mess/meal-off/index.blade.php` (collapsed/expanded cards), `mess/meal-off/_card.blade.php` partial; tests: approve works, reject requires reason (D-15, OFF-04), approved meal off deducts from grid (MEAL-07 cross-check), audit log writes. |
| 2.5 | Bazar + fixed expenses + categories | 2 | 2.1 (uses Member, lays foundation for Phase 3) | Migrations: add `kind` to `expense_categories`, drop `expense_type` from `expenses` (PITFALL #4); update `Expense` and `ExpenseCategory` models (drop `expense_type` from fillable, add `kind` to fillable + cast); routes: `/mess/expenses` (index), `/mess/expenses/bazar/create` (GET, POST), `/mess/expenses/fixed/create` (GET, POST), `/mess/categories` (index, store, destroy); controllers: `ExpenseController` (index + createBazar + storeBazar + createFixed + storeFixed), `ExpenseCategoryController` (index + store + destroy); services: `ExpenseService` (compute kind from category, validate amount > 0, validate date <= today), `ExpenseCategoryService` (prevent delete of default); views: `mess/expenses/index.blade.php`, `mess/expenses/bazar/create.blade.php`, `mess/expenses/fixed/create.blade.php`, `mess/categories/index.blade.php`; seeder: `ExpenseCategorySeeder` (D-21, PITFALL #5); tests: create bazar, create fixed, category dropdown filtered by kind (D-22), default category cannot be deleted (CAT-04), audit log writes. |

**Wave assignments:**
- **Wave 1 (parallel)**: 2.1 (Member CRUD) + 2.2 (Member self-view). 2.2 depends on 2.1's Member model getting relations, but the relations are small (one-line additions). To avoid serializing, **2.1 includes the Member relations as part of its first task**, and 2.2 references them.
- **Wave 2 (parallel)**: 2.3 (Daily meal grid) + 2.4 (Meal off approval) + 2.5 (Bazar + fixed + categories). 2.3 depends on 2.1 (Member). 2.4 depends on 2.2 (member self-submit) and 2.1. 2.5 depends on 2.1 (Member). All three can run in parallel after 2.1 + 2.2 complete.

**Phase 2.5 to 2.4 dependency note**: 2.4 (meal off approval) is independent of 2.5 (expenses). They can run in the same wave.

---

## 12. Test strategy (PERF-11, PERF-12)

- **Per plan, ~3-6 feature tests** in `tests/Feature/...`:
  - 2.1: `MemberCrudTest` (5 tests: create, edit, soft-delete, search, photo upload), `MemberAuditTest` (1 test).
  - 2.2: `MyProfileTest` (3 tests: view profile, edit emergency contact, cannot edit name), `MyMealOffTest` (3 tests: submit request, view own list, manager on-behalf).
  - 2.3: `MealGridTest` (4 tests: load grid, save all, meal-off row disabled, preset respects meal-off), `MealGridPerformanceTest` (1 test: N+1 prevention — assert query count).
  - 2.4: `MealOffApprovalTest` (4 tests: approve, reject with reason, reject without reason fails, queue tabs work), `MealOffAuditTest` (1 test).
  - 2.5: `ExpenseTest` (5 tests: create bazar, create fixed, list expenses, validation), `ExpenseCategoryTest` (4 tests: list, create, delete custom, cannot delete default).
- **Total: ~30 new feature tests** in Phase 2. Combined with Phase 1's 38 tests, that's ~68 tests total.
- **No unit tests** beyond what Phase 1 has — service classes are tested via feature tests.
- **Pint runs clean** on every commit (PERF-13).

---

## 13. Cross-phase consistency checks (the 5 things that must match Phase 1)

1. **mess_id resolution**: every `mess_id` column write uses `Mess::activeId()` (taste preference, Phase 1.4 fix).
2. **Form Requests, not inline validation**: every controller action uses a Form Request (PERF-07, Phase 1 convention).
3. **Anonymous migrations**: every new migration is an anonymous class with `up()` + `down()` + `Blueprint` typed param (Phase 1 convention).
4. **Attribute-based model config**: `#[Fillable]`, `#[Hidden]`, `casts()` method (Phase 1 convention).
5. **`__()` everywhere**: every user-facing string in Blade is wrapped (PERF-03, Phase 1 convention).

---

## 14. Open questions / decisions deferred to the agent

- **Exact photo aspect ratio** (D-23 agent's discretion): not constrained. Store as-is.
- **Image manipulation library** (D-23): not added. Photos stored as-is.
- **AJAX response format** (PITFALL #7): chosen **Blade partial HTML**, not JSON.
- **Quick action icons** (D-23): `⋯` (three dots) — universally understood.
- **File storage subdirectory layout**: `photos/`, `receipts/` (D-23).
- **Default category slugs**: kebab-case, e.g. `rice`, `fish`, `meat`, `vegetables`, `oil`, `gas`, `other`, `rent`, `cook-salary`, `internet`, `electricity`, `water`, `gas-refill`, `maintenance`, `cleaning`, `others`. (D-21, D-23.)
- **Date range picker vs two date inputs** (D-23): two separate `from` and `to` `<input type="date">` — simpler, mobile-friendly. (Native range pickers are inconsistent on mobile.)
- **Form layout breakpoints** (D-23): 375px baseline; 2-column at `sm:` (640px+). Per Phase 1 conventions.

---

## 15. Validation architecture (Nyquist — informational)

Nyquist validation is disabled in this project (`workflow.nyquist_validation: false` per `.planning/config.json`). VALIDATION.md is therefore not required for Phase 2. Test strategy in §12 above serves as the validation contract.

If Nyquist is enabled in the future, the test set in §12 would be the source of truth for the validation gates.

---

## RESEARCH COMPLETE
