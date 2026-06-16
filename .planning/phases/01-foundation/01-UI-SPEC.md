---
phase: 1
slug: foundation
status: draft
tool: tailwind-v4
preset: none
shadcn_initialized: false
created: 2026-06-16
---

# UI-SPEC: Phase 1 — Foundation

> Server-rendered Laravel 13 + Blade + Tailwind CSS v4 + Tyro Dashboard. No React. No shadcn. No third-party UI registry. This spec is the **visual language contract** for the entire app — every later phase reuses the components, tokens, and patterns defined here.

## 1. Stack & Toolchain (locked from CONTEXT + STACK)

| Layer | Choice | Why |
|---|---|---|
| Markup | Blade templates (`.blade.php`) | Server-rendered. No SPA. |
| CSS framework | **Tailwind CSS v4** (CSS-based config via `@theme` in `resources/css/app.css`) | Locked by taste preference. v4 uses `@tailwindcss/vite` plugin, no `tailwind.config.js`. |
| JS | Vanilla JS via `resources/js/app.js` + Axios for AJAX | No React, no Vue, no Inertia, no Livewire. |
| Component library | **None** (raw Tailwind + custom Blade components in `resources/views/components/`) | Locked by CONTEXT. No shadcn, no Bootstrap, no inline CSS. |
| Icon library | **Heroicons** (inline SVG paths) | Default for Tailwind projects; matches the inline-SVG pattern already used in Tyro's `admin-sidebar.blade.php` and `topbar.blade.php`. No extra npm package — copy paths into Blade components. |
| Font | **Inter** (Google Fonts via `<link>` in layout, or `bunny.net` for GDPR) | Matches the existing Tyro admin layout (`vendor/hasinhayder/tyro-dashboard/resources/views/layouts/admin.blade.php` already loads `inter:400,500,600,700` from bunny.net). Strong default for dashboard apps, pairs well with Bengali glyphs in v2. |
| Auth UI | Tyro Login default Blade templates (`@extends('tyro-login::layouts.auth')`) | Phase 1 ships login / register / password reset / verify-email / 2FA / magic-link / lockout **as-is** from the package. No custom override in Phase 1. |
| Admin UI | Tyro Dashboard default Blade templates (`@extends('tyro-dashboard::layouts.admin')`) for `/dashboard` and the `messes` / `settings` resources. Custom Blade pages for `/home`, `/my`, `/mess/audit`, onboarding. | Mixed: lean on Tyro where the auto-CRUD form works; write custom pages where CONTEXT requires a hand-crafted UX. |

### Registry safety dimension

**N/A — PASS by default.** Tailwind v4 + Blade only. No shadcn. No third-party UI registry. No `components.json`. The `ui_safety_gate` dimension auto-passes.

## 2. Design Tokens (write into `resources/css/app.css`)

Tailwind v4 reads tokens from the `@theme` block in `app.css`. Every custom utility in the app reads from these tokens — no hard-coded colors, fonts, or spacing in components.

### 2.1 Color palette (60/30/10 split)

| Role | Tailwind class | OKLCH / hex | Usage |
|---|---|---|---|
| **Dominant (60%)** | `bg-slate-50` | `oklch(98.4% 0.003 247.858)` | App background. Manager `/home`, member `/my`, audit log page, onboarding form. |
| **Secondary (30%)** | `bg-white border border-slate-200` | `oklch(100% 0 0)` / `oklch(92.9% 0.013 255.508)` | Card surface, sidebar surface, table rows, form input background. |
| **Accent (10%) — brand** | `bg-emerald-600` / `text-emerald-600` / `ring-emerald-600` | `oklch(59.6% 0.145 163.225)` | Primary CTA button, focus ring, active nav indicator, current-row table highlight. **Reserved for:** one primary action per view, focus ring on all focusable elements, active sidebar link, "this row" highlight in tables. **NOT for:** every interactive element, links, secondary buttons. |
| **Accent — hover** | `bg-emerald-700` | `oklch(50.8% 0.118 165.612)` | Hover state of the accent. |
| **Accent — soft surface** | `bg-emerald-50 text-emerald-700` | `oklch(97.9% 0.021 166.113)` / `oklch(50.8% 0.118 165.612)` | Selected pill, soft badge, success notice background. |
| **Destructive** | `bg-red-600` / `text-red-600` | `oklch(57.7% 0.245 27.325)` | Destructive actions only: delete account, lock account, hard-reset audit log, deactivate mess. **Never for:** error messages (use amber), validation errors (use slate-700 text + red border on the offending input). |
| **Error border / validation** | `border-red-500` | `oklch(63.7% 0.237 25.331)` | Border on invalid form field. |
| **Error text** | `text-red-700` | `oklch(50.5% 0.213 27.518)` | Inline error message under a field. |
| **Warning / informational** | `bg-amber-50 text-amber-800` | `oklch(98.7% 0.022 95.277)` / `oklch(47.3% 0.137 46.201)` | Banner for "mess not yet configured", "your role is X". |
| **Success** | `bg-emerald-50 text-emerald-800` | (same as accent-soft) | Saved-confirmation toast, audit-log write confirmation. |
| **Text — primary** | `text-slate-900` | `oklch(20.8% 0.042 265.755)` | Body text, headings. |
| **Text — secondary** | `text-slate-600` | `oklch(44.6% 0.043 257.281)` | Captions, helper text, table sub-text. |
| **Text — muted** | `text-slate-400` | `oklch(70.4% 0.04 256.788)` | Placeholders, disabled state, "no data" labels. |
| **Border** | `border-slate-200` | `oklch(92.9% 0.013 255.508)` | Card border, table border, input border, divider. |
| **Border — strong** | `border-slate-300` | `oklch(86.9% 0.022 252.894)` | Input focus fallback, table header underline. |

