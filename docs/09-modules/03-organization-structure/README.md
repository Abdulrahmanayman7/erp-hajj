# Module: Organizational Structure (الهيكل التنظيمي)

> **Status:** Specified (Sprint 007) — **not implemented**
> **Last updated:** 2026-08-09

## Purpose

Provide the **tenant-internal** organizational hierarchy foundation: departments, sections, and units that later modules (employees, tasks, meetings, warehouses, etc.) can reference.

This module is **not** multi-tenancy. A **tenant** is the customer/company boundary. **Organization structure** is the hierarchy *inside* that tenant.

## Sprint 007 scope (specification complete; implementation pending)

| In scope | Out of scope (this sprint) |
|---|---|
| Generic hierarchical **organization units** (adjacency list) | Positions / job titles catalog (deferred → Employees) |
| Types: `department` / `section` / `unit` (fixed enum) | User ↔ unit membership (deferred → Employees primary unit) |
| Optional **unit manager** (`manager_user_id` → User) — unit accountability only, **not** employee line supervisor | Multiple managers per unit; employee direct-manager / HR reporting |
| Codes, status lifecycle, move/re-parent; prefer deactivate over hard delete | Soft deletes; graphical org-chart / drag-and-drop |
| Tree + flat list APIs, Arabic RTL management UI | Pilgrim ops, transport, finance, HR, payroll, attendance |
| `organization_units.*` permissions + audit events | Platform admin; new auth flows; notifications |

## Architectural decision

**One table `organization_units`** with `parent_id` (adjacency list), not separate `departments` / `sections` / `teams` tables. See [ADR-0004](../../10-decisions/ADR-0004-ORGANIZATION-UNITS-HIERARCHY.md).

## Module placement

| Layer | Path |
|---|---|
| Backend | `backend/app/Modules/OrganizationStructure/` |
| Frontend | `frontend/src/modules/organization/` |
| App route | `/app/organization` |
| Sidebar | الهيكل التنظيمي (permission `organization_units.view`) |
| API | `/api/v1/organization-units` |

## Personas

| Persona | Typical use |
|---|---|
| Tenant Owner | Full structure management via roles that include `organization_units.*` |
| General Manager / Department Manager | View structure; create/update units where permitted |
| Other staff | Usually view-only or no access (permission-gated) |

RBAC roles remain separate from organizational units (e.g. role `department_manager` ≠ unit "عمليات").  
Unit manager (`manager_user_id`) ≠ employee direct manager (Employees module).

## Dependencies

- **Requires:** Tenancy, Authentication, Users & Authorization (implemented).
- **Consumed later by:** Employees (primary unit + positions + employee reporting), Contracts, Meetings, Tasks, Documents, Warehouses, etc.
- **Does not require:** Employees module to ship first — units stand alone; employee counts appear when Employees exists.

## References

- [BUSINESS_RULES.md](BUSINESS_RULES.md) · [DATA_MODEL.md](DATA_MODEL.md) · [API.md](API.md) · [PERMISSIONS.md](PERMISSIONS.md) · [UI.md](UI.md)
- [ACCEPTANCE_CRITERIA.md](ACCEPTANCE_CRITERIA.md) · [TEST_PLAN.md](TEST_PLAN.md)
- [ADR-0004](../../10-decisions/ADR-0004-ORGANIZATION-UNITS-HIERARCHY.md)
- [04-employees-and-supervisors/](../04-employees-and-supervisors/) · [USERS_AND_PERSONAS.md](../../01-business/USERS_AND_PERSONAS.md)
