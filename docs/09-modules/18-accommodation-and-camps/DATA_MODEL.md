# Accommodation and Camps — Data Model

> **Status:** Sprint 1–3 schema implemented. Sprint 3 **ACCEPTED** (`docs/reviews/ACCOMMODATION_SPRINT3_ACCEPTANCE.md`). ADR-0019 remains **PROPOSED / NOT ACCEPTED**.
> **Last updated:** 2026-10-19

Binding: [DATABASE_PRINCIPLES.md](../../03-database/DATABASE_PRINCIPLES.md) · [ADR-0017](../../10-decisions/ADR-0017-SEASON-PILGRIM-AND-PHASE2-BOUNDED-CONTEXTS.md). Sprint 3: [scoped approval](../../reviews/ACCOMMODATION_SPRINT3_SCOPED_APPROVAL.md).

## Classification

All accommodation business rows are **tenant-owned**. `tenant_id` comes from `TenantContext`, is immutable, and uses FK RESTRICT to `tenants`. The client never supplies `tenant_id`.

Season allocations, drafts, and scope rows are also **season-scoped**. `season_id` comes from `SeasonContext` / `X-Season-Id`, never from an authorization decision in the body.

Stable facilities, structures, units, and positions have **no** `season_id`. They persist across seasons. A write still uses the context season for the Draft/Active/Closed guard in [BUSINESS_RULES.md](BUSINESS_RULES.md).

No platform table. No `physical_capacity` column (Sprint 2 uses cross-season **detection only**, D4).

## Sprint 1 tables (implemented)

These ten names are the Sprint 1 schema.

ON DELETE is RESTRICT. Every table has `id`, `tenant_id`, and `timestamps`. Unique `(tenant_id, id)` exists on every table that is an FK target. MySQL names must stay within 64 characters.

`users` has a nullable `tenant_id` and **no** unique `(tenant_id, id)` (`2026_08_06_100001_add_tenant_id_to_users_table.php`). Sprint 1 does not alter `users`. Grantee and actor columns are a single-column FK to `users.id` RESTRICT. The action rejects a user whose `tenant_id` is null or differs from the context tenant.

### `accommodation_facilities`

Stable hotel or camp. Columns: `kind` (`hotel` \| `camp`), `code`, `name`, `is_active` (default true), `created_by` nullable FK `users`.

Unique: `(tenant_id, id)`, `(tenant_id, code)`.

### `accommodation_structures`

Optional building or floor. Columns: `facility_id`, nullable `parent_id`, `kind` (`building` \| `floor`), `name`, `created_by` nullable FK `users`.

Unique: `(tenant_id, id)`, `(tenant_id, facility_id, id)`.

Foreign keys:

- `(tenant_id, facility_id)` → `accommodation_facilities (tenant_id, id)`.
- `(tenant_id, facility_id, parent_id)` → `accommodation_structures (tenant_id, facility_id, id)`.

The application rejects a parent cycle. No unit is required to point at a structure.

### `accommodation_units`

Stable room or tent. Columns: `facility_id`, nullable `structure_id`, `unit_kind` (`room` \| `tent`), `code`, `created_by` nullable FK `users`.

Unique: `(tenant_id, id)`, `(tenant_id, facility_id, id)`, `(tenant_id, facility_id, code)`.

Foreign keys:

- `(tenant_id, facility_id)` → `accommodation_facilities (tenant_id, id)`.
- `(tenant_id, facility_id, structure_id)` → `accommodation_structures (tenant_id, facility_id, id)`.

### `accommodation_positions`

Stable bed or tent position. Columns: `unit_id`, `code`, `created_by` nullable FK `users`.

Unique: `(tenant_id, id)`, `(tenant_id, unit_id, id)`, `(tenant_id, unit_id, code)`.

Foreign key: `(tenant_id, unit_id)` → `accommodation_units (tenant_id, id)`.

### `accommodation_season_facilities`

This facility is selected for this season. Columns: `season_id`, `facility_id`, nullable `audience` (`male` \| `female` \| `family`; null means no facility restriction), `is_active` (default true), `created_by` nullable FK `users`.

