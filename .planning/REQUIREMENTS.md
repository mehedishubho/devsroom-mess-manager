# Requirements: Devsroom Mess Management

**Defined:** 2026-06-16
**Core Value:** A mess manager can run a full month end-to-end on a phone — enter meals, log bazar, take payments, close the month, and produce a correct member bill — without spreadsheets and without arguing about who owes what.

## v1 Requirements

### Authentication & Authorization

- [ ] **AUTH-01**: Manager can log in with email and password (via Tyro Login)
- [ ] **AUTH-02**: Member can log in with email and password (via Tyro Login)
- [ ] **AUTH-03**: Super admin can log in (via Tyro Login, `super-admin` role)
- [ ] **AUTH-04**: Account is locked out after 5 failed login attempts for 15 minutes (Tyro built-in)
- [ ] **AUTH-05**: User can reset password via email link (Tyro built-in)
- [ ] **AUTH-06**: Member role restricts user to viewing their own data only (no other members, no settings, no admin)
- [ ] **AUTH-07**: Manager role grants access to mess configuration, members, meals, expenses, payments, reports
- [ ] **AUTH-08**: Super admin role grants access to all messes and Tyro dashboard (`/dashboard`)
- [ ] **AUTH-09**: User session persists across browser refresh
- [ ] **AUTH-10**: User can log out from any page

### Mess Configuration

- [ ] **MESS-01**: Super admin can configure one mess (name, address, monthly rent, manager contact, status)
- [ ] **MESS-02**: Mess settings store currency (BDT default), date format (DD-MM-YYYY default), meal values (breakfast 0.5, lunch 1, dinner 1)
- [ ] **MESS-03**: Mess can be marked active/inactive without losing data
- [ ] **MESS-04**: Mess displays current active member count on dashboard

### Member Management

- [ ] **MEM-01**: Manager can create a member with full profile (name, mobile, email, NID optional, profession, room number, seat number, joining date, emergency contact, profile photo)
- [ ] **MEM-02**: Manager can view, edit, and soft-delete (mark inactive) a member
- [ ] **MEM-03**: Member has status: active, inactive, former (with leaving date)
- [ ] **MEM-04**: Manager can search members by name, mobile, email, or room number
- [ ] **MEM-05**: Member can view their own profile and edit limited fields (photo, password, emergency contact)
- [ ] **MEM-06**: Member can view their own meal history, bill, payment history, and monthly reports
- [ ] **MEM-07**: Profile photo upload is supported (max 2MB, JPG/PNG/WEBP)
- [ ] **MEM-08**: All member fields are validated (mobile format, email format, required fields)
- [ ] **MEM-09**: All members carry `mess_id` (single mess in v1, multi-mess ready)

### Daily Meal Entry

- [ ] **MEAL-01**: Manager can view today's meal grid (rows=members, columns=breakfast/lunch/dinner)
- [ ] **MEAL-02**: Manager can navigate to any past or future date's meal grid
- [ ] **MEAL-03**: Manager can check/uncheck any meal for any member on the grid
- [ ] **MEAL-04**: Grid has "Mark all 3 meals" preset (1 click sets all active members to all 3 meals)
- [ ] **MEAL-05**: Grid has "Mark all 0 meals" preset (1 click clears all)
- [ ] **MEAL-06**: Per-member quick actions: "All on", "All off", "Breakfast only", "Lunch only", "Dinner only"
- [ ] **MEAL-07**: Members with approved meal off for the date are shown grayed out and not editable
- [ ] **MEAL-08**: Bulk save persists all changes in a single transaction
- [ ] **MEAL-09**: Default meal values: breakfast=0.5, lunch=1, dinner=1 (configurable in settings)
- [ ] **MEAL-10**: Meal entry validation: member must be active, meal count cannot exceed meal values
- [ ] **MEAL-11**: Daily grid loads in < 100ms (eager load to prevent N+1)

### Meal Off System

