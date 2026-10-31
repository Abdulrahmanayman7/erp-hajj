# Transportation and Fleet — Data Model

> **Status:** Implemented (backend sprint 1).
> **Last updated:** 2026-10-05
> Binding: [ADR-0018](../../10-decisions/ADR-0018-TRANSPORTATION-MOVEMENTS-RUNS-AND-FLEET.md). Every table is tenant-owned (`tenant_id`, `TenantOwned` + `UsesTenantScope`). Season-owned tables also implement `SeasonOwned`.

## Conventions

- Composite foreign keys enforce same-tenant / same-season integrity: `(tenant_id, season_id) → seasons(tenant_id, id)`, `(season_id, x_id) → x(season_id, id)`, `(tenant_id, x_id) → x(tenant_id, id)` for tenant-level parents.
- "At most one current" and "no duplicate occupying passenger" use a **stored generated nullable flag** plus UNIQUE (portable partial-unique emulation for MySQL 8 / MariaDB / SQLite).
- Long FK and index names are explicit (MySQL 64-character limit).
- No CHECK constraints (project convention); exactly-one and range rules are enforced in actions.
- No GPS, QR, offline, seat, Accommodation, or `current_vehicle` columns anywhere.

## Tenant-level tables

### `transport_points`

| Column | Type | Notes |
|---|---|---|
| `id`, `tenant_id` | bigint | `unique(tenant_id, id)` |
| `code` | string(40) | unique per tenant |
| `name` | string(255) | |
| `point_type` | string(32) | `airport`, `station`, `gathering_point`, `custom` |
| `is_active` | bool | default true |
| `notes` | text null | |
| `created_by` | FK users null | |

### `transport_vehicles`

| Column | Type | Notes |
|---|---|---|
| `id`, `tenant_id` | bigint | `unique(tenant_id, id)` |
| `vehicle_number` | string(32) | `VEH-######`, unique per tenant, server-generated |
| `plate_number` | string(32) | trimmed + upper-cased, unique per tenant |
| `vehicle_class` | string(32) | `bus`, `minibus`, `car` |
| `asset_id` | FK assets null | `ON DELETE SET NULL`, globally unique (one Vehicle per Asset), same tenant checked in action |
| `contract_id` | FK contracts null | `ON DELETE SET NULL`, same tenant checked in action |
| `is_active` | bool | |
| `notes`, `created_by` | | |

### `transport_vehicle_sequences`

`tenant_id` (unique), `next_number`. Locked with `lockForUpdate` when numbering.

### `transport_external_drivers`

| Column | Type | Notes |
|---|---|---|
| `id`, `tenant_id` | bigint | `unique(tenant_id, id)` |
| `full_name` | string(150) | |
| `phone` | string(30) null | |
| `is_active` | bool | |
| `notes`, `created_by` | | |

## Season-owned tables

### `transport_season_vehicles`

| Column | Type | Notes |
|---|---|---|
| `id`, `tenant_id`, `season_id` | | FK `(tenant_id, season_id) → seasons`; `unique(season_id, id)` |
| `transport_vehicle_id` | | FK `(tenant_id, transport_vehicle_id) → transport_vehicles(tenant_id, id)`; `unique(season_id, transport_vehicle_id)` |
| `operational_capacity` | unsigned smallint | ≥ 1 |
| `service_level` | string(32) | `standard`, `premium` |
| `default_driver_employee_id` | FK employees null | restrict |
| `default_external_driver_id` | null | FK `(tenant_id, default_external_driver_id) → transport_external_drivers(tenant_id, id)` |
| `default_supervisor_employee_id` | FK employees null | restrict |
| `is_active`, `notes`, `created_by` | | |

At most one of the two default driver columns is set (action-enforced).

### `transport_entitlements`

| Column | Type | Notes |
|---|---|---|
| `id`, `tenant_id`, `season_id` | | FK `(tenant_id, season_id) → seasons` |
| `season_program_id` | | FK `(season_id, season_program_id) → season_programs(season_id, id)` restrict; `unique(season_id, season_program_id)` |
| `vehicle_class` | string(32) null | null = any |
| `service_level` | string(32) null | null = any; at least one of the two set |
| `notes`, `created_by` | | |

### `transport_movement_sequences`

`tenant_id`, `season_id` (`unique(tenant_id, season_id)`, FK to seasons), `next_number`.

### `transport_movements`

| Column | Type | Notes |
|---|---|---|
| `id`, `tenant_id`, `season_id` | | `unique(season_id, id)` |
| `movement_number` | string(32) | `MOV-######`, unique per season |
| `origin_point_id`, `destination_point_id` | | FK `(tenant_id, …) → transport_points(tenant_id, id)`; must differ |
| `planned_departure_at`, `planned_arrival_at` | datetime | arrival > departure |
| `status` | string(32) | `draft`, `planned`, `in_progress`, `completed`, `cancelled` |
| `planned_at`, `started_at`, `completed_at`, `cancelled_at` | datetime null | |
| `cancellation_reason` | string(1000) null | |
| `notes`, `created_by` | | |

