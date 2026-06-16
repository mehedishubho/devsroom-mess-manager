# Phase 2: Members + Daily Operations - Context

**Gathered:** 2026-06-17
**Status:** Ready for planning

<domain>
## Phase Boundary

Manager can run a full day of mess operations on a phone — manage members, enter meals via bulk grid, approve meal off, log bazar, record fixed expenses. Member can view their own profile and request meal off for a date range. The 5 plans in the roadmap deliver this slice end-to-end (MEM, MEAL, OFF, GUEST, BAZAR, FIXED, CAT requirement groups). Payments, advance balance, month-close, reports, and dashboard cards are all Phase 3+ and out of scope.

</domain>

<decisions>
## Implementation Decisions

### Member photo upload (MEM-07)

- **D-01:** Member photos stored on **local storage** (`storage/app/public` disk). Standard Laravel default. The `public/storage` symlink is already standard in this skeleton.
- **D-02:** Photo upload UI is **mobile-first with native camera support**: tap a circle, opens camera on phone, file picker on desktop. Uses HTML `<input type="file" accept="image/*" capture="environment">` — no extra JS library needed.
- **D-03:** Photo is **optional**. If upload fails or file is too large, the form keeps the rest of the data; the member is created/updated without a photo. Manager can add/replace it later from the Edit Member page. (Recommended best-effort pattern from PROJECT.md.)
- **D-04:** Photo can be uploaded on **Create Member AND on Edit Member** (replace). Members can also replace their own photo from `/my/profile` per MEM-05.

### Member form fields (MEM-01)

- **D-05:** Keep the **single `room_or_seat` text field** (no split into two columns). Bangladesh messes often use combined labels like `R-101 / Seat-A` or `3rd Floor, R-12`; one flexible field is enough. Free-form text input.

### Member search (MEM-04)

- **D-06:** Member search uses **live debounced AJAX**. Type-as-you-go, ~300ms debounce, server-side LIKE query on name/mobile/email/room. Returns to the same list view with results in place. Modern phone UX; no page reload.

### Daily meal grid UX (MEAL-01 to MEAL-11)

- **D-07:** Grid saves via a **single "Save all" button at the bottom**. Manager checks/unchecks across all members, taps once. All changes go in one transaction. Matches the "60 clicks → 4" workflow in PROJECT.md.
- **D-08:** Date navigation = **◀ Today ▶ + a date picker** for jump. Mobile-friendly. "Today" pill is the default. Disabled prev/next at month boundaries (no boundary, actually allow any date).
- **D-09:** Grid shows **only active members** (`status=active`). Inactive and former members are excluded entirely. Faster on phone, focused on the daily task.
- **D-10:** Members with **approved meal off** are shown grayed out, checkboxes disabled, with an "On meal off until MM-DD" badge. Manager can SEE the deduction but cannot override. (Matches OFF-05.)
- **D-11:** Preset behavior: **"Mark all 3 meals"** and **"Mark all 0 meals"** (full grid) plus **per-row quick actions** "All on" / "All off" / "Breakfast only" / "Lunch only" / "Dinner only" (MEAL-06).
- **D-12:** Per-member quick actions and grid presets **respect meal-off state** — meal-off rows are skipped by presets (cannot be force-set).

### Meal off request flow (OFF-01 to OFF-07)

- **D-13:** **Deduct on approval**, not on the day itself. When manager approves, the deduction shows in the grid from the approval moment forward (including today). Simplest semantics; matches OFF-06 "auto-deducts from that member's meal count for the date range" as a runtime computation.
- **D-14:** **Both members and manager can submit meal off requests**. Member self-submits via `/my` (primary path). Manager can submit on a member's behalf from the member profile (e.g. member calls). All requests go through the same approval flow.
- **D-15:** Manager approval UI = **one-by-one with required rejection reason**. Tap a request to expand, then Approve / Reject. Rejecting opens a reason field (required, OFF-04). No bulk approve (mobile-friendly single-record flow).
- **D-16:** **Required reasons** for meal off: reason is required on submit (member or manager) — see OFF-01.