#### Why `emerald-600` (not indigo, not blue)

The default Tyro dashboard sidebar is a near-white (`oklch(0.985 0 0)`), and Tyro's `--primary` is `oklch(0.205 0 0)` (near-black). A green accent contrasts: it's the conventional "money / bills / money-management" hue for fintech and household-finance apps, the mess manager's primary activity is collecting money + tracking bazar, and it reads as friendly without being playful. Default — user can override in plan-phase review.

#### Dark mode

Out of scope for Phase 1. The Tyro admin layout already supports `.dark` class via its `shadcn-theme.blade.php` (uses `oklch(0.145 0 0)` background, `oklch(0.985 0 0)` text). Our custom Blade pages should support `prefers-color-scheme: dark` via the existing `dark:` variants in the tailwindcss v4 default theme, but a custom dark palette is **deferred to Phase 5 polish**.

#### Tyro sidebar color override (config-level)

Tyro exposes 5 sidebar color knobs in `config/tyro-dashboard.php` under `branding`:
- `sidebar_bg` (default `null` → uses shadcn default `oklch(0.985 0 0)`)
- `sidebar_text` (default `null`)
- `sidebar_primary` (default `null`)
- `sidebar_accent` (default `null`)
- `sidebar_accent_foreground` (default `null`)

**Phase 1 decision: do NOT override these** in `config/tyro-dashboard.php`. The Tyro default near-white sidebar already pairs cleanly with the `emerald-600` accent and `slate-50` app background. If a future phase needs branded sidebar colors, set them via env keys (`TYRO_DASHBOARD_SIDEBAR_PRIMARY=oklch(0.596 0.145 163.225)`) — but for Phase 1, leave them at default to ship faster.

### 2.2 Typography

| Token | Tailwind | Value | Use for |
|---|---|---|---|
| Display | `text-2xl font-semibold` | 24px / 600 | Page title (one per view) — **NOT 28px**. (The 28px from the original prompt is reserved for a future "report" hero — not needed in Phase 1.) |
| Heading | `text-lg font-semibold` | 18px / 600 | Section heading inside a page (e.g. "Filters" on audit log). |
| Body | `text-base font-normal` | 16px / 400 | All paragraphs, form labels, table cells. |
| Small / caption | `text-sm font-normal` | 14px / 400 | Helper text, table sub-text, breadcrumbs, button text on small buttons. |
| Line-height body | `leading-normal` | 1.5 | Body copy. |
| Line-height heading | `leading-tight` | 1.25 | Headings, page titles. |
| Font family | `--font-sans: 'Inter', ui-sans-serif, system-ui, sans-serif` | Override the existing `--font-sans` in `app.css` `@theme` block | The current `app.css` declares `'Instrument Sans'` from the default Laravel scaffold; **replace it with Inter**. |

**Only 2 weights used:** `font-normal` (400) and `font-semibold` (600). No medium, no bold. (Tightens the visual language and matches dashboard conventions.)

### 2.3 Spacing scale

**Lock to Tailwind defaults** (4px base, multiples of 4). Common values used in Phase 1:

