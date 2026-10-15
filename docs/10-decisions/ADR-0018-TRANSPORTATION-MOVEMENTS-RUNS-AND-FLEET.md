# ADR-0018 — Transportation: Movements, Vehicle Runs, Fleet, and Foundation Integration

> **Status:** Accepted — backend sprint 1 implemented 2026-10-05
> **Date:** 2026-10-05
> **Deciders:** Transportation business discovery (approved decisions T1–T10)
> **Builds on:** [ADR-0017](ADR-0017-SEASON-PILGRIM-AND-PHASE2-BOUNDED-CONTEXTS.md) (bounded contexts), [ADR-0003](ADR-0003-MULTI-TENANCY.md), [ADR-0012](ADR-0012-ASSET-CUSTODY-AND-OWNERSHIP.md), [ADR-0015](ADR-0015-SEMANTIC-AUDIT-TRAIL-AND-IMMUTABLE-HISTORY.md)

## Context

ADR-0017 established Transportation as a tenant-scoped, season-aware bounded context but left every entity, status, and workflow TBD because the earlier discovery text (decisions 1–30) was never transcribed. The Transportation Implementation Readiness Report listed twelve database-critical gaps (TDB-01 … TDB-12). Business discovery has now approved **T1–T10**, which resolve them. This ADR records T1–T10 and the implementation decisions taken to build them without inventing further business rules.

## Decision — approved business rules (T1–T10)

### T1 — Movement aggregate

Two operational levels:

- **Transportation Movement** — the larger business movement (for example Hotel → Mina, 600 pilgrims, 12 buses).
- **Vehicle Run** — one vehicle's execution inside a Movement. A Movement contains many Runs. Passenger manifests belong to the Run. Buses are never flattened into one Movement row.

### T2 — Transport Points

Origin and destination are structured **Transport Points** (airport, station, gathering point, custom), never free text as the primary model. A point may later be linked read-only to another domain resource (for example a future Accommodation Facility). Transportation never owns that resource, and this sprint creates **no** dependency on Accommodation.

### T3 — Vehicle registry and season fleet

```text
Tenant → Vehicle Registry → Vehicle
Season → Season Fleet → SeasonVehicle → Vehicle
```

Vehicle is tenant-level and survives across seasons. SeasonVehicle is the vehicle's participation in one Season. Runs reference **SeasonVehicle**, never Vehicle directly. A Vehicle may hold optional read-only references to an **Asset** and a **Contract**; neither is required, and Transportation never mutates Asset custody, Asset lifecycle, or Contract lifecycle. The same Vehicle cannot be registered twice in one Season.

### T4 — Drivers and Transport Supervisors

SeasonVehicle may carry planning defaults (default driver, default Transport Supervisor). The Run records the **actual** crew and keeps it when defaults change later. Transport Supervisor is a Transportation concept, always an Employee, and is not `employees.supervisor_id` or a Pilgrim Group Supervisor. Internal drivers are Employees. Hired drivers use a minimal Transportation-owned **ExternalDriver** identity (not an Employee, User, or HR record). The driver reference is polymorphic at the domain level (internal → Employee, external → ExternalDriver) and stored relationally with constrained foreign keys — no unconstrained morph columns.

### T5 — Passenger manifest and capacity

The authoritative manifest is materialized **per SeasonPilgrim** on the Run. Bulk assignment may accept individual SeasonPilgrims, a PilgrimGroup, or a FamilyGroup, but resolves them at assignment time into individual passenger rows. Later membership changes never rewrite the manifest. Source metadata (`individual` / `pilgrim_group` / `family_group`) is preserved without a live dependency on membership. Only **Active** SeasonPilgrims are eligible. Crew are not passengers. Capacity is **count-based** (no seat maps, seat numbers, or seat inventory). SeasonVehicle carries operational capacity; a Run may set a lower override that may not exceed it. **No overbooking.** Assignment and capacity checks are transactional and concurrency-safe. Families are not required to share a Run.

### T6 — Movement / Run lifecycle and operational timeline

```text
Movement:  Draft → Planned → In Progress → Completed      (Draft | Planned → Cancelled)
Run:       Planned → Boarding → Departed → Arrived → Completed   (cancel before departure)
```

Movement status is persisted, not derived from Runs. Transportation keeps an **append-only Operational Timeline** separate from Core/Audit, recording at minimum `occurred_at`, `recorded_at`, actor, event type, and context. Backdated events and corrections require a reason; a correction appends a superseding event and never rewrites the original. Core/Audit still records security/accountability.

### T7 — Resource assignment history and overlap