- [ ] **OFF-01**: Member can request meal off for a date range with a reason (vacation, outside tour, other)
- [ ] **OFF-02**: Member can view their own meal off requests and their status
- [ ] **OFF-03**: Manager can view all pending meal off requests
- [ ] **OFF-04**: Manager can approve or reject a meal off request (rejection requires a reason)
- [ ] **OFF-05**: Only approved meal off requests affect meal count
- [ ] **OFF-06**: Approved meal off auto-deducts meals from that member for the date range
- [ ] **OFF-07**: Meal off request audit trail includes member, manager who approved, timestamp, reason

### Guest Meals

- [ ] **GUEST-01**: Manager can add a guest meal (guest name, member name, date, meal type, quantity)
- [ ] **GUEST-02**: Guest meal is charged to the specified member's bill at the current meal rate
- [ ] **GUEST-03**: Guest meals are visible in the member statement
- [ ] **GUEST-04**: Manager can edit or delete a guest meal (only if the month is not closed)

### Bazar / Market Expenses

- [ ] **BAZAR-01**: Manager can record a bazar purchase (date, purchased by, vendor, description, amount, optional receipt image)
- [ ] **BAZAR-02**: Receipt image upload is optional (JPG/PNG, max 5MB)
- [ ] **BAZAR-03**: Bazar expenses are categorized (default: Rice, Fish, Meat, Vegetables, Oil, Gas, Other)
- [ ] **BAZAR-04**: Manager can view daily bazar entries
- [ ] **BAZAR-05**: Manager can view monthly bazar summary (total, by category, by purchaser)
- [ ] **BAZAR-06**: Bazar expenses with no receipt show a "no receipt" badge in reports

### Fixed Monthly Expenses

- [ ] **FIXED-01**: Manager can record fixed monthly expenses (rent, cook salary, maid salary, internet, electricity, water, gas, security, maintenance, cleaning, other)
- [ ] **FIXED-02**: Fixed expenses have a category with `kind=fixed`
- [ ] **FIXED-03**: Fixed expenses are split equally among active members
- [ ] **FIXED-04**: Fixed expenses do NOT enter the meal rate calculation

### Expense Categories

- [ ] **CAT-01**: Default categories ship: Bazar (kind=bazar), Rent, Cook Salary, Internet, Electricity, Water, Gas, Maintenance, Cleaning, Others (kind=fixed or other)
- [ ] **CAT-02**: Manager can create custom categories with a `kind` (bazar, fixed, other)
- [ ] **CAT-03**: Categories are scoped per mess
- [ ] **CAT-04**: System categories cannot be deleted (only custom)

### Payments

- [ ] **PAY-01**: Manager can record a payment (member, date, amount, method, reference number, notes)
- [ ] **PAY-02**: Payment methods: Cash, bKash, Nagad, Rocket, Bank Transfer
- [ ] **PAY-03**: Payment has a `type` field: `bill_payment` or `advance_deposit` (NOT NULL, schema-enforced)
- [ ] **PAY-04**: Manager can view payment history (filter by member, method, date)
- [ ] **PAY-05**: Member can view their own payment history
- [ ] **PAY-06**: Payment can be edited or deleted only if the month is not closed

### Advance Balance

- [ ] **ADV-01**: Each member has an `advance_balance` field that carries forward month-to-month
- [ ] **ADV-02**: Advance deposits (`type=advance_deposit`) increase the member's advance balance
- [ ] **ADV-03**: Bill payments (`type=bill_payment`) may consume advance balance before considering cash paid in that month
- [ ] **ADV-04**: If a member's bill is less than their advance, the excess carries forward to next month
- [ ] **ADV-05**: If a member's bill exceeds their advance, the difference is the new "due" amount
- [ ] **ADV-06**: Member can view their current advance balance any time
- [ ] **ADV-07**: Advance balance adjustments (corrections) are logged in the audit trail

### Live Bill Preview

