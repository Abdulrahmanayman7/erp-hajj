# Season and Pilgrim Foundation — Permissions

> **Status:** Seeded in `PermissionCatalog.php` (Phase 2 foundation sprint).
> **Last updated:** 2026-10-05

Binding: [PERMISSION_MODEL.md](../../06-security/PERMISSION_MODEL.md). Names are `module.action`. Tenant Owner does not bypass Policies. Every policy also requires the record to belong to the actor's tenant.

## Catalog

| Namespace | Permissions |
|---|---|
| `seasons` | `view`, `create`, `update`, `delete`, `activate`, `close`, `reopen` |
| `pilgrims` | `view`, `create`, `update`, `delete`, `manage_identity_documents` |
| `season_pilgrims` | `view`, `register`, `activate`, `complete`, `cancel`, `delete`, `refresh_snapshot`, `correct_snapshot`, `change_program` |
| `season_programs` | `view`, `manage`, `delete` |
| `season_family_groups` | `view`, `manage`, `assign_members` |
| `season_pilgrim_groups` | `view`, `manage`, `assign_members`, `manage_supervisors` |

The documentation-sprint namespace `family_groups` was implemented as `season_family_groups` (season-specific, consistent with the other season-scoped namespaces).

High-risk: `seasons.delete`, `seasons.close`, `seasons.reopen`, `pilgrims.delete`, `season_pilgrims.cancel`, `season_pilgrims.delete`, `season_pilgrims.correct_snapshot`, `season_programs.delete`.

## Role templates

| Template | Grants |
|---|---|
| `tenant_owner` | All. Basis: [Users & Authorization PERMISSIONS.md](../02-users-and-authorization/PERMISSIONS.md) ("All … seed permissions") and its BUSINESS_RULES ("Tenant Owners are seeded with the full catalog") |
| All other templates (`general_manager`, `auditor`, …) | **None** |

**Pending decision (role matrix):** no repository document maps these permissions to `general_manager`, `auditor`, or any other template. The earlier implementation assumption was removed in the hardening pass. Until the business approves a role matrix, tenants grant them through custom roles.

## Pilgrim Group supervisors

Being assigned as a Pilgrim Group supervisor (primary or assistant) grants **no** application permission. Access still comes only from roles (tested).

## Platform

No `platform_tenants.*` change. Platform users do not receive these tenant permissions.
