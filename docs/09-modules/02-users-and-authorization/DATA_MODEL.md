# Users and Authorization — Data Model (conceptual)

> **Status:** Conceptual — Users/RBAC migrations not yet implemented; auth-facing User fields aligned with Authentication Sprint 005
> **Last updated:** 2026-08-08

All tenant-owned tables carry `tenant_id` with automatic scoping. Platform users have `tenant_id = NULL` (Tenant Foundation).

## User

| Field | Notes |
|---|---|
| Tenant | Owning tenant (`tenant_id` nullable — platform vs tenant user) |
| Name | Full name |
| Login identifier | **Email** (globally unique; normalized lowercase) — decided in Authentication |
| Password | Hashed |
| Status | **`active` / `disabled`** — decided in Authentication; migration documented there for Sprint 005 |
| Employee link | Optional link to employee record (rules TBD in Employees module) |
| Timestamps | Standard |

`email_verified_at` may exist on the table; email verification is **out of Sprint 005**.

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

- Whether permission catalog is seeded per release or database-driven: TBD at RBAC implementation.
- Package choice (e.g. spatie/laravel-permission) vs. hand-rolled: TBD — must be documented if added.
- Employee ↔ user linking rules: TBD with Employees module.