- [x] **PREVIEW-01**: Manager can see "if we closed today, meal rate would be ৳X" on the dashboard
- [x] **PREVIEW-02**: Manager can see each member's running bill for the current month
- [x] **PREVIEW-03**: Member can see their own running bill for the current month
- [x] **PREVIEW-04**: Preview updates within 2 seconds of a write (cache invalidation)
- [x] **PREVIEW-05**: Preview is cached with 1-hour TTL to prevent stampede

### Month-End Close

- [ ] **CLOSE-01**: Manager can trigger month-close for a (year, month)
- [ ] **CLOSE-02**: Month-close runs as a queued job (not synchronously)
- [ ] **CLOSE-03**: Manager receives a notification when close completes (success or failure)
- [x] **CLOSE-04**: Close computes meal rate: `total_bazar / total_meals`
- [x] **CLOSE-05**: Close computes fixed cost share: `total_fixed / active_member_count` (prorated by days for mid-month joiners/leavers)
- [x] **CLOSE-06**: Close computes per-member bill: `meal_cost + fixed_share - payments - advance_applied`
- [ ] **CLOSE-07**: Close is idempotent: `UNIQUE INDEX (mess_id, year, month)` on `monthly_closings` — duplicate close attempts are refused
- [ ] **CLOSE-08**: Close uses `SELECT ... FOR UPDATE` to lock the mess row
- [ ] **CLOSE-09**: Close persists immutable snapshot to `monthly_closings` and `monthly_member_summaries`
- [ ] **CLOSE-10**: Once closed, the month is hard-locked — no edits to expenses, meals, payments, or meal off for that month
- [ ] **CLOSE-11**: Closed month view is read-only with a "MONTH CLOSED" banner
- [ ] **CLOSE-12**: Corrections to closed months go through a separate `monthly_corrections` table, not edits to the original

### Reports

- [ ] **RPT-01**: Manager can view Monthly Report (total members, total meals, meal rate, total bazar, fixed expenses, total due, total advance)
- [ ] **RPT-02**: Manager can view Member Statement (meals, guest meals, payments, due, advance) for any member, any month
- [ ] **RPT-03**: Manager can view Expense Report (filter by date range, category, month)
- [ ] **RPT-04**: Manager can view Payment Report (filter by member, method, date range)
- [ ] **RPT-05**: Member can view their own Member Statement
- [ ] **RPT-06**: Member can view the Monthly Report for the mess
- [ ] **RPT-07**: Reports support PDF export (Dompdf)
- [ ] **RPT-08**: Reports support Excel/CSV export (Maatwebsite/Excel)

### Dashboard

- [ ] **DASH-01**: Manager dashboard shows cards: Total Members, Today's Meals, Current Meal Rate, Monthly Expenses, Total Due, Total Advance
- [ ] **DASH-02**: Manager dashboard shows charts: Expense Trend (last 6 months), Meal Trend (last 30 days), Payment Trend (last 6 months)
- [ ] **DASH-03**: Manager dashboard shows "Pending meal off requests" count
- [ ] **DASH-04**: Member dashboard shows: My Meals (this month), My Bill (this month), My Advance, My Payment History
- [ ] **DASH-05**: Dashboard cards refresh on page load, cached for 1 hour
- [ ] **DASH-06**: Dashboard supports date range filtering for trend charts

### Notifications

- [ ] **NOTIF-01**: Manager receives in-app notification when month-close completes
- [ ] **NOTIF-02**: Member receives in-app notification when their meal off request is approved/rejected
- [ ] **NOTIF-03**: Member receives in-app notification when a payment is recorded for them
- [ ] **NOTIF-04**: Manager can send a due reminder notification to all members with due balance
- [ ] **NOTIF-05**: Notification center is accessible from the main nav

### Settings

- [ ] **SET-01**: Manager can edit meal values (breakfast, lunch, dinner)
- [ ] **SET-02**: Manager can edit currency (default BDT)
- [ ] **SET-03**: Manager can edit date format (default DD-MM-YYYY)
- [ ] **SET-04**: Manager can toggle auto-monthly-close (off in v1, prep for v2)
- [ ] **SET-05**: Settings are scoped per mess (v1: 1 mess)

