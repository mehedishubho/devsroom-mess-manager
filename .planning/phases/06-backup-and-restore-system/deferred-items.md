# Phase 6 — Deferred Items (out-of-scope discoveries)

Discovered during Plan 06-01 execution. Per the executor deviation rules, out-of-scope pre-existing issues are logged here, NOT fixed in this plan.

## Pre-existing: `php artisan config:cache` fails (closure in tyro-login config)

- **Discovered during:** Plan 06-01 Task 1 (config:cache verification step)
- **Symptom:** `LogicException: Your configuration files could not be serialized because the value at "tyro-login.redirects.after_login" is non-serializable.`
- **Root cause:** `hasinhayder/tyro-login`'s config registers a Closure for `redirects.after_login` which Laravel's `ConfigCacheCommand` cannot `var_export` / `eval` reconstruct.
- **Verified pre-existing:** `git stash` on a clean tree (commit `c6dcc9c` — before Plan 06-01) reproduces the same failure.
- **Scope decision:** NOT introduced by Plan 06-01; not a backup-system concern. Logging here only. The dev workflow already uses `php artisan config:clear` per Plan 05-01's convention.
- **Suggested owner / follow-up:** Phase 5 polish or a future hardening pass (not Phase 6). The fix is either to publish tyro-login's config and convert the Closure to a string path, or to upstream a PR to hasinhayder/tyro-login.
