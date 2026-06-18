# Phase 5: Polish + Pilot - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in `05-CONTEXT.md` — this log preserves the alternatives considered.

**Date:** 2026-06-18
**Phase:** 5-polish-pilot
**Areas discussed:** Real-mess pilot logistics, Performance & N+1 tooling, Mobile polish depth, Docs + deployment target

---

## Real-mess pilot logistics

| Option | Description | Selected |
|--------|-------------|----------|
| My own / known mess | Dev has direct access to a real mess ready to pilot — fastest path, real data, tight feedback | ✓ |
| A contact's mess | Warm intro to someone else's mess — real data, one step removed from daily ops | |
| Not identified yet | No mess lined up — phase becomes "prep for pilot," actual pilot slips past the boundary | |

**User's choice:** My own / known mess (direct access)

| Option | Description | Selected |
|--------|-------------|----------|
| Fresh start, current month | Pilot tracks from current month only; no historical import; pilot month IS the first recorded month | ✓ |
| Import last 1–2 months | Backfill prior months from Excel for context — requires a one-off CSV importer (scope add) | |
| Full history import | Migrate full history — significant effort, out of scope for a polish phase | |

**User's choice:** Fresh start, current month (no importer needed)

| Option | Description | Selected |
|--------|-------------|----------|
| Hybrid — I set up, they run daily | Dev configures mess+members+settings, manager runs daily ops — lowest friction for a non-technical manager | ✓ |
| Fully guided, side-by-side | Dev present for everything including first days of entry — highest support, best observation | |
| Self-serve with setup guide | Manager follows docs/onboarding alone — requires genuinely self-serve UX + docs | |

**User's choice:** Hybrid — dev configures, manager runs daily ops

| Option | Description | Selected |
|--------|-------------|----------|
| One clean month-close + members see bills | Close completes, members view bills, no data-loss/math bugs — matches v1 milestone | ✓ |
| Two consecutive clean cycles | Above + a second month to verify carry-forward + closed-month lock across cycles | |
| Clean cycle + manager sign-off | Above + manager confirms they'd keep using it / prefer it over spreadsheet | |

**User's choice:** One clean month-close + members see bills

---

## Performance & N+1 tooling

| Option | Description | Selected |
|--------|-------------|----------|
| Debugbar (dev toolbar) | Per-request queries, N+1 warnings, timings, cache hits/misses — fits page-budget + N+1 checks | |
| Telescope (observability) | App-wide dashboard incl. queued job timing + cache across requests — good for the close budget | |
| Both (Debugbar + Telescope) | Debugbar for page-load/N+1; Telescope for CloseMonthJob + cache hit-rate | ✓ |

**User's choice:** Both (Debugbar + Telescope)

| Option | Description | Selected |
|--------|-------------|----------|
| Reproducible ~50-member seeder | Generates ~50 members + a full month; re-runnable; doubles as demo dataset | ✓ |
| Use real pilot data | Wait for pilot mess to hit ~50 and measure live — real but not reproducible, only available late | |
| Manual one-off insert | Artisan tinker/raw SQL to hit 50 once — fastest but not repeatable | |

**User's choice:** Reproducible ~50-member seeder

| Option | Description | Selected |
|--------|-------------|----------|
| Manual check + record in VERIFICATION | Run each screen with Debugbar, record query count + timing in `05-VERIFICATION.md` — pragmatic | ✓ |
| Automated benchmark test | Timed PHPUnit test asserting <100ms — repeatable CI gate but famously flaky | |
| Manual + a query-count smoke test | Manual numbers now + a lightweight regression test locking the N+1 fix | |

**User's choice:** Manual check + record in VERIFICATION

| Option | Description | Selected |
|--------|-------------|----------|
| Eyeball via Debugbar cache tab | Load dashboard, confirm bill-preview + dash:counts keys hit (not recompute) — no code changes | ✓ |
| Temporary hit/miss logger | Log cache get/put to a file, compute >80% ratio over a session — more rigorous, throwaway code | |
| Rely on design (no explicit measure) | Phase 3 invalidation-on-write guarantees freshness; treat >80% as met-by-design | |

**User's choice:** Eyeball via Debugbar cache tab

---

## Mobile polish depth

