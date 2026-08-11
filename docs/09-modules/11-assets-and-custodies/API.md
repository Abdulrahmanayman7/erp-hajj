# Assets and Custodies — API

> **Status:** Implemented (Sprint 015)
> **Last updated:** 2026-08-11
> Base: `/api/v1` · Auth: Sanctum SPA · Envelope: [API_STANDARDS.md](../../04-api/API_STANDARDS.md)

All routes: authenticated + tenant-active. Authorization via Policies. Cross-tenant → **404**.
**No** generic `PATCH status`. **No** PATCH/DELETE on custodies or status transitions after completion rules.

---

## Categories

| Method | Path | Permission |
|---|---|---|
| GET | `/asset-categories` | `assets.view` |
| POST | `/asset-categories` | `assets.update` |
| PATCH | `/asset-categories/{category}` | `assets.update` |
| DELETE | `/asset-categories/{category}` | `assets.update` (unused only) |

---

## Assets

| Method | Path | Permission | Notes |
|---|---|---|---|
| GET | `/assets` | `assets.view` | Paginated |
| POST | `/assets` | `assets.create` | Creates `available` |
| GET | `/assets/{asset}` | `assets.view` **or** holder self-view | |
| PATCH | `/assets/{asset}` | `assets.update` | Metadata only; not number/status/custody cache |
| DELETE | `/assets/{asset}` | `assets.delete` | Unused only |
| POST | `/assets/{asset}/maintenance` | `assets.update` | `available` → `maintenance` |
| POST | `/assets/{asset}/restore` | `assets.update` | `maintenance`\|`damaged` → `available` |
| POST | `/assets/{asset}/retire` | `assets.retire` | Reason required |
| POST | `/assets/{asset}/declare-lost` | `assets.retire` | Reason required; closes active custody if any |
| POST | `/assets/{asset}/assign` | `assets.assign` | Body: employee_id, notes, condition, expected_return_at |
| POST | `/assets/{asset}/return` | `assets.return` | Body: next_status, condition, notes |

### Assign body

```json
{
  "employee_id": 1,
  "expected_return_at": null,
  "condition_at_assignment": "good",
  "assignment_notes": "..."
}
```

### Return body

```json
{
  "next_status": "available",
  "condition_at_return": "fair",
  "return_notes": "..."
}
```

`next_status` ∈ `available` \| `maintenance` \| `damaged` \| `retired`.

---

## Custodies

| Method | Path | Permission | Notes |
|---|---|---|---|
| GET | `/asset-custodies` | `assets.view` | Paginated history/list |
| GET | `/asset-custodies/{custody}` | `assets.view` **or** holder self-view | |
| GET | `/my-custodies` | `assets.view` + Employee link | Scoped to actor’s Employee |

**No** POST create except via `/assets/{asset}/assign`.
**No** PATCH/DELETE.

---

## List filters / sorting

### Assets

`search` (number/name/serial/barcode), `status`, `category_id`, `warehouse_id`, `organization_unit_id`, `employee_id` (active custody assignee), `serial_number`, `acquisition_from`/`acquisition_to`.
Default sort: `asset_number` ASC.

### Custodies

`asset_id`, `employee_id`, `status`, `assigned_from`/`assigned_to`, `overdue` (active && expected_return_at < now).
Default sort: `assigned_at` DESC.

---

## Error codes

| Code | When |
|---|---|
| `ASSET_NOT_FOUND` | Prefer generic 404 |
| `ASSET_IN_USE` | Delete blocked / operation blocked |
| `ASSET_NOT_AVAILABLE` | Assign when not `available` |
| `ASSET_ALREADY_ASSIGNED` | Active custody exists |
| `ASSET_NOT_ASSIGNED` | Return without active custody |
| `ASSET_INVALID_STATUS_TRANSITION` | Illegal lifecycle action |
| `ASSET_SERIAL_ALREADY_EXISTS` | Duplicate serial |
| `ASSET_BARCODE_ALREADY_EXISTS` | Duplicate barcode |
| `ASSET_EMPLOYEE_INVALID` | Missing/inactive/foreign employee |
| `ASSET_WAREHOUSE_INVALID` | Missing/inactive/foreign warehouse on assign metadata |
| `ASSET_ORGANIZATION_INVALID` | Missing/inactive/foreign org unit |
| `ASSET_CATEGORY_INVALID` | Inactive/foreign category |
| `ASSET_CATEGORY_IN_USE` | Category delete blocked |
| `ASSET_CUSTODY_CONFLICT` | Concurrent assign race |
| `ASSET_RETIRE_REASON_REQUIRED` | Empty retire/lost reason |
| `ASSET_IMMUTABLE` | Mutate returned custody / number / history |
| `CUSTODY_NOT_FOUND` | Prefer 404 |

Cross-tenant → **404**.

---

## Audit events

| Event | When |
|---|---|
| `ASSET_CREATED` / `ASSET_UPDATED` / `ASSET_DELETED` | Registry |
| `ASSET_CATEGORY_CREATED` / `_UPDATED` / `_DELETED` | Categories |
| `ASSET_SENT_TO_MAINTENANCE` / `ASSET_RESTORED` | Maintenance cycle |
| `ASSET_RETIRED` / `ASSET_DECLARED_LOST` | Terminal-ish |
| `ASSET_ASSIGNED` | Custody assign (includes custody id/number) |
| `ASSET_RETURNED` | Custody return + next status |

Do **not** emit a redundant generic `ASSET_STATUS_CHANGED` when a more specific event exists; status transition table remains authoritative history.

Payload concepts: ids/numbers, employee summary, warehouse/org, from/to status, reason, custody_number, actor, tenant, correlation ID.

---

## Resource notes

- Asset show includes compact `current_custody` (id, number, employee, assigned_at, expected_return_at) when `in_use`.
- Current employee is **only** via `current_custody.employee` (derived/cache), never a forged top-level editable field.
- Transition/custody histories on details endpoints or nested collections — paginate if large.
- Do not expose internal lock tokens.
