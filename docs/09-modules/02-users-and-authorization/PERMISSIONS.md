# Users and Authorization — Permissions

> **Status:** Approved for Sprint 006 (seed only what is listed under “Sprint 006 seed set”)
> **Last updated:** 2026-08-08

## Rules

- Backend Policies enforce every capability; frontend `can()` is UX only.
- Tenant Owner does **not** bypass authorization.
- Role/permission assignment changes are audited.
- Self-escalation guards: [BUSINESS_RULES.md](BUSINESS_RULES.md) §8.
- Platform permissions (`platform_tenants.*`) are never assignable to tenant roles — see [00-tenancy/PERMISSIONS.md](../00-tenancy/PERMISSIONS.md).

## Sprint 006 seed set (implemented modules only)

### Users

| Permission | Purpose |
|---|---|
| `users.view` | List/view tenant users |
| `users.create` | Create users |
| `users.update` | Update name/email |
| `users.disable` | Enable/disable users |
| `users.assign_roles` | Replace a user’s role set |

`users.delete` — **not seeded** in Sprint 006 (hard delete omitted).

### Roles & catalog

| Permission | Purpose |
|---|---|
| `roles.view` | List/view roles |
| `roles.create` | Create custom roles |
| `roles.update` | Update role metadata |
| `roles.delete` | Hard-delete unused custom roles (safe conditions only) |
| `roles.assign_permissions` | Replace a role’s permission set (subset rule) |
| `permissions.view` | Read global permission catalog (tenant UI matrix) |

### Already-implemented adjacent modules

| Permission | Purpose |
|---|---|
| `dashboard.view` | Access authenticated home / future dashboard |
| `tenant_settings.view` | View tenant settings (API may land with settings sprint) |
| `tenant_settings.update` | Update tenant settings |

## Default template → permission mapping (Sprint 006)

Permissions below are **only** from the Sprint 006 seed set. Future module permissions are added when those modules ship; templates are updated by provisioning/sync, not by inventing unused capabilities.

| Role `code` | Permissions |
|---|---|
| `tenant_owner` | **All** Sprint 006 seed permissions |
| `general_manager` | `dashboard.view`, `users.view`, `roles.view`, `permissions.view`, `tenant_settings.view` |
| `department_manager` | `dashboard.view`, `users.view` |
| `supervisor` | `dashboard.view` |
| `employee` | `dashboard.view` |
| `auditor` | `dashboard.view`, `users.view`, `roles.view`, `permissions.view` |
| `read_only` | `dashboard.view` |

Custom roles: any subset the creator is allowed to grant (subset rule).

## Platform permissions (not tenant-assignable)

Authoritative list: [00-tenancy/PERMISSIONS.md](../00-tenancy/PERMISSIONS.md) — `platform_tenants.view|create|update|activate|suspend|archive|access_data`.

## Master catalog growth

Long-term names for future modules remain documented in [PERMISSION_MODEL.md](../../06-security/PERMISSION_MODEL.md). **Do not seed** them until code exists.
