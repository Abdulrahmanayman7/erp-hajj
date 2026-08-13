# System Settings — Permissions

> **Status:** Implemented (Sprint 020) — names seeded; API/UI shipped
> **Last updated:** 2026-08-13

## Catalog (stable — do not rename)

| Permission | Purpose |
|---|---|
| `tenant_settings.view` | View effective settings for the current tenant |
| `tenant_settings.update` | Update mutable settings (audited; high-risk) |

No `tenant_settings.create` / `tenant_settings.delete` — singleton resource.

## Default role templates (current `PermissionCatalog` — preserve)

| Role `code` | `view` | `update` |
|---|---|---|
| `tenant_owner` | yes (all permissions) | yes |
| `general_manager` | yes | **no** |
| `department_manager` | no | no |
| `supervisor` | no | no |
| `employee` | no | no |
| `auditor` | no | no |
| `read_only` | no | no |

`tenant_settings.update` remains on the **high-risk** list (UI warning when assigning).

## Rules

- Policies/Gates only; no role-name checks.
- Frontend `can('tenant_settings.view'|'update')` is UX only.
- Platform users are not Settings consumers for tenant self-service (no tenant context).
