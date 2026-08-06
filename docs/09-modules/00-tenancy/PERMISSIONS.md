# Tenancy — Permissions

> **Status:** Partially approved
> **Last updated:** 2026-08-06

## Tenant-level

| Permission | Purpose |
|---|---|
| `tenant_settings.view` | View tenant settings |
| `tenant_settings.update` | Update tenant settings (audited) |

## Platform-level (Super Admin)

Permission names for tenant lifecycle management (create/activate/suspend tenants, exceptional tenant data access): **TBD** — must follow the `module.action` convention and be defined before implementation.

## Rules

- Exceptional Super Admin access to tenant data is permission-controlled and always audited.