### Guest meal charge rate (GUEST-02)

- **D-17:** `guest_meal.charge_amount` = **configured meal_value × quantity** at entry time. Manager picks the meal_type, system multiplies by the mess setting (breakfast=0.5, lunch=1, dinner=1) and stores the result. The charge is **locked at entry** — month-close does not re-compute it. Predictable, simple, and matches the meal_value=0.5/1/1 default.

### Bazar & fixed expenses (BAZAR-01 to BAZAR-06, FIXED-01 to FIXED-04)

- **D-18:** `expenses.purchased_by` is a **member dropdown** (FK to members, nullable). Manager picks the mess member who physically went to bazar. Optional — leaving blank is OK. Matches the existing schema.
- **D-19:** Receipt image upload uses the **same pattern as member photos**: local storage (`storage/app/public/receipts/`), mobile-first with camera support, optional, best-effort. JPG/PNG, max 5MB per BAZAR-02. No receipt shows a "no receipt" badge in lists/reports (BAZAR-06).

### Expense categories (CAT-01 to CAT-04)

- **D-20:** **Reconcile the schema**: add a `kind` enum column (`bazar`, `fixed`, `other`) to `expense_categories`. **Drop `expense_type` from `expenses`** (replaced by category.kind). The bazar/fixed distinction lives on the category, not on the expense. Migration adds `kind` to `expense_categories`, drops `expense_type` from `expenses`. Update `ExpenseCategory` model to include `kind` in fillable + cast to enum.
- **D-21:** Default categories ship via **seeder** (rice, fish, meat, vegetables, oil, gas, other — all `kind=bazar`) plus rent, cook_salary, internet, electricity, water, gas_refill, maintenance, cleaning, others (`kind=fixed`). Defaults are marked `is_default=true` and **cannot be deleted** (CAT-04). Manager can create custom categories with any kind.
- **D-22:** Expense form picks category from a dropdown **filtered by expense kind** at form-load (bazar form shows bazar categories, fixed-expense form shows fixed categories). Single kind switcher is out of scope for v1.

### Member self-view (MEM-05, MEM-06, OFF-01)

- **D-23:** `/my` page in Phase 2 contains: **profile (view + edit photo, password, emergency_contact) + meal history (own meal_entries, own guest_meals) + meal off request form + own meal off request list**. No current-month bill preview (PREVIEW-03 is Phase 3). No member dashboard cards (DASH-04 is Phase 4). Just the minimum member self-service slice for Phase 2.
- **D-24:** Members cannot edit their own `name`, `email`, `mobile`, `room_or_seat`, `joining_date` (per MEM-05 "edit limited fields"). They can edit: `photo_path`, `password`, `emergency_contact`. Manager edits the rest.

### the agent's Discretion

- Exact photo dimensions / aspect ratio / compression
- Whether to use a third-party image manipulation library (Intervention/Image) or rely on Laravel's built-in image handling
- Exact AJAX endpoint shape for live member search (path, response format)
- Quick action icons / button labels (as long as they read clearly in English + use `__()`)
- File storage subdirectory layout (`photos/`, `receipts/`, etc.) under `storage/app/public/`
- Default category list exact slugs (kebab-case, unique per mess) — see D-21
- Whether the meal off request form uses a date range picker or two separate from/to date inputs
- Form layout breakpoints for the member list and form (375px baseline per Phase 5 polish)

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Project context
- `.planning/PROJECT.md` — Vision, constraints, key decisions, adopted recommendations (esp. "Treat meal consumption as a derived ledger" and "60 clicks → 4")
- `.planning/REQUIREMENTS.md` — 154 v1 requirements (MEM, MEAL, OFF, GUEST, BAZAR, FIXED, CAT, PERF sections relevant to Phase 2)
- `.planning/ROADMAP.md` — Phase 2 success criteria and out-of-scope items
- `.planning/STATE.md` — Current progress, validations from Phase 1