### Audit Log

- [ ] **AUDIT-01**: Every write to `meal_entries`, `meal_off_requests`, `guest_meals`, `expenses`, `payments`, `members` writes an audit log entry
- [ ] **AUDIT-02**: Audit log records: user_id, action, model, before, after, timestamp, IP
- [ ] **AUDIT-03**: Audit log is append-only (no edits, no deletes)
- [ ] **AUDIT-04**: Manager can view the audit log filtered by model, user, date
- [ ] **AUDIT-05**: Domain audit log is separate from Tyro's user/role audit log

### Quality, Performance, UX

- [ ] **PERF-01**: All money fields use `decimal:2` cast and `DECIMAL(10,2)` columns (no float)
- [ ] **PERF-02**: All date fields use Carbon; app timezone is `Asia/Dhaka`
- [ ] **PERF-03**: All user-facing strings are wrapped in `__()` (English only shipped, Bengali-ready)
- [ ] **PERF-04**: All manager-facing screens are mobile-first (375px baseline)
- [ ] **PERF-05**: All domain tables have `mess_id` (multi-mess ready)
- [ ] **PERF-06**: Current-month aggregates cached with 1-hour TTL, invalidated on write
- [ ] **PERF-07**: Form Requests used for all user input (no `validate()` in controllers)
- [ ] **PERF-08**: Domain models use the `Auditable` trait
- [ ] **PERF-09**: Eloquent global scope for per-mess filtering (v1: trivially mess_id=1)
- [ ] **PERF-10**: Service layer for business logic (no Repository pattern)
- [ ] **PERF-11**: PHPUnit tests for all Form Requests and Service methods
- [ ] **PERF-12**: Feature tests for all controller actions (happy path + auth check)
- [ ] **PERF-13**: Laravel Pint runs clean on all committed code

## v2 Requirements

Deferred to future milestone. Tracked but not in current roadmap.

### Multi-Mess / Multi-Tenant
- **MULTI-01**: Super admin can create and manage multiple messes
- **MULTI-02**: Mess manager is scoped to their assigned mess only
- **MULTI-03**: Mess switcher in the nav for super admin
- **MULTI-04**: Per-mess branding (logo, colors)

### Real Payment Gateway Integration
- **PAY-API-01**: bKash payment gateway integration
- **PAY-API-02**: Nagad payment gateway integration
- **PAY-API-03**: Rocket payment gateway integration
- **PAY-API-04**: Webhook handler for payment confirmations
- **PAY-API-05**: Auto-create payment record on webhook success

### Progressive Web App
- **PWA-01**: Service worker for offline support
- **PWA-02**: Install prompt (Add to Home Screen)
- **PWA-03**: Offline meal entry queue (sync when online)
- **PWA-04**: Push notifications

### Localization
- **LOC-01**: Bengali language translations (bn.json)
- **LOC-02**: Bengali UI for member-facing screens
- **LOC-03**: Bengali PDF reports

### Communications
- **COMM-01**: SMS notifications (via Twilio or local SMS gateway)
- **COMM-02**: WhatsApp notifications (via WhatsApp Business API)
- **COMM-03**: Email digests (weekly summary)

### Public API
- **API-01**: RESTful API for member mobile app
- **API-02**: Sanctum token issuance for members
- **API-03**: API rate limiting

### Mobile App
- **MOB-01**: Native iOS app (React Native or Swift)
- **MOB-02**: Native Android app (React Native or Kotlin)

### Real-Time
- **RT-01**: WebSocket-based real-time dashboard updates
- **RT-02**: Real-time notifications

### 2FA
- **AUTH-2FA-01**: Enforce 2FA for admin role
- **AUTH-2FA-02**: Optional 2FA for managers
- **AUTH-2FA-03**: Recovery codes

