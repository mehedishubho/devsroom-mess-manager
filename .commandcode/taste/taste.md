# Stack

- Use Laravel 13 for this project. Confidence: 0.80
- Use MySQL as the database. Confidence: 0.80
- Disable 2FA in development environment (email system is not configured for sending 2FA codes). Confidence: 0.75

# Naming

- Use snake_case for database names (e.g. `devsroom_mess_management`, not `devsroom-mess-management`). Confidence: 0.60

# Workflow

See [workflow/taste.md](workflow/taste.md)

# Architecture

- Never use `config('mess.active_mess_id')` directly as the integer for `mess_id` columns. Use the `Mess::activeId()` helper which resolves the id at runtime from the `messes` table (with config as override only). Confidence: 0.85
- External integrations must fail open: a third-party API/network failure (e.g. Google Sheets) must never block, slow, or roll back the primary database write — the DB stays the source of truth and sync errors surface separately. Confidence: 0.6
- Prefers feature/integration setup (credentials, connection) to be configurable from the app dashboard by the user, rather than only via env/config files. Confidence: 0.55
