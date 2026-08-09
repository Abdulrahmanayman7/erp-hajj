# Organizational Structure — API

> **Status:** Specified (Sprint 007) — **no endpoints yet**
> **Last updated:** 2026-08-09

All endpoints under `/api/v1`, authenticated Sanctum session, tenant middleware chain as existing modules. Envelope per [API_STANDARDS.md](../../04-api/API_STANDARDS.md).

Permissions use prefix **`organization_units.*`**. Policies are authoritative. (Obsolete draft name `departments.*` must not be used.)

## Resource shape (`OrganizationUnitResource`)

```json
{
  "id": 1,
  "name": "إدارة العمليات",
  "code": "OPS",
  "type": "department",
  "status": "active",
  "parent_id": null,
  "sort_order": 0,
  "manager": {
    "id": 12,
    "name": "…",
    "email": "…"
  },
  "children_count": 3,
  "depth": 0,
  "created_at": "…",
  "updated_at": "…"
}
```

- `manager` is the **unit responsible manager** (`manager_user_id`); may be `null`. Not an employee line supervisor.
- `depth` is derived (root = `0`); max allowed depth is the MVP application safety limit (8) — see [BUSINESS_RULES.md](BUSINESS_RULES.md).
- Tree nodes may embed `children: OrganizationUnitResource[]` when `view=tree`.
- Do **not** embed full user lists per unit.
- `employee_count` omitted until Employees module exists.

---

## Endpoints

### 1. List / tree

| | |
|---|---|
| **Method / route** | `GET /api/v1/organization-units` |
| **Permission** | `organization_units.view` |
| **Query** | `view=tree` (default) \| `view=flat` · `status=active\|inactive\|all` (default `all`) · `search` (name/code, flat or filter tree) · `parent_id` (flat children only; optional) |

**Tree strategy:** One endpoint. Default returns nested tree for the tenant (roots → children). Flat returns a list ordered by `sort_order` then `name`, each with `parent_id` / `depth`, suitable for `AppSelect` parent pickers.

**Response (tree):** `{ "data": [ /* roots with nested children */ ] }`  
**Response (flat):** `{ "data": [ … ], "meta": { pagination if needed } }` — for typical sizes, flat may return all without pagination; if > page size threshold, paginate flat only.

### 2. Show

| | |
|---|---|
| **Method / route** | `GET /api/v1/organization-units/{organization_unit}` |
| **Permission** | `organization_units.view` |
| **Tenant** | Missing / other tenant → **404** |

### 3. Create

| | |
|---|---|
| **Method / route** | `POST /api/v1/organization-units` |
| **Permission** | `organization_units.create` |
| **Body** | `name` (required), `code` (required), `type` (required enum), `parent_id` (nullable), `manager_user_id` (nullable), `sort_order` (optional), `status` (optional; default `active`) |

**Validation:** TenantExists parent & manager; parent active if set; resulting depth ≤ MVP max depth (centralized constant); code TenantUnique; sibling name unique; manager user `is_active`.

**Audit:** `ORGANIZATION_UNIT_CREATED` (+ `ORGANIZATION_MANAGER_ASSIGNED` if unit manager set).

### 4. Update

| | |
|---|---|
| **Method / route** | `PATCH /api/v1/organization-units/{organization_unit}` |
| **Permission** | `organization_units.update` |
| **Body** | `name`, `type`, `manager_user_id` (nullable to clear), `sort_order` — **not** `code`; **not** `parent_id` (use move); **not** `status` (use activate/deactivate) |

**Audit:** `ORGANIZATION_UNIT_UPDATED`; if unit manager changes → `ORGANIZATION_MANAGER_ASSIGNED`.

### 5. Move / re-parent

| | |
|---|---|
| **Method / route** | `POST /api/v1/organization-units/{organization_unit}/move` |
| **Permission** | `organization_units.update` |
| **Body** | `parent_id` (nullable for promote to root) |

**Domain errors:** circular reference; parent inactive; parent other-tenant/missing (as validation/`404` on unit); subtree depth would exceed MVP max → `ORGANIZATION_UNIT_DEPTH_EXCEEDED`.

**Audit:** `ORGANIZATION_UNIT_MOVED` (old/new `parent_id`).

### 6. Activate

| | |
|---|---|
| **Method / route** | `POST /api/v1/organization-units/{organization_unit}/activate` |
| **Permission** | `organization_units.update` |
| **Audit** | `ORGANIZATION_UNIT_ACTIVATED` |

### 7. Deactivate

| | |
|---|---|
| **Method / route** | `POST /api/v1/organization-units/{organization_unit}/deactivate` |
| **Permission** | `organization_units.update` |
| **Notes** | Preferred retirement path. Children not auto-deactivated; unit cannot be used as new parent while inactive |

**Audit:** `ORGANIZATION_UNIT_DEACTIVATED`

### 8. Delete

| | |
|---|---|
| **Method / route** | `DELETE /api/v1/organization-units/{organization_unit}` |
| **Permission** | `organization_units.delete` |
| **Policy** | Prefer deactivate. Hard delete only when [delete policy](BUSINESS_RULES.md#hard-delete-policy-mvp) is fully satisfied |
| **Blocked when** | Has children → `ORGANIZATION_UNIT_HAS_CHILDREN`; has employees → `ORGANIZATION_UNIT_HAS_EMPLOYEES`; other business/history references → `ORGANIZATION_UNIT_IN_USE` (when consumers exist) |

**Audit:** `ORGANIZATION_UNIT_DELETED` (only on successful hard delete)

---

## Explicitly not in Sprint 007

- `/positions/*`
- User assignment endpoints
- Dedicated `/organization-units/tree` (use `view=tree` on list)
- Bulk import / reorder drag API

---

## Stable domain error codes

| Code | HTTP (typical) | Meaning |
|---|---|---|
| `ORGANIZATION_UNIT_NOT_FOUND` | 404 | Prefer generic not-found for route model; may use this in domain layer |
| `ORGANIZATION_UNIT_PARENT_INVALID` | 422 | Parent not usable |
| `ORGANIZATION_UNIT_PARENT_INACTIVE` | 422 | Parent inactive |
| `ORGANIZATION_UNIT_CIRCULAR_REFERENCE` | 422 | Move/create would cycle |
| `ORGANIZATION_UNIT_DEPTH_EXCEEDED` | 422 | Create/move would exceed MVP max depth (application safety limit) |
| `ORGANIZATION_UNIT_HAS_CHILDREN` | 422 | Delete blocked — has child units |
| `ORGANIZATION_UNIT_HAS_EMPLOYEES` | 422 | Delete blocked — employees reference (when Employees ships) |
| `ORGANIZATION_UNIT_IN_USE` | 422 | Delete blocked — other business/history references requiring preservation |
| `ORGANIZATION_UNIT_INACTIVE` | 422 | Operation requires active unit (where applicable) |
| `ORGANIZATION_UNIT_CODE_TAKEN` | 422 | Code unique violation |
| `ORGANIZATION_UNIT_NAME_TAKEN` | 422 | Sibling name conflict |
| `ORGANIZATION_UNIT_CODE_IMMUTABLE` | 422 | Attempt to change code |
| `ORGANIZATION_MANAGER_INVALID` | 422 | Unit manager missing / wrong tenant / disabled |
| `AUTHORIZATION_DENIED` | 403 | Policy fail (existing) |

Cross-tenant: **404**, never a domain “exists in other tenant” code.

Validation failures use the standard `422` envelope (`VALIDATION_ERROR` / field errors) where appropriate; domain codes above for business rule failures per API standards pattern used by Auth/RBAC.
