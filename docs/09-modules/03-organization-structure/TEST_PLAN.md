# Organizational Structure — Test Plan

> **Status:** **Implemented** (Sprint 007) — Pest feature suite + Vitest API/utils coverage
> **Last updated:** 2026-08-09

Tooling: **Pest** (backend), **Vitest** (frontend). Failing cross-tenant / policy tests block merge.

---

## Backend (Pest)

### A. CRUD & lifecycle

| # | Case |
|---|---|
| B01 | Create root unit success (depth 0) |
| B02 | Create child under active parent success |
| B03 | Create under inactive parent → 422 `ORGANIZATION_UNIT_PARENT_INACTIVE` |
| B04 | Validation 422 (missing name/code/type) |
| B05 | Duplicate code same tenant → 422 |
| B06 | Duplicate sibling name → 422 |
| B07 | Update name/type/sort/unit manager success |
| B08 | Attempt change code → rejected |
| B09 | Activate / deactivate success + audit |
| B10 | Delete leaf with no references success (`organization_units.delete`) |
| B11 | Delete with children → 422 `ORGANIZATION_UNIT_HAS_CHILDREN` |
| B12 | Delete without `organization_units.delete` → 403 |
| B13 | Prefer-deactivate path: inactive unit remains; not hard-deleted |

### B. Hierarchy & depth

| # | Case |
|---|---|
| B20 | Move to new parent success + `ORGANIZATION_UNIT_MOVED` |
| B21 | Move to own descendant → circular `ORGANIZATION_UNIT_CIRCULAR_REFERENCE` |
| B22 | Move to self → rejected |
| B23 | Promote to root (`parent_id` null) → depth 0 |
| B24 | Create that would yield depth > max → `ORGANIZATION_UNIT_DEPTH_EXCEEDED` |
| B24b | Move subtree that would yield any node depth > max → `ORGANIZATION_UNIT_DEPTH_EXCEEDED` |
| B24c | Depth limit read from centralized constant/config (not ad-hoc literals in multiple places) — assert via one source in tests where practical |
| B25 | Tree endpoint returns nested structure; flat returns parent_id + depth |
| B26 | No N+1 on tree for fixture of N units (assert query count budget where practical) |

### C. Unit manager (not employee supervisor)

| # | Case |
|---|---|
| B30 | Assign active same-tenant unit manager |
| B31 | Assign disabled user → 422 |
| B32 | Assign foreign-tenant user id → validation fail / no write |
| B33 | Clear unit manager |
| B34 | Unit manager disable after assign — unit still readable; manager id retained |
| B35 | Assigning unit manager does not change effective RBAC permissions of that user |

### D. Tenant isolation (mandatory)

| # | Case |
|---|---|
| B40 | Tenant A cannot GET tenant B unit → **404** |
| B41 | Tenant A cannot PATCH/DELETE/move tenant B unit → **404**, no write |
| B42 | Tenant A cannot set `parent_id` to tenant B unit |
| B43 | Tenant A cannot set `manager_user_id` to tenant B user |
| B44 | `tenant_id` in payload ignored / rejected; row uses context tenant |

### E. RBAC (`organization_units.*`)

| # | Case |
|---|---|
| B50 | Unauthenticated → 401 |
| B51 | Authenticated without permission → 403 |
| B52 | Matrix: `organization_units.view|create|update|delete` each endpoint |

### F. Audit

| # | Case |
|---|---|
| B60 | Create/update/move/activate/deactivate/delete/unit-manager change produce expected events |
| B61 | Audit values contain no passwords/tokens |

### G. Inactive behavior

| # | Case |
|---|---|
| B70 | Inactive unit excluded from parent candidates |
| B71 | Inactive unit still visible with status filter |

### H. Future delete-policy hooks (when consumers exist)

| # | Case |
|---|---|
| B80 | Delete with employee FK → `ORGANIZATION_UNIT_HAS_EMPLOYEES` (Employees sprint) |
| B81 | Delete with other business/history refs → `ORGANIZATION_UNIT_IN_USE` (as consumers ship) |

---

## Frontend (Vitest)

| # | Case |
|---|---|
| F01 | Tree renders nested nodes (fixture) |
| F02 | Search filters visible nodes |
| F03 | Empty / loading / error states |
| F04 | Create button hidden without `organization_units.create` |
| F05 | Edit/delete/move actions gated by `organization_units.*` |
| F06 | Drawer validation (required fields, code format) |
| F07 | API 422 domain errors surfaced in UI (incl. depth exceeded) |
| F08 | Deactivate / move / delete open `AppConfirmDialog` (not `window.confirm`) |
| F09 | Details pane updates on selection; unit manager labeled as unit responsibility (not employee supervisor) |
| F10 | RTL smoke: tree layout direction classes / logical properties where asserted by project patterns |

---

## Explicitly out of this matrix until later modules

- Employee assignment / `ORGANIZATION_UNIT_HAS_EMPLOYEES` enforcement until Employees ships
- Positions CRUD
- `/auth/me` org payload
- E2E Playwright (proposed future)
