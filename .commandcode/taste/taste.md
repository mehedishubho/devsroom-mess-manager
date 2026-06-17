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
