# Transportation and Fleet — Acceptance Criteria

> **Status:** Backend sprint 1 (T1–T10).
> **Last updated:** 2026-10-05

## Boundaries

- [x] All data tenant-scoped; `tenant_id` never accepted from input; cross-tenant access returns `404`.
- [x] Seasonal data uses Season Context; cross-season route models return `404`; Closed seasons are read-only.
- [x] Seasonal pilgrim operations reference SeasonPilgrim; Transportation never writes foundation, Employee, Asset, Contract, or Accommodation rows.
- [x] Drivers / Transport Supervisors are Employees, except the minimal ExternalDriver; roles grant no permissions.

## T1–T10

- [x] T1: Movements contain Vehicle Runs; manifests belong to the Run.
- [x] T2: Structured Transport Points; no Accommodation dependency.
- [x] T3: Tenant Vehicle registry + SeasonVehicle; Runs reference SeasonVehicle; optional read-only Asset/Contract links; no duplicate SeasonVehicle per season.
- [x] T4: SeasonVehicle default crew; actual crew on the Run; internal (Employee) or external driver via constrained FKs; Transport Supervisor is an Employee.
- [x] T5: Manifest materialized per SeasonPilgrim; bulk sources resolved at assignment with source snapshot; Active only; count-based capacity with override ≤ vehicle capacity; no overbooking; no seats.
- [x] T6: Movement and Run lifecycles; persisted Movement status; append-only timeline with backdating / correction rules.
- [x] T7: Assignment history with one current per resource kind; reason after execution began; overlap prevention for vehicle / driver / ExternalDriver.
- [x] T8: Passenger lifecycle; `SeasonPilgrimCancelled` handling (cancel / review / preserve), idempotent.
- [x] T9: Typed Program entitlement; privileged override with reason + audit; snapshot preserved; mismatch exposed, never auto-moved.
- [x] T10: Operational reference checker; closing readiness codes; cancellation listener; generic foundation deletion-reference extension (`SEASON_RESOURCE_REFERENCED`); no hard FK for source metadata.

## Quality

- [x] Policies on every endpoint; high-risk permissions flagged.
- [x] Audit events for registry, fleet, entitlement, movement, run, crew, manifest, override, correction, review actions.
- [x] Pest suites per TEST_PLAN; SQLite + MySQL 8 verified.
- [ ] Frontend (future sprint).
- [ ] Role-template grants beyond `tenant_owner` (role matrix TBD).
