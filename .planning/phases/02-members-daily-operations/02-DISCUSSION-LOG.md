# Phase 2: Members + Daily Operations - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-06-17
**Phase:** 02-members-daily-operations
**Areas discussed:** member photo upload, daily meal grid UX, meal off approval flow, expense category schema, guest meal charge rate, bazar purchased-by, member self-view scope, grid member filter, room/seat field shape, member search behavior, expense receipt upload, member-photo upload timing

---

## Member photo upload (MEM-07)

| Option | Description | Selected |
|--------|-------------|----------|
| Local storage (storage/app/public) | Files on local disk. Standard Laravel default. | ✓ |
| S3-compatible storage | Files on S3 / DO Spaces / MinIO. More setup. | |
| You decide | Let the agent pick. | |

| Option | Description | Selected |
|--------|-------------|----------|
| Camera + file input (mobile-first) | Native camera on phone, file picker on desktop. | ✓ |
| File picker only | Plain file input. | |
| You decide | | |

| Option | Description | Selected |
|--------|-------------|----------|
| Photo is optional, validation errors are clear | Best-effort upload. Member created without photo on failure. | ✓ |
| Photo is required | Block creation on upload failure. | |
| You decide | | |

**User's choice:** All three locked — D-01, D-02, D-03 in CONTEXT.md.
**Notes:** Mobile-first is the manager's primary form factor (PROJECT.md). Best-effort matches the "Receipt image upload is optional in v1" adopted recommendation.

---

## Daily meal grid UX (MEAL-01 to MEAL-11)

| Option | Description | Selected |
|--------|-------------|----------|
| Single 'Save all' button at bottom | All changes in one transaction. | ✓ |
| Auto-save per row | Each tap auto-saves. | |
| You decide | | |

| Option | Description | Selected |
|--------|-------------|----------|
| Prev/Today/Next + date picker | Fast mobile nav. | ✓ |
| Date picker only | | |
| Week view (7 days) | | |

| Option | Description | Selected |
|--------|-------------|----------|
| Row grayed out, checkboxes disabled | Cannot bypass approval. | ✓ |
| Row grayed, manager can still toggle | Allows override. | |
| You decide | | |

**User's choice:** All three locked — D-07, D-08, D-10 in CONTEXT.md. D-09 (active members only) and D-11/D-12 (preset behavior) followed naturally from "fast phone UX" context.
**Notes:** Single Save matches the "60 clicks → 4" recommendation. Meal-off as grayed/disabled matches OFF-05.

---

## Meal off approval flow (OFF-04 to OFF-07)

| Option | Description | Selected |
|--------|-------------|----------|
| Deduct immediately on approval | Simplest, matches OFF-06 wording. | ✓ |
| Deduct on the day itself | More 'correct' but harder to reason. | |
| You decide | | |

| Option | Description | Selected |
|--------|-------------|----------|
| One-by-one with required rejection reason | Mobile-friendly. | ✓ |
| Bulk approve/reject | Faster for many, but mobile-cluttered. | |
| You decide | | |

| Option | Description | Selected |
|--------|-------------|----------|
| Member self-submits (primary), manager can submit on behalf | Most flexible. | ✓ |
| Member self-submits only | Stricter. | |
| You decide | | |

**User's choice:** All three locked — D-13, D-14, D-15 in CONTEXT.md. D-16 (required reason) added for clarity per OFF-01.
**Notes:** "Deduct on approval" is runtime computation; no scheduled job needed.

---

## Expense category schema (CAT-01)

| Option | Description | Selected |
|--------|-------------|----------|
| Move kind to categories, remove expense_type | Reconciles the discrepancy; cleaner model. | ✓ |
| Keep expense_type on expenses | Simpler — one fewer column. | |
| You decide | | |

| Option | Description | Selected |
|--------|-------------|----------|
| Custom allowed, defaults cannot be deleted | Matches CAT-04. | ✓ |
| Manager can also delete defaults | | |
| You decide | | |

**User's choice:** Both locked — D-20, D-21 in CONTEXT.md. D-22 (kind-filtered dropdowns) is the natural UI follow-on.
**Notes:** The current schema has `expense_type` on `expenses` but no `kind` on `expense_categories`. Moving to categories-driven kind matches REQUIREMENTS.md exactly.

---

## Guest meal charge rate (GUEST-02)

