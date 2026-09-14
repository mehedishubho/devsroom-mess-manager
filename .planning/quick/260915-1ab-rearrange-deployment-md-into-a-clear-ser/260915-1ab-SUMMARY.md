---
quick_id: 260915-1ab
slug: rearrange-deployment-md-into-a-clear-ser
date: 2026-09-14
status: complete
mode: quick
commits: [7e9eee4, 5cf8b1a]
files_modified: [DEPLOYMENT.md, README.md, SETUP-USAGE-DEPLOY.md, .env.example]
---

# Quick Task 260915-1ab — SUMMARY

**Task:** Rearrange DEPLOYMENT.md into a clear, serially-ordered setup guide for public use.

**Execution note:** GSD subagents in this runtime (gsd-planner, gsd-executor) receive only
Glob/Grep tools, so the plan was persisted and executed by the orchestrator following
`260915-1ab-PLAN.md` exactly. Both subagent dispatches confirmed the limitation and stopped
cleanly (executor did so on the first turn without wasting tokens).

## What changed

### Task 1 — DEPLOYMENT.md restructured (commit `7e9eee4`)
755 lines / 13 sections → 764 lines / 12 sections, in serial reading order:

| New | Content | Old |
|-----|---------|-----|
| §1 | Pick your deployment path (+ new reading-order note) | §1 |
| §2 | Prerequisites (production VPS) | §2 |
| §3 | Prepare the production `.env` — one checklist (3.1 core, 3.2 backup keys, 3.3 Docker-panels-only) | §5 + §12.2 |
| §4 | Path A — Laravel Forge (4.1 provision, 4.2 queue worker, 4.3 scheduler, 4.4 HTTPS+storage note) | §3 (+§6, §7 note) |
| §5 | Path B — Manual VPS (5.1 clone, 5.2 nginx, 5.3 supervisor worker, 5.4 cron+callout, 5.5 HTTPS, 5.6 storage) | §4 (+§6, §7) |
| §6 | Path C — Docker panels (6.1 three files, 6.2 env pointer→§3, 6.3 Dokploy, 6.4 Coolify, 6.5 worker+scheduler in containers) | §12 |
| §7 | Path D — cPanel shared hosting | §13 |
| §8 | Post-install essentials (8.1 super-admin, 8.2 SMTP) | from §8 step 2 |
| §9 | First-deploy verification | §8 |
| §10 | Post-deploy monitoring | §9 |
| §11 | Troubleshooting (all paths) — ONE table with a Path column | §10 + §11.9 + §12.5 |
| §12 | Backup & restore runbook (appendix, 12.1–12.8) | §11.1–11.8 |

- Env merge: old §12.2's 8 rows dissolved — APP_KEY/APP_URL/APP_DEBUG/MAIL_*/BACKUP_* notes
  UNIONED into §3.1/§3.2 rows; MYSQL_ROOT_PASSWORD + LOG_CHANNEL re-homed in §3.3
  (tagged Docker-only) with the original intro sentence + MARIADB_* mapping note.
- Troubleshooting merge: 22 rows → 20 rows with a Path column; exactly two de-duplications
  ("Month-close never completes", "500 on PDF/Excel export") per plan; all traceability tags
  (T-05-03-05, T-05-03-01, CLOSE-12, Pitfall 2/4/10, T-06-02-07, T-06-04-03) preserved.
- All 28 unique internal § references rewritten per the plan's mapping; the ambiguous
  "( /up — §6 step 1 )" re-pointed to the §6.1 healthcheck block by referent.
- Zero content loss: Dockerfile, docker-compose.yml, .dockerignore, nginx vhost, supervisor
  conf, cron lines, chown/chmod block, tinker/unzip/mysql restore commands all verbatim.

### Task 2 — External citations re-pointed (commit `5cf8b1a`)
- README.md:388 disaster-recovery ref §11 → §12.
- SETUP-USAGE-DEPLOY.md:167 §12→§6, §13→§7 (its own §5.3 left as-is); :214 §4.2→§5.2; :221 §4.3→§5.3.
- .env.example:42 §11.9→§11 (Pitfall 2); :94 §11.9→§11; :96 §11.7→§12.7. Comments only.
- config/database.php:68 "per DEPLOYMENT.md §2" — verified still valid, no change needed.

## Verification (actual outputs)

- Headings: 12 `## N.` sections in exact order 1–12 (grep `^## [0-9]+\.`).
- Dead-ref sweep: 28 unique `§N[.M]` refs in DEPLOYMENT.md → **0 dead** (every ref has a
  matching `## N.` or `### N.M.` heading).
- Baselines → final: lines 755 → 764 (target 730–800); table rows 72 → 61
  (fully accounted: −6 env merge, −6 troubleshooting merge, +1 new MAIL_* row);
  column-0 fences 22 → 24 (+2 = the §8.1 super-admin block moved from 4-space-indented
  to column 0 — formatting normalization, same code content); ALL fences (incl. indented)
  = 40, balanced.
- External citation counts: README `DEPLOYMENT.md §12` = 1; SETUP-USAGE-DEPLOY.md §6/§7/§5.2/§5.3
  = 4 occurrences on 3 lines; .env.example §11 ×2 lines + §12.7 ×1 = 3 lines.
- Pointer chain intact: SETUP-USAGE-DEPLOY.md `### 5.3 Deploy on shared hosting` exists (L231);
  DEPLOYMENT.md §7 cites it once.
- Verbatim-block spot check: supervisor conf (L213/224), cron (L237), chown (L257),
  .dockerignore (L278), Dockerfile (L306), compose (L389), post-close-hook row (L609),
  APP_KEY-rotation step (L710) — all present.
- Blast radius: `git status` shows exactly the 4 planned docs + `.planning/` bookkeeping
  (`.commandcode/settings.json` is session-tooling noise, deliberately NOT committed).

## Deviations from plan

1. Fence-count gate "==" baseline showed +2 (column-0 normalization of one relocated block);
   all-fence count balanced at 40. Benign — documented above instead of re-indenting the
   super-admin block to keep the count artificially identical.
2. Table-row gate said "≥ baseline − 8"; actual −11 with a full accounting (the plan's
   estimate didn't count the two removed table header/separator row pairs and the +1 MAIL_*
   addition). No content lost — every old row's text survives in the merged tables.
3. Executor/planner subagents had no write tools in this runtime — orchestrator executed.

## Commits

- `7e9eee4` docs(quick-260915-1ab): restructure DEPLOYMENT.md into serial path-first guide
- `5cf8b1a` docs(quick-260915-1ab): re-point external DEPLOYMENT.md section citations
