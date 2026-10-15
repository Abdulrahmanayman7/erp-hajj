# Season and Pilgrim Foundation — Data Model

> **Status:** Implemented (Phase 2 foundation sprint, backend only). Migrations `2026_10_05_100000` … `100010`.
> **Last updated:** 2026-10-05

Binding: [ADR-0017](../../10-decisions/ADR-0017-SEASON-PILGRIM-AND-PHASE2-BOUNDED-CONTEXTS.md) · [DATABASE_PRINCIPLES.md](../../03-database/DATABASE_PRINCIPLES.md).

## Classification

All tables are **tenant-owned** (`tenant_id` NOT NULL, server-set, immutable, `ON DELETE RESTRICT` to `tenants`). No platform table was added. Models implement `TenantOwned` + `UsesTenantScope`; season-scoped models also implement `SeasonOwned`.

| Table | Purpose |
|---|---|
| `seasons` | Hajj season under a tenant; Draft / Active / Closed |
| `pilgrims` | Stable identity (`full_name`, `gender`, `nationality`, `phone`, `notes`) |
| `pilgrim_identity_documents` | Identity documents of a Pilgrim (`document_type`, `issuing_country`, `document_number`, `normalized_number`) |
| `season_programs` | Season-specific programs (packages); `is_active` |
| `season_pilgrim_groups` | Season-specific operational pilgrim groups |
| `season_family_groups` | Season-specific family groups |
| `season_pilgrims` | One participation per (tenant, season, pilgrim); current program, pilgrim group, family group, primary-member flag, lifecycle columns |
| `season_pilgrim_reference_sequences` | Per (tenant, season) counter for reference numbers |
| `season_pilgrim_identity_snapshots` | One identity snapshot per participation (captured values, lock, correction tracking) |
| `season_pilgrim_program_changes` | Append-only program history (from → to, reason, actor, correlation id) |
| `season_pilgrim_group_supervisors` | Supervisor assignments (Employee, `primary`/`assistant`, `assigned_at`/`ended_at` history) |

Pilgrim group membership and family membership are **columns on `season_pilgrims`** (single-valued; no transfer history is required). Supervisor history is kept as rows with `ended_at`.

## Invariants and how they are enforced

| Invariant | Enforcement |
|---|---|
| ≤1 Active season per tenant | Stored generated column `seasons.active_flag` (1 when active, else NULL) + `UNIQUE(tenant_id, active_flag)`; action-level check with row lock |
| Unique season code / hajj year per tenant | `UNIQUE(tenant_id, code)`, `UNIQUE(tenant_id, hajj_year)` |
| One participation per pilgrim per season | `UNIQUE(tenant_id, season_id, pilgrim_id)` |
| Unique reference number per season | `UNIQUE(tenant_id, season_id, reference_number)`; generated under `lockForUpdate` on the sequence row |
| No duplicate identity document per tenant | `UNIQUE(tenant_id, document_type, issuing_country, normalized_number)` (number uppercased, spaces/dashes stripped) |
| Same-tenant references | Composite FKs `(tenant_id, season_id) → seasons(tenant_id, id)` and `(tenant_id, pilgrim_id) → pilgrims(tenant_id, id)` |
| Same-season references | Composite FKs `(season_id, season_program_id / season_pilgrim_group_id / season_family_group_id) → child(season_id, id)` |
| ≤1 primary member per family | Generated `family_primary_flag` + `UNIQUE(season_family_group_id, family_primary_flag)` |
| ≤1 current primary supervisor per group; no duplicate current assignment | Generated `primary_active_flag` / `active_flag` + unique indexes |
| One snapshot per participation | `UNIQUE(season_pilgrim_id)` |
| Snapshot belongs to a participation of the same tenant | Composite FK `(tenant_id, season_pilgrim_id) → season_pilgrims(tenant_id, id)` (`ON DELETE RESTRICT`) |
| Program history row matches its participation's tenant and season | `season_pilgrim_program_changes.season_id` + composite FK `(tenant_id, season_id, season_pilgrim_id) → season_pilgrims(tenant_id, season_id, id)` |
| Program history references programs of the same season | Composite FKs `(season_id, from_season_program_id / to_season_program_id) → season_programs(season_id, id)` |
| Supervisor assignment belongs to a group of the same tenant | Composite FK `(tenant_id, season_pilgrim_group_id) → season_pilgrim_groups(tenant_id, id)` |
| Program history immutable | Model rejects updates; no update/delete endpoint |
| No accommodation/transport state on pilgrims | No `current_room*`, `current_bed*`, `current_accommodation*` columns; no transport/accommodation tables (schema tests) |

