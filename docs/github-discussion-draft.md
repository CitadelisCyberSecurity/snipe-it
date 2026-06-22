# RFC: Access Review / License Audit Campaign feature

> **Status:** Draft — do not post until Days 3–4 UI is complete (manager review screen + admin results page).
> Add 2–3 screenshots at the bottom before posting.

---

## Problem

Organizations using Snipe-IT for license management frequently need to run periodic access reviews — a process where a manager confirms which of their direct reports actually need each software license they're currently assigned. This is a standard compliance requirement for SOC 2 Type II, ISO 27001, and internal IT hygiene programs.

Today there's no way to do this inside Snipe-IT. The typical workaround is to export a CSV of license seat assignments, email it to each manager, wait for responses, then manually process the results. This is error-prone, hard to audit, and time-consuming at scale.

Snipe-IT already has all the raw data needed to automate this: license seats, user records with `manager_id`, and purchase cost data. This proposal describes a feature that ties those together into a structured review workflow.

---

## Proposed feature: Access Review Campaigns

The feature introduces a **Campaign** concept: an admin creates a campaign, launches it (which snapshots the current license seat assignments), managers review their direct reports' licenses and mark each one Keep / Modify / Delete with an optional comment, and then the admin executes the results (revokes Delete decisions, flags Modify items for manual follow-up).

### Workflow

```
Admin creates campaign (draft)
        ↓
Admin launches campaign → snapshot of all active license seats is taken
        ↓
Each manager logs in → sees only their direct reports' licenses
Manager marks each row: Keep / Modify / Delete + optional comment
Manager marks their review complete
        ↓
Admin views results → executes Delete (triggers existing LicenseCheckin logic)
Admin closes campaign
```

### Data model

Two new tables, zero changes to existing tables:

**`access_review_campaigns`**

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | PK |
| `name` | string | |
| `description` | text | nullable |
| `status` | string(16) | `draft` / `active` / `closed` |
| `launched_at` | timestamp | nullable |
| `closed_at` | timestamp | nullable |
| `created_by` | integer | FK → users |
| `deleted_at` | timestamp | soft delete |

**`access_review_items`**

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | PK |
| `campaign_id` | integer | FK → campaigns |
| `user_id` | integer | the license holder at snapshot time |
| `manager_id` | integer | the manager at snapshot time — frozen, doesn't change if the user gets a new manager mid-campaign |
| `license_id` | integer | |
| `license_seat_id` | integer | unique per campaign |
| `license_name_snapshot` | string | frozen copy of license name |
| `cost_per_seat_snapshot` | decimal(20,2) | `purchase_cost / seats` at snapshot time |
| `manager_status` | string(16) | `keep` / `modify` / `delete` — null until reviewed |
| `manager_comment` | text | nullable |
| `manager_completed_at` | timestamp | set when manager marks their review complete |
| `admin_executed_at` | timestamp | set when admin executes the decision |
| `admin_executed_by` | integer | FK → users |

The snapshot approach means the audit trail is immutable: even if a license is later deleted or a user's manager changes, the review record reflects what was true when the campaign was launched.

### Scope (v1)

- **Covers licenses only.** Assets, accessories, and consumables are out of scope for a first version.
- **Managers review direct reports only.** Uses the existing `manager_id` field on `users`. No skip-level.
- **Delete = real checkin.** Executing a Delete decision calls the same logic as the existing LicenseCheckin flow — no separate code path.
- **Modify = manual flag.** No automation. The admin sees a "needs manual attention" bucket and handles it via the existing UI.
- **No FMCS support yet.** Multi-company scoping would need to be added before this is merge-ready; flagging it explicitly.

---

## Implementation approach

I've built a working proof-of-concept on a fork. Here's what was done and how it integrates with Snipe-IT's existing patterns:

**New files only — core files touched minimally:**
- Models extend `SnipeModel`, use `Presentable`, and follow existing model conventions
- Controllers use `$this->authorize('admin')` for admin routes; manager routes are scoped by data (`WHERE manager_id = auth()->id()`)
- API controller returns data through a `Transformer` (same pattern as `AssetsTransformer`, etc.)
- Datatables layout defined via a `Presenter` (same pattern as other entities)
- 4 migrations, all additive — no changes to existing tables
- Translation strings in `resources/lang/en-US/admin/access-review/general.php`

**Core file changes are 3 files, ~28 lines total:**
- `routes/web.php` — route group (~12 lines)
- `routes/api.php` — single GET route (~7 lines)
- `resources/views/layouts/default.blade.php` — sidebar nav item (~9 lines)

**Tests:**
- Unit tests for the snapshot action (9 tests covering filtering, cost calculation, manager-at-snapshot-time)
- Feature tests for admin CRUD and launch/close state transitions

---

## Questions for the maintainers

Before investing further in making this PR-ready, I want to check a few things:

1. **Is this a use case you'd want in core?** It's a meaningful workflow addition but also a significant surface area. A feature flag to hide the sidebar item for installs that don't need it might make it easier to accept.

2. **FMCS** — I'd add full multi-company support before opening a formal PR. Is there a preferred pattern beyond the `companyId` query-param approach used in the selectlist endpoints?

3. **Translation strings** — I used manually-maintained PHP lang files following the existing pattern. Do you prefer I integrate with Weblate instead, or is the PHP file approach fine for a PR?

4. **Scope creep guard** — Should asset check-in be in scope for v1, or is licenses-only the right place to start? Happy to be guided here.

5. **Anything blocking from an architecture standpoint?** I want to find out early if there's a design decision I should revisit.

---

Happy to share the branch link or screenshots of the current UI if useful. Not trying to land a giant PR cold — just want to check if this direction makes sense to the team before doing the full polish pass.

---

## Screenshots

<!-- Add these before posting:
  1. Manager review screen (Day 3) — the table with Keep/Modify/Delete per license row
  2. Admin results/execute page (Day 4) — the decisions summary with Execute button
  3. Campaign list with draft/active/closed status badges
-->