Unique: `(tenant_id, id)`, `(tenant_id, season_id, id)`, `(tenant_id, season_id, id, facility_id)`, `(tenant_id, season_id, facility_id)`.

Foreign keys:

- `(tenant_id, season_id)` → `seasons (tenant_id, id)` (`seasons_tenant_id_id_unique`).
- `(tenant_id, facility_id)` → `accommodation_facilities (tenant_id, id)`.

### `accommodation_season_units`

This unit is selected for this season. Columns: `season_id`, `season_facility_id`, `facility_id` (same facility as the season-facility row and the unit), `unit_id`, `allocation_mode` (`capacity` \| `position`), nullable unsigned `capacity`, `audience` (`male` \| `female` \| `family`, required), `is_active` (default true), `created_by` nullable FK `users`.

Sprint 1: `capacity` stored; not enforced against drafts. Sprint 2 (D3): for `allocation_mode=position`, operational capacity equals the count of enabled season positions subject to this `capacity` as a **ceiling** (do not auto-write `capacity` from position count). Decreases that conflict with confirmed stays are rejected (D5).

Unique: `(tenant_id, id)`, `(tenant_id, season_id, id)`, `(tenant_id, season_id, id, unit_id)`, `(tenant_id, season_id, unit_id)`.

Foreign keys:

- `(tenant_id, season_id)` → `seasons (tenant_id, id)`.
- `(tenant_id, season_id, season_facility_id, facility_id)` → `accommodation_season_facilities (tenant_id, season_id, id, facility_id)`.
- `(tenant_id, facility_id, unit_id)` → `accommodation_units (tenant_id, facility_id, id)`.

A unit from another facility cannot be allocated to this season facility.

### `accommodation_season_positions`

Which stable positions are enabled on that season unit. Columns: `season_id`, `season_unit_id`, `unit_id` (the season unit’s unit), `position_id`, `is_active` (default true), `created_by` nullable FK `users`.

Unique: `(tenant_id, id)`, `(tenant_id, season_id, id)`, `(tenant_id, season_id, position_id)`.

Foreign keys:

- `(tenant_id, season_id, season_unit_id, unit_id)` → `accommodation_season_units (tenant_id, season_id, id, unit_id)`.
- `(tenant_id, unit_id, position_id)` → `accommodation_positions (tenant_id, unit_id, id)`.

### `accommodation_assignments`

Sprint 1: draft stays. Sprint 2 (implemented): also `confirmed` via confirm APIs. Sprint 3 (Gate A plan): ops statuses, `cancelled` / `completed`, `actual_departed_at`, optional `relocated_from_assignment_id`.

Columns (today): `season_id`, `season_pilgrim_id`, `season_unit_id`, `unit_id`, nullable `season_position_id`, `booking_status`, nullable `operational_status`, `starts_at`, `original_ends_at`, `effective_ends_at`, nullable `family_group_id`, nullable `checkout_source`, `created_by` nullable FK `users`.

**Sprint 2 CHECK (`acc_asg_booking_ck` — implemented):** draft branch keeps Sprint 1 predicates; confirmed branch allows `booking_status='confirmed'` with null `operational_status` / `checkout_source`, `effective_ends_at = original_ends_at`, valid interval. No cancelled/completed/ops statuses yet.

**Sprint 3 CHECK (planned Gate B):** widen for confirmed ops (`assigned`/`arrived`/`checked_in`), `cancelled`, `completed`/`checked_out`, `effective_ends_at <= original_ends_at`, plus nullable `actual_departed_at` (B2) and nullable `relocated_from_assignment_id`. Exact branch SQL in scoped approval §8.

- Drafts: overlapping intervals allowed; no capacity reservation.
- Confirmed / completed: capacity, position, pilgrim overlap, family, and cross-season detection enforced in application under locks (see BUSINESS_RULES). Occupancy includes completed until `effective_ends_at`; excludes cancelled.
- Any SeasonPilgrim may be linked on a **draft**, including terminal. Confirmation eligibility is D1 (`registered` / `active` only).

Unique: `(tenant_id, id)`, `(tenant_id, season_id, id)`. There is **no** unique key on unit and time.

