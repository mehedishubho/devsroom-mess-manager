# Phase 5: Polish + Pilot - Context

**Gathered:** 2026-06-18
**Status:** Ready for planning

<domain>
## Phase Boundary

Make the app production-ready for one real mess. This is a **hardening + pilot phase**, not a feature-building phase: hit the performance budgets, polish mobile UX, close dev/prod parity gaps, finish documentation, and run a real mess through one full monthly cycle.

Requirements covered: PERF-04 (mobile-first), PERF-06 (cache), PERF-13 (Pint) + general polish. Success criteria are ROADMAP Phase 5 #1–#12.

The pilot is intentionally minimal: **one mess the dev has direct access to, fresh-start the current month (no importer), hybrid onboarding (dev configures, manager runs daily), and the bar is one clean month-close with members viewing their own bills and zero data-loss/math bugs.** No two-cycle gate, no formal sign-off, no historical migration.

New capabilities (multi-mess, bKash API, PWA, Bengali, SMS, real-time, public API) are v2 and out of scope. Feature-building is done — this phase measures, fixes, documents, and ships.

</domain>

<decisions>
## Implementation Decisions

### Real-mess pilot logistics (success #12)

- **D-01:** **Pilot mess = a real mess the dev has direct access to** (own / family / close contact). Real data, tight feedback loop. No cold outreach, no volunteer recruiting.
- **D-02:** **Fresh start, current month — no historical importer.** The pilot month IS the first recorded month. Building a CSV/Excel importer is explicitly out of scope for a polish phase; it's a post-pilot concern if a second mess needs it.
- **D-03:** **Hybrid onboarding.** The dev configures the mess + members + settings (screen-share or in-person); the manager then runs daily ops (meals, bazar, payments, close). The manager is NOT expected to self-serve setup.
- **D-04:** **Pilot success bar = one clean month-close completes + members can view their own bills + zero data-loss or math-wrong bugs.** NOT two consecutive cycles, NOT a formal manager sign-off — those are nice-to-haves beyond the bar. (If the pilot naturally runs a second month, observe advance/due carry-forward, but don't gate on it.)
- **D-05:** **Feedback channel = direct WhatsApp/call with the manager** (Claude's discretion). No in-app feedback/bug-report feature is built for the pilot.

### Performance & N+1 tooling (success #2, #3, #4, #5, #6)

- **D-06:** **Install both `barryvdh/laravel-debugbar` and `laravel/telescope` as dev-only (`require-dev`) composer deps.** Debugbar = per-request page-load timing, N+1 warnings, cache hits/misses (the screen budgets). Telescope = queued `CloseMonthJob` timing + cache across requests (the close budget + cache hit-rate). Neither ships to production (gated by `APP_ENV=local` / authorized dashboard).
- **D-07:** **Build a reproducible ~50-member seeder** (~50 members + a full month of meals, bazar, fixed expenses, payments) to verify the @50-member budgets (grid <100ms, close <30s). **This same seeder seeds the demo dataset for the README demo-credentials story** — one seeder, two purposes.
- **D-08:** **Acceptance = manual measurement with Debugbar, recorded in `05-VERIFICATION.md`.** No automated timing tests (flaky across machines). A query-count smoke test (assert grid does < N queries regardless of wall-clock) is acceptable to lock an N+1 fix but is NOT required.
- **D-09:** **Cache hit-rate (>80%) measured by eyeballing Debugbar's cache tab** on repeat dashboard loads — the `bill-preview:{mess_id}:{YYYY}-{MM}` + `dash:counts:{mess_id}:{YYYY}-{MM}` keys should hit, not recompute. No temporary logger unless Debugbar is ambiguous.
- **D-10:** **Performance budgets are a hard pass/fail gate, not aspirational.** A missed budget means "fix the N+1 / slow query / missing cache," NOT "relax the budget." Grid <100ms @50 members; dashboard <500ms; close <30s @50 members; cache hit >80%.

### Mobile polish (success #1)

- **D-11:** **Mobile tested via browser DevTools device emulation at 320/375/768/1024 during polish, then confirmed on a real Android device during the pilot** (matches the ROADMAP risk note "test on actual mobile devices in Phase 5 pilot"). DevTools-only is not sufficient; the real device is the final authority.
- **D-12:** **Polish depth = full responsive audit at all breakpoints + a dedicated meal-grid touch-target (≥44px) and density pass.** The meal grid is the densest, most-used manager screen on a phone. NOT a full interaction rework (no bottom-sheet patterns, no UX redesign).
- **D-13:** **360px is the practical support floor** (covers virtually all modern phones incl. iPhone SE / small Androids). **320px is best-effort, NOT a hard gate.** Do not heavily compromise the grid (horizontal scroll, collapsed columns) just to fit 320px.
- **D-14:** **Manager daily-ops screens (meal grid, bazar/expense entry, payments) get the most polish attention** — that's where the manager lives on a phone. Member-facing screens are read-mostly and lower-priority.

### Docs + deployment (success #10, #11)

- **D-15:** **Deploy target = a VPS (DigitalOcean/Hetzner/etc.) via Laravel Forge or manual setup**, running a persistent queue worker (supervisor) for `CloseMonthJob` + MySQL + a public URL. **Shared hosting is ruled out** — it cannot run a persistent worker reliably for the queued month-close. This is the deployment-guide target.
- **D-16:** **README rewritten in full:** what the app is, prerequisites (PHP 8.4, MySQL 8+, Node), clone/composer/npm/`.env`/migrate setup, running the ~50-member demo seeder, **demo manager + member credentials** (from the seeder, D-07), and common commands (pint, tests, `queue:work`, `schedule:run`). Replaces the default Laravel stub.
- **D-17:** **AGENTS.md = re-run the auto-gen updater** (sync codebase maps to Phases 2–4 code) **AND add a hand-written "domain walkthrough" section** covering: the bill math (bazar-only meal rate, equal fixed split, advance/due carry-forward), the month-close flow (queued, idempotent via UNIQUE index, hard-locked via `EnsureMonthIsOpen`, immutable snapshot + `monthly_corrections`), the cache key strategy (`bill-preview` + `dash:counts`, both mess-scoped, invalidate on write), and the role/IDOR model (no `{member}` URL param on member routes — member derived from session).
- **D-18:** **Write `DEPLOYMENT.md` production-hardening checklist** (APP_DEBUG=false, HTTPS, APP_URL, queue worker via supervisor, `schedule:run` cron, storage perms, production MySQL `.env`) **AND fix the dev `.env` sqlite→MySQL parity as an explicit task.** The live `.env` currently runs `DB_CONNECTION=sqlite` with no MySQL keys — a violation of the MySQL-only constraint (tests already run on MySQL via `phpunit.xml`).

### Mechanical audits (Claude drives with a stated bar)

- **D-19:** **Pint clean** (`vendor/bin/pint --test`) on all committed code — carry-forward standard, enforced. No new work unless a drift is found.
- **D-20:** **`__()` scan** — all user-facing strings wrapped. Audit-only (grep for un-wrapped Blade `{{ }}` output). Full Bengali translation (`bn.json`) stays deferred to v2.
- **D-21:** **Timezone verification — confirm `Asia/Dhaka` everywhere.** ⚠️ `config/app.php` defaults to `env('APP_TIMEZONE', 'UTC')` and `.env` has no `APP_TIMEZONE` key → the app likely runs UTC. Fix = set `APP_TIMEZONE=Asia/Dhaka` in `.env` + `.env.example` and verify. (STATE.md claims this was validated in Phase 1 — re-verify and close the gap.)
- **D-22:** **Test coverage measured (>70% target)** via `phpunit --coverage-text` (requires xdebug/pcov). Identify gap areas and add tests where cheap and meaningful. This is a measurement + targeted-fill task, NOT a blanket "write tests everywhere."
- **D-23:** **Clear the 4 pending Phase 4 HUMAN-UAT items** (chart rendering, PDF layout, mobile responsive, cache refresh — see `04-HUMAN-UAT.md`) as part of Phase 5 polish. They're the bridge from "automated tests green" to "a human confirms it works."

### Claude's Discretion

- Exact seeder name/location (`database/seeders/PerfDemoSeeder.php` or similar) and factory-driven member/row counts
- Debugbar/Telescope config (env-gating, Telescope dashboard authorization)
- Which specific N+1 queries to fix first (let Debugbar/Telescope surface them) and the fix approach (eager load vs. select-specific vs. cache)
- Mobile touch-target implementation (Tailwind `min-h-[44px]` convention already used in Phase 4 — reuse)
- `DEPLOYMENT.md` exact structure and Forge-vs-manual step depth
- Whether to add a query-count smoke test (D-08 says acceptable, not required)
- Coverage tool choice (xdebug vs pcov) — note no CI pipeline exists yet (CONCERNS #16); adding CI is NOT required for the pilot
- Pilot timeline / start date — driven by the dev's relationship with the pilot mess, not a planning artifact

### Folded Todos

None — no pending todos matched Phase 5 (`todo match-phase 5` returned 0 matches).

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Project context
- `.planning/PROJECT.md` — Vision, constraints (mobile-first 375px, MySQL-only, `Asia/Dhaka`, `__()` everywhere, decimal money, single mess), anti-recommendations (no PWA/bKash/Bengali in v1)
- `.planning/REQUIREMENTS.md` — **PERF-04, PERF-06, PERF-13** are the Phase 5 requirement surface; the **v1 milestone "Done means"** block; the **Out of Scope** table
- `.planning/ROADMAP.md` — Phase 5 goal, **success criteria #1–#12**, plan breakdown (5.1 mobile, 5.2 perf, 5.3 docs+pilot), v1 milestone definition, **Risks row** (mobile real-device testing)
- `.planning/STATE.md` — Current progress, validations, pre-existing issues flagged in session notes

### Prior phase context (Phase 5 hardens what these built)
- `.planning/phases/04-reports-dashboard/04-HUMAN-UAT.md` — **the 4 pending human-UAT items Phase 5 must clear (D-23)**
- `.planning/phases/04-reports-dashboard/04-VERIFICATION.md` — what's verified vs `human_needed` coming out of Phase 4
- `.planning/phases/04-reports-dashboard/04-CONTEXT.md` — dashboard cache strategy (D-17 `bill-preview` + `dash:counts` keys), the ⚠️ `BillPreviewService` debug-throw flag (verify resolved before perf work)
- `.planning/phases/03-payments-month-close/03-CONTEXT.md` — **D-14/D-15 cache** (single `bill-preview` key, 1h TTL, `database` driver, invalidate on write), D-16 live-vs-snapshot, `CloseMonthJob` + idempotency + `EnsureMonthIsOpen` hard-lock
- `.planning/phases/02-members-daily-operations/02-CONTEXT.md` — meal grid decisions (the densest screen Phase 5 polishes), mobile-first patterns
- `.planning/phases/01-foundation/01-CONTEXT.md` — timezone, decimal money, `mess_id`, `Auditable`, service layer, `__()`

### Codebase maps (already in repo)
- `.planning/codebase/STACK.md` — installed packages (**confirms Debugbar/Telescope NOT installed — to add in this phase**)
- `.planning/codebase/CONVENTIONS.md` — code style, Pint preset, test style, Form Request pattern
- `.planning/codebase/INTEGRATIONS.md` — cache (`database` driver, no tags), queue (`database` driver — **production wants supervisor/Forge for the persistent worker**)
- `.planning/codebase/TESTING.md` — PHPUnit 12, `RefreshDatabase`, factory usage (the seeder builds on these factories)
- `.planning/codebase/CONCERNS.md` — **#4** (no `.env` committed), **#9** (`APP_DEBUG=true`), **#15** (sqlite tests), **#16** (no CI), **#17** (default README) — all directly relevant Phase 5 concerns

### Research
- `.planning/research/SUMMARY.md` — stack decisions
- `.planning/research/PITFALLS.md` — cache pitfalls (#11 staleness, #15 stampede) relevant to cache-hit measurement

### Skills + taste
- `.agents/skills/laravel-best-practices/SKILL.md` — N+1 detection, caching patterns, Pint
- `.agents/skills/tyro-dashboard/SKILL.md` — Tyro integration patterns
- `.commandcode/taste/taste.md` — Laravel 13, MySQL, snake_case, `Mess::activeId()`

### Perf-audit code targets (the budgets live here)
- `app/Services/MealGridService.php` — the daily grid (**the <100ms @50 members budget**)
- `app/Services/DashboardService.php` — manager dashboard cards (**the <500ms budget**)
- `app/Jobs/CloseMonthJob.php` — the queued close (**the <30s @50 budget**)
- `app/Services/BillPreviewService.php` + `app/Services/BillPreviewInvalidator.php` — the cached computation feeding dashboard + reports
- `app/Providers/AppServiceProvider.php` — `invalidateForModel()` cache hook (verify it covers `dash:counts`)
- `config/app.php` — `'timezone' => env('APP_TIMEZONE', 'UTC')` (**the UTC-default gap to fix, D-21**)
- `database/factories/*` — factories the seeder (D-07) builds on

### Docs to update/create
- `README.md` — rewrite (D-16)
- `AGENTS.md` — refresh auto-gen + add domain walkthrough (D-17)
- `DEPLOYMENT.md` — new, production-hardening checklist (D-18)

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `database/factories/*` (Member, Expense, Payment, MealEntry, etc.) — the reproducible seeder (D-07) builds on these, no new factory plumbing needed
- `App\Services\BillPreviewService` + `BillPreviewInvalidator` — the cache surface to measure (D-09) and the bill math the domain walkthrough (D-17) must explain
- `App\Services\DashboardService`, `MealGridService` + `App\Jobs\CloseMonthJob` — the three perf-budget targets (D-10)
- `App\Providers\AppServiceProvider::invalidateForModel()` — cache invalidation already wired for 5 models on `saved`/`deleted` (Phase 4 extended it for `dash:counts`); verify, don't rebuild
- Tailwind `min-h-[44px]` touch-target convention (Phase 4) — reuse for the mobile pass (D-12)
- `phpunit.xml` already points at a MySQL testing DB (`devsroom_mess_management_testing`) — test-side MySQL parity exists; only the dev `.env` lacks it (D-18)

### Established Patterns
- **Service layer** (16 services in `app/Services/`) — no perf/business logic in controllers; perf fixes land in services
- **`Cache::remember` / `Cache::forget`**, `database` driver, no tags, string keys (Phase 3 D-14) — the cache-hit measurement (D-09) targets these keys
- **Mobile-first 375px**, Tailwind v4 + Blade, no inline CSS (PROJECT.md constraint) — the polish audit extends this to 320–1024 (D-11/D-13)
- **PHPUnit 12**, `test_` prefix, `RefreshDatabase` — coverage measurement (D-22) runs on this suite

### Integration Points
- `composer.json` `require-dev` — add `barryvdh/laravel-debugbar` + `laravel/telescope` (D-06)
- `database/seeders/` — add the ~50-member demo/perf seeder (D-07); register in `DatabaseSeeder` (guarded so prod seed stays safe)
- `.env` + `.env.example` — `APP_TIMEZONE=Asia/Dhaka` (D-21), `DB_CONNECTION=mysql` + MySQL keys (D-18), and the production `APP_DEBUG=false` / HTTPS / APP_URL guidance in `DEPLOYMENT.md` (D-18)
- `README.md`, `AGENTS.md`, `DEPLOYMENT.md` — docs surface (D-16/D-17/D-18)

### ⚠️ Pre-existing issues to verify/close during this phase
- `app/Services/BillPreviewService.php` debug-throw — flagged in `04-CONTEXT.md`; STATE session notes say Plan 03.3 removed it. **Verify it's gone before perf work depends on the service.**
- `.env` sqlite parity (D-18) and `APP_TIMEZONE` UTC default (D-21) — both confirmed live during this context-gathering scout.

</code_context>

<specifics>
## Specific Ideas

- **The ~50-member seeder is the Swiss-army knife:** perf fixture + demo dataset + README demo-credentials source. One seeder, three uses — build it once, early.
- **Perf budgets are a hard gate, not aspirational** — a missed budget means fix the code (eager-load the N+1, add the missing cache key), never relax the number.
- **The pilot is deliberately minimal:** one mess, fresh month, hybrid setup, one clean close. No importer, no two-cycle gate, no sign-off ceremony. Ship the smallest thing that proves the v1 milestone.
- **Real Android device is the final mobile authority**, not DevTools emulation. DevTools catches layout breaks fast; the pilot phone catches the real-device quirks.
- **`DEPLOYMENT.md` + the `.env` parity fix are paired** — documenting production hardening while the dev `.env` still runs sqlite would be incoherent; fix both together.

</specifics>

<deferred>
## Deferred Ideas

- **Two-cycle pilot** (verify advance/due carry-forward + closed-month lock across months) — beyond the one-clean-close bar (D-04); observe if it happens naturally, don't gate on it.
- **Manager sign-off / "prefer over spreadsheet" gate** — subjective, beyond the bar.
- **Historical data importer** (CSV/Excel backfill) — explicitly out (fresh start, D-02); build post-pilot only if a second mess needs it.
- **Bengali translations (`bn.json`)** — v2 (LOC-01..03); `__()` audit (D-20) is the v1-ready part only.
- **CI pipeline** (GitHub Actions running tests on MySQL) — CONCERNS #16; not required for the pilot, add post-pilot.
- **Redis for cache/queue in production** — `database` driver is fine for one mess in v1 (CONCERNS #3); Redis when scaling.
- **Full mobile interaction rework / bottom-sheet patterns** — chose audit + density pass (D-12); revisit only if the pilot demands it.
- **Strict 320px support** — chose 360px floor (D-13).
- **Automated perf benchmark tests** — chose manual measurement (D-08); timing tests are flaky across machines.
- **In-app pilot feedback/bug-report feature** — chose a direct WhatsApp channel (D-05); no new feature.

</deferred>

---

*Phase: 05-polish-pilot*
*Context gathered: 2026-06-18*
