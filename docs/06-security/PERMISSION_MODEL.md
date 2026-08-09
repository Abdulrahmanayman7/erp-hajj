# Permission Model

> **Status:** Approved (catalog may grow with modules). Sprint 006–009 permissions are seeded as implemented; Sprint 010 meetings permissions are named in catalog docs — **not seeded until Meetings is implemented**.
>
> **Last updated:** 2026-08-09

## Purpose

Define the granular permission naming model and the master permission catalog. Module docs list their own slice in `PERMISSIONS.md` under [docs/09-modules/](../09-modules/). Binding Sprint 006 rules: [02-users-and-authorization/](../09-modules/02-users-and-authorization/).

## Model

- Permissions are named **`module.action`** (e.g. `contracts.approve`). Machine `name` is **immutable** and globally unique.
- Permissions are a **platform-global catalog** (no `tenant_id`). Roles are **tenant-owned**. Links: `role_permissions`, `user_roles`. **No direct user↔permission grants** in MVP.
- Roles are **dynamic**; personas in [USERS_AND_PERSONAS.md](../01-business/USERS_AND_PERSONAS.md) are default **templates** only.
- Multiple roles per user; effective permissions = **union** of active roles.
- Enforcement is backend-side via **Policies and Gates**. Frontend `can()` is UX only.
- **Seed only permissions for modules that exist in code.** The master catalog below is the long-term vocabulary; Sprint 006 seeds the slice in [02-users-and-authorization/PERMISSIONS.md](../09-modules/02-users-and-authorization/PERMISSIONS.md).

## Rules

- **The Tenant Owner must not bypass authorization automatically.** All users pass Policies or Gates.
- Exceptional access (e.g. Platform Super Admin into tenant data) must be explicit, permission-controlled, and **audited**.
- Sensitive data access has its own permission (e.g. `employees.view_sensitive_data`).
- Permission and role assignment changes are audited.
- Platform permissions use existing Tenancy names: **`platform_tenants.*`** — never assignable to tenant roles. See [00-tenancy/PERMISSIONS.md](../09-modules/00-tenancy/PERMISSIONS.md).

## Master Catalog (documentation target)

| Module | Permissions |
|---|---|
| Users | `users.view` `users.create` `users.update` `users.disable` `users.assign_roles` · `users.delete` (reserved; **not seeded** in Sprint 006 — disable replaces hard delete) |
| Roles | `roles.view` `roles.create` `roles.update` `roles.delete` `roles.assign_permissions` |
| Permissions (catalog read) | `permissions.view` |
| Organization units | `organization_units.view` `organization_units.create` `organization_units.update` `organization_units.delete` — covers types department/section/unit; **seeded** with Sprint 007 ([03-organization-structure/PERMISSIONS.md](../09-modules/03-organization-structure/PERMISSIONS.md)). Obsolete draft names `departments.*` must not be seeded. |
| Employees | `employees.view` `employees.create` `employees.update` `employees.deactivate` `employees.assign_supervisor` — **seeded with Sprint 008** ([04-employees-and-supervisors/PERMISSIONS.md](../09-modules/04-employees-and-supervisors/PERMISSIONS.md)). `employees.delete` / `employees.view_sensitive_data` reserved (**not seeded**). |
| Positions | `positions.view` `positions.create` `positions.update` `positions.delete` — **seeded with Sprint 008** ([04-employees-and-supervisors/PERMISSIONS.md](../09-modules/04-employees-and-supervisors/PERMISSIONS.md)). |
| Contracts | `contracts.view` `contracts.create` `contracts.update` `contracts.review` `contracts.approve` `contracts.sign` `contracts.execute` `contracts.close` `contracts.renew` `contracts.cancel` `contracts.delete` — **seeded with Sprint 009** ([05-contracts/PERMISSIONS.md](../09-modules/05-contracts/PERMISSIONS.md)) |
| Meetings | `meetings.view` `meetings.create` `meetings.update` `meetings.cancel` `meetings.manage_attendees` `meetings.manage_minutes` — specified Sprint 010 ([06-meetings/PERMISSIONS.md](../09-modules/06-meetings/PERMISSIONS.md)); seeded at implementation |
| Decisions | `decisions.view` `decisions.create` `decisions.update` `decisions.approve` `decisions.close` `decisions.delete` |
| Tasks | `tasks.view` `tasks.create` `tasks.update` `tasks.assign` `tasks.change_status` `tasks.complete` `tasks.delete` |
| Documents | `documents.view` `documents.upload` `documents.download` `documents.update` `documents.delete` `documents.manage_categories` |
| Warehouses | `warehouses.view` `warehouses.create` `warehouses.update` `warehouses.delete` |
| Inventory | `inventory.view` `inventory.add` `inventory.issue` `inventory.transfer` `inventory.return` `inventory.adjust` |
| Assets | `assets.view` `assets.create` `assets.update` `assets.delete` `assets.assign` `assets.return` `assets.retire` |
| Audit logs | `audit_logs.view` `audit_logs.export` |
| Dashboard | `dashboard.view` |
| Tenant settings | `tenant_settings.view` `tenant_settings.update` |
| Platform (tenants) | `platform_tenants.view` `platform_tenants.create` `platform_tenants.update` `platform_tenants.activate` `platform_tenants.suspend` `platform_tenants.archive` `platform_tenants.access_data` |

## Default role templates

Seeded system role codes and Sprint 006 permission mapping: [02-users-and-authorization/PERMISSIONS.md](../09-modules/02-users-and-authorization/PERMISSIONS.md) and [BUSINESS_RULES.md](../09-modules/02-users-and-authorization/BUSINESS_RULES.md).

## TBD

- Custody-specific permissions beyond `assets.assign`/`assets.return`: TBD.
- Notification-related permissions: TBD.
