# Features Research — Bangladesh Mess Management System

## Project: Devsroom Mess Management
**Date:** 2026-06-16
**Milestone:** v1

## Feature Categories

### Table Stakes (Must Have or Users Leave)

#### Authentication & Authorization
- Email/password login for manager and members
- Role-based access (admin, manager, member)
- Password reset via email
- Account lockout after failed attempts
- Session management
- Member self-view only (cannot see other members' data)

#### Member Management
- CRUD members with full profile
- Active/former member status
- Member search by name/mobile/room
- Member profile view with photo

#### Daily Operations (the manager's daily workflow)
- Bulk meal entry grid (rows=members, columns=meals)
- "Apply to all" preset for fast entry
- Daily bazar expense logging
- Receipt image upload (optional)
- Fixed expense recording (rent, utilities, salaries)

#### Meal Off System
- Member requests meal off for date range
- Manager approval workflow
- Auto-deduction from meal count

#### Guest Meals
- Manager records guest meal
- Charged to specific member's bill

#### Payments
- Record payment (date, amount, method, reference #, notes)
- Multiple methods: Cash, bKash, Nagad, Rocket, Bank
- Advance deposit support
- Payment history per member

#### Month-End Close
- Compute meal rate from bazar only
- Compute fixed cost share (split equally)
- Compute individual bills
- Persist immutable snapshot
- Hard-lock closed months
- Idempotent close (unique per mess+year+month)

#### Reports
- Monthly report
- Member statement
- Expense report
- Payment report

#### Dashboard
- Key metrics cards
- Trend charts (expense, meal, payment)
- Today's meal count

### Differentiators (Competitive Advantage)

- **Live meal rate preview** mid-month ("if we closed today, ৳X")
- **Live "running bill" view** for members (current month, anytime)
- **Queued month-close** with progress notification
- **Domain-specific audit log** separate from user/role audit
- **Bulk meal grid with smart defaults** (mark all full, then uncheck the 2 on meal off)
- **Bangladesh-first workflow** (bazar, meal off/vacation/outside tour, guest meals, bKash/Nagad, advance carry-forward)
- **Member self-service meal off request** (no need to call the manager)

### Anti-Features (Do NOT Build in v1)

| Anti-Feature | Reason |
|---|---|
| Real bKash/Nagad/Rocket API integration | Regulatory overhead, 2-4 weeks of work, not the v1 bottleneck |
| PWA / service workers / offline mode | Separate project, significant complexity |
| Bengali translations | Wrap in `__()` for v2 readiness, but don't ship bn.json |
| Public API / external integrations | Sanctum is installed but unused; dashboard is the only consumer |
| Multi-mess / multi-tenant | Schema-ready, but not built. v2. |
| Real-time websockets | Page reload is fine for v1. |
| Native mobile app | Responsive web only in v1. |
| SMS / WhatsApp notifications | In-app only in v1. |
| 2FA (Tyro supports it, but optional) | Not blocking v1. |
| Social login (Google/Facebook) | Not blocking v1. Email/password is fine. |
| Per-meal-type custom rates (e.g., special dinner rate) | Stick to 0.5/1/1 default + settings. |
| Inventory management (rice kg remaining) | Different product. Mess = consumption tracking. |
| Cook/maid management (attendance, leave) | Different product. HR for mess staff. |
| Mess finder / directory | We build for one mess, not a marketplace. |
| Chat / messaging | Not the product. |
| Calendar view of meals | Grid is enough. Calendar can come in v2 if requested. |

## Bangladesh-Specific Features (Cultural Fit)

These are not anti-features; they are table stakes in this market but might seem niche:

- **BDT currency formatting** (৳ symbol, comma-separated thousands)
- **DD-MM-YYYY date format** default
- **bKash/Nagad/Rocket payment methods** as fields, not integrations
- **Vacation / outside tour** as a meal off type (members leave for Eid, family visits, work trips)
- **Guest meal charging** (members can host guests, charged to their bill)
- **Advance balance carry-forward** (members deposit ৳5000 in advance, used over multiple months)
- **Monthly closing discipline** (mess culture = close on the 1st of next month, no editing)
- **Bazar transparency** (members want to see who bought what, when, where)
- **Mobile-first for manager** (most mess managers use phones, not laptops)

## Feature Dependencies (Build Order Implications)

```
Foundation:
  - Mess settings (currency, meal values, auto-close)
  - Member CRUD
  - Audit log trait

Daily Operations (depends on members + settings):
  - Daily meal entry (depends on members)
  - Meal off request (depends on members)
  - Guest meal (depends on members)
  - Bazar expense (depends on settings for currency)
  - Fixed expense (depends on settings)
  - Payment recording (depends on members)

Monthly Close (depends on all of the above):
  - Meal rate calculation
  - Fixed cost share
  - Member bill computation
  - Snapshot persistence

Reports (depends on close + daily ops):
  - Monthly report
  - Member statement
  - Expense report
  - Payment report

Dashboard (depends on everything):
  - Metric cards
  - Trend charts
```

## v1 Feature Scope Summary

**In v1 (must ship):**
- 1 mess configuration
- Member CRUD
- Daily meal entry (bulk grid)
- Meal off (request + approve)
- Guest meal
- Bazar expense
- Fixed expense
- Payment recording
- Advance balance
- Month-close (idempotent, queued, hard-locked)
- 4 reports (monthly, member statement, expense, payment)
- Dashboard with cards + charts
- In-app notifications
- Settings
- Domain audit log
- Member self-view
- Manager mobile-first UI

**Deferred to v2:**
- Multi-mess
- PWA
- Real bKash/Nagad/Rocket API
- Bengali translations
- Bengali UI strings
- Public API
- SMS/WhatsApp notifications
- 2FA enforcement
- Social login
- Native mobile app
- Calendar view
- Inventory tracking

## Quality Gates

- [x] Categories are clear (table stakes / differentiators / anti-features)
- [x] Complexity noted for each feature
- [x] Dependencies between features identified