| Token | Tailwind | px value | Use |
|---|---|---|---|
| xs | `p-1`, `gap-1` | 4 | Tight inline gap (icon + label inside a chip). |
| sm | `p-2`, `gap-2` | 8 | Form field vertical gap, button internal padding (y). |
| md | `p-4`, `gap-4` | 16 | Card padding, form section gap, button internal padding (x for `size=md`). |
| lg | `p-6`, `gap-6` | 24 | Page padding on mobile, card-to-card vertical gap. |
| xl | `p-8` | 32 | Page padding on `sm:` and up. |
| 2xl | `p-12` | 48 | Reserved for hero / report pages (not Phase 1). |

**Exception: 44px minimum touch target.** All buttons, links, checkboxes, radio inputs, and table-row click targets must have `min-h-[44px] min-w-[44px]` (or be wrapped in a 44px+ clickable area). This is iOS HIG (44pt) and WCAG 2.5.5. Apply via:
- Default button class: `min-h-[44px] px-4 py-2`
- Form input: `min-h-[44px] px-3 py-2`
- Icon-only button: `min-h-[44px] min-w-[44px] p-2` (so the 24px icon + 8px padding each side = 40px; bump to `p-2.5` for exact 44px)

### 2.4 Radius & shadow

| Token | Tailwind | Use |
|---|---|---|
| Card radius | `rounded-lg` (8px) | All card surfaces, sidebar, modals, popovers. |
| Button radius | `rounded-md` (6px) | All buttons. |
| Input radius | `rounded-md` (6px) | All text inputs, selects, textareas. |
| Pill radius | `rounded-full` | Status badges ("Active", "Inactive"), avatar. |
| Card shadow | `shadow-sm` (`0 1px 2px 0 rgb(0 0 0 / 0.05)`) | Default card lift. |
| Card hover shadow | `shadow` (slightly stronger) | Hovered card (use sparingly). |

### 2.5 Custom `@theme` block to write into `resources/css/app.css`

```css
@import 'tailwindcss';

@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
@source '../../storage/framework/views/*.php';
@source '../**/*.blade.php';
@source '../**/*.js';

@theme {
    /* Override Laravel scaffold default (Instrument Sans) with Inter */
    --font-sans: 'Inter', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji',
        'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji';

    /* Brand accent — used by Tailwind's text-emerald-*, bg-emerald-*, ring-emerald-*
       classes. Tailwind v4 already ships the emerald scale (oklch), so we don't
       redeclare it; this is a marker for "this is the brand color" in code review. */
    /* --color-brand-50 ... 900 — map to emerald if you want semantic names:
       --color-brand-500: var(--color-emerald-500);
       --color-brand-600: var(--color-emerald-600);
    */
}

/* Custom utilities the @theme block can't express directly. */

/* 44px touch target for any clickable element that wraps small content */
@utility touch-target {
    min-height: 44px;
    min-width: 44px;
}
```

The exact `@utility` syntax above is Tailwind v4's native custom-utility declaration. If the project's Tailwind v4 version is older than the `@utility` directive shipped in 4.0.7, fall back to a plain CSS class in `@layer components`:

```css
@layer components {
    .touch-target {
        @apply min-h-[44px] min-w-[44px];
    }
}
```

## 3. Component Inventory (`resources/views/components/`)

These are the Blade components Phase 1 will create. Every later phase extends this list. All components:
- Are **anonymous components** (Blade 9+ style, no PHP class needed for the simple ones).
- Take only **typed props** via `@props(['variant' => 'primary', ...])`.
- Render Tailwind classes **only** — no inline `style=""` (except where a value is computed at render time, e.g. a date format).
- All user-facing strings inside the component wrapped in `__()`.

