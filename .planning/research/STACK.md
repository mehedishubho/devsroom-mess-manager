# Stack Research — Bangladesh Mess Management System

## Project: Devsroom Mess Management
**Date:** 2026-06-16
**Milestone:** v1 (greenfield domain, but Laravel skeleton is in place)

## Confirmed Stack (from existing codebase)

- **PHP 8.4** (declared `^8.3` in composer.json)
- **Laravel 13.15** (latest stable)
- **MySQL 8+** (per taste preference — not sqlite)
- **Tailwind CSS v4** via `@tailwindcss/vite`
- **Vite 7** as build tool
- **Laravel Sanctum 4.3** (installed but unused in v1)
- **Tyro Dashboard 1.36** (hasinhayder/tyro-dashboard) — admin CRUD
- **Tyro Login** (hasinhayder/tyro-login) — auth UI with lockout, 2FA, magic links
- **Tyro** (hasinhayder/tyro) — roles and privileges (transitive dependency)
- **PHPUnit 12.5** (NOT Pest)
- **Laravel Boost 2.4** — AI agent tooling
- **Laravel Pint 1.27** — code style
- **Laravel Pail 1.2** — error tailing in dev

## Recommended Additions for Mess Domain

### High Confidence (use these)

- **`spatie/laravel-permission`** — NOT needed. Tyro handles roles/privileges. Don't double up.
- **`spatie/laravel-medialibrary`** — NOT needed for v1. We just need a single image per member and optional receipt images per bazar. Use Laravel's `Storage` disk + image validation. Adding medialibrary is overkill.
- **`barryvdh/laravel-dompdf`** — RECOMMENDED for member statements and monthly reports. Bangladesh messes expect PDF statements. Use `snappy`/`dompdf` to render Blade templates to PDF.
- **`maatwebsite/excel`** — RECOMMENDED for expense/payment exports. Mess managers and members both want Excel/CSV export. Excel is a cultural fit in Bangladesh.
- **`intervention/image`** — NOT needed. We don't transform images (no thumbnails, no resizing). Just store and display.
- **`owen-it/laravel-auditing`** — RECOMMENDED as the basis for our `Auditable` trait. Battle-tested, supports model events, queue writes. We extend it for our domain events.
- **`laravel/scout`** — NOT needed. We don't have search-heavy features. MySQL `LIKE` and fulltext is enough for member search.

### Lower Confidence / Optional

- **`laravel/sanctum`** — Installed. Use for member-facing API later (mobile app, v2). Not in v1.
- **`laravel/socialite`** — Optional. Only if we add Google/Facebook login. v1 is email/password only.
- **`livewire/inertia`** — Not needed. Server-rendered Blade is the right call for mess management. We don't need SPA interactivity.
- **`laravel/horizon`** — Not needed. Queued jobs (month-close) are infrequent. Default queue dashboard via Tyro is fine.
- **`sentry/sentry-laravel`** — Recommended for production. Not v1-blocker.

## Frontend Decisions

- **No JavaScript framework** — server-rendered Blade is correct.
- **HTMX or Alpine.js** — RECOMMENDED for the daily meal grid (toggle breakfast/lunch/dinner checkboxes with no full-page reload). Alpine.js is lighter; HTMX is more powerful. Pick Alpine.js for v1 — fits the "no JS framework" philosophy.
- **Charts** — Use **Chart.js** (loaded via Vite, no NPM framework needed). Dashboard shows expense trend, meal trend, payment trend.
- **Date picker** — Use **Flatpickr** (lightweight, no jQuery). Bangladesh needs DD-MM-YYYY format support — Flatpickr handles this.
- **Form validation feedback** — Tyro already provides flash messages. No additional library needed.

## Currency / Locale

- **Money formatting** — Use PHP's `NumberFormatter` with `bn_BD` locale for BDT. Don't roll custom formatting.
- **Date format** — `d-m-Y` (DD-MM-YYYY) is the Bangladesh default. Configurable in settings, but default to this.

## Real-Time / Async

- **No websockets needed in v1.** Dashboard refresh on page load. Queue jobs handle background work.
- **Queue driver** — `database` is fine for v1. Move to Redis in v2 if performance demands.

## NOT to use (rationale)

| Library | Why NOT |
|---|---|
| Pest | PHPUnit is the project standard. Taste + existing config. |
| Bootstrap | Tailwind only, per taste. |
| Livewire | Overkill for mess workflows. Server-rendered is simpler. |
| Inertia.js | No SPA architecture in v1. |
| Sentry (v1-blocker) | Optional, not blocking. Add in v1.1 if needed. |
| Real bKash/Nagad SDK | Out of scope for v1. Manager records payments manually. |

## Quality Gates

- [x] Versions are current (Laravel 13.15, PHP 8.4, MySQL 8+)
- [x] Rationale explains WHY for each choice
- [x] Confidence levels assigned