### `transport_vehicle_runs`

| Column | Type | Notes |
|---|---|---|
| `id`, `tenant_id`, `season_id` | | `unique(season_id, id)`, `unique(transport_movement_id, id)` |
| `transport_movement_id` | | FK `(season_id, transport_movement_id) → transport_movements(season_id, id)` |
| `run_number` | unsigned smallint | unique per movement |
| `status` | string(32) | `planned`, `boarding`, `departed`, `arrived`, `completed`, `cancelled` |
| `capacity_override` | unsigned smallint null | ≤ SeasonVehicle capacity |
| `boarding_started_at`, `departed_at`, `arrived_at`, `completed_at`, `cancelled_at` | datetime null | |
| `cancellation_reason`, `notes`, `created_by` | | |

### Assignment history

`transport_run_vehicle_assignments`, `transport_run_driver_assignments`, `transport_run_supervisor_assignments` share:

| Column | Notes |
|---|---|
| `id`, `tenant_id`, `season_id` | FK `(tenant_id, season_id) → seasons` |
| `vehicle_run_id` | FK `(season_id, vehicle_run_id) → transport_vehicle_runs(season_id, id)` |
| `assigned_at`, `assigned_by` | |
| `ended_at`, `ended_by` | null while current |
| `reason` | string(1000) null (required for substitution after execution began) |
| `current_flag` | stored generated: `1` when `ended_at IS NULL`, else NULL; `unique(vehicle_run_id, current_flag)` |

Resource columns:

- vehicle: `transport_season_vehicle_id` — FK `(season_id, transport_season_vehicle_id) → transport_season_vehicles(season_id, id)`.
- driver: `driver_employee_id` (FK employees) **or** `external_driver_id` (FK `(tenant_id, external_driver_id) → transport_external_drivers(tenant_id, id)`); exactly one (action-enforced).
- supervisor: `supervisor_employee_id` (FK employees).

### `transport_run_passengers`

| Column | Type | Notes |
|---|---|---|
| `id`, `tenant_id`, `season_id` | | `unique(vehicle_run_id, id)` |
| `vehicle_run_id` | | FK `(season_id, vehicle_run_id) → transport_vehicle_runs(season_id, id)` |
| `season_pilgrim_id` | | FK `(tenant_id, season_id, season_pilgrim_id) → season_pilgrims(tenant_id, season_id, id)` restrict |
| `status` | string(32) | `assigned`, `boarded`, `arrived`, `no_show`, `cancelled` |
| `occupying_flag` | generated | `1` for assigned/boarded/arrived; `unique(vehicle_run_id, season_pilgrim_id, occupying_flag)` |
| `assignment_source` | string(32) | `individual`, `pilgrim_group`, `family_group` |
| `source_group_id` | bigint null | **no FK** (snapshot) |
| `source_group_label` | string(255) null | snapshot |
| `program_id_at_assignment` | bigint null | **no FK**; immutable Program at assignment |
| `program_code_at_assignment` | string(40) null | immutable |
| `entitlement_id_at_assignment` | bigint null | **no FK**; which entitlement was evaluated |
| `entitlement_vehicle_class`, `entitlement_service_level` | string(32) null | required classification from that entitlement |
| `season_vehicle_id_at_assignment` | bigint null | **no FK**; SeasonVehicle compared at assignment |
| `vehicle_class_at_assignment`, `service_level_at_assignment` | string(32) null | vehicle classification compared at assignment |
| `entitlement_status_at_assignment` | string(32) | `none`, `compatible`, `overridden` (immutable) |
| `override_at_assignment` | bool | immutable; true when override authorized the assignment |
| `override_reason_at_assignment` | string(1000) null | immutable |
| `overridden_by_at_assignment` | FK users null | immutable |
| `entitlement_override` | bool | **live** override flag (may be set later) |
| `entitlement_override_reason` | string(1000) null | live |
| `entitlement_overridden_by`, `entitlement_overridden_at` | null | live |
| `assigned_at`, `assigned_by` | | `assigned_by` is part of the immutable assignment context |
| `boarded_at`, `arrived_at`, `no_show_at`, `cancelled_at`, `cancelled_by`, `cancellation_reason` | null | |

Program deletion is blocked while a snapshot references it (deletion checker, not FK).

### `transport_timeline_events` (append-only)

