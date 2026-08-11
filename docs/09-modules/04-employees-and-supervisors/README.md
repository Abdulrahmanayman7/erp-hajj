# Module: Employees and Supervisors (الموظفون والمشرفون)

> **Status:** Implemented (Sprint 008)
> **Last updated:** 2026-08-11

## Purpose

Manage each tenant’s **personnel records**: employees as the primary staff entity, with an optional login **User** link, a primary **organization unit**, an optional **position**, and an optional **direct supervisor** (employee self-reference).

**Supervisor** in the operational sense means an employee who appears as another employee’s `supervisor_id` and/or holds the RBAC role `supervisor`. Sprint 008 does **not** duplicate identity records and does **not** build HR supervisor dossiers (experience, seasons, training).

## Domain separation (non-negotiable)

| Concept | Meaning | Owned by |
|---|---|---|
| **User** | Login / auth account | Users & Authorization |
| **Employee** | Business personnel record | This module |
| **OrganizationUnit.manager_user_id** | User responsible for a **unit** | Organization Structure |
| **Employee.supervisor_id** | Employee’s direct **reporting** supervisor | This module |
| **Warehouse.responsible_employee_id** | Oversight metadata only (not RBAC) | Warehouses & Inventory (Sprint 014 implemented) |
| **AssetCustody.employee_id** | Custody receiver (not RBAC) | Assets & Custodies (Sprint 015 **implemented**; ADR-0012) |
| **RBAC role** | Authorization capabilities | Users & Authorization |

## Sprint 008 scope

| In scope | Out of scope |
|---|---|
| `employees` + minimal `positions` catalog | Payroll, attendance, leave, performance |
| Optional `user_id` link (1:1 when set) | Creating Users/credentials from Employees UI |
| Required primary `organization_unit_id` | Multiple org memberships |
| `supervisor_id` + cycle prevention | Backup supervisor; org-hierarchy-restricted supervisors |
| Server-generated tenant-unique employee numbers | Soft deletes; hard-delete API |
| Status `active` / `inactive` | National ID, passport, salary, insurance |
| Permissions, Policies, API, Arabic RTL UI | Contracts, tasks, custodies, documents attach flows |
| Audit events + Pest/Vitest matrices | Notifications; row-level org scoping |

## Module placement

| Layer | Path |
|---|---|
| Backend | `backend/app/Modules/Employees/` |
| Frontend | `frontend/src/modules/employees/` |
| App route | `/app/employees` |
| Sidebar | الموظفون (`employees.view`) |
| API | `/api/v1/employees`, `/api/v1/positions` |

## Personas

| Persona | Typical use |
|---|---|
| Tenant Owner / General Manager | Full employee & position administration |
| Department Manager | View/update employees where permitted |
| Supervisor (RBAC) | View team-related data where permitted (no row-scope in Sprint 008) |
| Employee (RBAC) | Usually no admin access to this module |

## Dependencies

- **Requires:** Tenancy, Auth, Users/RBAC, Organization Structure (implemented).
- **Integrates:** `organization_units` (RESTRICT FK); optional `users` (SET NULL).
- **Later consumers:** Contracts, Tasks, Custodies / Assets (Sprint 015 — **implemented**; ADR-0012), Documents (Sprint 013 — implemented; morph alias `employee`; ADR-0010).

## Architectural note

Minimal tenant-owned **`positions`** table (not a free-text-only job title) — see [BUSINESS_RULES.md](BUSINESS_RULES.md) and [ADR-0005](../../10-decisions/ADR-0005-EMPLOYEE-POSITIONS-CATALOG.md).

## References

- [BUSINESS_RULES.md](BUSINESS_RULES.md) · [DATA_MODEL.md](DATA_MODEL.md) · [API.md](API.md) · [PERMISSIONS.md](PERMISSIONS.md) · [UI.md](UI.md)
- [ACCEPTANCE_CRITERIA.md](ACCEPTANCE_CRITERIA.md) · [TEST_PLAN.md](TEST_PLAN.md)
- [03-organization-structure/](../03-organization-structure/) · [02-users-and-authorization/](../02-users-and-authorization/)