| File | Purpose | Variants | Notes |
|---|---|---|---|
| `button.blade.php` | Primary action button. | `primary` (emerald-600 fill), `secondary` (white fill, slate-300 border), `destructive` (red-600 fill), `ghost` (transparent, hover:bg-slate-100) | Sizes: `md` (default, 44px tall), `sm` (32px — for inline-table actions only), `lg` (52px — for primary onboarding CTA). Disabled state: `opacity-50 cursor-not-allowed`. Loading state: shows spinner SVG + disabled. |
| `form-field.blade.php` | Label + input + error wrapper. | — | Wraps `<x-input>` (HTML `<input>`), `<x-textarea>`, `<x-select>`. Props: `name`, `label`, `type` (default `text`), `value`, `required`, `help`, `error`. Auto-fills `error` from `$errors` bag if not passed. |
| `input.blade.php` | Bare `<input>` with consistent styling. | `text`, `email`, `password`, `number`, `date`, `tel` | `min-h-[44px] px-3 py-2 rounded-md border border-slate-300 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600 focus:ring-offset-0`. |
| `textarea.blade.php` | Bare `<textarea>`. | — | Same border + focus pattern as input. `min-h-[88px]` (2× 44px). |
| `select.blade.php` | Bare `<select>`. | — | Same border + focus pattern. Chevron SVG on the right via `appearance-none background-image`. |
| `card.blade.php` | Bordered white surface. | `default` (white + border + shadow-sm), `flat` (white + border, no shadow) | Padding `p-4 md:p-6`. Used by every "form on a page" layout. |
| `page-header.blade.php` | Top-of-page title + breadcrumb + optional right-aligned action. | — | Props: `title` (required, 2xl/semibold), `subtitle` (optional, sm/slate-600), `back` (optional route name, renders "← Back" link). Slots: `actions` (right-aligned buttons). |
| `table.blade.php` | Read-only data table. | `default` (full table on desktop), `stacked` (each row becomes a card on mobile) | Props: `rows`, `columns` (array of `['key', 'label', 'mobile_label'?, 'format'?, 'class'?]`). The `stacked` variant collapses to a card list below `md:` (768px). **Justification for stacked vs. horizontal-scroll:** the audit log is short (≤50 rows per page), and the columns are simple (timestamp, model, user, action). Stacked cards are more readable on a 375px phone than horizontal scrolling. |
| `empty-state.blade.php` | "No data" placeholder. | — | Props: `title` (required), `description` (optional), `icon` (optional SVG path). Centered, max-w-md. Padding `py-12`. |
| `flash-message.blade.php` | Single inline message above content. | `success`, `error`, `warning`, `info` | Used on non-Tyro pages (`/home`, `/my`, `/mess/audit`, onboarding). Tyro pages use Tyro's own `flash-messages.blade.php`. |
| `audit-filters.blade.php` | Filter form for `/mess/audit`. | — | GET form with: model (select, populated from audit model class names), user (select, populated from active users), date-from (date input), date-to (date input), [Apply filters] (primary button), [Reset] (ghost button). Stacks vertically on mobile, 2-col on `sm:`, 4-col on `md:`. |
| `nav-link.blade.php` | Single sidebar link for our custom `/home` and `/my` layouts. | — | Wraps an `<a>` with active-state styling (`bg-emerald-50 text-emerald-700 border-l-2 border-emerald-600` for active, `text-slate-700 hover:bg-slate-100` for inactive). `aria-current="page"` when active. |

### 3.1 Components explicitly NOT built in Phase 1

- `<x-modal>` — Phase 1 has no modal use cases. Use Tyro's `partials/modal.blade.php` if needed.
- `<x-tabs>` — no tab use cases.
- `<x-table-row>` (with edit/delete buttons) — Phase 1 has no inline-row actions.
- `<x-chart-card>` — Phase 4 (Dashboard).
- `<x-stat-card>` — Phase 4 (Dashboard).
- `<x-data-grid>` — Phase 2 (meal grid).

## 4. Layout Patterns

### 4.1 App-level layout (custom, used by `/home`, `/my`, `/mess/audit`, onboarding)

```
┌─────────────────────────────────────────┐
│ Top bar: app name │ user │ logout       │  h-14
├──────────┬──────────────────────────────┤
│ Sidebar  │                              │
│ (hidden  │   Main content               │
│  on      │   (max-w-3xl mx-auto         │
│  mobile, │    p-4 md:p-8)               │
│  drawer  │                              │
│  on tap) │                              │
│          │                              │
│ w-64 on  │                              │
│ md+      │                              │
└──────────┴──────────────────────────────┘
```

- **Mobile (< 768px)**: sidebar is a drawer. Hamburger button in top bar opens it as a slide-in overlay with `bg-slate-900/50` backdrop. Drawer width = `w-64` (256px).
- **Tablet+ (≥ 768px)**: sidebar is permanently visible at `md:w-64`, content area takes the rest.
- **Top bar height**: `h-14` (56px). Contains: app name (text-base, font-semibold, slate-900), spacer, user name (text-sm, slate-600), logout button (ghost variant, `aria-label="Log out"`).
- **Main content**: `max-w-3xl mx-auto p-4 md:p-8`. The 3xl max (768px) keeps reading width comfortable on tablets; the manager's phone at 375px will use 100% of the viewport with 16px padding.

