# Employees and Supervisors — Permissions

> **Status:** Approved for Sprint 008
> **Last updated:** 2026-08-09

## Seeded with Sprint 008 implementation

### Employees

| Permission | Purpose |
|---|---|
| `employees.view` | List/show employees |
| `employees.create` | Create employees |
| `employees.update` | Update profile fields; activate; link/unlink user; change organization/position |
| `employees.deactivate` | Deactivate employees |
| `employees.assign_supervisor` | Set/clear/change `supervisor_id` |

### Positions

| Permission | Purpose |
|---|---|
| `positions.view` | List/show positions |
| `positions.create` | Create positions |
| `positions.update` | Update / activate position |
| `positions.delete` | Delete unused positions (or deactivate via update — see API) |

## Not seeded in Sprint 008

| Permission | Reason |
|---|---|
| `employees.delete` | No hard-delete API; reserved/obsolete vs deactivate |
| `employees.view_sensitive_data` | No national-ID (or similar) fields in MVP schema |
| `employees.classify_supervisor` | No separate dossier classification API |

**Historical:** Master catalog previously listed `employees.delete` and `employees.view_sensitive_data`. Sprint 008 seeds the table above; do not seed unused names.

## Default role template guidance

| System role | Suggested grants |
|---|---|
| Tenant Owner | All `employees.*` + all `positions.*` (via `allNames()`) |
| General Manager | All employees + positions permissions |
| Department Manager | `employees.view`, `employees.update`, `employees.assign_supervisor`, `positions.view` |
| Supervisor | `employees.view` (safe default; no row-scope) |
| Employee / read_only | None (or `employees.view` only if product wants self-service later — **default none**) |
| Auditor | `employees.view`, `positions.view` |

**TBD at seed PR:** Exact General Manager / Department Manager maps — do not invent row-level “only my unit” scope.

## Policy authority

- `EmployeePolicy`, `PositionPolicy` — permission checks only; never role-name checks.
- Frontend `can()` is UX only.