Each Run keeps temporal assignment history for SeasonVehicle, Driver, and Transport Supervisor, with at most one current of each. Substitution never overwrites history; after execution has begun it requires a reason. Cancelled Runs keep their assignments. Overlapping operational assignments are prevented for the same SeasonVehicle, the same internal Driver Employee, and the same ExternalDriver, using transactions, row locking, and server-side checks (a plain UNIQUE cannot express time-range overlap on MySQL). Transport Supervisor overlap is **not** a blocking rule.

### T8 — Passenger lifecycle

```text
Assigned → Boarded → Arrived        side outcomes from Assigned: No-show, Cancelled
```

No event sourcing: the passenger row holds the current status plus timestamps. On `SeasonPilgrimCancelled`: not boarded and Run not departed → passenger Cancelled, capacity released, row kept; boarded or Run departed → Transportation **Needs Review**; already Arrived → history kept. The handler is idempotent and never deletes history.

### T9 — Transport Program entitlements

Transportation owns a small typed entitlement per SeasonProgram (read-only reference), covering **vehicle class** and **service level**. No transportation fields on SeasonProgram and no key/value schema. Vehicles / SeasonVehicles expose the matching typed classification. Normal assignment must satisfy the current entitlement when one exists; a privileged override needs a dedicated permission, a mandatory reason, and Audit. The Program / entitlement context at assignment time is preserved; later Program changes never rewrite the manifest, and an incompatible current entitlement is exposed as a mismatch rather than moving the passenger.

### T10 — Foundation integration

- A passenger row is an operational reference to the SeasonPilgrim: Transportation registers a `SeasonPilgrimOperationalReferenceChecker` (defence in depth).
- Transportation contributes to **Season Closing Readiness**. BLOCKING: Movement In Progress, Run Boarding, Run Departed, unresolved critical review. WARNING: Planned Movement never executed, unresolved non-critical review, entitlement override / mismatch. Completed and Cancelled operations without critical issues do not block.
- `SeasonPilgrimCancelled` is handled per T8; Transportation never writes back to the foundation.
- The foundation gains the **smallest** generic deletion-reference extension point so external contexts can report historical references before a hard delete, with a stable error. Deactivation is never blocked by history. The foundation imports no Transportation class.
- Group/family source metadata does not become a permanent hard FK.

## Implementation decisions (sprint 1)

Taken to build T1–T10 without adding business scope. Each is the narrowest reading of the approved rules.

| # | Decision | Why |
|---|---|---|
| I1 | A Run's operational window is its Movement's planned window (`planned_departure_at` … `planned_arrival_at`). Overlap = two Runs in `planned/boarding/departed/arrived` with intersecting windows sharing the same current SeasonVehicle, internal driver, or ExternalDriver. | T7 needs a time range; the Movement carries the schedule. Completed/Cancelled Runs no longer occupy resources. |
| I2 | Planning (fleet, movements, runs, manifests) is allowed in Draft and Active seasons; execution (Movement start, Run boarding → completion, passenger outcomes) requires an **Active** season. Closed seasons reject every Transportation write, corrections included. | Foundation: Draft = preparation, Active = operational, Closed = read-only. |
| I3 | A Movement can complete only when all its Runs are Completed or Cancelled. A Run can complete only when every passenger is Arrived, No-show, or Cancelled. | Keeps persisted statuses consistent without deriving them. |
| I4 | Cancelling a Movement cancels its (Planned) Runs; cancelling a Run cancels its Assigned passengers. A Boarding Run with Boarded passengers cannot be cancelled (T8 has no Boarded → Cancelled edge). | No history falsification. |
| I5 | A Run is created with a SeasonVehicle (T3). Driver / supervisor default to the SeasonVehicle defaults at creation, copied into assignment rows. Departure requires a current driver. | T4 actual crew vs defaults. |
| I6 | Capacity counts passengers in `assigned`, `boarded`, `arrived`. No-show and Cancelled release capacity. | T5/T8: cancelled releases capacity; a no-show did not travel. |
| I7 | `occurred_at` defaults to now; a future value is rejected; a value older than the configured tolerance (`transportation.backdate_tolerance_minutes`, default 15) is **backdated** and requires a reason plus `transportation.correct_history`. | T6 backdating needs an objective threshold; value is configurable. |
| I8 | A correction changes only the `occurred_at` of a lifecycle/outcome timeline event, appends a `event_corrected` event that supersedes it (one correction per event; corrections can themselves be corrected), and updates the matching status timestamp on the entity. | T6 append-only correction. |
| I9 | Classification values (current supported set): vehicle class `bus`, `minibus`, `car` on Vehicle; service level `standard`, `premium` on SeasonVehicle. Extending the set is a business decision. | T9 requires typed classification; smallest set. |
| I10 | Program-change mismatches are computed live (manifest `entitlement_status`, readiness WARNING) because the foundation emits no program-change event and must not be changed for it. An explicit override on an existing passenger acknowledges a mismatch (permission + reason + audit). Review items are raised by `SeasonPilgrimCancelled` conflicts. | T9/T10 without new foundation events. |
| I11 | Cancellation review severity: passenger **Boarded** (physically on the vehicle, not arrived) → `critical`; passenger Assigned/No-show on a departed Run → `non_critical`. One review per passenger and type (idempotent). | T8/T10 critical vs non-critical. |
| I12 | Asset and Contract links are single-column FKs `ON DELETE SET NULL` (those modules stay authoritative; Transportation never blocks them). An Asset links to at most one Vehicle. | T3 read-only references; ADR-0012. |
| I13 | Transportation's deletion checker blocks hard delete of a **Season** holding any Transportation data and of a **Program** referenced by an entitlement or a passenger's assignment snapshot. Group/family source metadata (id + label snapshot, no FK) does not block group/family deletion. | T10 deletion extension; no brittle FK for source metadata. |
| I14 | Transport Points: Transportation-owned only. Future domain linking is an additive, typed link (one nullable FK column or link table per linked domain, added when that domain exists). No link columns or tables exist yet. | T2: no dependency on, and no reference to, nonexistent Accommodation tables. |