Indexes: Sprint 2 pilgrim/unit/position status-start indexes exist.

Foreign keys: unchanged from Sprint 1 (`seasons`, `season_pilgrims`, season units/positions); Sprint 3 adds optional self-FK for relocate chain.

## Sprint 2 tables (implemented)

| Table | Purpose |
|---|---|
| `accommodation_pilgrim_locks` | Lock anchor only: unique `(tenant_id, season_id, season_pilgrim_id)`, FK to `season_pilgrims` RESTRICT |

`accommodation_events` was named for Sprint 2 confirm timeline but **was not delivered** (confirm uses `Core/Audit` only). Sprint 3 Gate B creates it for ops events.

**Not** in Sprint 2: `accommodation_family_reviews`, `accommodation_cancellation_tasks`.

## Sprint 3 tables / columns (Gate B — implemented)

| Change | Purpose |
|---|---|
| `accommodation_assignments.actual_departed_at` | Late departure recording without extending reserved capacity (B2) |
| `accommodation_assignments.relocated_from_assignment_id` | History link destination → source (same tenant+season composite FK, RESTRICT) |
| Widen `acc_asg_booking_ck` | Represent ops / cancelled / completed safely |
| `accommodation_events` | Append-only `arrived`, `checked_in`, `checked_out`, `relocated`, `cancelled` |

Relocation chains: each destination may point at its immediate source via `relocated_from_assignment_id`. Follow the FK backward for history; sources are never cascade-deleted. Self-reference is **not** CHECK-enforced on MariaDB (AUTO_INCREMENT limitation); Gate C writers must reject it.

**Not** in Sprint 3: `accommodation_cancellation_tasks`, `accommodation_family_reviews`.

### `accommodation_facility_scopes`

Columns: `season_id`, `facility_id`, `user_id` FK `users.id` RESTRICT, `granted_by` nullable FK `users`, `revoked_at` nullable, generated `active_grant` (`1` when `revoked_at` is null, else null). Same generated-flag pattern as `seasons.active_flag`.

Unique: `(tenant_id, season_id, user_id, facility_id, active_grant)`.

Foreign keys: `(tenant_id, season_id)` → `seasons (tenant_id, id)`; `(tenant_id, facility_id)` → `accommodation_facilities (tenant_id, id)`.

Revocation sets `revoked_at`. It does not delete the row.

### `accommodation_supervisor_scopes`

Same as facility scope without `facility_id`. Unique `(tenant_id, season_id, user_id, active_grant)`. FK `(tenant_id, season_id)` → `seasons (tenant_id, id)`.

## Isolation

| Case | Result |
|---|---|
| Id belonging to another tenant | 404 |
| Seasonal row whose `season_id` is not the context season | 404 |
| Composite FK to another tenant or season | Rejected by the database |

Hard delete of a facility, unit, or position that is still referenced fails by RESTRICT. `is_active = false` is an update, not a cascade.

## Forbidden columns

On Pilgrim or SeasonPilgrim:

- `current_room_id`
- `current_bed_id`
- `current_accommodation_id`

Sprint 1 does not write `SeasonPilgrim.status`, family membership, or any transportation row.

## Not in Sprint 1 / Sprint 2 / Sprint 3 (Gate A)

Automatic Foundation cancellation handling (listener), family reviews, cancellation tasks, extension, historical correction, post-Closed writes, and a shared `physical_capacity` formula remain out. Capacity-decrease **safety for confirmed stays** is Sprint 2 D5. Sprint 3 staff cancel / checkout / relocate are the approved capacity-release paths (see BUSINESS_RULES Sprint 3).

Demand, queue, auto-allocation, notification configuration, offline queue, and QR remain unnamed.

## Audit

`Core/Audit` for create, update, deactivate, draft delete, scope grant, scope revoke, confirm, and (Sprint 3) ops mutations. Operational timeline is `accommodation_events` (Gate B+), not the audit viewer. Passwords, tokens, and secrets never appear in audit values.

## Deployment

Sprint 3 migrations are authorized only by Gate B engineering kickoff under the scoped approval — not by this Module 18 file alone. Foundation `seasons` and `season_pilgrims` are not altered.
