# Employees and Supervisors — Data Model

> **Status:** Implemented (Sprint 008)
> **Last updated:** 2026-08-09

Binding: [DATABASE_PRINCIPLES.md](../../03-database/DATABASE_PRINCIPLES.md), [MULTI_TENANCY.md](../../02-architecture/MULTI_TENANCY.md).

## Tables

### `positions` (tenant-owned)

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | NO | AI | PK |
| `tenant_id` | BIGINT UNSIGNED | NO | — | FK → `tenants`; never from client |
| `name` | VARCHAR(150) | NO | — | Display title |
| `code` | VARCHAR(50) | YES | `NULL` | Optional; tenant-unique when set |
| `is_active` | BOOLEAN | NO | `true` | |
| `created_at` / `updated_at` | TIMESTAMP | YES | — | |

**Uniques:** `(tenant_id, name)`; `(tenant_id, code)` where code not null (app + partial/unique strategy — enforce in Form Request; optional generated uniqueness).

**Indexes:** `(tenant_id, is_active)`.

**FK:** `tenant_id` → `tenants` RESTRICT.

### `employees` (tenant-owned)

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | NO | AI | PK |
| `tenant_id` | BIGINT UNSIGNED | NO | — | FK → `tenants`; never from client |
| `user_id` | BIGINT UNSIGNED | YES | `NULL` | FK → `users`; optional login link |
| `employee_number` | VARCHAR(32) | NO | — | Server-generated; immutable |
| `full_name` | VARCHAR(150) | NO | — | |
| `phone` | VARCHAR(30) | YES | `NULL` | Optional contact |
| `email` | VARCHAR(255) | YES | `NULL` | Optional contact (≠ User.email requirement) |
| `organization_unit_id` | BIGINT UNSIGNED | NO | — | FK → `organization_units` |
| `position_id` | BIGINT UNSIGNED | YES | `NULL` | FK → `positions` |
| `supervisor_id` | BIGINT UNSIGNED | YES | `NULL` | FK → `employees` |
| `status` | VARCHAR(20) | NO | `active` | `active` \| `inactive` |
| `hire_date` | DATE | YES | `NULL` | |
| `notes` | TEXT | YES | `NULL` | Non-sensitive operational notes |
| `created_at` / `updated_at` | TIMESTAMP | YES | — | |

**Explicitly excluded from MVP:** national ID, passport, salary, bank, insurance, attendance, leave, performance ratings.

### Foreign keys / ON DELETE

| FK | On delete |
|---|---|
| `tenant_id` → `tenants` | RESTRICT |
| `user_id` → `users` | **SET NULL** |
| `organization_unit_id` → `organization_units` | **RESTRICT** |
| `position_id` → `positions` | **SET NULL** (or RESTRICT if position in use — **prefer RESTRICT** when employees reference active position; deactivate position instead of delete) |
| `supervisor_id` → `employees` | **SET NULL** |

**Position delete policy:** Hard-delete position only if no employees reference it; else deactivate (`is_active = false`).

### Unique constraints

| Constraint | Columns |
|---|---|
| `employees_tenant_number_unique` | (`tenant_id`, `employee_number`) |
| `employees_user_id_unique` | (`user_id`) — MySQL allows multiple NULLs |

### Indexes (tenant-leading)

| Index | Columns |
|---|---|
| Unique | (`tenant_id`, `employee_number`) |
| Index | (`tenant_id`, `status`) |
| Index | (`tenant_id`, `organization_unit_id`) |
| Index | (`tenant_id`, `position_id`) |
| Index | (`tenant_id`, `supervisor_id`) |
| Index | (`tenant_id`, `full_name`) | searchable lists |

### Model contracts

- `Employee`, `Position` implement `TenantOwned` + `UsesTenantScope`.
- Never fillable `tenant_id` / `employee_number` from client mass-assignment on create beyond server set.
- Immutable: `tenant_id`, `employee_number`.

### Relations (Employee)

- `organizationUnit()`, `position()`, `supervisor()`, `subordinates()`, `user()`, `tenant()`

## User / auth impact

- **No** new columns on `users`.
- **`/auth/me`:** optional additive later — **not required** for Sprint 008 UI (Employees module loads its own data). Default: **no `/auth/me` change** unless a clear shell need appears (avoid bloat).

## Migration order (future implementation)

1. `positions`
2. `employees` (FKs to tenants, users, organization_units, positions, self)
3. Seed `employees.*` + `positions.*` permissions; update role templates
4. No User migration; no Organization Structure schema change (consumers already planned RESTRICT)

## Rollout impact

- Existing Users and Organization Units unchanged.
- Org unit hard-delete becomes blocked once employees reference the unit.
- Re-run RBAC catalog sync / system role provisioning after deploy.