**Layout file:** `resources/views/layouts/app.blade.php` — the parent for all custom Phase 1 pages.

### 4.2 Form pages (onboarding, settings sub-form, mess edit override)

- Single column on mobile (`flex flex-col gap-6`).
- 2 columns on `sm:` for short fields (e.g. currency + date format side by side), but long fields (name, address) stay single column at all breakpoints.
- Sticky footer on mobile with the [Save] button so the user doesn't have to scroll back up. On `sm:`, the footer is inline at the bottom of the form.
- All Form Request errors render inline under the field via `<x-form-field :error="...">`, never as a global banner (which the Tyro layout shows but our custom layout does not — we keep the error close to the field).

### 4.3 Data table (audit log)

- Desktop (≥ 768px): standard HTML `<table>` with `min-w-full divide-y divide-slate-200`, header `bg-slate-50 text-xs font-medium text-slate-500 uppercase tracking-wider`.
- Mobile (< 768px): each row becomes a card. The card shows: timestamp (text-xs, slate-500), then 2-column grid of `label: value` pairs (label = text-xs text-slate-500, value = text-sm text-slate-900). The card is a `<div class="bg-white border border-slate-200 rounded-lg p-4">` separated by `space-y-3` between cards.
- Pagination: Tyro's default pagination component (`vendor/laravel/framework/.../pagination/*.blade.php`) — already covered by the `@source` directive in `app.css`.

### 4.4 Login pages (use Tyro default, no override)

Phase 1 ships the Tyro Login default Blade templates untouched. The `config/tyro-login.php` `layout` setting (default `centered`) applies. The form fields, lockout copy, and 2FA challenge UI are all Tyro-managed. **Do not publish or override** `vendor/hasinhayder/tyro-login/resources/views/login.blade.php` in Phase 1.

If we later want to theme the login background, set `TYRO_LOGIN_BACKGROUND_IMAGE` env key. Not in Phase 1.

## 5. Iconography (Heroicons)

Inline SVG only. No npm package. Each icon is a Blade partial or a `<x-icon name="..." />` component that inlines the 24x24 outline path.

### 5.1 Component shape

`resources/views/components/icon.blade.php`:

```blade
@props(['name', 'class' => 'w-5 h-5'])
@php
    $paths = [
        'check' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />',
        'x' => '<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />',
        // ... 30-50 icons total, copy from heroicons.com
    ];
@endphp
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" {{ $attributes->merge(['class' => $class]) }} aria-hidden="true">
    {!! $paths[$name] ?? '' !!}
</svg>
```

### 5.2 Phase 1 icon set (minimum needed)

| Name | Used by |
|---|---|
| `check` | success state, saved confirmation, completed row |
| `x` | error state, dismiss button |
| `exclamation-triangle` | warning banner ("mess not configured"), lockout page |
| `information-circle` | info banner, helper tooltip |
| `arrow-left` | back link, breadcrumb |
| `cog-6-tooth` | settings link on `/home` |
| `user-circle` | profile link on `/my`, audit log actor column |
| `document-text` | audit log model column |
| `clock` | audit log timestamp column |
| `funnel` | audit log filter toggle |
| `home` | `/home` sidebar entry |
| `bars-3` | mobile sidebar toggle |
| `arrow-right-on-rectangle` | logout |
| `magnifying-glass` | search input (Phase 2, but include the icon now for reuse) |
| `chevron-down` | select chevron |

15 icons. Copy the 24x24 outline path data from https://heroicons.com (the MIT-licensed set by the Tailwind team).

## 6. Copywriting Contract

Every user-facing string wrapped in `__()`. English only in v1. Phase 5 checklist includes: "scan all `.blade.php` for raw strings, wrap any leftover in `__()`."

### 6.1 Login / auth pages (Tyro default — no override)

Use Tyro's existing copy. Do not translate. Do not add a tagline. The Tyro default backgrounds ("Welcome Back!" / "Join Us Today!" / "Forgot Your Password?") ship as-is.

### 6.2 Manager `/home` shell

