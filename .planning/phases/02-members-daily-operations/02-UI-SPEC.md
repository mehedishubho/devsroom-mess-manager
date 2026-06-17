---
phase: 2
slug: members-daily-operations
status: draft
tool: tailwind-v4
preset: none
shadcn_initialized: false
created: 2026-06-17
extends: 01-foundation/01-UI-SPEC
---

# UI-SPEC: Phase 2 — Members + Daily Operations

> **Inherits everything from `01-UI-SPEC.md`** (Tailwind v4 + Blade + Heroicons + Inter, 6/6 dimensions PASS). This file extends the design system with the new components, layout patterns, and copywriting needed for member management, the daily meal grid, meal off requests, and expense entry. No tokens, color, or typography changes — only new components and screen specs.

## 0. Reading order for downstream agents

1. **First:** Read `.planning/phases/01-foundation/01-UI-SPEC.md` end-to-end. It defines the design system, token names, and component base patterns this spec extends.
2. **Then:** Read this file. It defines the **new** components and the **Phase 2 surface inventory**.
3. **Then:** Read `02-CONTEXT.md` for the locked design decisions (D-01 to D-24). Many screen behaviors are already specified there; this file does not repeat them.

## 1. Stack (inherited, no changes)

Identical to 01-UI-SPEC §1. Tailwind v4 + Blade + Heroicons + Inter. No shadcn, no third-party UI registry, no inline CSS.

**Registry safety dimension:** N/A — same as Phase 1. Auto-PASS.

## 2. Design tokens (inherited, no changes)

All color, typography, radius, shadow, and spacing tokens come from `01-UI-SPEC.md` §2. The `@theme` block in `resources/css/app.css` is not modified in Phase 2 — only the existing utilities are used.

**44px touch-target rule** is enforced on every new interactive element in this spec. Buttons, checkboxes, row-actions, and grid cells must meet the 44px minimum.

## 3. New Blade components (Phase 2 only)

The Phase 1 spec listed `<x-modal>`, `<x-tabs>`, `<x-data-grid>` as future components. Phase 2 ships the first two of those, plus several new pieces. **All components follow the same rules as Phase 1: anonymous Blade components, typed props, Tailwind-only, `__()` everywhere.**

