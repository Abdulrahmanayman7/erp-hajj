# Transportation and Fleet — Business Rules

> **Status:** Approved (T1–T10) and implemented in backend sprint 1.
> **Last updated:** 2026-10-05
> Binding: [ADR-0018](../../10-decisions/ADR-0018-TRANSPORTATION-MOVEMENTS-RUNS-AND-FLEET.md). Implementation decisions are marked **(I#)** and listed in ADR-0018.

## 1. Boundaries (ADR-0017, still binding)

1. Tenant-scoped; `tenant_id` is never taken from the client.
2. Season-aware. Seasonal resources take `season_id` from the Season Context (`X-Season-Id`, default Active season), never from payloads.
3. Seasonal pilgrim operations reference **SeasonPilgrim**.
4. Staff are existing **Employees**. No Driver or Supervisor identity tables, except the minimal **ExternalDriver** (T4).
5. Organization units are not transport groups.
6. Transportation owns transportation operational state only; it never writes Season, Pilgrim, SeasonPilgrim (including `status`), Program, Pilgrim/Family Group, Employee, Asset, Contract, or Accommodation rows.

## 2. Transport Points (T2, I14)

- Types: `airport`, `station`, `gathering_point`, `custom`. Code unique per tenant.
- Tenant-level and reusable across seasons. Deactivate instead of delete once used; a used point cannot be hard-deleted (`TRANSPORT_POINT_IN_USE`). Inactive points cannot be used by new or edited Movements.
- Origin and destination of a Movement must differ.
- Linking a point to another domain resource (for example a future Accommodation Facility) is a future additive, typed link. Nothing references Accommodation today.

## 3. Vehicle registry (T3, I9, I12)

- Vehicle is tenant-level: server-generated business reference `VEH-######`, `plate_number` (unique per tenant, stored trimmed and upper-cased), `vehicle_class` (`bus`, `minibus`, `car` — current supported values), optional notes.
- Optional read-only links: one Asset (an Asset links to at most one Vehicle) and one Contract (many Vehicles may share a Contract). Both same-tenant; both may be added later. Link changes are audited. If the Asset or Contract is deleted in its own module, the link becomes empty.
- Deactivate instead of delete once registered in any season (`TRANSPORT_VEHICLE_IN_USE`). Inactive vehicles cannot join a season fleet.

## 4. Season fleet (T3, T4, T5)

- SeasonVehicle = Vehicle × Season, unique per season (`TRANSPORT_SEASON_VEHICLE_DUPLICATE`).
- Carries `operational_capacity` (≥ 1), `service_level` (`standard`, `premium`), and planning defaults: default driver (internal Employee **or** ExternalDriver) and default Transport Supervisor (Employee). Defaults must be active and same-tenant.
- Capacity cannot be reduced below the occupied count or override of any current non-terminal Run using the vehicle (`TRANSPORT_CAPACITY_BELOW_ASSIGNED`).
- Deactivate instead of delete once assigned to any Run (`TRANSPORT_SEASON_VEHICLE_IN_USE`). Inactive SeasonVehicles cannot be assigned to Runs.

## 5. Drivers and Transport Supervisors (T4)

- Internal driver = active Employee of the tenant. External driver = active ExternalDriver (`full_name`, optional `phone`, notes). ExternalDriver is not an Employee, User, or HR record.
- Transport Supervisor = active Employee; not `employees.supervisor_id`, not a Pilgrim Group Supervisor.
- No role grants permissions.

## 6. Movements (T1, T6, I1–I4)

- Movement: number `MOV-######` (per season), origin and destination Transport Points, planned departure and planned arrival (arrival after departure), notes.
- Lifecycle: `draft → planned → in_progress → completed`; `draft | planned → cancelled` (reason required). No other states.
- Editable (points, planned window, notes) only in `draft` / `planned`. Changing the window re-checks resource overlap.
- `start` requires an Active season. `complete` requires all Runs Completed or Cancelled (I3). `cancel` cancels its Runs and their Assigned passengers (I4).
- Movement status is persisted, not derived from Runs.

## 7. Vehicle Runs (T1, T6, T7, I1, I3–I5)

- A Run belongs to one Movement, has a sequential `run_number`, and is created with a SeasonVehicle. Driver and supervisor default from the SeasonVehicle at creation and are stored as actual assignments. Copied defaults are validated like explicit assignments (active Employee / ExternalDriver, no driver overlap), so creation can fail with `TRANSPORT_EMPLOYEE_INVALID`, `TRANSPORT_EXTERNAL_DRIVER_INACTIVE`, `TRANSPORT_VEHICLE_OVERLAP`, or `TRANSPORT_DRIVER_OVERLAP`.
- The initial SeasonVehicle and any copied default driver / supervisor are written as the **first current rows** of the Run's assignment-history tables (T7). The `run_created` timeline event may summarize them; it does not replace history. Later substitutions end the current row and append a new one; `vehicle_assigned` / `driver_assigned` / `supervisor_assigned` timeline events are recorded for those substitutions.

- Runs can be added while the Movement is `draft`, `planned`, or `in_progress`.
- Lifecycle: `planned → boarding → departed → arrived → completed`; `planned | boarding → cancelled` (reason required).
  - `start-boarding` requires the Movement `in_progress` and an Active season.
  - `depart` requires a current driver.
  - `complete` requires every passenger Arrived, No-show, or Cancelled.
  - A Boarding Run with Boarded passengers cannot be cancelled (`TRANSPORT_RUN_HAS_BOARDED_PASSENGERS`).
- Optional `capacity_override` ≤ the current SeasonVehicle capacity and ≥ the occupied count.

## 8. Resource assignments (T7, I1)

- Each Run keeps history rows for vehicle, driver, and supervisor (`assigned_at` / `ended_at`), including the **initial** assignments created with the Run. At most one current of each (database-enforced).
- Substitution ends the current row and appends a new one; history is never overwritten. After execution has begun (`boarding`, `departed`, `arrived`) a reason is required. Substitution is not allowed on Completed or Cancelled Runs, which keep their assignments.
- A vehicle substitution must fit the occupied count and the override.
- Overlap is rejected for the same SeasonVehicle (`TRANSPORT_VEHICLE_OVERLAP`), the same internal driver Employee, or the same ExternalDriver (`TRANSPORT_DRIVER_OVERLAP`), considering Runs in `planned / boarding / departed / arrived` with intersecting Movement windows. Checked server-side inside a transaction with row locks. Supervisor overlap is allowed.
- **Operational breakdowns (ADR-0021 D03)** are a separate domain (`transport_vehicle_breakdowns`). Recording a breakdown does not by itself change the current vehicle. **Vehicle replacement** against an open breakdown is atomic: it re-validates season writability, run non-terminal, affected vehicle still current, replacement active in season, overlap-free, and capacity. It then appends T7 history (end current + new assignment) and may move passengers only via an **explicit reassignment plan**. Changing `vehicle_id` / appending T7 alone is not passenger reassignment. Boarded/arrived that exceed replacement capacity block the replace (`TRANSPORT_REPLACEMENT_BOARDED_EXCEED_CAPACITY`). Insufficient capacity without a complete plan → `TRANSPORT_REPLACEMENT_CAPACITY_PLAN_REQUIRED`. One replacement per breakdown; idempotency key supported. Statuses: `open` → `replaced` | `closed` | `cancelled`.

## 9. Passenger manifest and capacity (T5, I6)

- One row per SeasonPilgrim per Run. Only **Active** SeasonPilgrims of the same season are eligible.
- Bulk add accepts a list of SeasonPilgrims, a PilgrimGroup, or a FamilyGroup, resolved at assignment time into rows with `assignment_source` (`individual` / `pilgrim_group` / `family_group`) and a source snapshot (`source_group_id`, `source_group_label`). No FK to the group/family. Later membership changes do not alter rows.
- **All sources are all-or-nothing.** Membership is resolved first; every resolved SeasonPilgrim is validated before any passenger row is inserted. If any member fails (not Active, already on the Run, capacity, entitlement mismatch without override, tenant/season mismatch), the command fails atomically with a stable code and the failing `season_pilgrim_ids` in `errors`. An empty group/family → `TRANSPORT_PASSENGERS_NONE_ELIGIBLE`. There is no silent skipping and no partial manifest.
- An explicit entitlement override (permission + reason) may make entitlement-incompatible members eligible for that assignment.
- Passengers can be added while the Run is `planned` or `boarding` and the Movement is not Completed/Cancelled.
- Capacity is a count: effective capacity = `capacity_override` or the current SeasonVehicle capacity; occupied = passengers `assigned`, `boarded`, `arrived`. **No overbooking** (`TRANSPORT_CAPACITY_EXCEEDED`).
- Duplicate occupying passenger in one Run is rejected (database-enforced).
- Families are not required to share a Run. Crew are not passengers.

## 10. Passenger lifecycle (T8)

- `assigned → boarded → arrived`; `assigned → no_show`; `assigned → cancelled` (reason required).
  - `board`: Run `boarding`. `no_show`: Run `boarding`, `departed`, or `arrived`. `arrive`: Run `arrived`. `cancel`: Run `planned` or `boarding`.
  - Bulk outcomes are all-or-nothing: if any passenger cannot make the transition (or belongs to another Run), nothing changes and `TRANSPORT_PASSENGER_INVALID_TRANSITION` lists the offending ids in `errors.passenger_ids`.
- Current status + timestamps on the row; transitions also append timeline events.

## 11. Entitlements (T9, I9, I10)

- One Transportation entitlement per SeasonProgram per season, with `vehicle_class` and/or `service_level` (at least one). An empty field means "any".
- Normal assignment requires the passenger's current Program entitlement (if any) to match the Run's current vehicle class and SeasonVehicle service level (`TRANSPORT_ENTITLEMENT_MISMATCH`).
- Override: `entitlement_override` + `override_reason` + permission `transportation.override_entitlement`, audited. An existing passenger whose current entitlement no longer matches can be overridden the same way.
- Each passenger row stores an **immutable assignment context** (normalized columns, not a key/value blob): Program id/code, evaluated entitlement id and required vehicle_class/service_level, SeasonVehicle id and its vehicle_class/service_level at assignment, entitlement status at assignment, whether an override was used (who/why), and `assigned_by`. Later Program changes, entitlement edits, or SeasonVehicle / Vehicle classification changes **never** rewrite it. The manifest separately exposes the **live** `entitlement_status` (`none`, `compatible`, `mismatch`, `overridden`); a live mismatch is a Needs Review / readiness WARNING and never auto-moves the passenger. A later privileged override updates only the live override fields.

## 12. Operational timeline (T6, I7, I8)

- Append-only events with `occurred_at`, `recorded_at`, actor, type, reason, context, and correlation id. Never updated or deleted.
- `occurred_at` defaults to now; future values are rejected (`TRANSPORT_OCCURRED_AT_FUTURE`). Older than the tolerance (config, default 15 minutes) = backdated: reason (`TRANSPORT_BACKDATE_REASON_REQUIRED`) and `transportation.correct_history` (`TRANSPORT_BACKDATE_FORBIDDEN`) required.
- Correction (`transportation.correct_history`, reason required): appends `event_corrected` superseding one lifecycle/outcome event with a new `occurred_at`, and updates the entity's matching timestamp. One correction per event; the latest correction can be corrected again.
- Core/Audit remains the security/accountability record.

## 13. Review items (T8, T10, I11)

- Raised by `SeasonPilgrimCancelled` when history cannot be changed:
  - passenger Boarded (on the vehicle) → `season_pilgrim_cancelled_on_board`, severity `critical`;
  - passenger Assigned or No-show on a departed Run → `season_pilgrim_cancelled_after_departure`, severity `non_critical`.
- One review per passenger and type. Resolution (`transportation.resolve_reviews`) requires a note and never changes passenger history.

## 14. SeasonPilgrim cancellation (T8, T10)

| Passenger state | Action |
|---|---|
| Assigned, Run `planned` / `boarding` | Passenger → Cancelled, capacity released, timeline + audit |
| Assigned / No-show, Run departed or later | Non-critical review |
| Boarded | Critical review |
| Arrived | Nothing (history preserved) |
| Cancelled | Nothing |

Idempotent. Never deletes Transportation rows. Never writes the foundation.

## 15. Season closing readiness (T10)

| Level | Code | Condition |
|---|---|---|
| BLOCKING | `TRANSPORT_MOVEMENTS_IN_PROGRESS` | Movement `in_progress` |
| BLOCKING | `TRANSPORT_RUNS_BOARDING` | Run `boarding` |
| BLOCKING | `TRANSPORT_RUNS_DEPARTED` | Run `departed` |
| BLOCKING | `TRANSPORT_CRITICAL_REVIEWS_OPEN` | Open critical review |
| WARNING | `TRANSPORT_MOVEMENTS_NOT_EXECUTED` | Movement `planned` |
| WARNING | `TRANSPORT_NON_CRITICAL_REVIEWS_OPEN` | Open non-critical review |
| WARNING | `TRANSPORT_ENTITLEMENT_OVERRIDES` | Non-cancelled passenger with an override |
| WARNING | `TRANSPORT_ENTITLEMENT_MISMATCHES` | Occupying passenger with a live mismatch and no override |
| OK | `TRANSPORT_OPERATIONS_SETTLED` | None of the above |

## 16. Foundation references (T10, I13)

- Operational reference: any passenger row references the SeasonPilgrim (blocks its hard delete).
- Deletion references: a Season with any Transportation data, and a Program used by an entitlement or by a passenger's assignment snapshot, cannot be hard-deleted (`SEASON_RESOURCE_REFERENCED`). Deactivating a Program is unaffected. Group/family deletion is not blocked by source metadata.

## 17. Closed seasons (I2)

Readable. Every Transportation write in a Closed season is rejected (`SEASON_CLOSED`), corrections included.

## Non-blocking TBDs

- Arabic labels; extension of vehicle class / service level values (business decision).
- Backdate tolerance value (configurable).
- Notification types and recipients (ADR-0013 catalog).
- Documents on vehicles / movements (morph aliases), exports, reports.
- Linking Transport Points to Accommodation facilities (when Accommodation exists).
- Role-template grants beyond `tenant_owner`.
- GPS, QR, offline (not approved for Transportation).
- Frontend.
