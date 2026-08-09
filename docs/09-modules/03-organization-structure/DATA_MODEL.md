# Organizational Structure — Data Model

> **Status:** **Implemented** (Sprint 007)
> **Last updated:** 2026-08-09

All columns and constraints below match the live migration `2026_08_09_200000_create_organization_units_table`. Binding tenancy rules: [MULTI_TENANCY.md](../../02-architecture/MULTI_TENANCY.md), [DATABASE_PRINCIPLES.md](../../03-database/DATABASE_PRINCIPLES.md).

## Tables in Sprint 007

### `organization_units` (tenant-owned)

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | NO | AI | PK |
| `tenant_id` | BIGINT UNSIGNED | NO | — | FK → `tenants.id`; **never** from client |
| `parent_id` | BIGINT UNSIGNED | YES | `NULL` | FK → `organization_units.id`; root = `NULL` (depth 0) |
| `name` | VARCHAR(150) | NO | — | Display name |
| `code` | VARCHAR(50) | NO | — | Immutable; tenant-unique |
| `type` | VARCHAR(20) | NO | — | Enum string: `department` \| `section` \| `unit` |
| `status` | VARCHAR(20) | NO | `active` | `active` \| `inactive` |
| `manager_user_id` | BIGINT UNSIGNED | YES | `NULL` | FK → `users.id` (same tenant). **Unit responsible manager only** — not employee line/HR supervisor (see below) |
| `sort_order` | INT | NO | `0` | Sibling order |
| `created_at` | TIMESTAMP | YES | — | |
| `updated_at` | TIMESTAMP | YES | — | |

**No soft deletes.** Prefer `status = inactive`. Hard delete only under the [delete policy](BUSINESS_RULES.md#hard-delete-policy-mvp).

### `manager_user_id` semantics

Means: **the user responsible for managing this organizational unit**.

Does **not** mean employee direct supervisor, HR reporting manager, or employee line manager. Employee-level reporting relationships belong to the Employees / Supervisors module and must stay separate.

### Foreign keys / ON DELETE

| FK | On delete | Rationale |
|---|---|---|
| `tenant_id` → `tenants` | RESTRICT (or cascade only via platform tenant purge policy — follow existing tenant tables) | Align with other tenant-owned tables |
| `parent_id` → `organization_units` | **RESTRICT** | Cannot delete parent with children (app also blocks) |
| `manager_user_id` → `users` | **SET NULL** | Clearing/removing user clears unit manager pointer; prefer disable users in MVP so this is rare |

### Unique constraints

| Name (indicative) | Columns |
|---|---|
| `organization_units_tenant_code_unique` | (`tenant_id`, `code`) |

Sibling name uniqueness enforced in application (and ideally DB). MySQL `NULL` parent uniqueness: use a **generated/stored `parent_key`** column (e.g. `COALESCE(parent_id, 0)`) for unique (`tenant_id`, `parent_key`, `name`) **or** enforce only in Form Request + tests. Prefer generated column at implementation if portable.

### Indexes (tenant-leading)

| Index | Columns | Purpose |
|---|---|---|
| PK | `id` | |
| Unique | (`tenant_id`, `code`) | Code lookup |
| Index | (`tenant_id`, `parent_id`) | Children of a node |
| Index | (`tenant_id`, `status`) | Filter active/inactive |
| Index | (`tenant_id`, `manager_user_id`) | Units for which a user is unit manager |

### Model contracts

- Implements `TenantOwned`; uses `UsesTenantScope`.
- Mass-assignment: never fillable `tenant_id`.
- API Resource: no internal-only flags beyond documented fields.

### Depth (not a DB column)

Depth is **derived** from the parent chain (root = 0). Maximum depth **8** is an **application MVP safety constraint**, not a schema limit. Centralize the limit in one constant/config at implementation — do not encode depth as a required stored column unless a later performance ADR requires it.

## Explicitly deferred tables

| Table | Deferred to |
|---|---|
| `positions` | Employees module (or thin precursor sprint) |
| `organization_unit_user` / user membership | Employees (`primary_organization_unit_id` on employees) |

## User table impact (Sprint 007)

**None.** Do not add `organization_unit_id` (or similar) to `users`. Users remain auth accounts only (Sprint 006 contract preserved).

## `/auth/me` impact (Sprint 007)

**None.** Do not add `organizational_unit` / `job_title` until a stable User↔org link exists (Employees). Avoid bloating AuthUserResource.

## Hierarchy query strategy

- **Adjacency list** only — no nested-set / closure-table package unless a future Change Request proves need at >500 nodes with heavy subtree queries.
- Load all units for tenant (or filtered set) in one query; assemble tree in PHP/TS.
- Cycle check and depth check: ancestor walk from proposed parent / compute depths for moved subtree against the centralized max-depth constant.

## Future FK / reference behavior (not Sprint 007)

Consuming modules **must** reference `organization_units.id` with ON DELETE **RESTRICT** (or equivalent app-level block) so hard delete fails once business references exist. Examples:

| Future consumer | Expected behavior |
|---|---|
| `employees.primary_organization_unit_id` | ON DELETE **RESTRICT** → delete blocked; use deactivate |
| Tasks / meetings / warehouses / documents (when linked) | Same: RESTRICT or app reject with `ORGANIZATION_UNIT_IN_USE` (or module-specific code) |
| Auditable business history requiring preservation | Once present, hard delete rejected; deactivate only |

## Migration order (future implementation)

1. Create `organization_units` table + FKs/indexes.
2. Seed permission catalog rows for `organization_units.view|create|update|delete` (not the obsolete `departments.*` draft names).
3. Update default role permission maps (e.g. Tenant Owner / General Manager templates) per [PERMISSIONS.md](PERMISSIONS.md).
4. No User migration.
5. No positions migration in this sprint.
