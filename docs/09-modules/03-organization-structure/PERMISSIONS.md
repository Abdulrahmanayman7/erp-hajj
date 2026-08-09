# Organizational Structure — Permissions

> **Status:** Approved for Sprint 007 — **seeded with implementation**
> **Last updated:** 2026-08-09

## Seeded with Sprint 007 implementation

| Permission | Purpose |
|---|---|
| `organization_units.view` | View organization units (list/tree/show) |
| `organization_units.create` | Create units |
| `organization_units.update` | Update fields, move, activate, deactivate, assign/clear unit manager |
| `organization_units.delete` | Hard-delete units only when the delete policy allows |

The `organization_units.*` prefix matches the domain entity (`OrganizationUnit`), table (`organization_units`), and API (`/organization-units`). It covers all unit **types** (`department` / `section` / `unit`).

**Historical note:** Early drafts used `departments.*`. That temporary namespace is **superseded** and must not be seeded.

## Not seeded in Sprint 007

| Permission | Reason |
|---|---|
| `organization_units.assign_users` | User membership deferred to Employees |
| `organization_units.assign_manager` | Covered by `organization_units.update` |
| `organization_units.activate` / `deactivate` | Covered by `organization_units.update` (same pattern as roles) |
| `positions.*` | Positions deferred |

## Policy authority

- `OrganizationUnitPolicy` gates every endpoint.
- Frontend `can('organization_units.*')` is UX only.
- Tenant Owner / system roles receive these permissions only via **role templates** / grants — never by persona bypass.

## Default role template guidance (implementation)

Align with Sprint 006 system role seeds when wiring:

| System role (typical) | Suggested grants |
|---|---|
| Tenant Owner | All `organization_units.*` |
| General Manager | All `organization_units.*` (or view+create+update without delete — **confirm at seed PR**; recommendation: full set while UI prefers deactivate over delete) |
| Department Manager | `organization_units.view` (+ update if product wants managers to edit their subtree — **TBD at seed**; safe default: **view only** until Employees/scope rules exist) |
| Others | None unless assigned |

**TBD (seed PR):** Exact permission map for `department_manager` / `supervisor` templates — must not invent subtree-scoped authorization in Sprint 007 (no “only my unit” data scope yet). MVP remains **permission-wide within tenant**, not row-level org scope.