### Prior phase context
- `.planning/phases/01-foundation/01-CONTEXT.md` — 24 locked Phase 1 decisions (D-01 to D-24)
- `.planning/phases/01-foundation/01.3-SUMMARY.md` — what was built in Plan 01.3 (layout, controllers, services patterns)
- `.planning/phases/01-foundation/01.1-SUMMARY.md` — base schema, models, scopes, factories, test setup
- `.planning/phases/01-foundation/01.2-SUMMARY.md` — auth, roles, post-login redirect closure
- `.planning/phases/01-foundation/01.4-fix-mess-id-mismatch.md` — fixes from Phase 1.4 (member invitation flow)
- `.planning/phases/01-foundation/01.5-fix-onboarding-redirect.md` — fixes from Phase 1.5
- `.planning/phases/01-foundation/01-UAT.md` — Phase 1 verification status (13/15 pass, 1 blocked on email)

### Codebase maps (already in repo)
- `.planning/codebase/STACK.md` — Installed packages, runtime versions
- `.planning/codebase/CONVENTIONS.md` — Code style, attribute-based model config, migration style, test style, Form Request pattern
- `.planning/codebase/STRUCTURE.md` — Directory layout, where things go
- `.planning/codebase/INTEGRATIONS.md` — Tyro config, mail, cache, queue, session drivers

