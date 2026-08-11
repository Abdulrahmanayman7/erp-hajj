# Permission Model

> **Status:** Approved (catalog may grow with modules)
> **Last updated:** 2026-08-06

## Purpose

Define the granular permission naming model and the master permission catalog. Module docs list their own slice in `PERMISSIONS.md` under [docs/09-modules/](../09-modules/).

## Model

- Permissions are named **`module.action`** (e.g. `contracts.approve`).
- Roles are **dynamic**: a role is a named set of permissions; personas in [USERS_AND_PERSONAS.md](../01-business/USERS_AND_PERSONAS.md) are default templates only.
- Enforcement is backend-side via **Policies and Gates**. Frontend permission checks are UX only.

## Rules

- **The Tenant Owner must not bypass authorization automatically.** All users pass Policies or Gates.
- Exceptional access (e.g. Platform Super Admin into tenant data) must be explicit, permission-controlled, and **audited**.
- Sensitive data access has its own permission (e.g. `employees.view_sensitive_data`).
- Permission changes are audited.

## Master Catalog

| Module | Permissions |
|---|---|
| Users | `users.view` `users.create` `users.update` `users.disable` `users.delete` |
| Roles | `roles.view` `roles.create` `roles.update` `roles.delete` `roles.assign_permissions` |
| Departments | `departments.view` `departments.create` `departments.update` `departments.delete` |
| Employees | `employees.view` `employees.create` `employees.update` `employees.delete` `employees.view_sensitive_data` |
| Contracts | `contracts.view` `contracts.create` `contracts.update` `contracts.review` `contracts.approve` `contracts.sign` `contracts.execute` `contracts.close` `contracts.renew` `contracts.delete` |
| Meetings | `meetings.view` `meetings.create` `meetings.update` `meetings.cancel` `meetings.manage_attendees` `meetings.manage_minutes` |
| Decisions | `decisions.view` `decisions.create` `decisions.update` `decisions.approve` `decisions.close` `decisions.delete` |
| Tasks | `tasks.view` `tasks.create` `tasks.update` `tasks.assign` `tasks.change_status` `tasks.complete` `tasks.delete` |
| Documents | `documents.view` `documents.upload` `documents.download` `documents.update` `documents.delete` `documents.manage_categories` |
| Warehouses | `warehouses.view` `warehouses.create` `warehouses.update` `warehouses.delete` |
| Inventory | `inventory.view` `inventory.add` `inventory.issue` `inventory.transfer` `inventory.return` `inventory.adjust` |
| Assets | `assets.view` `assets.create` `assets.update` `assets.delete` `assets.assign` `assets.return` `assets.retire` |
| Audit logs | `audit_logs.view` `audit_logs.export` |
| Dashboard | `dashboard.view` |
| Tenant settings | `tenant_settings.view` `tenant_settings.update` |

## TBD

- Custody-specific permissions beyond `assets.assign`/`assets.return` (e.g. custody correction permission): TBD.
- Notification-related permissions: TBD.
- Platform-level (Super Admin) permission names for tenant lifecycle management: TBD.
- Default role templates → permission mapping: TBD.