| File | Purpose | Variants | Notes |
|---|---|---|---|
| `components/member-card.blade.php` | Single member display in lists (mobile-friendly). | — | Props: `member` (Member model), `showStatus` (bool, default true). Renders: circular avatar (photo or initials), name, room_or_seat, status pill, right-side chevron or action slot. |
| `components/meal-grid-row.blade.php` | One row of the daily meal grid (one member across B/L/D). | `active`, `meal-off` (grayed + badge), `readonly` | Props: `member`, `date`, `values` (breakfast/lunch/dinner booleans), `mealOffUntil` (date or null), `editable` (bool, default true). Renders: name, B/L/D checkboxes (44px touch target), quick-action menu. |
| `components/meal-grid-checkbox.blade.php` | One 44px checkbox cell with proper accessibility. | `breakfast`, `lunch`, `dinner` | Props: `name`, `value` (0/1), `checked`, `disabled`, `mealType` (for label). Renders: large checkbox with `aria-label="Breakfast for {member name}"`. |
| `components/quick-action-dropdown.blade.php` | Per-row quick actions in the meal grid (D-11). | — | Props: `memberId`, `date`, `actions` (array of `['value' => 'all-on', 'label' => '...']`). Renders: trigger button + popover. Plain HTML `<details>`/`<summary>` for mobile-friendly, no JS. |
| `components/photo-input.blade.php` | Photo upload with circular preview (D-02). | `circle-md` (64px), `circle-lg` (96px) | Props: `name`, `value` (current path or null), `preview` (URL), `capture` (bool, default true). Renders: circular preview, `<input type="file" accept="image/*" capture="environment">`, clear button. |
| `components/status-pill.blade.php` | Status badge (active / inactive / former / pending / approved / rejected). | — | Props: `variant` (active, inactive, former, pending, approved, rejected), `label` (optional override). Colors: emerald-50/700 (active, approved), slate-100/600 (inactive, pending), red-50/700 (rejected, former). |
| `components/meal-off-badge.blade.php` | Inline "On meal off until MM-DD" badge (D-10). | — | Props: `until` (date or string). Renders: `bg-slate-100 text-slate-600` rounded-full, text-xs, `aria-label="On meal off until {date}"`. |
| `components/expense-form-fields.blade.php` | The shared form fields for bazar + fixed expense (D-22, BAZAR-01, FIXED-01). | `bazar`, `fixed` | Props: `form` (the Form Request), `expense` (the model, null on create), `kind` (bazar/fixed). Renders: date, category dropdown (filtered by kind), amount, description, plus bazar-only: purchased-by, vendor, receipt. |
| `components/mess-date-nav.blade.php` | The ◀ Today ▶ + date picker (D-08). | — | Props: `date` (Carbon), `route` (named route), `routeParams` (array). Renders: prev button, "Today" pill (current date label), date `<input type="date">`, next button. Self-submitting GET form — no JS required. |
| `components/empty-state.blade.php` | Generic "no data" placeholder. (Phase 1 spec listed it but didn't ship it; Phase 2 ships it.) | — | Props: `title`, `description`, `icon` (SVG name), `actionLabel`, `actionRoute`. Centered, `py-12`, `max-w-md mx-auto`. |
| `components/tab-nav.blade.php` | Tab navigation (replaces the need for separate URLs). | — | Props: `tabs` (array of `['key', 'label', 'active']`). Renders: horizontal scrollable tab strip with `aria-current="page"` on the active tab. |
| `components/drawer.blade.php` | The mobile drawer pattern (already partially in `layouts/app.blade.php`; this is a generic helper). | — | Props: `id`, `open` (bool, default false). Wraps content in a slide-in panel with backdrop. Used by member-list filters and meal-off approval queue. |

### 3.1 Components explicitly NOT built in Phase 2

- `<x-modal>` — Phase 2 uses inline forms and confirm-on-button-press; no modal use cases. (Phase 1 deferred; still deferred.)
- `<x-data-grid>` (table with inline edit) — the daily meal grid is its own custom implementation, not a generic table component. (Phase 1 deferred; deferred again — the grid is bespoke.)
- `<x-chart-card>` — Phase 4.
- `<x-stat-card>` — Phase 4.

## 4. New layout patterns

### 4.1 Manager `/home` (Phase 2 replacement)

Phase 1 had `/home` as three cards. Phase 2 replaces it with **a more useful manager dashboard stub** that links to the new Phase 2 surfaces and previews today's state. Cards become: **Members count**, **Today's meals filled**, **Pending meal off requests**, **Bazar + fixed this month**, with each card a click-through link to the respective list. The exact counts/cards are placeholders for Phase 4 dashboard polish; Phase 2 ships the layout and link wiring, not the real data.

```
┌────────────────────────────────────────────┐
│ Top bar (existing)                         │
├──────────┬─────────────────────────────────┤
│ Sidebar  │  Welcome, {name}                │
│          │  Phase 2 quick stats:            │
│ Home     │  ┌─────────┬─────────┐           │
│ Members  │  │ Members │ Meals   │           │
│ Daily    │  │   12    │  8/12   │           │
│ Meal off │  └─────────┴─────────┘           │
│ Expenses │  ┌─────────┬─────────┐           │
│ Settings │  │ Pending │ Bazar   │           │
│ Audit    │  │   2     │ ৳1,234  │           │
│          │  └─────────┴─────────┘           │
└──────────┴─────────────────────────────────┘
```

Stats are computed live (single SQL each) on `/home` load. No caching in Phase 2 — Phase 4 adds 1-hour TTL with invalidation.

### 4.2 Member list (`/mess/members`)

```
┌────────────────────────────────────────────┐
│ Top bar (existing)                         │
├──────────┬─────────────────────────────────┤
│ Sidebar  │  Members                          │
│          │  [Search box] [Add member]        │
│          │  ┌──────────────────────────────┐│
│          │  │ (avatar)  Rahim Ahmed        ││
│          │  │           R-201  ● Active     ││
│          │  └──────────────────────────────┘│
│          │  ┌──────────────────────────────┐│
│          │  │ (avatar)  Karim Hossain      ││
│          │  │           R-202  ● Inactive   ││
│          │  └──────────────────────────────┘│
│          │  ...                              │
└──────────┴─────────────────────────────────┘
```

- **Mobile (< 768px)**: one member per card (D-04 stack). Each card is the entire row — no table on phone.
- **Desktop (≥ 768px)**: table layout with columns: Photo, Name, Room/Seat, Mobile, Status, Actions. Card-stack only happens below `md:`.
- **Search (D-06)**: live AJAX with 300ms debounce. The list is replaced in place (no page reload). Endpoint: `GET /mess/members/search?q=...` returns rendered partial (or JSON of rows; plan 2.1 picks one).
- **Search bar**: sticky on top of the list, full-width on mobile, `max-w-md` on desktop.
- **Add member button**: `primary` variant, top-right. Always visible.
- **No pagination in Phase 2** — small messes (< 50 members) don't need it. Phase 4 adds pagination if needed.

### 4.3 Member form (`/mess/members/create`, `/mess/members/{id}/edit`)

```
┌────────────────────────────────────────────┐
│  Add member                                │
│  Create a new mess member.                 │
│  ┌──────────────────────────────────────┐ │
│  │ (photo)   [Name           ]          │ │
│  │           [Mobile         ]          │ │
│  │           [Email          ]          │ │
│  │           [NID (optional) ]          │ │
│  │           [Profession     ]          │ │
│  │           [Room/seat      ]          │ │
│  │           [Joining date   ]          │ │
│  │           [Status         ]          │ │
│  │           [Emergency ct.  ]          │ │
│  │                                       │ │
│  │  [Cancel]                 [Save]      │ │
│  └──────────────────────────────────────┘ │
└────────────────────────────────────────────┘
```

- Single column on mobile, two columns on `sm:` for short fields (mobile + email, profession + room, joining + status).
- Photo at the top-left, circular preview. `capture="environment"` on mobile so the camera launches.
- Sticky save bar at the bottom on mobile (always visible).
- Validation errors inline via `<x-form-field :error="...">` (Phase 1 component, reused).
- All `__()` labels and help text per §6.1.

### 4.4 Member profile view (`/mess/members/{id}`)

- Read-only summary of all member fields.
- Sections: **Profile** (photo + name + status pill + contact details), **Recent meals** (last 30 days, table or list of B/L/D booleans), **Meal off requests** (status pills + dates), **Guest meals** (list with date, meal type, charge).
- Manager can click **Edit** to go to the form, or **Request meal off** to open a small inline form (D-14 — manager can submit on behalf).
- **Mobile**: sections stack vertically, **Recent meals** table collapses to a single-line list "Jun 12 — B, L, D" per row.

### 4.5 Daily meal grid (`/mess/meals`)

This is the Phase 2 center piece. The grid is **NOT a generic data table** — it's a bespoke responsive grid.

```
┌────────────────────────────────────────────┐
│  Daily meals                               │
│  ◀  [ Today (Jun 17, 2026) ]  ▶            │
│                                            │
│  [Mark all 3]  [Mark all 0]                │
│  ┌──────────────────────────────────────┐ │
│  │ Name       │ B  │ L  │ D  │  Quick   │ │
│  ├──────────────────────────────────────┤ │
│  │ Rahim      │ ✓  │ ✓  │ ✓  │  [⋯]     │ │
│  │ R-201      │    │    │    │          │ │
│  ├──────────────────────────────────────┤ │
│  │ Karim      │ ☐  │ ✓  │ ✓  │  [⋯]     │ │
│  │ R-202      │    │    │    │          │ │
│  ├──────────────────────────────────────┤ │
│  │ Jamal ⓜ   │ —  │ —  │ —  │  [⋯]     │ │
│  │ (meal off) │    │    │    │          │ │
│  └──────────────────────────────────────┘ │
│                                            │
│              [Save all changes]            │
└────────────────────────────────────────────┘
```

- **Date nav** (D-08): `<x-mess-date-nav>` at the top. The form auto-submits on date change.
- **Preset bar** (D-11): "Mark all 3 meals" and "Mark all 0 meals" buttons. Respect meal-off state (D-12) — meal-off rows are skipped by presets.
- **Grid table**: each row = one active member. Columns: Name (with room/seat below), Breakfast, Lunch, Dinner, Quick actions. On mobile (`< 768px`), the grid **horizontally scrolls** if members are wide (a single meal-off row is at least 80px wide). Vertical orientation is preserved — the manager's mental model is "rows = members, columns = meals."
- **Checkboxes** (D-07): large 44px tap targets. Each is a hidden `<input type="checkbox">` with a styled `<label>` (no native checkbox rendering on iOS Safari).
- **Meal-off rows** (D-10): `bg-slate-100`, `text-slate-400` (muted), checkboxes `disabled`, inline `<x-meal-off-badge>` next to the name showing "On meal off until {date}".
- **Quick actions** (D-11): per-row dropdown with "All on", "All off", "Breakfast only", "Lunch only", "Dinner only". Plain `<details>`/`<summary>` — no JS. On click, updates the row's checkboxes via JS (small inline script).
- **Save button** (D-07): sticky at the bottom, full-width on mobile, auto-aligned right on desktop. Single button = single transaction.
- **Form fields**: each row is named `entries[<member_id>][breakfast]`, `entries[<member_id>][lunch]`, `entries[<member_id>][dinner]`. Hidden `<input type="hidden" name="entries[<member_id>][date]">` carries the date. The form is POSTed to `/mess/meals` which upserts all rows in one DB transaction.

### 4.6 Meal off approval queue (`/mess/meal-off`)

```
┌────────────────────────────────────────────┐
│  Meal off requests                         │
│  3 pending, 12 approved this month         │
│                                            │
│  ▸ Rahim Ahmed — Jun 18 to Jun 22          │
│    Reason: "Going home for Eid"            │
│    [Approve]  [Reject]                     │
│                                            │
│  ▸ Karim Hossain — Jun 20 to Jun 25        │
│    Reason: "Official tour"                 │
│    [Approve]  [Reject]                     │
└────────────────────────────────────────────┘
```

- **Default state**: collapsed cards, only `Member name — date range` visible. One tap expands (D-15).
- **Expanded state**: reason, Approve / Reject buttons. Reject opens an inline reason field (D-15, OFF-04).
- **Tabs at the top**: `Pending (3)` | `Approved` | `Rejected`. Tabs are URL-fragment based (`#pending`, `#approved`, `#rejected`) so they're shareable. Default = `#pending`.
- **No bulk approve** (D-15). One-by-one only.
- **Sort order**: pending by `requested_at` ascending (oldest first — fair ordering). Approved/Rejected by `acted_at` descending (most recent first).

### 4.7 Bazar entry form (`/mess/expenses/bazar/create`)

```
┌────────────────────────────────────────────┐
│  Record bazar                              │
│  Date: [2026-06-17]                        │
│  Category: [Fish ▾]                        │
│  Purchased by: [Rahim Ahmed ▾] (optional)  │
│  Vendor: [Karwan Bazar] (optional)         │
│  Description: [1.5kg Rui fish, fresh]      │
│  Amount (BDT): [450.00]                    │
│  Receipt: [📷 Take photo] (optional)       │
│  [Cancel]                       [Save]     │
└────────────────────────────────────────────┘
```

- Single column, mobile-first.
- Category dropdown is **filtered to bazar kind** at form load (D-22).
- Receipt: circular preview, camera input (`capture="environment"`).
- Validation: amount > 0, date <= today, category belongs to the mess.

### 4.8 Fixed expense form (`/mess/expenses/fixed/create`)

- Same as bazar, but: **no purchased-by field** (rent goes to a landlord, not a member), **no vendor**, **no receipt** (fixed expenses are bills like rent that come via bKash — no paper receipt). Category filtered to fixed kind (D-22).
- Fields: date, category, description, amount.

### 4.9 Expense category manager (`/mess/categories`)

```
┌────────────────────────────────────────────┐
│  Expense categories                        │
│  [+ New category]                          │
│  ┌──────────────────────────────────────┐ │
│  │ Rice         [bazar]  🔒 default     │ │
│  │ Fish         [bazar]  🔒 default     │ │
│  │ Rent         [fixed]  🔒 default     │ │
│  │ Cook bonus   [bazar]      [+ delete] │ │
│  └──────────────────────────────────────┘ │
└────────────────────────────────────────────┘
```

- Single column list. Each row: name, kind pill, lock icon (if default), delete button (if not default).
- Add button opens a small inline form (or a `?show=create` query param with an inline section). No modal (per Phase 1 deferred modal list).
- Kind pill uses the same component as the meal grid status badge (reuse `<x-status-pill variant="bazar">` etc. — extended in Phase 2 to include bazar/fixed/other).

### 4.10 Member `/my` page (Phase 2 replacement)

```
┌────────────────────────────────────────────┐
│  Hi, Rahim!                                │
│  [Profile tab]  [Meal off]  [My meals]     │
│  ─────────────                              │
│                                            │
│  ┌──────────────────────────────────────┐ │
│  │ (avatar)  Rahim Ahmed               │ │
│  │           R-201                      │ │
│  │           +880 1700 000000           │ │
│  │           [Edit photo] [Change pwd]  │ │
│  │           Emergency: +880 1800...    │ │
│  │           [Edit emergency contact]   │ │
│  └──────────────────────────────────────┘ │
└────────────────────────────────────────────┘
```

- **Tabs (D-23)**: `Profile | Meal off | My meals` (one URL, hash-based for shareable links: `#profile`, `#meal-off`, `#meals`).
- **Profile tab**: photo, name (read-only), room (read-only), mobile (read-only), emergency contact (editable inline), change password (link to Tyro's password change). D-24 enforced — name/email/mobile/room/joining_date are not editable by the member.
- **Meal off tab**: request form (date range, reason) + list of own requests with status pills.
- **My meals tab**: read-only list of own meal entries for the current month. "Jun 14 — B, L, D" per row. No edit — the member cannot change their own meals (MEAL-03 is manager-only).

### 4.11 Sidebar additions (manager only)

Add four new links to the existing `layouts/app.blade.php` sidebar nav:

| Order | Label | Icon | Route |
|---|---|---|---|
| 1 | `__('Home')` | `home` (existing) | `home` |
| 2 | `__('Members')` | `users` (new) | `mess.members.index` |
| 3 | `__('Daily meals')` | `calendar-days` (new) | `mess.meals.index` |
| 4 | `__('Meal off')` | `calendar-x` (new) | `mess.meal-off.index` |
| 5 | `__('Expenses')` | `banknotes` (new) | `mess.expenses.index` |
| 6 | `__('Categories')` | `tag` (new) | `mess.categories.index` |
| 7 | `__('Mess settings')` | `cog-6-tooth` (existing) | `mess.settings.edit` |
| 8 | `__('Audit log')` | `document-text` (existing) | `mess.audit` |

Order is intentional: daily-action surfaces (Members → Meals → Meal off → Expenses) are first; configuration is second.

## 5. New icon set additions

Extend the Phase 1 icon list with:

| Name | Used by |
|---|---|
| `users` | sidebar "Members" link |
| `user-plus` | "Add member" button |
| `calendar-days` | sidebar "Daily meals" link, date nav |
| `calendar-x` | sidebar "Meal off" link, meal-off badge |
| `banknotes` | sidebar "Expenses" link |
| `tag` | sidebar "Categories" link, category pill |
| `camera` | photo input "Take photo" button |
| `pencil` | inline edit button |
| `trash` | delete button (categories, future destructive actions) |
| `chevron-down` | per-row quick action dropdown trigger (Phase 1 listed this — confirmed needed) |
| `chevron-right` | card link affordance |
| `x-circle` | clear photo button |
| `arrow-down-tray` | (reserved, not used in Phase 2) |
| `plus` | add button (alternative to user-plus) |

15 new icons. Total Phase 1 + Phase 2: 30 icons. Copy from heroicons.com (MIT).

## 6. Copywriting contract (new for Phase 2)

All strings wrapped in `__()`. English only.

### 6.1 Member form

| Element | Copy |
|---|---|
| Page title (create) | `__('Add a member')` |
| Page title (edit) | `__('Edit member')` |
| Page subtitle | `__('Create a new mess member. They will be set as active.')` (create) / `__('Update :name\'s information.', ['name' => $member->name])` (edit) |
| Field — name | `__('Name')`, required, help `__('Full legal name')` |
| Field — mobile | `__('Mobile')`, optional, help `__('BD format, e.g. 01700-000000')`, validation `regex:/^(01)[3-9]\d{8}$/` |
| Field — email | `__('Email')`, optional (per MEM-08), help `__('Required if the member will log in')` |
| Field — nid | `__('NID')`, optional, help `__('National ID number')` |
| Field — profession | `__('Profession')`, optional |
| Field — room_or_seat | `__('Room or seat')`, optional, help `__('e.g. R-201 or 3rd floor, R-12')` |
| Field — joining_date | `__('Joining date')`, optional, default = today on create |
| Field — status | `__('Status')`, required, options: Active (default), Inactive, Former (with leaving_date field) |
| Field — leaving_date | `__('Leaving date')`, shown only when status = former |
| Field — emergency_contact | `__('Emergency contact')`, optional, help `__('Name and phone of a relative')` |
| Field — photo | `__('Photo')`, optional, help `__('JPG, PNG, or WEBP. Max 2 MB.')` |
| [Save] | `__('Save member')` |
| [Cancel] | `__('Cancel')` |
| Empty state (no members) | `__('No members yet.')` + `__('Add the first member to start tracking meals and expenses.')` |
| Error — duplicate email | `__('A member with this email already exists in this mess.')` |
| Error — duplicate mobile | `__('A member with this mobile number already exists in this mess.')` |
| Error — photo too large | `__('Photo must be 2 MB or smaller.')` |

### 6.2 Member list

| Element | Copy |
|---|---|
| Page title | `__('Members')` |
| Page subtitle | `__(":count active members", ['count' => $activeCount])` (active count only, not total) |
| Search placeholder | `__('Search by name, mobile, email, or room…')` |
| [Add member] | `__('Add member')` |
| Status pill — active | `__('Active')` |
| Status pill — inactive | `__('Inactive')` |
| Status pill — former | `__('Former')` |
| Card sub-text (when inactive) | `__('Left on :date', ['date' => $member->leaving_date->format('d M Y')])` |
| Empty state (no matches) | `__('No members match your search.')` + `__('Try a different keyword or clear the search.')` |
| Error — search endpoint | `__('Search is temporarily unavailable.')` + `__('Please refresh the page.')` |

### 6.3 Member profile

| Element | Copy |
|---|---|
| Page title | `__(':name\'s profile', ['name' => $member->name])` |
| Section — Profile | `__('Profile')` |
| Section — Recent meals | `__('Recent meals (last 30 days)')` |
| Section — Meal off requests | `__('Meal off requests')` |
| Section — Guest meals | `__('Guest meals')` |
| [Edit] | `__('Edit')` |
| [Request meal off] | `__('Request meal off for :name', ['name' => $member->name])` (D-14) |
| Empty state — no recent meals | `__('No meals recorded in the last 30 days.')` |
| Empty state — no meal off | `__('No meal off requests.')` |
| Empty state — no guest meals | `__('No guest meals.')` |

### 6.4 Daily meal grid

| Element | Copy |
|---|---|
| Page title | `__('Daily meals')` |
| Date pill (today) | `__('Today, :date', ['date' => $date->format('d M Y')])` |
| Date pill (other) | `__(':day, :date', ['day' => $date->format('l'), 'date' => $date->format('d M Y')])` |
| [Mark all 3 meals] | `__('Mark all 3 meals')` (D-04 preset) |
| [Mark all 0 meals] | `__('Mark all 0 meals')` (D-05 preset) |
| Quick action — All on | `__('All on')` |
| Quick action — All off | `__('All off')` |
| Quick action — Breakfast only | `__('Breakfast only')` |
| Quick action — Lunch only | `__('Lunch only')` |
| Quick action — Dinner only | `__('Dinner only')` |
| Meal-off badge | `__('On meal off until :date', ['date' => $offUntil->format('d M')])` |
| [Save all] | `__('Save all changes')` |
| Saved confirmation | `__('Meals saved for :date.', ['date' => $date->format('d M Y')])` |
| Empty state (no active members) | `__('No active members to record meals for.')` + `__('Add members first or change the date.')` |
| Error — saving | `__('We couldn\'t save the meals.')` + `__('Please try again. If the problem persists, refresh the page.')` |

### 6.5 Meal off approval

| Element | Copy |
|---|---|
| Page title | `__('Meal off requests')` |
| Page subtitle | `__(':pending pending, :approved approved this month', ['pending' => $pendingCount, 'approved' => $approvedCount])` |
| Tab — Pending | `__('Pending (:count)', ['count' => $pendingCount])` |
| Tab — Approved | `__('Approved')` |
| Tab — Rejected | `__('Rejected')` |
| Collapsed card | `__(':name — :from to :to', ['name' => $request->member->name, 'from' => $request->from_date->format('d M'), 'to' => $request->to_date->format('d M')])` |
| Expanded card — reason label | `__('Reason')` |
| [Approve] | `__('Approve')` |
| [Reject] | `__('Reject')` |
| Reject reason placeholder | `__('Why is this request rejected?')` |
| Reject reason field label | `__('Rejection reason (required)')` |
| [Confirm reject] | `__('Confirm reject')` |
| [Cancel reject] | `__('Cancel')` |
| Status pill — approved | `__('Approved')` |
| Status pill — rejected | `__('Rejected')` |
| Status pill — pending | `__('Pending')` |
| Empty state (no pending) | `__('No pending meal off requests.')` + `__('You\'re all caught up.')` |
| Empty state (no approved) | `__('No approved requests this month.')` |
| Empty state (no rejected) | `__('No rejected requests.')` |
| Error — approving | `__('We couldn\'t approve the request.')` + `__('Please refresh and try again.')` |
| Error — rejection reason missing | `__('Please provide a reason for the rejection.')` |

### 6.6 Member `/my` page

| Element | Copy |
|---|---|
| Page title | `__('Hi, :name!', ['name' => $member->name])` |
| Tab — Profile | `__('Profile')` |
| Tab — Meal off | `__('Meal off')` |
| Tab — My meals | `__('My meals')` |
| Profile — emergency contact label | `__('Emergency contact')` |
| [Edit emergency contact] | `__('Edit')` |
| [Change password] | `__('Change password')` |
| Meal off form — from date | `__('From date')` |
| Meal off form — to date | `__('To date')` |
| Meal off form — reason | `__('Reason')` (required) |
| Meal off form — placeholder | `__('e.g. Going home, official tour, family event')` |
| [Submit request] | `__('Request meal off')` |
| Meal off list — empty | `__('You have no meal off requests.')` |
| My meals — empty | `__('No meals recorded for you this month yet.')` |
| My meals — row | `__(':date — :meals', ['date' => $entry->date->format('d M, l'), 'meals' => $mealsText])` (where `$mealsText` is "B, L, D" or "B, D" or "—" if none) |

### 6.7 Bazar & fixed expense forms

| Element | Copy |
|---|---|
| Page title — bazar | `__('Record bazar')` |
| Page title — fixed | `__('Record fixed expense')` |
| Field — date | `__('Date')`, required, default = today, max = today |
| Field — category | `__('Category')`, required, dropdown filtered by kind |
| Field — purchased by (bazar only) | `__('Purchased by')`, optional, dropdown of active members |
| Field — vendor (bazar only) | `__('Vendor')`, optional |
| Field — description | `__('Description')`, optional, help `__('e.g. 1.5kg Rui fish, fresh')` |
| Field — amount | `__('Amount (BDT)')`, required, numeric, > 0 |
| Field — receipt (bazar only) | `__('Receipt')`, optional, help `__('JPG or PNG. Max 5 MB.')` |
| [Save] | `__('Save expense')` |
| [Cancel] | `__('Cancel')` |
| Saved confirmation — bazar | `__('Bazar expense of ৳:amount saved.', ['amount' => $amount])` |
| Saved confirmation — fixed | `__('Fixed expense of ৳:amount saved.', ['amount' => $amount])` |
| Error — amount zero | `__('Amount must be greater than zero.')` |
| Error — date in future | `__('Date cannot be in the future.')` |
| Error — receipt too large | `__('Receipt must be 5 MB or smaller.')` |

### 6.8 Expense category manager

| Element | Copy |
|---|---|
| Page title | `__('Expense categories')` |
| Page subtitle | `__('Categories are filtered by kind on the bazar and fixed expense forms.')` |
| [+ New category] | `__('New category')` |
| Kind pill — bazar | `__('Bazar')` |
| Kind pill — fixed | `__('Fixed')` |
| Kind pill — other | `__('Other')` |
| Default lock tooltip | `__('Default category — cannot be deleted')` |
| [Delete] (custom only) | `__('Delete')` |
| Delete confirmation | `__('Are you sure you want to delete :name? Existing expenses will keep their category label.', ['name' => $category->name])` |
| [Cancel delete] | `__('Cancel')` |
| [Confirm delete] | `__('Delete')` |
| Empty state (no categories) | `__('No categories yet.')` + `__('Run the database seeder to load defaults.')` |

## 7. Accessibility (Phase 2 additions, baseline inherited from Phase 1)

- **Meal grid checkboxes** (D-07): each checkbox has an `aria-label="Breakfast for {member name} on {date}"`. The label is rendered via the `<label for="...">` association, not the visible text, so screen readers announce the right thing.
- **Meal-off row indication**: rows use `aria-disabled="true"` on the form controls AND a visually hidden `<p>` that says "On meal off until {date} — cannot edit". The visible `<x-meal-off-badge>` provides the visual cue.
- **Quick action dropdowns**: use `<details>`/`<summary>` (native HTML, keyboard-accessible). On `Enter` or `Space`, the summary toggles. On `Escape` (when open), the details closes. No JS-based focus traps.
- **Date nav**: the date `<input type="date">` is wrapped in a `<label>` with `aria-label="Choose a different date"`. The prev/next buttons have `aria-label="Previous day"` / `"Next day"`.
- **Status pills**: have an `aria-label` describing the state in full, e.g. "Status: Active" (visible text is just "Active" — the aria-label provides context).
- **Tab nav** (D-23): the `<x-tab-nav>` uses the ARIA tabs pattern (`role="tablist"`, `role="tab"`, `aria-selected`, `aria-controls`). Only one tab is in the tab order at a time. Arrow keys move between tabs.
- **Photo input**: the camera button has `aria-label="Take a photo"` on mobile (when `capture="environment"` triggers) and `"Choose a photo"` on desktop (when capture is ignored by the OS).

## 8. Browser & internationalization (inherited, no changes)

Identical to 01-UI-SPEC §8 and §9. Modern evergreen browsers only. All strings in `__()`. English only in v1.

## 9. Phase 2 surface inventory (the 14 new screens + 4 existing updates)

| # | Surface | URL | Plan | Spec section |
|---|---|---|---|---|
| 1 | Members list | `/mess/members` | 2.1 | §4.2, §6.2 |
| 2 | Member create | `/mess/members/create` | 2.1 | §4.3, §6.1 |
| 3 | Member edit | `/mess/members/{id}/edit` | 2.1 | §4.3, §6.1 |
| 4 | Member profile view | `/mess/members/{id}` | 2.1 | §4.4, §6.3 |
| 5 | Member search (AJAX) | `/mess/members/search` | 2.1 | §4.2 |
| 6 | Daily meal grid | `/mess/meals` | 2.3 | §4.5, §6.4 |
| 7 | Meal off approval queue | `/mess/meal-off` | 2.4 | §4.6, §6.5 |
| 8 | Bazar expense form | `/mess/expenses/bazar/create` | 2.5 | §4.7, §6.7 |
| 9 | Fixed expense form | `/mess/expenses/fixed/create` | 2.5 | §4.8, §6.7 |
| 10 | Expense list | `/mess/expenses` | 2.5 | (table view, similar to audit log) |
| 11 | Category manager | `/mess/categories` | 2.5 | §4.9, §6.8 |
| 12 | Member self-view (3 tabs) | `/my` | 2.2 | §4.10, §6.6 |
| 13 | Member meal off request form | `/my#meal-off` (tab) | 2.2 | §6.6 |
| 14 | Member self meal off on-behalf (manager) | `/mess/members/{id}#meal-off` | 2.2 | §6.3 |
| U1 | Manager `/home` (updated) | `/home` | 2.1 | §4.1 |
| U2 | Manager sidebar (additions) | (layout) | 2.1 | §4.11 |
| U3 | Member `/my` (replaces Phase 1 placeholder) | `/my` | 2.2 | §4.10 |
| U4 | Audit log (existing, no change) | `/mess/audit` | — | 01-UI-SPEC §6.5 |

## 10. Implementation checklist (the 6-dimension gate)

This is the same checklist as Phase 1, plus a Phase 2 check.

| # | Dimension | Status | Evidence |
|---|---|---|---|
| 1 | **Design system locked** | ✓ (inherited) | 01-UI-SPEC §2 |
| 2 | **Component inventory defined** | ✓ | §3 (12 new components listed) |
| 3 | **Layout patterns specified** | ✓ | §4 (11 new layout patterns) |
| 4 | **Copywriting contract complete** | ✓ | §6 (8 screen copy sections) |
| 5 | **Accessibility baseline met** | ✓ (inherited) + §7 (5 new a11y rules) | §7 |
| 6 | **i18n + browser support declared** | ✓ (inherited) | 01-UI-SPEC §8, §9 |
| 7 | **Phase 2 surface inventory** | ✓ | §9 (14 new + 4 updated) |

**Registry safety dimension:** N/A — same as Phase 1. Auto-PASS.

---

## UI-SPEC COMPLETE