### Social Login
- **SOCIAL-01**: Google login
- **SOCIAL-02**: Facebook login
- **SOCIAL-03**: GitHub login (for tech-savvy messes)

### Advanced Reports
- **RPT-ADV-01**: Year-over-year comparison
- **RPT-ADV-02**: Cost trend analysis
- **RPT-ADV-03**: Member behavior insights

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
|---------|--------|
| Real bKash/Nagad/Rocket API integration | Regulatory overhead, 2-4 weeks of work, not the v1 bottleneck. Manager records payments with reference numbers. |
| PWA / service workers / offline mode | Separate project. Significant complexity (sync, conflict resolution). |
| Bengali language translations | Strings wrapped in `__()` for v2 readiness, but no bn.json shipped. |
| Public API / external integrations | Sanctum is installed but unused. Dashboard is the only consumer. |
| Multi-mess / multi-tenant | Schema-ready (`mess_id` on all tables), but a single mess is v1. |
| Real-time websockets | Simple page reload is fine. Websockets are a separate architecture. |
| Native mobile app (iOS/Android) | Responsive web only in v1. |
| SMS / WhatsApp notifications | In-app only in v1. |
| Inventory management (rice kg remaining, stock tracking) | Different product. Mess = consumption tracking, not inventory. |
| Cook/maid management (attendance, leave, payroll) | Different product. HR for mess staff. |
| Mess finder / directory / marketplace | We build for one mess, not a marketplace. |
| Chat / messaging between members | Not the product. |
| Calendar view of meals | Grid is enough for v1. Calendar can come later if requested. |
| Per-meal-type custom rates (e.g., special dinner rate) | Stick to 0.5/1/1 default + settings. |
| Member-submitted bazar expenses | Manager-only in v1. Trust + accuracy. |
| Receipt OCR / automatic expense categorization | Out of scope. Manager categorizes manually. |
| 2FA enforcement | Tyro supports it, but v1 is email/password only. |
| Social login (Google/Facebook) | v1 is email/password only. |
| Floating-point money | Strictly decimal. Float is a bug. |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| AUTH-01 to AUTH-10 | Phase 1 | Pending |
| MESS-01 to MESS-04 | Phase 1 | Pending |
| SET-01 to SET-05 | Phase 1 | Pending |
| AUDIT-01 to AUDIT-05 | Phase 1 | Pending |
| PERF-01, PERF-02, PERF-03 | Phase 1 | Pending |
| PERF-05, PERF-07, PERF-08 | Phase 1 | Pending |
| PERF-09, PERF-10 | Phase 1 | Pending |
| MEM-01 to MEM-09 | Phase 2 | Pending |
| MEAL-01 to MEAL-11 | Phase 2 | Pending |
| OFF-01 to OFF-07 | Phase 2 | Pending |
| GUEST-01 to GUEST-04 | Phase 2 | Pending |
| BAZAR-01 to BAZAR-06 | Phase 2 | Pending |
| FIXED-01 to FIXED-04 | Phase 2 | Pending |
| CAT-01 to CAT-04 | Phase 2 | Pending |
| PERF-04, PERF-11, PERF-12 | Phase 2 | Pending |
| PAY-01 to PAY-06 | Phase 3 | Pending |
| ADV-01 to ADV-07 | Phase 3 | Pending |
| PREVIEW-01 to PREVIEW-05 | Phase 3 | Pending |
| CLOSE-01 to CLOSE-12 | Phase 3 | Pending |
| NOTIF-01 to NOTIF-05 | Phase 3 | Pending |
| RPT-01 to RPT-08 | Phase 4 | Pending |
| DASH-01 to DASH-06 | Phase 4 | Pending |
| PERF-06, PERF-13 | Phase 5 | Pending |

**Coverage:**
- v1 requirements: 154 total
- Mapped to phases: 154
- Unmapped: 0 ✓

---
*Requirements defined: 2026-06-16*
*Last updated: 2026-06-16 after initial definition*