| Option | Description | Selected |
|--------|-------------|----------|
| Use configured meal_value (0.5/1/1) | Simple, predictable. | ✓ |
| Use last closed month meal rate | Matches bazar/total_meals more closely. | |
| Manager enters charge manually | Most flexible, error-prone. | |
| You decide | | |

**User's choice:** D-17 in CONTEXT.md.
**Notes:** Locked at entry — month-close does not re-compute. Predictable for the member paying.

---

## Bazar 'purchased by' (BAZAR-01)

| Option | Description | Selected |
|--------|-------------|----------|
| Dropdown of members, default = first member, optional | Matches the existing FK schema. | ✓ |
| Add a 'Manager' option that's not a member | Schema change needed. | |
| Free text (any name) | Schema change. | |
| You decide | | |

**User's choice:** D-18 in CONTEXT.md.
**Notes:** Schema already has `purchased_by` → `members`. The dropdown UI is the smallest delta.

---

## Member self-view scope (MEM-05/06, OFF-01)

| Option | Description | Selected |
|--------|-------------|----------|
| Profile + meal history + meal off request only | Minimum viable for Phase 2. | ✓ |
| Full member dashboard in Phase 2 | Pulls PREVIEW logic forward. | |
| You decide | | |

**User's choice:** D-23 in CONTEXT.md. D-24 (member editable fields) added for clarity per MEM-05.
**Notes:** PREVIEW-03 is Phase 3, DASH-04 is Phase 4. Don't pull them forward.

---

## Grid member filter

| Option | Description | Selected |
|--------|-------------|----------|
| Only active members (recommended) | Fast, focused. | ✓ |
| Active members + 'Show inactive' toggle | | |
| All members, inactive grayed out | | |
| You decide | | |

**User's choice:** D-09 in CONTEXT.md.
**Notes:** Matches the "30+ meals/day" expectation.

---

## Room/seat field shape (MEM-01)

| Option | Description | Selected |
|--------|-------------|----------|
| Keep single 'room_or_seat' field | Free-form, flexible. | ✓ |
| Split into 'room' and 'seat' columns | Cleaner data, more validation. | |
| You decide | | |

**User's choice:** D-05 in CONTEXT.md.
**Notes:** Bangladesh messes often use combined labels; one field is enough.

---

## Member search behavior (MEM-04)

| Option | Description | Selected |
|--------|-------------|----------|
| Live search (debounced AJAX) | Modern phone UX. | ✓ |
| Form submit search | Simpler, no JS. | |
| You decide | | |

**User's choice:** D-06 in CONTEXT.md.
**Notes:** ~300ms debounce, server-side LIKE.

---

## Expense receipt upload (BAZAR-02)

| Option | Description | Selected |
|--------|-------------|----------|
| Same as photo: local + camera, optional, best-effort | Consistency. | ✓ |
| URL input only | Skips upload. | |
| No receipt in Phase 2 | Pushes to Phase 5. | |
| You decide | | |

**User's choice:** D-19 in CONTEXT.md.
**Notes:** Reuse the photo upload pattern + helper. JPG/PNG, max 5MB.

---

## Member-photo upload timing (MEM-07, MEM-05)

| Option | Description | Selected |
|--------|-------------|----------|
| Upload on create + replace on edit | Full flexibility. | ✓ |
| Upload only at creation | | |
| You decide | | |

**User's choice:** D-04 in CONTEXT.md.
**Notes:** Members can also replace their own photo from `/my/profile` per MEM-05.

---

## the agent's Discretion

- Exact photo dimensions / aspect ratio / compression
- Whether to use a third-party image manipulation library
- Exact AJAX endpoint shape for live member search
- Quick action icons / button labels
- File storage subdirectory layout
- Default category list exact slugs
- Whether the meal off request form uses a date range picker or two separate from/to date inputs
- Form layout breakpoints for the member list and form (375px baseline)

## Deferred Ideas

- **Receipt OCR / auto-categorization** — out of v1 scope.
- **Calendar view of meals** — grid is enough for v1.
- **Member self-bill preview** — Phase 3.
- **Member dashboard cards** — Phase 4.
- **Per-meal-type custom rates** — out of scope.
- **Member-submitted bazar expenses** — manager-only in v1.
- **2FA enforcement for member role** — deferred to v2.
- **Member-side live AJAX** — manager-only in Phase 2.
- **Inventory / stock tracking** — out of scope.
- **Cook/maid management** — out of scope.
</content>
</invoke>