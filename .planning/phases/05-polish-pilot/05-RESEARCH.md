# Phase 5: Polish + Pilot — Research

**Researched:** 2026-06-18
**Domain:** Laravel 13 hardening (perf tooling, seeder, mobile polish, deployment, mechanical audits)
**Confidence:** HIGH

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions (D-01..D-23 — research THESE, not alternatives)

**Pilot logistics (success #12):**
- D-01: Pilot mess = a real mess the dev has direct access to (own/family/close contact). No cold outreach.
- D-02: Fresh start, current month — NO historical importer (importer is post-pilot).
- D-03: Hybrid onboarding — dev configures mess + members + settings; manager runs daily ops. Manager NOT expected to self-serve setup.
- D-04: Success bar = one clean month-close completes + members view their own bills + zero data-loss/math bugs. NOT two cycles, NOT formal sign-off.
- D-05: Feedback channel = direct WhatsApp/call with the manager. NO in-app feedback/bug-report feature.

**Performance & N+1 tooling (success #2, #3, #4, #5, #6):**
- D-06: Install BOTH `barryvdh/laravel-debugbar` AND `laravel/telescope` as `require-dev`. Debugbar = per-request timing/N+1/cache hits. Telescope = queued CloseMonthJob timing + cache across requests. NEITHER ships to production (env-gated).
- D-07: Build ONE reproducible ~50-member seeder (~50 members + full month of meals/bazar/fixed/payments). Doubles as README demo-credentials dataset. One seeder, two purposes.
- D-08: Acceptance = MANUAL measurement with Debugbar, recorded in `05-VERIFICATION.md`. No automated timing tests. A query-count smoke test (assert grid does < N queries) is acceptable but NOT required.
- D-09: Cache hit-rate (>80%) measured by eyeballing Debugbar's cache tab on repeat dashboard loads — `bill-preview:{mess_id}:{YYYY}-{MM}` + `dash:counts:{mess_id}:{YYYY}-{MM}` keys. No temporary logger unless Debugbar is ambiguous.
- D-10: Performance budgets are a HARD pass/fail gate, not aspirational. Missed budget = fix the N+1/slow query/missing cache, NEVER relax the number. Grid <100ms @50; dashboard <500ms; close <30s @50; cache hit >80%.

**Mobile polish (success #1):**
- D-11: Mobile tested via browser DevTools device emulation at 320/375/768/1024 during polish, then confirmed on a REAL Android device during the pilot. Real device is the final authority.
- D-12: Polish depth = full responsive audit at all breakpoints + dedicated meal-grid touch-target (≥44px) and density pass. NOT a full interaction rework (no bottom-sheet, no UX redesign).
- D-13: 360px is the practical support floor. 320px is best-effort, NOT a hard gate. Don't heavily compromise the grid (horizontal scroll, collapsed columns) just to fit 320px.
- D-14: Manager daily-ops screens (meal grid, bazar/expense entry, payments) get the MOST polish attention. Member-facing screens are read-mostly and lower-priority.

**Docs + deployment (success #10, #11):**
- D-15: Deploy target = a VPS (DigitalOcean/Hetzner/etc.) via Laravel Forge OR manual setup, running a persistent queue worker (supervisor) for CloseMonthJob + MySQL + public URL. Shared hosting RULED OUT (can't run persistent worker).
- D-16: README rewritten in FULL — what the app is, prerequisites (PHP 8.4, MySQL 8+, Node), clone/composer/npm/.env/migrate setup, running the ~50-member demo seeder, demo manager + member credentials, common commands. Replaces the default Laravel stub.
- D-17: AGENTS.md = re-run auto-gen updater + add a hand-written "domain walkthrough" section (bill math, month-close flow, cache key strategy, role/IDOR model).
- D-18: Write `DEPLOYMENT.md` production-hardening checklist (APP_DEBUG=false, HTTPS, APP_URL, queue worker via supervisor, schedule:run cron, storage perms, production MySQL .env) AND fix the dev `.env` sqlite→MySQL parity as an explicit task.

**Mechanical audits:**
- D-19: Pint clean (`vendor/bin/pint --test`) on all committed code. No new work unless drift is found.
- D-20: `__()` scan — audit-only (grep for un-wrapped Blade `{{ }}`). Full Bengali (`bn.json`) deferred to v2.
- D-21: Timezone — confirm `Asia/Dhaka` everywhere. `config/app.php` defaults to `env('APP_TIMEZONE', 'UTC')` with no `APP_TIMEZONE` in `.env` → likely runs UTC. Fix = set `APP_TIMEZONE=Asia/Dhaka` in `.env` + `.env.example`.
- D-22: Test coverage measured (>70% target) via `phpunit --coverage-text`. Measurement + targeted-fill, NOT blanket-test-writing.
- D-23: Clear the 4 pending Phase 4 HUMAN-UAT items (chart rendering, PDF layout, mobile responsive, cache refresh — see `04-HUMAN-UAT.md`).

### Claude's Discretion
- Exact seeder name/location (`database/seeders/PerfDemoSeeder.php` or similar) and factory-driven counts
- Debugbar/Telescope config (env-gating, Telescope dashboard authorization)
- Which N+1 queries to fix first + fix approach (eager load vs select-specific vs cache)
- Mobile touch-target implementation (Tailwind `min-h-[44px]` convention — reuse)
- `DEPLOYMENT.md` exact structure and Forge-vs-manual step depth
- Whether to add a query-count smoke test
- Coverage tool choice (xdebug vs pcov) — NO CI pipeline exists yet; adding CI is NOT required for the pilot
- Pilot timeline / start date (driven by the dev's relationship with the pilot mess)

### Deferred Ideas (OUT OF SCOPE — ignore completely)
- Two-cycle pilot (advance/due carry-forward across months) — observe naturally, don't gate
- Manager sign-off / "prefer over spreadsheet" gate — subjective, beyond bar
- Historical data importer (CSV/Excel) — post-pilot only
- Bengali translations (`bn.json`) — v2
- CI pipeline (GitHub Actions) — post-pilot
- Redis for cache/queue — `database` driver is fine for one mess in v1
- Full mobile interaction rework / bottom-sheet patterns
- Strict 320px support (360px is the floor)
- Automated perf benchmark tests — manual measurement
- In-app pilot feedback/bug-report feature
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| PERF-04 | All manager-facing screens are mobile-first (375px baseline) | Mobile polish audit (§Mobile Polish); meal-grid density + touch-target pass; DevTools 320/375/768/1024 → real Android; manager screens prioritized (D-14); 360px floor (D-13) |
| PERF-06 | Current-month aggregates cached with 1-hour TTL, invalidated on write | Cache hit-rate measurement (§Cache Hit-Rate Methodology); verify existing `bill-preview` + `dash:counts` keys hit >80% on repeat dashboard loads via Debugbar cache tab |
| PERF-13 | Laravel Pint runs clean on all committed code | `vendor/bin/pint --test` (D-19); Pint 1.29.x current; Laravel preset |
| (general polish) | ROADMAP Phase 5 success criteria #1–#12 | All research sections map to these criteria; #1→Mobile, #2/#3/#4/#5/#6→Perf, #7→Timezone, #8→`__()`, #9→Pint+coverage, #10→README, #11→AGENTS.md, #12→Pilot |
</phase_requirements>

## Project Constraints (from CLAUDE.md / global)

No project-level `./CLAUDE.md` exists (verified — file does not exist in repo root). Global `~/.claude/CLAUDE.md` only registers the `graphify` skill (unrelated to this phase).

Effective project constraints come from `.planning/PROJECT.md` + `.commandcode/taste/taste.md` (per CONTEXT.md canonical refs):

| Constraint | Authority | Phase 5 enforcement |
|---|---|---|
| Mobile-first 375px baseline (360px practical floor in Phase 5 per D-13) | PROJECT.md | Audit at 320/375/768/1024 (D-11) |
| MySQL only (no sqlite) | taste.md | Fix dev `.env` sqlite→MySQL (D-18) |
| `Asia/Dhaka` timezone everywhere | PROJECT.md / STATE.md | Verify + fix `APP_TIMEZONE` (D-21) |
| `__()` everywhere (English shipped, Bengali-ready) | PROJECT.md | Audit-only scan (D-20) |
| Decimal money (never float) | PROJECT.md | Carry-forward — no Phase 5 work implied |
| Single mess in v1 (`mess_id` on all tables) | PROJECT.md | Carry-forward — cache keys are already mess-scoped (verified in `AppServiceProvider`) |
| Service layer (no Repository pattern) | PROJECT.md | Perf fixes land in services (§Perf Targets) |
| No JS/CSS in Blade, no HTML in PHP | laravel-best-practices skill | Audit consideration for any polish changes |
| `env()` only inside config files | laravel-best-practices skill | Timezone/parity fixes go in `.env`+`.env.example`, NOT hardcoded |

---

## Summary

Phase 5 is a **hardening + pilot phase**, not a feature phase. The app is functionally complete (154 tests, Phase 4 dashboard + exports + reports landed in Plan 04-03). The work is: (1) measure and fix performance against hard budgets using two new dev-only tools (Debugbar + Telescope); (2) build one reproducible ~50-member seeder that triples as perf fixture + demo dataset + README demo-credentials source; (3) audit mobile UX at four breakpoints + run a meal-grid density/touch pass; (4) close three small dev/prod parity gaps (timezone UTC default, dev `.env` sqlite, missing production-hardening doc); (5) clear 4 human-UAT items from Phase 4; (6) rewrite README + AGENTS.md, add DEPLOYMENT.md; (7) onboard one real mess for a clean monthly cycle.

**Critical verifications performed during this research (pre-existing issues flagged in CONTEXT.md):**
- ✅ `BillPreviewService.php` has **NO debug-throw** — confirmed by reading the file. The `throw new \RuntimeException('DBG:...')` flagged in `04-CONTEXT.md` is gone; STATE.md's claim that Plan 03.3 removed it is **TRUE**. Lines 87–196 are clean. (One `RuntimeException` exists in `AdvanceBalanceService.php:83` but it's a legitimate domain throw — "Adjustment amount cannot be zero" — NOT debug code.)
- ✅ `AppServiceProvider::invalidateForModel()` **ALREADY** forgets `dash:counts:{mess_id}:{YYYY}-{MM}` on the same `saved`/`deleted` hook as `bill-preview` (lines 96–114). No rebuild needed — Phase 4 work landed it. Verify-only.
- ✅ `config/app.php:68` confirms `'timezone' => env('APP_TIMEZONE', 'UTC')` — **UTC default is live**. D-21 gap confirmed real.
- ✅ `.env.example:23` uses `DB_CONNECTION=sqlite` with MySQL keys commented out — D-18 parity gap confirmed real.
- ✅ `phpunit.xml` already runs MySQL (`devsroom_mess_management_testing`) — test-side parity exists, only dev `.env` lacks it (D-18 scope confirmed narrow).
- ✅ `MealGridService::buildGridData` is **already N+1-safe** — `whereIn('member_id', $activeMembers->pluck('id'))` is used for both `MealEntry` and `MealOffRequest` (2 queries, not N+1). The grid may already meet the <100ms budget; verify, don't assume a fix.

**Environment findings:**
- PHP 8.4.15 (ZTS), `pdo_mysql` + `zip` + `gd` + `curl` + `mbstring` loaded.
- **`xdebug` and `pcov` are NOT installed** — coverage measurement (D-22) is BLOCKED until one is installed. This is the one true blocker; see §Environment Availability.
- `mysql` CLI is not on PATH (Windows Git Bash quirk), but `pdo_mysql` works — DB is reachable from PHP. Forge/manual prod will have `mysql` CLI on Linux.
- Node `v24.15.0` exists (per STACK.md) but not on PATH in this shell — npm runs from outside Git Bash.
- No `supervisor`, no Forge CLI in dev (expected — these are prod tools, not dev deps).

**Primary recommendation:** Sequence the work so the ~50-member seeder (D-07) lands first — it unblocks the perf measurement (D-08/D-09), the demo-credentials story (D-16), AND is a prerequisite for any realistic mobile density pass (the grid needs real rows). Install Debugbar + Telescope + pcov in the same Wave 0. Run the four mechanical audits (timezone, parity, Pint, `__()`) early because they're cheap and surface silent bugs that distort perf measurement. Do the mobile responsive audit + meal-grid density pass before declaring perf budgets met — layout reflows can change query patterns.

**Package version verification (authoritative — Packagist API, 2026-06-18):**
| Package | Verified latest | Laravel 13? | PHP? |
|---|---|---|---|
| `barryvdh/laravel-debugbar` | **v4.3.0** | `^11\|^12\|^13.0` ✅ | `^8.2` ✅ (project on 8.4) |
| `laravel/telescope` | **v5.20.0** | `^8.37\|^9\|^10\|^11\|^12\|^13.0` ✅ | `^8.0` ✅ |
| `laravel/pint` | **v1.29.3** (installed: 1.29.1) | n/a (PHAR-style) | `^8.2` ✅ |

---

## Standard Stack

### Core (to ADD this phase)

| Package | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| `barryvdh/laravel-debugbar` | `^4.3` (latest v4.3.0) | Per-request timing, N+1 detection, cache hits/misses, query listing in a browser bar | The de-facto Laravel perf-debugging tool [VERIFIED: Packagist API + github.com/barryvdh/laravel-debugbar] |
| `laravel/telescope` | `^5.20` (latest v5.20.0) | Queued job timing across requests (CloseMonthJob), cross-request cache inspection, request lifecycle | First-party Laravel dev tool; pairs with Debugbar for queued/asynchronous work [VERIFIED: Packagist API + laravel.com/docs/13.x/telescope] |

Both go in `composer.json` `require-dev` (D-06). **Neither is currently installed** (verified: `vendor/barryvdh/laravel-debugbar` and `vendor/laravel/telescope` do not exist).

### Supporting (already installed — carry-forward)

| Package | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `laravel/pint` | 1.29.1 (latest 1.29.3) | Code-style fixer, Laravel preset | D-19 — `vendor/bin/pint --test` on all committed code |
| `phpunit/phpunit` | 12.5.30 | Test runner + coverage | D-22 — `--coverage-text` (needs xdebug/pcov, currently MISSING) |
| `owen-it/laravel-auditing` | 14.0.4 | Domain audit log | No Phase 5 work — verify perf impact during audit |
| `maatwebsite/excel` | 3.1.69 | Excel exports (Phase 4) | No Phase 5 work |
| `barryvdh/laravel-dompdf` | 3.1.2 | PDF exports (Phase 4) | No Phase 5 work |
| `chart.js` | 4.5.1 (npm) | Dashboard charts | Human-UAT item #1 (chart rendering) |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Debugbar | Laravel Pulse | Pulse is a long-term production APM; Debugbar is sharper for one-shot N+1 + per-request timing in dev. **CONTEXT.md locked both Debugbar + Telescope** — don't substitute. Pulse deferred. |
| Telescope | Laravel Horizon | Horizon is Redis-only queue monitoring; project uses `database` queue driver. Horizon doesn't fit. Telescope covers queue + everything else. |
| Pint | PHP-CS-Fixer (directly) | Pint IS PHP-CS-Fixer with a Laravel preset baked in. No tradeoff — Pint is the right call. |
| xdebug | pcov | **pcov is much faster for coverage-only use** (xdebug is a full debugger with step-through). For D-22 coverage measurement, pcov is the better choice — it doesn't slow normal test runs the way xdebug does. [VERIFIED: phpunit docs recommend pcov for coverage] |

**Installation (Wave 0):**
```bash
# Both as require-dev (D-06 — NEITHER ships to production)
composer require --dev "barryvdh/laravel-debugbar:^4.3" "laravel/telescope:^5.20"

# Telescope has its own provider + migrations + asset publish (REQUIRED post-install)
php artisan vendor:publish --tag=telescope-config
php artisan vendor:publish --tag=telescope-migrations
php artisan telescope:install   # OR the publish commands above
php artisan migrate              # adds telescope_entries, telescope_entries_tags, telescope_monitoring

# Pint bump (optional, project on 1.29.1, latest 1.29.3 — non-breaking)
composer update laravel/pint
```

**Version verification (already performed via Packagist API — see Summary table above).**

---

## Architecture Patterns

### Recommended Project Structure (Phase 5 additions)

```
database/
├── seeders/
│   ├── DatabaseSeeder.php          # MODIFIED — guard the demo seeder (see Pattern 2)
│   ├── ExpenseCategorySeeder.php   # existing
│   └── PerfDemoSeeder.php          # NEW (D-07) — ~50 members + full month; perf fixture + demo creds
config/
├── app.php                         # UNCHANGED (timezone fix is in .env, not here)
├── debugbar.php                    # NEW (published by package) — env-gate + AJAX config
└── telescope.php                   # NEW (published by package) — enabled closure + pruning
app/Providers/
└── TelescopeServiceProvider.php    # NEW (auto-created by telescope:install) — Gate::define
docs (repo root)
├── README.md                       # REWRITTEN (D-16)
├── AGENTS.md                       # REFRESHED + hand-written domain walkthrough (D-17)
└── DEPLOYMENT.md                   # NEW (D-18)
.env + .env.example                 # MODIFIED — APP_TIMEZONE=Asia/Dhaka, DB_CONNECTION=mysql (+ MySQL keys)
```

### Pattern 1: Env-gate Debugbar so it NEVER touches production

Debugbar ships with sensible defaults but the project must make the gate explicit. The package's `config/debugbar.php` already keys off `APP_DEBUG` and `APP_ENV`, but the project should verify, not assume.

```php
// config/debugbar.php (published by the package — key excerpts to VERIFY/CUSTOMIZE)
'enabled' => env('DEBUGBAR_ENABLED', true),  // master toggle

// ⚠️ The package auto-disables when APP_DEBUG=false. But the safe pattern is to
// also gate by APP_ENV, because Telescope install sometimes leaves APP_DEBUG=true
// in staging. The package DOES respect this; verify after publish.

// Prevent Debugbar from corrupting AJAX/JSON responses (Pitfall 2 below).
// Set FALSE if it injects the debugbar HTML into JSON responses on this app.
'capture_ajax' => true,    // keep TRUE — we WANT to see the meal-grid AJAX timing
'ajax_handler_enabled' => false,  // do NOT auto-append the bar to AJAX bodies

// Exclude the PDF export routes (Dompdf does not want a debug bar injected).
'exclude_paths' => [
    'api/*',           // future-proofing
    '*.pdf',           // PDF export routes return binary — Debugbar must not touch
],
```

**Gating strategy (defense-in-depth):** rely on (a) the package's built-in `APP_DEBUG=false` → disabled behavior, AND (b) the `require-dev` install so a `composer install --no-dev` on prod literally cannot load it. `[CITED: github.com/barryvdh/laravel-debugbar config]`

### Pattern 2: Telescope `enabled` closure + `Gate::define` for dashboard auth

Telescope is more dangerous than Debugbar in prod — it writes to the DB (`telescope_entries`) on every request. Two-layer gating:

```php
// config/telescope.php (published)
'use_redis' => env('TELESCOPE_USE_REDIS', false),
'driver' => env('TELESCOPE_DRIVER', 'database'),

// LAYER 1: the 'enabled' closure — Telescope ONLY records when this returns true.
// In production this returns false, so nothing is written to telescope_entries.
'enabled' => env('TELESCOPE_ENABLED', fn () => app()->environment('local')),

// pruning — keep telescope_entries from growing unbounded (Pitfall 3)
'prune' => [
    'hours' => 24,  // default is 24h; bump to 72 if you want more history in dev
],

// watcher gating — disable the slow ones in dev if perf measurement is distorted
'watchers' => [
    // ...
],
```

```php
// app/Providers/TelescopeServiceProvider.php (auto-created by telescope:install)
// LAYER 2: even if Telescope is enabled, the dashboard at /telescope requires this gate.
// Defaults to "local env only" — for staging/prod, define the gate explicitly.
protected function gate(): void
{
    Gate::define('viewTelescope', function ($user) {
        // For this project: only super-admin may view Telescope in non-local envs.
        // In local env the gate is bypassed automatically.
        return $user->hasRole('super-admin');
    });
}
```

`[CITED: laravel.com/docs/13.x/telescope — "enabled" closure + Gate authorization]`

### Pattern 3: The ~50-member demo/perf seeder (D-07) — guarded registration

The seeder has THREE purposes — perf fixture, demo dataset, README creds source — so it must be **deterministic** (same rows every run) and **guarded** (production `db:seed` must never run it). Use `WithoutModelEvents` (existing `DatabaseSeeder` already does) so the audit log + cache invalidation hooks don't fire 50×N times during seeding.

```php
// database/seeders/PerfDemoSeeder.php (NEW)
namespace Database\Seeders;

use App\Models\{AdvanceBalance, Expense, ExpenseCategory, MealEntry, Member, Mess, Payment, User};
use App\Support\{ExpenseKind, MealType, PaymentType};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PerfDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Guard (D-07): never run unless explicitly invoked via --class or an env flag.
        // DatabaseSeeder does NOT call this by default — see Pattern below.

        // 1. Deterministic manager + member demo logins (README creds source)
        $mess = Mess::factory()->create(['name' => 'Demo Mess']);
        app()->instance('currentMessId', $mess->id);  // see note on Mess::activeId below

        $manager = User::factory()->create([
            'email' => 'manager@demo.test',
            'password' => bcrypt('password'),
        ]);
        $manager->assignRole('admin');

        // 2. ~50 members (mix of active/former to exercise denominator math)
        $members = Member::factory()
            ->count(48)
            ->for($mess)
            ->create();
        Member::factory()->former()->for($mess)->create();   // 1 former (proration)
        Member::factory()->inactive()->for($mess)->create();  // 1 inactive (excluded)

        // 3. A full month of meals (B/L/D) — random realistic pattern
        $today = now();
        foreach ($members as $member) {
            for ($d = 1; $d <= $today->day; $d++) {  // up through "today"
                MealEntry::factory()
                    ->for($mess)
                    ->for($member)
                    ->create([
                        'date' => $today->copy()->setDay($d)->toDateString(),
                        'breakfast' => rand(0, 1) === 1,
                        'lunch' => true,  // lunch is near-universal
                        'dinner' => rand(0, 1) === 1,
                    ]);
            }
        }

        // 4. Bazar (kind=bazar) + fixed (kind=fixed) expenses
        $bazarCategories = ExpenseCategory::where('kind', ExpenseKind::BAZAR)->pluck('id');
        $fixedCategories = ExpenseCategory::where('kind', ExpenseKind::FIXED)->pluck('id');
        // 30 days of bazar, 2-3 entries/day; 1 of each fixed category for the month
        // (use Expense::factory()->for($mess)->create([...]) with explicit category + date)

        // 5. Some payments (mix bill_payment + advance_deposit) for ~half the members
        // (use Payment::factory() with explicit type)
    }
}
```

```php
// database/seeders/DatabaseSeeder.php (MODIFIED — guarded)
public function run(): void
{
    $this->call([
        ExpenseCategorySeeder::class,
    ]);

    User::factory()->create(['name' => 'Test User', 'email' => 'test@example.com']);

    // D-07: PerfDemoSeeder is NEVER called by the default DatabaseSeeder.
    // Run it explicitly: `php artisan db:seed --class=PerfDemoSeeder`
    // OR an env guard: `if (app()->environment('local', 'testing')) $this->call(PerfDemoSeeder::class);`
}
```

**`Mess::activeId()` note:** the seeder must set the active mess explicitly. `Mess::activeId()` (verified in `app/Models/Mess.php:32`) caches the first mess row's id per request. In a seeder run, call it after creating the demo mess; factories use `Mess::activeId() ?? Mess::factory()` (verified in `MemberFactory.php:16`, `ExpenseFactory.php:17`) so they'll pick up the demo mess once it exists.

`[VERIFIED: existing factories MemberFactory.php:16, ExpenseFactory.php:17 use this pattern]`

### Pattern 4: Manual measurement methodology (D-08/D-09)

The four budgets are measured MANUALLY and recorded in `05-VERIFICATION.md`. There are no automated timing tests (D-08 locked this — flaky across machines). Concrete recipe:

```text
STEP 1 — Setup
  composer db:seed --class=PerfDemoSeeder   # ~50 members + full month
  php artisan cache:clear                   # cold-cache start
  php artisan serve                         # or the dev composer script

STEP 2 — Cold-cache measurement (first load)
  Browse to /home (manager dashboard) logged in as the demo manager
  Debugbar appears at the bottom. Click the clock icon to open the timeline.
  Record: total time, query count, query time.
  Cold cache — should MISS both keys. This is NOT the budget number.

STEP 3 — Warm-cache measurement (the budget number)
  Reload /home 3 times. Debugbar's cache tab should show:
    READ   bill-preview:{mess_id}:{YYYY}-{MM}  HIT
    READ   dash:counts:{mess_id}:{YYYY}-{MM}   HIT
  Record the warm load time. THIS is the dashboard <500ms budget.

STEP 4 — Grid budget
  Browse to /mess/meals (today's grid). Record warm time. THIS is the <100ms budget.

STEP 5 — Close budget
  php artisan queue:work --queue=default &   # start worker
  Browse to /mess/close, trigger close for current month
  In Telescope (/telescope/jobs), find the CloseMonthJob entry.
  Record its duration. THIS is the <30s budget.
  (queue:work runs CloseMonthJob async; Telescope captures the job handle time)

STEP 6 — Cache hit-rate (D-09)
  Reload /home 5 times. In Debugbar → Cache tab, count HITs vs MISSes.
  hit_rate = hits / (hits + misses). Must be > 80%.
  Both keys (bill-preview + dash:counts) should be HIT on every warm reload.
```

### Anti-Patterns to Avoid

- **Relaxing a missed budget.** D-10: budgets are a HARD gate. If grid is 180ms @50, the answer is "find the N+1 / add the index / add the cache," NOT "call 180ms close enough." `[VERIFIED: CONTEXT.md D-10]`
- **Leaving Telescope recording in production.** Telescope writes one row per request/event to `telescope_entries`. The `enabled` closure MUST return false in production, or the table grows unbounded. See Pitfall 3.
- **Running the demo seeder in production.** `DatabaseSeeder` must NOT call `PerfDemoSeeder`. Guard explicitly. See Pattern 2.
- **Letting Debugbar inject into PDF exports.** Dompdf renders the HTML response body; if Debugbar appends its bar HTML, the PDF breaks. Exclude `*.pdf` paths in `config/debugbar.php`. See Pitfall 2.
- **Modifying the cache strategy during measurement.** Don't change `bill-preview` TTL or key structure mid-measurement. The strategy is locked (D-14/D-15/D-17 from prior phases); measure what's there. If a budget fails, the fix is an N+1 or index, not a new cache layer.
- **Assuming the grid has N+1.** `MealGridService::buildGridData` already uses `whereIn` for both MealEntry and MealOffRequest. It may already pass. Verify, don't assume a fix is needed.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| N+1 detection | Manual query logging | Debugbar's Queries tab (auto-detects repeated queries) + `Model::preventLazyLoading()` | Debugbar highlights N+1 visually; preventLazyLoading throws on lazy load in dev. [CITED: laravel-best-practices skill §1] |
| Per-request timing | `microtime(true)` wrapping | Debugbar's Timeline tab | Handles boot, middleware, controller, view, DB automatically. |
| Cache hit/miss tracking | Wrap every `Cache::remember` in a logger | Debugbar's Cache tab | Auto-instruments all cache operations for the request. |
| Queued job timing | Add logging to `CloseMonthJob::handle()` | Telescope's Jobs tab | Captures queue-time + handle-time + payload + exceptions across requests. |
| Coverage measurement | Custom test-runner wrapper | `phpunit --coverage-text` with pcov/xdebug | PHPUnit ships this; only needs the PHP extension. |
| Code style | Custom PHP-CS-Fixer config | `vendor/bin/pint` (Laravel preset) | Already configured in the project (CONVENTIONS.md). |
| Demo data | Hand-written SQL inserts | Factory-driven seeder (existing factories in `database/factories/*`) | 16 factories already exist; the seeder builds on them. |
| Production process supervision | `nohup php artisan queue:work &` or shell loops | supervisor (manual setup) OR Forge (managed) | Without supervisor, the queue worker dies on deploy and never restarts. The whole point of D-15 is "shared hosting can't run a persistent worker reliably." |
| Cron-based scheduler trigger | Manual `cron`/`crontab` editing if using Forge | Forge's "Scheduler" UI | Forge writes the crontab entry for you and shows logs. |
| Deployment automation | rsync scripts | Forge (deploy-on-git-push) OR a documented manual runbook | Either is acceptable per D-15; pick based on budget. |

**Key insight:** Phase 5 is a *measurement + polish* phase. Every tool in "Use Instead" above already exists and is the standard. The only things being built are the seeder (D-07), three docs (D-16/D-17/D-18), and targeted N+1 fixes (only if a budget actually fails). Don't build infrastructure.

---

## Runtime State Inventory

**Trigger:** This phase does NOT primarily involve rename/refactor/migration. The pre-existing-issues verification is a *read-only* confirm-the-bug-is-gone check, not a rename. The `.env` sqlite→MySQL parity fix is config-only (not a data migration — MySQL is already the real DB via phpunit.xml; only the dev `.env` was wrong).

**Verdict:** SKIPPED — no rename, no rebrand, no data migration. The state-bearing items this phase touches (cache keys `bill-preview` / `dash:counts`, `.env` keys, seeder records) are CREATE/config operations, not mutations of existing runtime state.

(Cross-check: the Phase 1→5 timeline has NO rename/refactor events flagged in STATE.md session notes. The cache key strategy is stable from Phase 3 D-14 onward. No collection-name or user_id renames.)

---

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP 8.4 | runtime | ✅ | 8.4.15 (ZTS) | — |
| `pdo_mysql` ext | DB layer (dev + prod) | ✅ | loaded | — |
| `zip` ext | Maatwebsite Excel exports | ✅ | loaded | — |
| `gd` ext | profile photo upload (Phase 2) | ✅ | loaded | — |
| `mbstring` ext | UTF-8 / `__()` / i18n | ✅ | loaded | — |
| MySQL server | dev DB (D-18 parity fix) | ✅ (reachable via PHP) | 8.x (per phpunit.xml conn works) | — |
| `mysql` CLI | manual DB inspection | ❌ | not on PATH (Windows Git Bash) | Use `php artisan tinker` / `DB::table()` for inspection; non-blocking |
| `xdebug` OR `pcov` ext | D-22 coverage measurement (`--coverage-text`) | ❌ | NOT INSTALLED | **BLOCKER for D-22** — install pcov (preferred) or xdebug |
| Node.js | frontend build (`npm run build`) | ⚠️ | v24.15.0 per STACK.md, not on PATH in this shell | Run npm from PowerShell/cmd, not Git Bash |
| `barryvdh/laravel-debugbar` | D-06 perf measurement | ❌ | not installed (will add) | — |
| `laravel/telescope` | D-06 queue/cache measurement | ❌ | not installed (will add) | — |
| `supervisor` | D-15 prod queue worker | ❌ (dev only — expected) | n/a | Use Forge (managed) or install via apt on the VPS |
| Laravel Forge | D-15 prod deployment (OPTIONAL) | n/a | external SaaS | Manual VPS setup is the explicit alternative (D-15 allows either) |
| Real Android device | D-11 mobile final authority | n/a | dev-held | DevTools emulation is the interim during polish |

**Missing dependencies with no fallback:**
- **`xdebug` or `pcov` for coverage (D-22).** This is the only true blocker. Without one of these, `vendor/bin/phpunit --coverage-text` errors with "No code coverage driver available." The Wave 0 task must install pcov (preferred — it's much faster than xdebug for coverage-only use and doesn't slow normal test runs). On Windows, pcov DLLs are available from the PECL / windows.php.net releases; drop the DLL in the PHP `ext/` dir and add `extension=pcov` to `php.ini`. `[CITED: phpunit docs — "pcov is the recommended coverage driver"]`
- **A real mess + real Android device for the pilot (D-01, D-11).** These are human/logistical dependencies, not installable tools. The dev owns this (D-01/D-03).

**Missing dependencies with fallback:**
- `mysql` CLI not on PATH → use `php artisan tinker` for any direct DB inspection. Non-blocking for the whole phase.
- Node not on PATH in this shell → run npm/Vite commands from PowerShell or `cmd.exe`. Non-blocking.
- supervisor / Forge → not needed in dev; only on the prod VPS. The DEPLOYMENT.md doc is the deliverable; the actual VPS setup is the pilot infra step (D-15).

---

## Common Pitfalls

### Pitfall 1: Telescope writes unbounded data to the production DB
**What goes wrong:** Telescope is installed, `TELESCOPE_ENABLED` is left at its default (or `true`), and the prod deploy runs with Telescope recording every request. The `telescope_entries` table grows by N rows per request + job + cache op. Within days it bloats the DB; within weeks it can cause real latency.
**Why it happens:** Telescope's default `enabled` closure is `env('TELESCOPE_ENABLED', fn () => app()->environment('local'))` — so it's gated by env. But (a) people copy `.env.example` which may not have `TELESCOPE_ENABLED`, and (b) the package auto-discovers its provider, so a misconfigured env still loads it.
**How to avoid:**
1. Set `TELESCOPE_ENABLED=false` explicitly in `.env.example` (so prod `.env` copies inherit it).
2. Verify the `config/telescope.php` `enabled` closure defaults to `app()->environment('local')`.
3. The package is `require-dev` per D-06 — a prod `composer install --no-dev` literally cannot load it. This is the strongest guarantee.
4. Schedule `php artisan telescope:prune` daily in dev (the package ships this command). `[CITED: laravel.com/docs/13.x/telescope — pruning]`
**Warning signs:** DB size growing unexpectedly after deploy; `telescope_entries` row count > 100k in dev.

### Pitfall 2: Debugbar corrupts JSON / PDF responses
**What goes wrong:** Debugbar appends a `<script>` + HTML payload to responses to render the bar. For JSON API responses (AJAX) this corrupts the JSON; for PDF responses (Dompdf) the bar HTML ends up in the PDF body and breaks rendering.
**Why it happens:** Default `capture_ajax` is `true` and there's no path exclusion by default.
**How to avoid:**
1. In `config/debugbar.php`, set `exclude_paths` to include `'*.pdf'` and any pure-API paths.
2. Keep `capture_ajax => true` (we DO want AJAX timing for the meal-grid save), but set `ajax_handler_enabled => false` so the bar isn't injected into AJAX response bodies.
3. After install, immediately download a PDF export (e.g. `/mess/reports/monthly.pdf`) and verify it renders cleanly with Debugbar enabled in local.
**Warning signs:** PDF exports rendering as garbage; JSON responses with trailing `<script>`; "Unexpected token <" errors in browser console on AJAX. `[VERIFIED: github.com/barryvdh/laravel-debugbar/issues/670 + the Phase 4 PDF layout — `layouts/pdf.blade.php` is plain CSS, no @vite, but Debugbar injection is separate]`

### Pitfall 3: Coverage run is dramatically slower than the normal test run
**What goes wrong:** Installing xdebug (instead of pcov) for coverage makes the entire PHPUnit suite 3–5× slower, which discourages running it. People start skipping coverage, and D-22 becomes a never-run task.
**Why it happens:** xdebug is a full debugging tool with breakpoints + stack instrumentation; pcov is purpose-built for line coverage only.
**How to avoid:** Install **pcov**, NOT xdebug, for D-22. pcov is roughly 2–3× faster than xdebug for coverage-only workloads. `[CITED: phpunit docs recommend pcov]`
**Warning signs:** `php artisan test` takes >2 min after installing the coverage driver (was <30s before). Switch to pcov.

### Pitfall 4: Debugbar shows "phantom" slowness during measurement
**What goes wrong:** Debugbar itself adds 50–150ms of overhead per request (its own boot + query capture + render). A page that's actually 60ms reads as 180ms in Debugbar's timeline.
**Why it happens:** Debugbar is a profiler — it instruments everything. This is fine for N+1 detection (query COUNT is unaffected) but distorts wall-clock timing.
**How to avoid:**
1. For the **wall-clock budgets** (<100ms grid, <500ms dashboard), use Debugbar's **query count + query time**, not total request time. Query time is the real signal; total time includes Debugbar's overhead.
2. Cross-check with the browser DevTools Network tab "Time" column (Disable Debugbar temporarily via `DEBUGBAR_ENABLED=false` if a number is borderline).
3. For the **CloseMonthJob <30s** budget, use Telescope's job timing (NOT Debugbar — jobs don't go through HTTP).
**Warning signs:** A page reads 5× slower in Debugbar than in browser DevTools. Always sanity-check the first measurement.

### Pitfall 5: The cache hit-rate measurement gets distorted by the invalidation hooks
**What goes wrong:** During the D-09 measurement (reload /home 5×, count cache hits), you incidentally write some data (or the audit log / notification fires) which triggers `AppServiceProvider::invalidateForModel()` → `Cache::forget('bill-preview:...')` + `Cache::forget('dash:counts:...')`. The next reload MISSes, which you record as "low hit-rate."
**Why it happens:** The cache invalidation is wired to 5 models' `saved`/`deleted` events (verified). ANY write to those models invalidates the keys for the affected month.
**How to avoid:**
1. Run the hit-rate measurement as a pure READ loop — log in as manager, reload /home N times, DON'T submit any forms in between.
2. If you must interleave writes, record hit-rate separately for "post-write reload" (expected to be cold) vs "pure-read reload" (the >80% target applies here).
**Warning signs:** Hit-rate looks <80% but only because you wrote a meal entry between reloads. Read-only test first.

### Pitfall 6: The seeder triggers the audit log + cache hooks 50×N times
**What goes wrong:** `PerfDemoSeeder` creates ~50 members + ~50×30 meal entries + many expenses. Each `MealEntry::create()` fires `eloquent.saved` → `invalidateForModel` → `Cache::forget`. With ~1500 meal entries + 100+ expenses, that's thousands of cache writes and audit inserts, making the seed take minutes instead of seconds.
**Why it happens:** `WithoutModelEvents` (used by `DatabaseSeeder`) disables model events, BUT only on the model being created in the seeder's call chain. If the seeder uses factories that don't inherit `WithoutModelEvents`, events fire.
**How to avoid:**
1. Have `PerfDemoSeeder` `use WithoutModelEvents;` (matches the existing `DatabaseSeeder` pattern).
2. Disable audit during seed: `config(['audit.enabled' => false])` at the top of `run()` (the auditing package respects this).
3. Time the seeder itself — should be <30s for ~50 members + a month of data. If it's >2 min, events are firing.
**Warning signs:** Seeder takes minutes; `audit_logs` table has thousands of rows after a seed.

### Pitfall 7: APP_TIMEZONE change shifts existing date columns
**What goes wrong:** Setting `APP_TIMEZONE=Asia/Dhaka` (D-21) shifts how PHP interprets `now()` and Carbon. Any `date` / `datetime` column that was inserted while the app ran UTC now DISPLAYS at a +6h offset.
**Why it happens:** Laravel stores timestamps in the DB in the app timezone (not UTC) by default — UNLESS the column is `timestamp` and the model casts it to `datetime` (which is timezone-aware). The mess-management app uses `date` columns (not timestamp) for meal_entry/expense/payment `date`, so these are stored as bare dates and are unaffected. But `created_at`/`updated_at` (timestamp columns) and audit `created_at` are affected.
**How to avoid:**
1. This is a DEV-ONLY DB in the pilot pre-launch phase. The dev DB can be re-seeded after the timezone fix. Don't try to "migrate" timestamps.
2. For the pilot, fix `APP_TIMEZONE=Asia/Dhaka` BEFORE the pilot mess is onboarded (before any real data is inserted).
3. STATE.md claims timezone was "validated in Phase 1" — this is likely FALSE based on the current `config/app.php:68` (UTC default) + no `APP_TIMEZONE` in `.env.example`. Re-verify and close the gap (D-21 explicit).
**Warning signs:** Existing `audit_logs.created_at` reads 6h off from `now()` after the change; PDF reports show yesterday's date for entries made this morning.

### Pitfall 8: Closing the dev `.env` sqlite gap without a clean re-migrate
**What goes wrong:** Dev switches `DB_CONNECTION=mysql` but the existing sqlite DB had data; the MySQL DB is empty. The dev's previous test data is "gone."
**Why it happens:** sqlite and MySQL are different databases with different files/schemas.
**How to avoid:**
1. This is a fresh-start anyway (pilot is fresh-start per D-02). Run `php artisan migrate:fresh --seed` against MySQL after the `.env` change.
2. The seeder (Pattern 3) repopulates everything deterministically.
3. Don't try to migrate sqlite → MySQL. Start clean.
**Warning signs:** After the `.env` switch, login fails (no users). Run `migrate:fresh --seed`.

---

## Code Examples

### Example 1: Reading the Debugbar Queries tab for N+1
Verified pattern — Debugbar's Queries tab lists every query run for the request. N+1 looks like the SAME query repeated N times with different bindings (often `select * from X where id = ?` repeated for each member in a loop).

```text
# What N+1 looks like in Debugbar → Queries:
  0.32 ms  select * from `members` where `members`.`mess_id` = 1   ← 1 query for all members (good)
  0.21 ms  select * from `meal_entries` where `meal_entries`.`member_id` = 1   ← N+1 BEGIN
  0.18 ms  select * from `meal_entries` where `meal_entries`.`member_id` = 2
  0.19 ms  select * from `meal_entries` where `meal_entries`.`member_id` = 3
  ... (48 more)

# What good looks like (what MealGridService ALREADY does):
  0.32 ms  select * from `members` where `members`.`mess_id` = 1
  0.45 ms  select * from `meal_entries` where `date` = '2026-06-18' and `member_id` in (1,2,3,...,50)  ← ONE query
```

`[VERIFIED: MealGridService.php:27-31 uses whereIn('member_id', $activeMembers->pluck('id')) — already correct]`

### Example 2: Fixing an N+1 when found (eager load vs select-specific vs cache)

Per the laravel-best-practices skill §1 + §2 + §4, the order of preference for an N+1 fix:

```php
// BAD — N+1 (controller or blade loops over members and lazy-loads relation):
foreach ($members as $member) {
    echo $member->advanceBalance->balance;  // 1 query per member
}

// FIX OPTION A — eager load (best if you need all the relation data):
$members = Member::with('advanceBalance')->get();   // 2 queries total

// FIX OPTION B — select-specific subquery (best if you need ONE value):
$members = Member::addSelect([
    'advance_total' => AdvanceBalance::select('balance')
        ->whereColumn('member_id', 'members.id')
        ->take(1),
])->get();

// FIX OPTION C — cache the aggregate (best for read-mostly like the dashboard):
$totals = Cache::remember("dash:counts:{$messId}:{$ym}", now()->addHour(), function () {
    return ['total_advance' => AdvanceBalance::whereIn('member_id', $memberIds)->sum('balance')];
});

// Option C is what DashboardService ALREADY does (verified). Don't reinvent.
```

`[CITED: .agents/skills/laravel-best-practices/SKILL.md §1, §2, §4]`

### Example 3: Reading the Debugbar Cache tab (D-09 hit-rate)

```text
# Debugbar → Cache tab (after 3 warm reloads of /home):

  WRITE  bill-preview:1:2026-06   MISS → MISS (first load — cold)
  READ   bill-preview:1:2026-06   HIT  (2nd reload)
  READ   bill-preview:1:2026-06   HIT  (3rd reload)
  WRITE  dash:counts:1:2026-06    MISS → MISS (first load)
  READ   dash:counts:1:2026-06    HIT  (2nd reload)
  READ   dash:counts:1:2026-06    HIT  (3rd reload)

# hit_rate = 4 HITs / (4 HITs + 2 initial MISSes that populate) = 67% on cold+3warm
# But the REAL hit-rate (excluding the initial populate) = 4/4 = 100%.
# D-09: "on REPEAT dashboard loads" — measure the warm steady-state.
```

### Example 4: Query-count smoke test (OPTIONAL — D-08 says "acceptable, not required")

If the planner wants to LOCK an N+1 fix so it can't regress:

```php
// tests/Feature/Perf/MealGridQueryCountTest.php
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MealGridQueryCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_meal_grid_loads_under_10_queries_at_50_members(): void
    {
        // Seed 50 members + today's meals
        Member::factory()->count(50)->for($this->mess)->create();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->manager)->get('/mess/meals');

        $response->assertOk();
        $count = count(DB::getQueryLog());
        $this->assertLessThan(10, $count, "Meal grid ran {$count} queries, expected < 10 (N+1?)");
    }
}
```

This locks the query COUNT (not wall-clock — which is flaky). D-08 allows but doesn't require this.

### Example 5: Supervisor config for the CloseMonthJob worker (for DEPLOYMENT.md)

```ini
; /etc/supervisor/conf.d/mess-worker.conf  (Laravel Forge writes this for you if using Forge)
[program:mess-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/mess/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1                   ; 1 worker is enough for one mess (CloseMonthJob is rare)
redirect_stderr=true
stdout_logfile=/var/www/mess/storage/logs/worker.log
stopwaitsecs=3600            ; MUST exceed job timeout (CloseMonthJob timeout=120s)
```

```bash
# After writing the config:
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start mess-worker:*

# The schedule:run cron (Forge also adds this for you):
* * * * * cd /var/www/mess && php artisan schedule:run >> /dev/null 2>&1
```

`[CITED: laravel.com/docs/13.x/queues — Supervisor Configuration; laravel.com/docs/13.x/scheduling]`

### Example 6: Reading CloseMonthJob timing in Telescope

```text
# Browse to /telescope (gated by Gate::define viewTelescope) → Jobs tab

  CloseMonthJob   2026-06-18 14:23:01   COMPLETED
    Queue: default
    Wait time: 0.2s      ← time spent waiting in the queue before a worker picked it up
    Handle time: 4.7s    ← THIS is the <30s @50 budget metric (Telescope captures it for you)
    Payload: {"year":2026,"month":6,"closedBy":1}
    Tries: 1 / max 1
    Exceptions: 0
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `barryvdh/laravel-debugbar` namespace | `barryvdh/*` still works on Packagist but the maintained package is also published as `fruitcake/laravel-debugbar` | Debugbar v4.0.0 (late 2024) | None — `composer require barryvdh/laravel-debugbar:^4.3` still resolves correctly. Don't switch namespaces. |
| xdebug for coverage | pcov (purpose-built, faster) | pcov 1.0 released 2019; recommended by phpunit docs since PHPUnit 9 | Use pcov for D-22. Faster test runs, no debug-overhead when coverage isn't needed. |
| Manual cron `schedule:run` editing | Forge "Scheduler" UI writes the crontab | Forge current | Either works (D-15 allows both); Forge is less error-prone. |
| Shared hosting for Laravel | VPS (Forge or manual) for any app with queues | Always true for queue-using Laravel apps | D-15 explicitly rules out shared hosting because CloseMonthJob needs a persistent worker. |
| Laravel Pulse | Stable (v1) | Laravel 11+ | Out of scope for v1 (production APM, not dev tooling). Pulse deferred. |
| Laravel Horizon | Redis-only | Always | Doesn't fit — project uses `database` queue driver. Use Telescope for queue insight. |

**Deprecated/outdated:**
- `barryvdh/laravel-debugbar` v3.x — incompatible with Laravel 13. Use `^4.3`. `[VERIFIED: Packagist constraints]`
- `laravel/telescope` v4.x — incompatible with Laravel 13. Use `^5.20`. `[VERIFIED: Packagist constraints]`

---

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | 360px is a sufficient mobile support floor for the target user base (Bangladeshi mess managers on Android) | User Constraints D-13 / Mobile | LOW — iPhone SE is 375px, most Androids are 360+. 320px is iPhone 5/SE1 era (rare). If the pilot manager's phone is <360px, reconsider. |
| A2 | pcov will install cleanly on the dev Windows PHP 8.4 build | Environment Availability / Pitfall 3 | MEDIUM — pcov DLLs for PHP 8.4 ZTS Windows builds can lag. Fallback: xdebug (slower but universally packaged). Verify the DLL availability in Wave 0. |
| A3 | The pilot dev has direct access to ONE real mess willing to participate | User Constraints D-01 | LOW (per CONTEXT.md D-01) — but this is a human dependency, not a technical one. If it slips, the pilot criterion (#12) cannot be met; the OTHER 11 criteria still ship. |
| A4 | A persistent queue worker via supervisor on a VPS is the right pilot deployment topology | User Constraints D-15 | LOW — D-15 explicitly locks this; the alternative (shared hosting) is ruled out by the CloseMonthJob requirement. |
| A5 | Debugbar's `capture_ajax: true` won't corrupt the meal-grid AJAX save responses (only the rendered bar is suppressed via `ajax_handler_enabled: false`) | Pitfall 2 | LOW-MEDIUM — verify in Wave 0 by triggering a meal-grid save and checking the JSON response is clean. |

**If this table is otherwise empty:** All other claims were verified via direct file reads, the Packagist API, or official Laravel docs (cited inline).

---

## Open Questions

1. **Forge vs manual VPS — which to document primarily?**
   - What we know: D-15 allows EITHER. Forge is significantly less work (managed supervisor + cron + deploy-on-push). Manual is cheaper ($5/mo VPS vs $12/mo Forge + VPS).
   - What's unclear: The dev's budget preference.
   - Recommendation: Document Forge as the primary path (faster to ship the pilot) and include a "Manual VPS" appendix section in DEPLOYMENT.md. The pilot needs to ship quickly; ops cost can be optimized post-pilot.

2. **pcov availability for PHP 8.4 ZTS on Windows**
   - What we know: pcov PECL package tracks PHP releases but Windows DLLs lag.
   - What's unclear: Whether a prebuilt pcov DLL exists for PHP 8.4.15 ZTS specifically (ZTS is less common than NTS).
   - Recommendation: Wave 0 task — try `pecl install pcov` first; if that fails, download the DLL from windows.php.net downloads / PECL; if no ZTS-compatible DLL exists, fall back to xdebug (universally packaged). Coverage WILL be measurable either way.

3. **The pilot dev's MySQL credentials for the production VPS**
   - What we know: Dev environment uses password `125524` (STATE.md Open Questions resolved). Production VPS will need its own.
   - What's unclear: Whether the dev wants to provision the VPS MySQL themselves or use Forge's managed DB.
   - Recommendation: DEPLOYMENT.md should document both paths; the actual prod creds are entered at deploy time, not in the doc.

4. **Whether to bump Pint from 1.29.1 → 1.29.3**
   - What we know: Non-breaking patch bump.
   - What's unclear: Whether it matters (both are Laravel-preset compatible).
   - Recommendation: Yes, bump it during the Pint audit (D-19) — costs nothing, removes a "you're on an old version" flag from any future scan.

---

## Validation Architecture

> **Nyquist validation is DISABLED for this project** (the planner should treat it as optional per the research_focus note). This section is included ONLY because Phase 5's success criteria include explicit test coverage (#9) and the human-UAT items (D-23) — both of which are validation work.

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit 12.5.30 (NOT Pest) |
| Config file | `phpunit.xml` (verified — uses MySQL `devsroom_mess_management_testing`, `array` cache, `sync` queue) |
| Quick run command | `vendor/bin/phpunit --testsuite=Feature` |
| Full suite command | `vendor/bin/phpunit` (or `composer run test`) |
| Coverage command | `vendor/bin/phpunit --coverage-text` — **requires pcov or xdebug (currently MISSING)** |

### Phase Requirements → Test/Verification Map
| Req ID | Behavior | Verification Type | Method | File Exists? |
|--------|----------|-------------------|--------|-------------|
| PERF-04 | All pages render at 320/375/768/1024 | manual + real device | DevTools device emulation + pilot phone | Manual checklist in `05-VERIFICATION.md` |
| PERF-06 (success #2) | Grid <100ms @50 | manual measurement | Debugbar query tab on `/mess/meals` with 50 seeded members | Wave 1 (post-seeder) |
| PERF-06 (success #3) | Dashboard <500ms | manual measurement | Debugbar query tab on `/home` warm | Wave 1 |
| PERF-06 (success #4) | Month-close <30s @50 | manual measurement | Telescope Jobs tab on CloseMonthJob | Wave 1 |
| PERF-06 (success #5) | No N+1 in dev | manual inspection | Debugbar Queries tab — count repeated queries | Wave 1 |
| PERF-06 (success #6) | Cache hit >80% | manual measurement | Debugbar Cache tab on 5× warm reloads | Wave 1 |
| PERF-13 | Pint clean | automated | `vendor/bin/pint --test` | n/a (D-19) |
| (success #7) | Asia/Dhaka everywhere | manual verification | `grep APP_TIMEZONE .env` + `php artisan tinker → now()` | n/a (D-21) |
| (success #8) | `__()` everywhere | automated (grep) | grep for un-wrapped `{{ }}` in views | n/a (D-20) |
| (success #9) | Coverage >70% | automated | `vendor/bin/phpunit --coverage-text` (needs pcov/xdev) | Wave 0 (install driver) |
| D-23 HUMAN-UAT #1 | Chart rendering | manual | Browser: `/home` as admin; verify 3 charts visible | `04-HUMAN-UAT.md` |
| D-23 HUMAN-UAT #2 | PDF layout | manual | Download `/mess/reports/monthly.pdf`; verify | `04-HUMAN-UAT.md` |
| D-23 HUMAN-UAT #3 | Mobile responsive | manual | `/my` at 375px; cards stack, no overflow | `04-HUMAN-UAT.md` |
| D-23 HUMAN-UAT #4 | Cache refresh | manual | POST bazar expense; reload /home; verify <2s | `04-HUMAN-UAT.md` |

### Wave 0 Gaps
- [ ] **Install pcov OR xdebug** for coverage measurement (D-22 BLOCKER).
- [ ] **Install Debugbar + Telescope** (D-06).
- [ ] **Publish `config/debugbar.php` + `config/telescope.php`** + `TelescopeServiceProvider` (post-install commands).
- [ ] **Run `telescope:install` migrations** (adds `telescope_entries` + `telescope_entries_tags` + `telescope_monitoring`).
- [ ] **(Optional)** Create `tests/Feature/Perf/MealGridQueryCountTest.php` if the planner wants to lock the N+1 fix (D-08 says acceptable, not required).

*(Existing test infrastructure — 154 tests, 56 test files across Auth/Dashboard/Foundation/Mess/My/Report/Report/* — covers the full domain. No new fixture infrastructure needed beyond the seeder.)*

---

## Security Domain

> Phase 5 adds two dev-only debugging tools (Debugbar + Telescope) that, if misconfigured, can leak secrets or grant unauthorized dashboard access. The threat surface is narrow but real. ASVS coverage is minimal because this is NOT a feature phase — most controls were established in Phases 1–4 (auth via Tyro, IDOR prevention via session-derived member, decimal money, parameterized queries via Eloquent).

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | NO (no Phase 5 change) | Tyro Login (Phase 1) — already enforced |
| V3 Session Management | NO | Tyro sessions — already enforced |
| V4 Access Control | YES — Telescope dashboard gate | `Gate::define('viewTelescope', fn($u) => $u->hasRole('super-admin'))` + `enabled` closure (Pattern 2) |
| V5 Input Validation | NO (no new inputs) | Existing Form Requests |
| V6 Cryptography | NO | n/a |
| V7 Error Handling | YES — production APP_DEBUG=false | DEPLOYMENT.md checklist (D-18) |
| V9 Communications | YES — HTTPS in prod | DEPLOYMENT.md checklist (D-18) |
| V14 Configuration | YES — Debugbar/Telescope env-gating | Pattern 1 + Pattern 2; require-dev (D-06) |

### Known Threat Patterns for the Phase 5 tooling

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Telescope dashboard exposed in prod | Information Disclosure | (a) `enabled` closure returns false in prod; (b) `require-dev` (composer install --no-dev on prod); (c) `Gate::define` for non-local access. Three layers. |
| Debugbar leaking DB query bindings / stack traces | Information Disclosure | Same three-layer gate as Telescope + APP_DEBUG=false in prod. |
| `telescope_entries` table grows unbounded (DoS via DB size) | Denial of Service | `enabled` returns false in prod (no writes); schedule `telescope:prune` daily in dev. |
| Debugbar HTML corrupts PDF / API response | Tampering (data integrity) | `exclude_paths: ['*.pdf', 'api/*']` in `config/debugbar.php`. |
| `APP_DEBUG=true` shipped to prod | Information Disclosure | DEPLOYMENT.md checklist + verification step. |
| APP_TIMEZONE change shifts existing timestamps | Tampering (subtle data drift) | Fix BEFORE pilot onboarding (D-21 timing); dev DB re-seeded from scratch. |

---

## Sources

### Primary (HIGH confidence)
- **Packagist API** (`repo.packagist.org/p2/`) — version + constraint verification for `barryvdh/laravel-debugbar` (v4.3.0, `^11\|^12\|^13.0`), `laravel/telescope` (v5.20.0, `^13.0`), `laravel/pint` (v1.29.3). Queried 2026-06-18.
- **Direct file reads** of the perf-audit code targets: `BillPreviewService.php`, `DashboardService.php`, `MealGridService.php`, `CloseMonthJob.php`, `BillPreviewInvalidator.php`, `AppServiceProvider.php`, `config/app.php`, `composer.json`, `.env.example`, `phpunit.xml`, `DatabaseSeeder.php`, factories. (All claims tagged `[VERIFIED]` reference these reads.)
- **05-CONTEXT.md** — the 23 locked decisions (D-01..D-23) that scope this research.
- **STATE.md session notes** — Phase 1–4 history, the BillPreviewService debug-throw flag, timezone "validated in Phase 1" claim (now re-verified as gap).

### Secondary (MEDIUM confidence)
- [laravel.com/docs/13.x/telescope](https://laravel.com/docs/13.x/telescope) — `enabled` closure, `Gate::define('viewTelescope')`, pruning.
- [laravel.com/docs/13.x/queues](https://laravel.com/docs/13.x/queues) — Supervisor configuration pattern.
- [laravel.com/docs/13.x/scheduling](https://laravel.com/docs/13.x/scheduling) — `schedule:run` cron entry.
- [forge.laravel.com/docs/sites/queues](https://forge.laravel.com/docs/sites/queues) — Forge-managed supervisor + auto-restart on deploy.
- [github.com/barryvdh/laravel-debugbar](https://github.com/barryvdh/laravel-debugbar) — `config/debugbar.php` options (`capture_ajax`, `exclude_paths`, `enabled`).
- `phpunit.de` current docs — pcov recommendation for coverage.
- Project skills: `.agents/skills/laravel-best-practices/SKILL.md` §1/§2/§4 (N+1, caching patterns).

### Tertiary (LOW confidence)
- [github.com/laravel/telescope/issues/536](https://github.com/laravel/telescope/issues/536) — pruning very large tables (community workaround for the unbounded-growth Pitfall 1).

---

## Metadata

**Confidence breakdown:**
- Standard stack (Debugbar v4.3 / Telescope v5.20 / Pint 1.29): **HIGH** — verified via Packagist API directly against current published constraints.
- Pre-existing-issues verification (BillPreviewService debug-throw, dash:counts hook, timezone gap, sqlite parity): **HIGH** — verified by direct file reads during this session.
- Architecture patterns (env-gating, seeder, supervisor config): **HIGH** — drawn from official Laravel 13.x docs + verified existing project patterns.
- Pitfalls: **HIGH** for #1/#2/#4/#6 (verified against the project's actual stack), **MEDIUM** for #3/#7 (general Laravel knowledge applied to this project's specifics).
- Environment availability: **HIGH** — direct `php --version` + `php -m` probes; the only unknown (pcov DLL availability for PHP 8.4 ZTS Windows) is flagged as Wave 0 task, not a research gap.

**Research date:** 2026-06-18
**Valid until:** 2026-07-18 (30 days — stable stack; Debugbar/Telescope don't release breaking minors often, but re-verify version constraints at install time)

## RESEARCH COMPLETE

**Phase:** 5 — Polish + Pilot
**Confidence:** HIGH

### Key Findings
- **All three pre-existing-issues flagged in CONTEXT.md are verified CLOSED or confirmed real.** BillPreviewService debug-throw is GONE (Plan 03.3 fixed it). `dash:counts` cache hook ALREADY exists in AppServiceProvider (Phase 4 landed it). The timezone UTC-default + sqlite `.env` parity gaps are REAL and confirmed — these are the D-21 + D-18 fixes.
- **Both Debugbar (v4.3.0) and Telescope (v5.20.0) are verified Laravel 13 compatible** via direct Packagist API queries against their `composer.json` constraints. Install both as `require-dev` per D-06.
- **The MealGridService is ALREADY N+1-safe** — `whereIn('member_id', $activeMembers->pluck('id'))` for both MealEntry and MealOffRequest (2 queries, not N+1). The <100ms grid budget may already pass; verify with Debugbar, don't assume a fix is needed.
- **One true blocker: `pcov` or `xdebug` is NOT installed** — coverage measurement (D-22) is blocked until one is installed in Wave 0. Recommend pcov (faster). Windows ZTS DLL availability is the only minor uncertainty.
- **Telescope is more dangerous than Debugbar in prod** — it writes to `telescope_entries` on every request. Three-layer gating: `require-dev` + `enabled` closure defaulting to `app()->environment('local')` + `Gate::define('viewTelescope')` for super-admin only. Plus schedule `telescope:prune` daily.
- **The seeder (D-07) is the keystone Wave 1 deliverable** — it unblocks perf measurement (D-08/D-09), the README demo-credentials story (D-16), AND realistic mobile density testing. Build it early. Guard it so production `db:seed` never runs it.

### File Created
`D:\Devsroom-Work\devsroom-mess-management\.planning\phases\05-polish-pilot\05-RESEARCH.md`

### Confidence Assessment
| Area | Level | Reason |
|------|-------|--------|
| Standard stack | HIGH | Packagist API verification of versions + Laravel 13 constraints for both new packages |
| Pre-existing issues | HIGH | Direct file reads during this session — no inference |
| Architecture (gating, seeder, supervisor) | HIGH | Official Laravel 13.x docs + verified existing project patterns |
| Pitfalls | HIGH (most) / MEDIUM (a few) | Verified against project's actual stack where possible |
| Environment availability | HIGH | Direct probes; pcov-install detail flagged as Wave 0 task |

### Open Questions
1. Forge vs manual VPS as primary DEPLOYMENT.md path (recommend Forge primary + manual appendix).
2. pcov DLL availability for PHP 8.4 ZTS on Windows (Wave 0 task; xdebug fallback).
3. Production MySQL credentials (deploy-time, not doc-time).
4. Pint 1.29.1 → 1.29.3 bump (recommend yes, costs nothing).

### Ready for Planning
Research complete. The planner can now create PLAN.md files. Suggested plan breakdown aligns with the ROADMAP's 3 plans (5.1 Mobile UX polish, 5.2 Performance audit, 5.3 Documentation + deployment + pilot) with a Wave 0 covering: install pcov + Debugbar + Telescope + publish their configs + the timezone/parity/pint/`__()` mechanical audits (cheap, do early). The ~50-member seeder (D-07) should land in Wave 0 or early Wave 1 — it's the unblocker for both perf measurement and demo creds.
