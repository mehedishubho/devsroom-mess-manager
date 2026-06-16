# Research Summary — Devsroom Mess Management

**Date:** 2026-06-16
**Milestone:** v1

## Key Findings

### Stack
**Use**: Laravel 13.15 + MySQL 8+ + Tyro Dashboard + Tyro Login + Tailwind v4 + Vite 7 + PHPUnit 12
**Add**: `barryvdh/laravel-dompdf` (PDF), `maatwebsite/excel` (Excel export), `owen-it/laravel-auditing` (audit trait), Chart.js (charts), Flatpickr (date picker), Alpine.js (light interactivity)
**Avoid**: Pest (use PHPUnit), Bootstrap (use Tailwind), Livewire/Inertia (server-rendered is simpler), real bKash/Nagad SDK (out of scope v1), Sanctum public API (not in v1)

### Table Stakes (must ship)
- Email/password auth, role-based, member self-view only
- Member CRUD with full profile
- Daily meal entry (bulk grid, mobile-first)
- Meal off (request + approve workflow)
- Guest meal
- Bazar expense (with optional receipt)
- Fixed expense
- Payment recording (5 methods, including bKash/Nagad as fields)
- Advance balance with carry-forward
- Month-close (idempotent, queued, hard-locked, immutable snapshot)
- 4 reports
- Dashboard with cards + trend charts
- In-app notifications
- Settings (currency, meal values, dates)
- Domain audit log

### Differentiators
- Live meal rate preview ("if we closed today, ৳X")
- Live running bill for members
- Queued month-close with progress
- Domain-specific audit log (separate from Tyro)
- Bulk meal grid with smart presets
- Bangladesh-first workflow (bazar, meal off, guest meal, advance, bKash fields)
- Member self-service meal off

### Anti-Features (do NOT build in v1)
Real bKash/Nagad API, PWA, Bengali translations, public API, multi-mess, websockets, native mobile app, SMS/WhatsApp, inventory tracking, cook attendance

### Watch Out For (Top 5 Critical Pitfalls)
1. **Conflating bill payment vs advance deposit** — use a `type` column, enforce NOT NULL
2. **Float money math** — use `decimal:2` cast + `DECIMAL(10,2)` columns
3. **Month-close race** — use `UNIQUE (mess_id, year, month)` + transaction
4. **Editing closed month** — middleware check + immutable snapshot + corrections via separate table
5. **Timezone** — set `APP_TIMEZONE=Asia/Dhaka` in `config/app.php`

### Architecture
- Service layer for business logic, no Repository pattern (Eloquent is enough)
- Form Requests for all input validation
- Auditable trait on all domain models
- Mess scope via global Eloquent scope + middleware (v1: trivially mess_id=1)
- Money as `decimal:2`, dates as Carbon, time zone Asia/Dhaka
- Immutable monthly closing: `UNIQUE(mess_id, year, month)`, separate `monthly_corrections` table for adjustments

### Build Order
```
Phase 1: Foundation (settings, migrations, models, audit trait, Tyro integration)
Phase 2: Members + Daily Operations (CRUD, meal entry, meal off, guest, bazar, fixed)
Phase 3: Payments + Month-Close (payments, advance, close job, notifications)
Phase 4: Reports + Dashboard (4 reports, cards, charts, member self-view)
Phase 5: Polish (PDF, Excel, mobile polish, performance)
```

## Files
- [STACK.md](./STACK.md) — Tech stack details
- [FEATURES.md](./FEATURES.md) — Feature categories, anti-features, dependencies
- [ARCHITECTURE.md](./ARCHITECTURE.md) — Components, data flow, build order
- [PITFALLS.md](./PITFALLS.md) — Critical mistakes, prevention, phase mapping