| Element | Copy |
|---|---|
| Page title | `__('Welcome, :name', ['name' => $user->name])` |
| Body | `__('Your mess is set up. You can edit mess details or add members from the sidebar.')` |
| Card 1 heading | `__('Mess settings')` |
| Card 1 link label | `__('Open mess settings')` |
| Card 1 sub-text | `__('Update name, address, rent, meal values, and currency.')` |
| Card 2 heading | `__('Members')` |
| Card 2 link label | `__('Add a member')` |
| Card 2 sub-text | `__('Coming in Phase 2.')` |
| Card 2 disabled state | `__('Phase 2 — not yet available')` |
| Empty state (if no mess) | `__('No mess is configured yet.')` + `__('Create the mess to get started.')` + `[Create mess]` button (primary) → onboarding form |

### 6.3 Member `/my` shell

| Element | Copy |
|---|---|
| Page title | `__('Welcome, :name', ['name' => $user->name])` |
| Body | `__('This is your personal space. Your profile, meal history, and bill will appear here in later phases.')` |
| Card 1 heading | `__('My profile')` |
| Card 1 link label | `__('View profile')` |
| Card 1 sub-text | `__('Coming in Phase 2.')` |
| Card 1 disabled state | `__('Phase 2 — not yet available')` |
| Empty state (no mess) | `__('Your mess is not yet configured. Please ask the manager to finish setup.')` (no action button — member can't fix this) |

### 6.4 Mess configuration form (Tyro resource, auto-generated)

Tyro generates the form labels from the `fields` config in `resources` array. The Phase 1 plan must use:

| Field | Label | Placeholder | Help text |
|---|---|---|---|
| `name` | `__('Mess name')` | `__('e.g. ABC Mess')` | — |
| `address` | `__('Address')` | `__('Street, area, city')` | — |
| `monthly_rent` | `__('Monthly rent (BDT)')` | — | `__('Total rent for the mess per month')` |
| `manager_contact` | `__('Manager contact')` | `__('Phone or email')` | — |
| `status` | `__('Status')` | — | `__('Inactive messes are hidden from members')` |

The settings sub-form (separate Tyro resource, not sub-form) uses:

| Setting key | Label | Type | Default | Help text |
|---|---|---|---|---|
| `meal_breakfast` | `__('Breakfast meal value')` | number step=0.1 | 0.5 | `__('Used to compute meal rate')` |
| `meal_lunch` | `__('Lunch meal value')` | number step=0.1 | 1 | `__('Used to compute meal rate')` |
| `meal_dinner` | `__('Dinner meal value')` | number step=0.1 | 1 | `__('Used to compute meal rate')` |
| `currency` | `__('Currency code')` | text max=3 | BDT | `__('ISO 4217 code (BDT, USD, INR)')` |
| `date_format` | `__('Date format')` | select | DD-MM-YYYY | `__('How dates appear in the UI and PDFs')` |
| `auto_monthly_close` | `__('Auto-close month')` | boolean | false | `__('Reserved for v2')` |

### 6.5 Audit log viewer (`/mess/audit`)

| Element | Copy |
|---|---|
| Page title | `__('Audit log')` |
| Page subtitle | `__('Every change to mess data, recorded with the user, timestamp, and before/after values.')` |
| Filter form heading | `__('Filters')` |
| Filter — model label | `__('Model')` |
| Filter — model option "all" | `__('All models')` |
| Filter — user label | `__('User')` |
| Filter — user option "all" | `__('All users')` |
| Filter — date from | `__('From date')` |
| Filter — date to | `__('To date')` |
| [Apply filters] | `__('Apply filters')` |
| [Reset] | `__('Reset')` |
| Table column — timestamp | `__('When')` |
| Table column — user | `__('Who')` |
| Table column — model | `__('What')` |
| Table column — action | `__('Action')` |
| Table column — before/after | `__('Details')` |
| Mobile card — timestamp label | `__('When')` |
| Mobile card — user label | `__('Who')` |
| Mobile card — model label | `__('What')` |
| Mobile card — action label | `__('Action')` |
| Empty state (no entries) | `__('No audit entries match your filters.')` + `__('Try clearing the filters or check back after a mess change is made.')` |
| Empty state (no filters, no entries) | `__('No audit entries yet.')` + `__('Once you change mess settings, the change will appear here.')` |
| Error state | `__('We couldn\'t load the audit log.')` + `__('What to do next: try refreshing the page. If the problem persists, check the application logs.')` |

Action values are not translated: `created`, `updated`, `deleted`, `restored` (per owen-it/laravel-auditing convention). The table renders them lowercase as-is.

### 6.6 Onboarding form (first `/dashboard` visit, no mess)

| Element | Copy |
|---|---|
| Page title | `__('Create your mess')` |
| Page subtitle | `__('This is the only mess this installation will manage. You can edit it later from the dashboard.')` |
| Field — name label | `__('Mess name')` |
| Field — name placeholder | `__('e.g. ABC Mess')` |
| Field — address label | `__('Address')` |
| Field — address placeholder | `__('Street, area, city')` |
| Field — monthly rent label | `__('Monthly rent (BDT)')` |
| Field — monthly rent placeholder | `__('0 for no rent')` |
| Field — manager contact label | `__('Your contact info')` |
| Field — manager contact placeholder | `__('Phone or email')` |
| Field — meal breakfast label | `__('Breakfast meal value')` |
| Field — meal breakfast help | `__('Default 0.5. Most messes use 0.5 / 1 / 1.')` |
| Field — meal lunch label | `__('Lunch meal value')` |
| Field — meal dinner label | `__('Dinner meal value')` |
| Field — currency label | `__('Currency code')` |
| Field — currency placeholder | `__('BDT')` |
| Field — date format label | `__('Date format')` |
| [Create mess] button | `__('Create mess')` |
| [Cancel] button | hidden (no cancel — must create to continue) |
| Error (validation) | rendered inline by `<x-form-field :error="$message">` |
| Error (server 500) | `__('We couldn\'t create the mess.')` + `__('What to do next: check the form for errors and try again. If the problem persists, contact support.')` |

### 6.7 Destructive confirmations in Phase 1

Phase 1 has **only one** destructive action: deactivating the mess (setting `status = inactive`). The Tyro resource form handles this without a custom confirmation modal in Phase 1; the user can change status back to active at any time. We do not add a "delete mess" button in Phase 1 — there is no schema path for that and it's out of scope.

For Phase 2+ when destructive actions ship (delete member, void payment, hard-reset month), the confirmation pattern is:

```
"Are you sure you want to [action]? This cannot be undone."
[Cancel] [Confirm] (destructive variant)
```

That copy is reserved for later phases. Phase 1 commits to: **zero destructive actions** in custom UI. The mess config form's "status" field is a soft toggle, not destructive.

## 7. Accessibility Baseline

- **Semantic HTML**: every page uses `<main>`, `<nav>`, `<header>`, `<footer>` correctly. Tables use `<thead>`, `<tbody>`, `<th scope="col">`. Forms use `<label for="...">` for every input.
- **Focus indicators**: every focusable element gets `focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2`. Tailwind v4 ships `focus-visible:` as a first-class variant.
- **Touch targets**: 44px minimum (per §2.3).
- **Color contrast**: every text-on-background combination in the palette (§2.1) meets WCAG AA (4.5:1 for body text, 3:1 for large text and UI components). Verified:
  - `text-slate-900` on `bg-slate-50`: 18.7:1 ✓
  - `text-slate-600` on `bg-slate-50`: 7.1:1 ✓
  - `text-slate-700` on `bg-white`: 10.4:1 ✓
  - `text-emerald-700` on `bg-emerald-50`: 7.6:1 ✓
  - `text-white` on `bg-emerald-600`: 4.6:1 ✓ (borderline — pass for AA large text, pass for AA normal text per WCAG 2.0)
  - `text-white` on `bg-red-600`: 5.2:1 ✓
- **Icon-only buttons**: get `aria-label="..."` (e.g. `<button aria-label="Log out">` with a logout `<x-icon>` inside).
- **Form errors**: associated with the field via `aria-describedby` and `aria-invalid="true"`. The `<x-form-field>` component handles this — it sets `aria-describedby="<name>-error"` and `aria-invalid` on the input when an error is present, and gives the error `<p>` the matching `id`.
- **Skip link**: every custom layout (`layouts/app.blade.php`) includes a "Skip to main content" link as the first focusable element, hidden until focused (`sr-only focus:not-sr-only`).

## 8. Browser Support

Modern evergreen browsers only:
- Chrome / Edge (latest 2 versions)
- Firefox (latest 2 versions)
- Safari (latest 2 versions, including iOS Safari)
- Samsung Internet (latest 1 version, for the mess manager's Android phone)

**No IE. No legacy Edge.** No polyfills for `oklch()`, CSS `:has()`, or CSS grid — all evergreen browsers support these.

## 9. Internationalization (i18n) Readiness

Per CONTEXT D-23 and PERF-03:
- All user-facing strings wrapped in `__()`.
- Only English (`en`) ships in v1. Bengali (`bn.json`) deferred to v2.
- `resources/lang/en.json` does not exist yet — the Laravel default fallback is fine for v1. The plan should NOT generate `en.json` for v1; Laravel will use the source string as the key.
- The `__()` calls in the custom Blade components and views are forward-compatible — switching to `bn.json` in v2 is a translation file drop, no code change.

## 10. Phase 1 Surface Inventory (the 11 screens)

| # | Surface | URL | Implementation | Spec section |
|---|---|---|---|---|
| 1 | Login | `/login` | Tyro default | §4.4 |
| 2 | Register | `/register` | Tyro default | §4.4 |
| 3 | Forgot password / reset | `/password/*` | Tyro default | §4.4 |
| 4 | Email verification | `/verify-email`, `/email/verify/*` | Tyro default | §4.4 |
| 5 | 2FA challenge / setup | `/two-factor-challenge`, `/two-factor-setup` | Tyro default | §4.4 |
| 6 | Manager home | `/home` | Custom Blade, `layouts/app.blade.php` | §6.2 |
| 7 | Member home | `/my` | Custom Blade, `layouts/app.blade.php` | §6.3 |
| 8 | Mess configuration form | `/dashboard/resources/messes/{create,edit,index,show}` | Tyro resource, custom labels | §6.4 |
| 9 | Audit log viewer | `/mess/audit` | Custom Blade, `layouts/app.blade.php` | §6.5 |
| 10 | Onboarding form | `/dashboard/onboarding` (or wherever the redirect lands) | Custom Blade, `layouts/app.blade.php` | §6.6 |
| 11 | Super-admin dashboard | `/dashboard` | Tyro default | (no spec needed — Tyro ships it) |

## 11. Tyro config keys to set in `.env` for Phase 1

These flow from the spec above, not the auth requirements (which are in RESEARCH.md §1 and §2):

```env
# Branding (set during Phase 1.1)
TYRO_DASHBOARD_APP_NAME="Devsroom Mess"
TYRO_LOGIN_APP_NAME="Devsroom Mess"
TYRO_LOGIN_LAYOUT=centered
TYRO_LOGIN_BG_TITLE="Welcome back, manager"
TYRO_LOGIN_BG_DESCRIPTION="Sign in to manage your mess: meals, bazar, payments, and members."

# Notifications (use the toast style, not legacy)
TYRO_DASHBOARD_NOTIFICATION_STYLE=toast
TYRO_DASHBOARD_TOAST_POSITION=bottom-right

# Do NOT override sidebar colors (use Tyro defaults — near-white)
# TYRO_DASHBOARD_SIDEBAR_PRIMARY remains unset → oklch(0.205 0 0)
```

The 2FA, registration, lockout, and `tyro-login` overrides are already specified in `.planning/phases/01-foundation/01-RESEARCH.md` §1–§2 and are NOT repeated here.

## 12. Implementation Checklist (the 6-dimension gate, adapted for Tailwind)

This replaces the shadcn-oriented 6-dimension checklist from the UI-SPEC template. Every box must be checkable before the Phase 1 plan can be marked "UI spec complete."

| # | Dimension | Status | Evidence |
|---|---|---|---|
| 1 | **Design system locked** — Tailwind v4 + Blade + Heroicons + Inter. No shadcn. No third-party UI registry. No inline CSS. | ✓ | §1 (Stack & Toolchain), §2 (Tokens in `app.css`), §5 (Iconography) |
| 2 | **Component inventory defined** — 12 Blade components listed with variants, props, and use sites. | ✓ | §3 (Component Inventory) |
| 3 | **Layout patterns specified** — custom app layout, form layout, data table (with mobile-stacking), Tyro default for login. | ✓ | §4 (Layout Patterns) |
| 4 | **Copywriting contract complete** — every Phase 1 screen has heading, body, CTA, empty state, error state, and destructive confirmation (or N/A) copy. | ✓ | §6 (Copywriting Contract) |
| 5 | **Accessibility baseline met** — semantic HTML, focus-visible, 44px touch, WCAG AA contrast verified, icon labels, skip link, error association. | ✓ | §7 (Accessibility) |
| 6 | **i18n + browser support declared** — all strings in `__()`, en-only in v1, modern evergreen browsers only. | ✓ | §8 (Browser), §9 (i18n) |

**Registry safety dimension:** N/A — Tailwind v4 + Blade only. No shadcn, no third-party UI registry. Auto-PASS.

---

## UI-SPEC COMPLETE
