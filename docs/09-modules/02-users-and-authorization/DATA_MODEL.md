# Users and Authorization — Data Model

> **Status:** **Implemented** (Sprint 006). Auth User fields already exist.
> **Last updated:** 2026-08-08

## Classification

| Table | Class | Notes |
|---|---|---|
| `users` | Hybrid (existing) | `tenant_id` nullable; Sprint 005 `status` |
| `permissions` | Platform | Global catalog; no `tenant_id` |
| `roles` | Tenant-owned | `TenantOwned` + `UsesTenantScope` |
| `user_roles` | Tenant-owned pivot | Explicit name; carries `tenant_id` for integrity |
| `role_permissions` | Hybrid pivot | `role_id` implies tenant; `permission_id` global; store `tenant_id` denormalized for indexing/validation |

**Pivot naming (final):** `user_roles`, `role_permissions` — explicit over Laravel’s short `role_user` / `permission_role`.

---

## `permissions` (platform)

| Column | Type | Rules |
|---|---|---|
| `id` | BIGINT PK | |
| `name` | VARCHAR(100) UNIQUE | Immutable `module.action` |
| `display_name` | VARCHAR(150) | Human default (AR preferred in UI via i18n override) |
| `module` | VARCHAR(50) | Indexed; grouping key |
| `description` | TEXT NULL | |
| `created_at` / `updated_at` | timestamps | |

No soft deletes. Removal = reviewed migration.

---

## `roles` (tenant-owned)

| Column | Type | Rules |
|---|---|---|
| `id` | BIGINT PK | |
| `tenant_id` | FK → tenants RESTRICT | NOT NULL; immutable; indexed leading |
| `name` | VARCHAR(100) | UNIQUE (`tenant_id`, `name`) |
| `code` | VARCHAR(63) | UNIQUE (`tenant_id`, `code`); immutable; `/^[a-z][a-z0-9_]*$/` |
| `description` | TEXT NULL | |
| `is_system` | BOOLEAN | default false |
| `is_active` | BOOLEAN | default true |
| `created_by` | FK users NULL ON DELETE SET NULL | |
| timestamps | | |

---

## `user_roles`

| Column | Type | Rules |
|---|---|---|
| `id` | BIGINT PK | Prefer explicit id for auditability |
| `tenant_id` | FK tenants RESTRICT | Must match user.tenant_id and role.tenant_id |
| `user_id` | FK users RESTRICT | |
| `role_id` | FK roles RESTRICT | |
| `assigned_by` | FK users NULL SET NULL | Actor |
| `created_at` | timestamp | Assignment time; **no** `updated_at` (replace set via delete+insert or touch created_at only) |

**UNIQUE** (`tenant_id`, `user_id`, `role_id`).

Application **must** validate user and role belong to the same tenant before insert (defense in depth with `tenant_id` column).

---

## `role_permissions`

| Column | Type | Rules |
|---|---|---|
| `id` | BIGINT PK | |
| `tenant_id` | FK tenants RESTRICT | Denormalized from role; validates tenancy |
| `role_id` | FK roles RESTRICT | ON DELETE CASCADE optional — prefer RESTRICT + explicit cleanup |
| `permission_id` | FK permissions RESTRICT | |
| `assigned_by` | FK users NULL SET NULL | |
| `created_at` | timestamp | |

**UNIQUE** (`role_id`, `permission_id`) and/or (`tenant_id`, `role_id`, `permission_id`).

---

## Platform authorization storage (minimal)

Tenant RBAC tables must not store platform grants.

**Implemented:** `platform_roles` (no `tenant_id`) + `platform_role_permissions` + `platform_user_roles`.

| Table | Purpose |
|---|---|
| `platform_roles` | Platform-only roles (`code` unique); default `platform_super_admin` |
| `platform_role_permissions` | Links platform roles → global `permissions` rows (`platform_tenants.*`) |
| `platform_user_roles` | Assigns platform roles to users with `users.tenant_id` NULL |

- Effective resolution: `EffectivePlatformPermissions` (cached; empty for tenant users).
- First Setup (`POST /api/v1/platform/setup`) creates the first platform admin + provisions the Super Admin role; closes when any active platform admin with an active platform role exists (not when a tenant exists).
- `platform_tenants.*` are never assignable to tenant roles (`SyncRolePermissions` / `GrantAuthority`).
- Default Super Admin does **not** include `platform_tenants.access_data`.

---

## FK / delete behavior

| Parent | Child | On delete |
|---|---|---|
| tenants | roles, user_roles, role_permissions | RESTRICT |
| users | user_roles | RESTRICT (disable user instead) |
| roles | user_roles | RESTRICT if assignments exist; else allow role delete |
| roles | role_permissions | CASCADE or explicit delete in Action |
| permissions | role_permissions | RESTRICT (catalog sync must detach first) |

---

## Migration order (conceptual — do not create now)

1. `permissions`
2. `roles`
3. `user_roles`
4. `role_permissions`
5. (Optional later) platform role tables
6. Idempotent permission catalog sync
7. Tenant provisioning Action: default roles + Owner assignment for existing `rafee` / new tenants

### Existing users before RBAC

- Remain able to authenticate.
- Effective permissions empty until roles assigned.
- **Provisioning must** assign `tenant_owner` to a designated Owner for `rafee` in the same release to prevent admin lockout (operational runbook in implementation PR — no passwords in docs).

### Indexes

- `roles (tenant_id, name)`, `(tenant_id, code)`, `(tenant_id, is_active)`
- `user_roles (tenant_id, user_id)`, `(tenant_id, role_id)`
- `role_permissions (tenant_id, role_id)`, `(permission_id)`
- `permissions (module)`, unique `name`

### Rollback

Down migrations drop pivots then roles then permissions. Only safe before production data; after production, prefer expand-only catalog changes.
