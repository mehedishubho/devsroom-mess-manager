# Workflow
- Do not assume default local DB credentials (e.g. `root`/no password) without verifying with the user. Confidence: 0.55
- Offer proactive recommendations and suggestions for project features/architecture rather than just executing on answers. Confidence: 0.60
- When a UAT test fails, pause UAT, write a debug session to `.planning/debug/`, write a fix plan to `.planning/phases/{phase}/{plan}-fix-{name}.md`, then execute the fix. Confidence: 0.75
- When a UAT test cannot run due to a third-party dependency (e.g. email provider not configured, external API down), mark it as `blocked` (not `failed` or `pending`) with `blocked_by` and `reason` fields, then move to the next test. Confidence: 0.70
- Use `php artisan test` to run the test suite and `php vendor/bin/pint` for code style after making code changes. Confidence: 0.75
- Write commit messages to a temp file (e.g. `.git_commit_msg.txt`) and use `git commit -F` so multi-line messages don't get mangled by shell escaping. Confidence: 0.70
- Clean up temporary verification scripts (e.g. `verify_fix.php`, `reseed.php`) after running them. Confidence: 0.70