### Research
- `.planning/research/SUMMARY.md` — Stack decisions, anti-features, watch-out list
- `.planning/research/PITFALLS.md` — Top 5 critical pitfalls (timezone #5, decimal money #2)
- `.planning/research/ARCHITECTURE.md` — Service-layer-no-repository, Form Requests, Auditable trait
- `.planning/research/STACK.md` — Why owen-it/laravel-auditing, Chart.js, etc.

### Skills (project-local, used during implementation)
- `.agents/skills/tyro-dashboard/SKILL.md` — Tyro patterns, app integration, CRUD resources, sidebar overrides
- `.agents/skills/laravel-best-practices/SKILL.md` — General Laravel 13 best practices

### Taste preferences
- `.commandcode/taste/taste.md` — Laravel 13, MySQL, snake_case DB names, verify DB creds, **always use `Mess::activeId()`** (not `config('mess.active_mess_id')` directly)

### External package docs (to consult during research/planning)
- `owen-it/laravel-auditing` GitHub README — schema, config, model usage (already installed)
- Tyro Dashboard `config/tyro-dashboard.php` and `config/tyro-login.php` — role middleware
- Laravel file storage docs — `Storage::disk('public')` patterns, symlink (`php artisan storage:link`)

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `App\Models\User` (in `app/Models/User.php`) — Already has `HasTyroRoles`, `HasTwoFactorAuth`, `HasApiTokens`. Use as the base auth model. No changes needed.
- `App\Models\Mess` (`app/Models/Mess.php`) — Has the `Auditable` trait wired in. **Has the `activeId()` static helper** — use this for `mess_id` everywhere (taste preference D-04).
- `App\Models\Member` (`app/Models/Member.php`) — Already has `BelongsToActiveMess` trait, `SoftDeletes`, and the right fillable. Phase 2 wires Auditable trait + relations to MealEntry, MealOffRequest, GuestMeal, Expense.
- `App\Models\MealEntry` (`app/Models/MealEntry.php`) — Schema + casts in place. Phase 2 wires Auditable trait.
- `App\Models\MealOffRequest` (`app/Models/MealOffRequest.php`) — Schema + casts + SoftDeletes in place. Phase 2 wires Auditable trait.
- `App\Models\GuestMeal` (`app/Models/GuestMeal.php`) — Schema + casts in place. Phase 2 wires Auditable trait.
- `App\Models\Expense` (`app/Models/Expense.php`) — Schema + casts in place. **Phase 2 drops `expense_type` column** (D-20) and wires Auditable trait.
- `App\Models\ExpenseCategory` (`app/Models/ExpenseCategory.php`) — Schema + casts in place. **Phase 2 adds `kind` column** (D-20) and wires Auditable trait.
- `App\Models\Concerns\BelongsToActiveMess` — Trait applied to all 14 domain models. Auto-fills `mess_id` from `Mess::activeId()` on creating event.
- `App\Models\Scopes\MessScope` — Global Eloquent scope filtering by `mess_id = Mess::activeId()`. Applied to all domain models.
- `database/migrations/2026_06_16_220700_create_members_table.php` — members table with all MEM-01 fields, soft-deletes, unique `(mess_id, email)`.
- `database/migrations/2026_06_16_220800_create_meal_entries_table.php` — meal_entries with unique `(mess_id, member_id, date)`. Phase 2 wires `Auditable` trait on the model.
- `database/migrations/2026_06_16_220900_create_meal_off_requests_table.php` — meal_off_requests with soft-deletes.
- `database/migrations/2026_06_16_221000_create_guest_meals_table.php` — guest_meals with meal_value, quantity, charge_amount.
- `database/migrations/2026_06_16_221100_create_expense_categories_table.php` — **Phase 2 adds `kind` column** (D-20).
- `database/migrations/2026_06_16_221200_create_expenses_table.php` — **Phase 2 drops `expense_type` column** (D-20).
- `app/helpers.php` — `bdt()` helper available for BDT formatting in expense/bill views.
- `app/Http/Controllers/Mess/MessConfigController.php` — Reference pattern: Form Request → controller → redirect with flash.
- `app/Http/Controllers/Mess/MemberInviteController.php` — Reference pattern: User firstOrCreate, role assignment, MemberInvitation, Mailable.
- `app/Http/Controllers/HomeController.php` / `MyController.php` — Reference for manager + member home pages.
- `app/Http/Middleware/EnsureMessExists.php` — Applied to all manager routes; redirects to `/onboarding` if no mess.
- `app/Http/Requests/Mess/UpdateMessRequest.php` — Reference pattern: `authorize()` returns true for `admin || super-admin`.
- `app/Http/Requests/Mess/InviteMemberRequest.php` — Reference pattern: minimal Form Request with single field.
- `resources/views/layouts/app.blade.php` — Manager layout with top bar, sidebar nav, mobile drawer. **Reuse for all manager-facing pages** (members, meals, expenses).
- `resources/views/mess/members/invite.blade.php` — Reference for invite form pattern.
- `resources/views/mess/settings/edit.blade.php` — Reference for mess config form.
- `database/factories/MemberFactory.php` and 14 others — All factories stubbed. Phase 2 fills in states (e.g., `inactive()`, `former()`).
- `tests/TestCase.php` — Base class with `setUp()` setting `mess.active_mess_id = 1` and `seedTyroRoles()` helper.

### Established Patterns
- **Attribute-based model config**: `#[Fillable(['...'])]`, `#[Hidden(['...'])]` on models.
- **`casts()` method, not `$casts` property**.
- **Anonymous-class migrations** with `up()` and `down()`, `Blueprint` typed parameter.
- **Form Requests** for all input validation (`app/Http/Requests/`).
- **Test style**: PHPUnit 12, `test_` prefix, `void` return type, extends `Tests\TestCase`. Use `RefreshDatabase` for feature tests.
- **`__()` everywhere** for user-facing strings — even if English only.
- **snake_case columns, plural table names, `$table->timestamps()` on all tables**.
- **Direct controller invocation via Reflection** in tests to bypass CSRF (Phase 1 convention).
- **Tyro role checks**: `$user->hasRole('admin')` — use `admin` for manager, `user` for member.
- **Global mess scope** automatically filters all domain queries by `Mess::activeId()`. Use `Model::withoutGlobalScopes()` only at system boundaries (onboarding, set-password).

### Integration Points
- **Routes**: `routes/web.php` — Phase 2 adds manager routes under the `role:admin` + `EnsureMessExists` middleware group (e.g. `/mess/members/*`, `/mess/meals/*`, `/mess/meal-off/*`, `/mess/expenses/*`, `/mess/categories/*`). Member routes under `role:user` group (e.g. `/my/profile`, `/my/meal-off`, `/my/meals`).
- **Sidebar nav**: `resources/views/layouts/app.blade.php` — Phase 2 adds links: "Members", "Daily meals", "Meal off", "Expenses". Insert in the existing nav between "Mess settings" and "Audit log" (or before, depending on flow).
- **Member-facing nav**: Members currently have no nav. Phase 2 may add a minimal nav on `/my` (Profile, Meal off, History) or rely on tabbed views. Per agent discretion.
- **`.env`**: already has `DB_CONNECTION=mysql`, `DB_DATABASE=devsroom_mess_management`. No new env keys required.
- **Storage**: `php artisan storage:link` must be run (or already linked) for the public disk to be served.
- **Tyro `audits` table**: owen-it/laravel-auditing already publishes and runs the `audits` table. Domain models wire `Auditable` trait as they're built.
- **Mail**: Phase 1 mail driver is `log`. Meal-off approval notifications can use the same `log` driver for now (email-not-real in dev). Real notification delivery is Phase 3 (NOTIF-01 to NOTIF-05).

</code_context>

<specifics>
## Specific Ideas

- **Members list** (`/mess/members`): responsive table on desktop, cards on phone. Avatar + name + room + status pill. Search bar at top with live AJAX (D-06). "Add member" button → opens member form.
- **Member form** (`/mess/members/create`, `/mess/members/{id}/edit`): stacked single-column layout on phone, two-column on desktop. Fields in order: name, mobile, email, nid (optional), profession, room_or_seat, joining_date, status, emergency_contact, photo (circular preview + camera button). Cancel + Save at the bottom.
- **Member profile view** (`/mess/members/{id}`): same data as form, read-only with Edit button. Shows member's meal history (last 30 days), meal off requests, and any guest meals. Manager can submit meal off on behalf from this page (D-14).
- **Daily meal grid** (`/mess/meals`): default date is today. Date nav at top (◀ Today ▶ + date picker). Active members in rows, columns are B/L/D checkboxes + 3 quick-action dropdowns. Members on meal off are grayed and disabled with badge. "Save all" button at the bottom (sticky on mobile). Preset buttons at top: "All 3 on", "All off".
- **Meal off approval queue** (`/mess/meal-off`): list of pending requests (collapsed cards by default). Tap to expand → member name, date range, reason, Approve / Reject buttons. Reject opens inline reason field.
- **Bazar entry form** (`/mess/expenses/bazar/create`): date, category dropdown (filtered to bazar kind, D-22), purchased-by member dropdown (D-18, optional), vendor (optional), description, amount, receipt (D-19, optional). Save at the bottom.
- **Fixed expense form** (`/mess/expenses/fixed/create`): date, category dropdown (filtered to fixed kind), description, amount. No purchased-by (rent goes to the landlord, not a member).
- **Expense category manager** (`/mess/categories`): list of categories with kind pill, is_default lock icon. Add custom category button.
- **Member `/my` page** (`/my`): profile card (photo, name, room, mobile, emergency contact) with Edit button → opens profile edit (D-24). Meal off request form (date range + reason). List of own meal off requests with status pills. Tab or section for "My meals" — read-only list of own meal_entries for the current month.

</specifics>

<deferred>
## Deferred Ideas

- **Receipt OCR / auto-categorization** — out of v1 scope (PROJECT.md anti-recommendation).
- **Calendar view of meals** — grid is enough for v1 (PROJECT.md out-of-scope).
- **Member self-bill preview** — moves to Phase 3 (PREVIEW-03).
- **Member dashboard cards** — moves to Phase 4 (DASH-04).
- **Per-meal-type custom rates** — out of scope; 0.5/1/1 default only.
- **Member-submitted bazar expenses** — manager-only in v1 (PROJECT.md out-of-scope).
- **2FA enforcement for member role** — currently admin only (D-05 from Phase 1). Deferred to v2.
- **Member-side live AJAX** — only manager gets live search in Phase 2; member self-view is page-based.
- **Inventory / stock tracking** — out of scope entirely (different product).
- **Cook/maid management** — out of scope entirely.

</deferred>

---

*Phase: 02-members-daily-operations*
*Context gathered: 2026-06-17*
</content>
</invoke>