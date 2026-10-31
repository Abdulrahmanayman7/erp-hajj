# Accommodation and Camps — Acceptance Criteria

> **Status:** Discovery boundaries documented. Implementation not started.
> **Last updated:** 2026-10-04

## Documentation sprint

- [x] Accommodation is the occupancy source of truth.
- [x] Time-based sequential stays and both place modes are required.
- [x] Male / Female / Family audience; Family Group is not owned here.
- [x] Overbooking forbidden; capacity shortfall does not authorize another assignment.
- [x] Assigned / arrived / checked-in / checked-out distinguished from SeasonPilgrim status.
- [x] Transport arrival does not check in.
- [x] Relocation and check-in stay distinct events.
- [x] `occurred_at` and `recorded_at` documented.
- [x] Operational timeline ≠ audit trail; not full event sourcing.
- [x] Current Accommodation read model documented; no current-room columns on Pilgrim.
- [x] Offline and QR recorded as later slices; offline pending state is not global truth.
- [x] Notifications: configuration concept only; in-app delivery only.
- [x] Planning concepts listed without invented columns.
- [x] No migration, route, or permission seed.

## Implementation (later)

Blocked on TBD schemas and permission verbs. Any first slice must still respect the invariants above and include cross-tenant tests. Offline and QR are not required in the first slice.