| Option | Description | Selected |
|--------|-------------|----------|
| DevTools now + real device in pilot | Emulation at all breakpoints during polish, real Android confirm during pilot — matches ROADMAP risk note | ✓ |
| DevTools emulation only | Faster, but misses real-device quirks (input, viewport meta, font rendering) | |
| Real device from the start | Most authentic for every change but slowest to iterate | |

**User's choice:** DevTools now + real device in pilot

| Option | Description | Selected |
|--------|-------------|----------|
| Audit + meal-grid touch/density pass | Full responsive audit + dedicated ≥44px touch-target + density pass on the densest screen | ✓ |
| CSS breakpoint fixes only | Only fix things that visually break — no interaction/density rework | |
| Full interaction rework | Rethink UX of top screens — heaviest, risks redesign scope-creep | |

**User's choice:** Audit + meal-grid touch/density pass

| Option | Description | Selected |
|--------|-------------|----------|
| 360px practical floor | Support down to 360px (all modern phones); 320px best-effort, not a hard gate | ✓ |
| Strict 320px support | Hold the line at 320px as success #1 states — may force grid compromises | |

**User's choice:** 360px practical floor

---

## Docs + deployment target

| Option | Description | Selected |
|--------|-------------|----------|
| VPS (Forge or manual) | Cheap VPS + persistent queue worker (supervisor) + MySQL + public URL — queue/cron just work | ✓ |
| Shared hosting (cPanel) | Cheapest, common in BD, but persistent queue workers are awkward (close job constrained) | |
| Local/office box for the pilot | Dev/office machine, no public URL yet — simplest for one accessible mess | |

**User's choice:** VPS (Forge or manual)

| Option | Description | Selected |
|--------|-------------|----------|
| Full setup + demo creds + overview | What it is, prereqs, setup, demo seeder, demo manager+member creds, common commands | ✓ |
| Minimal setup + demo creds | Just setup commands + demo creds; skip overview/deployment detail | |
| Defer README | Wait until pilot surfaces what onboarding actually needs | |

**User's choice:** Full setup + demo creds + overview

| Option | Description | Selected |
|--------|-------------|----------|
| Refresh + hand-written domain section | Re-run auto-gen, then add a domain walkthrough (bill math, close flow, cache, role/IDOR model) | ✓ |
| Auto-gen refresh only | Sync codebase maps; no hand-written prose — agents still lack the "why" | |
| Leave as-is | Keep the 238-line init version; rely on `.planning/` docs for context | |

**User's choice:** Refresh + hand-written domain section

| Option | Description | Selected |
|--------|-------------|----------|
| DEPLOYMENT.md + fix sqlite parity | Hardening checklist doc AND fix the dev `.env` sqlite→MySQL parity as an explicit task | ✓ |
| Checklist doc only | Write the doc; don't touch the working dev `.env` this phase | |
| Ad-hoc during pilot | Skip a formal doc; handle production config when standing up the pilot deploy | |

**User's choice:** DEPLOYMENT.md + fix sqlite parity

---

## Claude's Discretion

(Logged for audit — these were NOT user decisions, they were deferred to Claude during planning/implementation)
- Seeder name/location and factory-driven row counts
- Debugbar/Telescope env-gating + authorization config
- Which N+1 queries to fix first (Debugbar/Telescope surfaces them) and the fix approach
- Mobile touch-target implementation (reuse Tailwind `min-h-[44px]`)
- `DEPLOYMENT.md` structure and Forge-vs-manual step depth
- Whether to add a query-count smoke test (acceptable, not required)
- Coverage tool (xdebug vs pcov); CI pipeline is NOT required for the pilot
- Pilot feedback channel (default direct WhatsApp/call with the manager)
- Pilot start date (driven by dev's relationship with the mess)

## Deferred Ideas

(Ideas mentioned/considered but explicitly out of Phase 5 scope)
- Two-cycle pilot / advance-due carry-forward gate — beyond one-clean-close bar
- Manager sign-off / "prefer over spreadsheet" gate — subjective
- Historical data importer — fresh start chosen; post-pilot if a 2nd mess needs it
- Bengali translations (`bn.json`) — v2
- CI pipeline (GitHub Actions on MySQL) — post-pilot
- Redis for production cache/queue — database driver fine for one mess in v1
- Full mobile interaction rework / bottom-sheet patterns — audit + density pass chosen
- Strict 320px support — 360px floor chosen
- Automated perf benchmark tests — manual measurement chosen
- In-app pilot feedback feature — direct channel chosen