## Hardening decisions (post-sprint 1)

| # | Decision | Why |
|---|---|---|
| H1 | Each passenger row stores the immutable assignment decision as typed columns: Program, entitlement evaluated, required class / service level, SeasonVehicle and its class / service level, status (`none` / `compatible` / `mismatch`), whether an override was used and its reason, plus who assigned and when. The model rejects updates to these columns. Later Program changes, entitlement edits, and SeasonVehicle / Vehicle reclassification never rewrite them. The effective override and the live status are separate. | T9: auditability of the decision as taken; no key/value snapshot. |
| H2 | The initial vehicle / driver / supervisor of a Run are written as current assignment rows at creation. Substitution ends the current row and appends a new one, so there is at most one current row per type. | T7 single history model, no implicit "initial" state. |
| H3 | Passenger assignment (individual, group, family) is all-or-nothing. Any non-eligible, duplicate, unoverridden-mismatch, or over-capacity member rejects the whole request with the failing `season_pilgrim_ids`. Nothing is skipped silently. An authorized override (permission + reason) makes mismatched members eligible. | T5/T9: no partial manifests. |
| H4 | Capacity, overlap, and duplicate checks use locking reads, because a plain read in an InnoDB REPEATABLE READ transaction can miss rows committed after the snapshot. Resources are locked before the Run's assignment-history locking read, to avoid gap-lock deadlocks. Transactions retry up to 3 times on deadlock (`TransportTransaction`). | Verified by MySQL multi-process race tests. |

## Consequences

- New module `backend/app/Modules/Transportation/` with 15 tenant-owned tables (`transport_*`), `transportation.*` permissions, audit events, and an operational timeline.
- Foundation change limited to `SeasonResourceDeletionChecker` + its tag and a check in the four foundation delete actions (stable code `SEASON_RESOURCE_REFERENCED`).
- GPS, QR, offline sync, seat maps, notifications, documents on transport records, exports, and the frontend remain out of this sprint.

## Rejected alternatives

| Alternative | Why rejected |
|---|---|
| One Movement row per bus | T1 two-level aggregate. |
| Free-text origin/destination | T2. |
| Season-only vehicle rows | T3: vehicles survive seasons. |
| Fake Employees for hired drivers / unconstrained morph driver column | T4. |
| Dynamic manifest resolved from current group membership | T5. |
| Seat tables | T5 count-based capacity. |
| Movement status derived from Runs | T6. |
| UNIQUE index for time overlap | Cannot express ranges on MySQL (T7). |
| Hard FK from passenger to group/family for source metadata | Brittle cleanup (T10). |
| Transportation imports inside foundation actions | T10: foundation knows only the contract. |

## References

- [17-transportation-and-fleet/](../09-modules/17-transportation-and-fleet/)
- [16-season-and-pilgrims/](../09-modules/16-season-and-pilgrims/)
- [CHANGE_REQUEST_PHASE2_FOUNDATION.md](../00-project/CHANGE_REQUEST_PHASE2_FOUNDATION.md)