| Column | Notes |
|---|---|
| `id`, `tenant_id`, `season_id` | |
| `transport_movement_id` | FK `(season_id, transport_movement_id) → transport_movements(season_id, id)` |
| `vehicle_run_id` | null; FK `(transport_movement_id, vehicle_run_id) → transport_vehicle_runs(transport_movement_id, id)` |
| `run_passenger_id` | null; FK `(vehicle_run_id, run_passenger_id) → transport_run_passengers(vehicle_run_id, id)` |
| `event_type` | string(64) |
| `occurred_at`, `recorded_at` | datetime |
| `actor_user_id` | FK users null (null = system) |
| `reason` | string(1000) null |
| `context` | json null |
| `supersedes_event_id` | null, FK self, **unique** (one correction per event) |
| `correlation_id` | string(100) null |

No `updated_at`. The model rejects update and delete.

Event types: `movement_created`, `movement_planned`, `movement_started`, `movement_completed`, `movement_cancelled`, `run_created`, `run_boarding_started`, `run_departed`, `run_arrived`, `run_completed`, `run_cancelled`, `vehicle_assigned`, `vehicle_replaced`, `driver_assigned`, `supervisor_assigned`, `passenger_assigned`, `passenger_boarded`, `passenger_arrived`, `passenger_no_show`, `passenger_cancelled`, `passenger_entitlement_overridden`, `review_raised`, `review_resolved`, `event_corrected`.

Correctable types (I8): `movement_started`, `movement_completed`, `run_boarding_started`, `run_departed`, `run_arrived`, `run_completed`, `passenger_boarded`, `passenger_arrived`, `passenger_no_show`, and `event_corrected`.

### `transport_review_items`

| Column | Notes |
|---|---|
| `id`, `tenant_id`, `season_id` | |
| `vehicle_run_id` | FK `(season_id, vehicle_run_id) → transport_vehicle_runs` |
| `run_passenger_id` | FK `(vehicle_run_id, run_passenger_id) → transport_run_passengers(vehicle_run_id, id)`; `unique(run_passenger_id, review_type)` |
| `season_pilgrim_id` | FK `(tenant_id, season_id, season_pilgrim_id) → season_pilgrims` |
| `review_type` | `season_pilgrim_cancelled_on_board`, `season_pilgrim_cancelled_after_departure` |
| `severity` | `critical`, `non_critical` |
| `status` | `open`, `resolved` |
| `details` | json null |
| `raised_at`, `resolved_at`, `resolved_by`, `resolution_note` | |

### `transport_vehicle_breakdowns` (S02 / ADR-0021 D03)

| Column | Notes |
|---|---|
| `id`, `tenant_id`, `season_id` | |
| `vehicle_run_id` | FK `(season_id, vehicle_run_id) → transport_vehicle_runs` |
| `transport_season_vehicle_id` | Affected SeasonVehicle at record time |
| `status` | `open`, `replaced`, `closed`, `cancelled` |
| `occurred_at` | Breakdown time |
| `reason`, `notes` | |
| `reported_by` | FK users |
| `replacement_id` | null until replace; FK to replacements |
| `closed_at`, `closed_by`, `closure_reason` | Set on close/cancel/replace |
| `created_at`, `updated_at` | |

### `transport_vehicle_replacements`

| Column | Notes |
|---|---|
| `id`, `tenant_id`, `season_id` | |
| `breakdown_id` | **unique** (one replacement per breakdown) |
| `vehicle_run_id` | Source run |
| `from_season_vehicle_id`, `to_season_vehicle_id` | |
| `status` | `completed` (MVP) |
| `reason` | |
| `passengers_retained`, `passengers_reassigned` | Counts after atomic execute |
| `idempotency_key` | null; unique per `(tenant_id, idempotency_key)` |
| `executed_at`, `executed_by` | |

Replacement updates the run via **T7 history append** (end current vehicle assignment + create new row). It does not invent a parallel vehicle-current field.

### `transport_replacement_passenger_moves`

| Column | Notes |
|---|---|
| `id`, `tenant_id`, `season_id` | |
| `replacement_id` | FK |
| `from_run_id`, `to_run_id` | |
| `season_pilgrim_id` | |
| `source_passenger_id`, `target_passenger_id` | Cancelled source row + new assigned row on target |
| unique | `(replacement_id, source_passenger_id)` |

Timeline adds `vehicle_replaced` (and continues to emit `vehicle_assigned` for the T7 append). Audit: `TRANSPORT_BREAKDOWN_RECORDED` / `_CLOSED` / `_CANCELLED`, `TRANSPORT_VEHICLE_REPLACEMENT_EXECUTED`.

## Delete behavior

- Points, Vehicles, ExternalDrivers, SeasonVehicles: hard delete only while unreferenced (action check + restrict FKs); otherwise deactivate.
- Entitlements: hard delete allowed (passenger snapshots keep the values).
- Movements, Runs, passengers, assignments, timeline, reviews, breakdowns, replacements: never deleted through the API.
