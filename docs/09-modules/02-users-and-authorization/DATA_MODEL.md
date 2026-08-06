# Users and Authorization — Data Model (conceptual)

> **Status:** Conceptual — no migrations exist
> **Last updated:** 2026-08-06

All tenant-owned tables carry `tenant_id` with automatic scoping.

## User

| Field | Notes |
|---|---|
| Tenant | Owning tenant |
| Name | Full name |
| Login identifier | Email/username — TBD |
| Password | Hashed |
| Status | Active / Disabled |
| Employee link | Optional link to employee record (rules TBD) |
| Timestamps | Standard |

## Role (dynamic, per tenant)

| Field | Notes |
|---|---|
| Tenant | Owning tenant |
| Name | Unique per tenant |
| Description | Optional |

## Permission (platform catalog)

| Field | Notes |
|---|---|
| Name | `module.action` from the [master catalog](../../06-security/PERMISSION_MODEL.md) |

Relations: User ↔ Roles (many-to-many), Role ↔ Permissions (many-to-many). Direct user-permission grants: TBD (not approved).

## TBD

- Whether permission catalog is seeded per release or database-driven: TBD at implementation.
- Package choice (e.g. spatie/laravel-permission) vs. hand-rolled: TBD — must be documented if added.