Generated nullable flag columns + UNIQUE emulate partial unique indexes portably on MySQL, MariaDB and SQLite (NULL never collides; verified by schema tests on all three).

### Child-table references kept as single-column FKs (deliberate)

- `season_pilgrim_identity_snapshots.pilgrim_identity_document_id` → `pilgrim_identity_documents(id)` `ON DELETE SET NULL`. It is provenance only (the captured values live on the snapshot). A composite `(tenant_id, document_id)` FK cannot use `SET NULL` because `tenant_id` is NOT NULL. Same-tenant / same-pilgrim provenance is enforced by `IdentitySnapshotCapturer` (tenant-scoped lookup of the participation's own pilgrim's document).
- `season_pilgrim_group_supervisors.employee_id` → `employees(id)`. A composite FK would require a `(tenant_id, id)` unique on `employees`, a schema change in the Employees module, which is outside this sprint. The assign action resolves the Employee through the tenant-scoped model and requires active status.
- `season_id` was added to `season_pilgrim_program_changes` only because it is the sole way to tie both history programs to the participation's season structurally. `tenant_id` / `season_id` were **not** duplicated onto the snapshot or supervisor tables, since the parent FK already pins them.

Supporting exact uniques for the composite FKs: `season_pilgrims(tenant_id, id)`, `season_pilgrims(tenant_id, season_id, id)`, `season_pilgrim_groups(tenant_id, id)`.

Explicit FK/index names (`sp_*`) are used on the child tables because Laravel's generated names exceed the 64-character MySQL/MariaDB identifier limit.

## Status enums

| Entity | Values |
|---|---|
| Season | `draft`, `active`, `closed` |
| SeasonPilgrim | `registered`, `active`, `completed`, `cancelled` |

## Deletion

- Season: only Draft, never activated (`first_activated_at` NULL), without participations.
- Pilgrim: hard delete only with **zero** participations (`PILGRIM_IN_USE`); no cascade. The `season_pilgrims → pilgrims` FK is `RESTRICT`, so the database also refuses it.
- SeasonPilgrim: only Registered, never activated, and not referenced by any tagged `SeasonPilgrimOperationalReferenceChecker` (future modules). Otherwise cancel (reason, actor, timestamp retained).
- Program: only if never used (current or history). Pilgrim group: only without members and supervisor history. Family group: only when empty.
- Season, Program, Pilgrim group, Family group: additionally not reported as referenced by any tagged `SeasonResourceDeletionChecker` (`SEASON_RESOURCE_REFERENCED`). Deactivation is never blocked by these checkers.

## Audit vs snapshot

System audit uses `Core/Audit` (`audit_logs`). The identity snapshot is business data. Identity document numbers are masked (last 4 characters) in audit values.

## Deployment

Migrations are additive and reversible (`down()` drops each table in reverse order). Verified `migrate:fresh` → `migrate:rollback --step=11` → `migrate` on SQLite, MariaDB 10.4.32 and MySQL 8.0.46; the module test suite passes on all three.

## Identity document types

`passport`, `national_id`, `residence_permit` are the **currently supported** values (enum `IdentityDocumentType`). New types require a documented business decision; no generic or key/value type exists. Date of birth and document expiry are out of the schema.
