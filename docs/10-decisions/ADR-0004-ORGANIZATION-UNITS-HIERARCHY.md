# ADR-0004: Generic hierarchical organization units

> **Status:** Accepted  
> **Date:** 2026-08-09  
> **Sprint:** 007 — Organization Structure (specification)

## Context

ERP Hajj needs a tenant-internal organizational hierarchy (departments, sections, units) so later modules can attach employees, tasks, meetings, and warehouses to structural nodes.

Alternatives considered:

1. **Separate tables** per level (`departments`, `sections`, `units` / teams) with typed FKs between them.
2. **Single hierarchical table** (`organization_units`) with adjacency-list `parent_id` and a descriptive `type` enum.
3. **Nested set / closure table / package** for hierarchy queries.

Glossary and prior module drafts already describe Department / Section / Unit as **types** of organizational unit, not necessarily separate aggregates. MVP must stay minimal and reusable without inventing deep HR structure.

## Decision

Use **one tenant-owned table `organization_units`** with:

- Adjacency list (`parent_id`, nullable for roots; root depth = 0)
- Fixed type enum: `department` | `section` | `unit` (descriptive; not a separate type catalog)
- Tenant-scoped immutable `code`
- Status `active` | `inactive` (prefer deactivate; hard delete only under strict policy; no soft deletes)
- Optional single `manager_user_id` → `users` meaning **unit responsible manager** only (not employee line/HR supervisor)
- MVP **application-level** maximum depth of **8** (centralized constant/config — not a database architectural limit)
- Tree assembled in application memory from a tenant-scoped query set

Do **not** introduce nested-set packages or closure tables in Sprint 007.

Permission names are **`organization_units.view|create|update|delete`** — aligned with the domain entity and `/api/v1/organization-units`. Early draft `departments.*` names are obsolete and must not be seeded.

Positions / job titles and user↔unit membership are **deferred** to the Employees track.

## Consequences

### Positive

- One CRUD/policy surface; simple move/re-parent and tree API
- Flexible depth without schema churn when tenants use shallow or deep trees
- Aligns with API resource `/organization-units` and permission namespace
- Clear separation from multi-tenancy, RBAC roles, and employee reporting relationships

### Negative / trade-offs

- Type does not enforce “section must be under department” unless rules are added later
- Deep trees rely on app-side cycle and depth checks (acceptable at planned volumes ≤ ~500 units/tenant)
- Hard-delete eligibility grows stricter as consuming modules add FKs/history

### Rejected

- Separate department/section/unit tables: more joins, harder moves across “levels”, duplicated policies
- Premature nested-set package: complexity unjustified for expected sizes
- Keeping `departments.*` permissions despite domain rename: naming mismatch with entity/API

## References

- [03-organization-structure/](../09-modules/03-organization-structure/)
- [DATABASE_PRINCIPLES.md](../03-database/DATABASE_PRINCIPLES.md)
- [PERMISSION_MODEL.md](../06-security/PERMISSION_MODEL.md)
