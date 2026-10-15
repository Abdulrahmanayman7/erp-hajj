# Module: Transportation and Fleet (النقل والأسطول)

> **Status:** Backend sprint 1 implemented (Phase 2). Frontend **not built**.
> **Last updated:** 2026-10-05
> Binding: [ADR-0018](../../10-decisions/ADR-0018-TRANSPORTATION-MOVEMENTS-RUNS-AND-FLEET.md) (approved decisions T1–T10) · [ADR-0017](../../10-decisions/ADR-0017-SEASON-PILGRIM-AND-PHASE2-BOUNDED-CONTEXTS.md) (bounded contexts)

## Purpose

Own **transportation operational state** for a tenant and season: the vehicle registry, the season fleet, Transport Points, Movements and their Vehicle Runs, run crew, passenger manifests, Transportation program entitlements, an operational timeline, and review items.

## Scope

| In scope (sprint 1, backend) | Out of scope |
|---|---|
| Transport Points (Transportation-owned) | Linking points to Accommodation (future additive link; Accommodation not implemented) |
| Vehicle registry (tenant-level) with optional read-only Asset / Contract links | Asset custody / lifecycle, Contract lifecycle, maintenance, fuel, insurance |
| Season fleet (SeasonVehicle) with capacity, service level, default crew | GPS, telematics, maps, geofences |
| ExternalDriver (minimal hired-driver identity) | Driver HR profile, fake Employees |
| Movements → Vehicle Runs → passengers (count-based capacity) | Seat maps, seat numbers, seat inventory |
| Lifecycles, resource assignment history, overlap prevention | QR, offline sync |
| Transportation program entitlements + privileged override | Notifications, documents on transport records, exports |
| Operational timeline (append-only) + corrections | Frontend pages |
| Review items, closing readiness, cancellation handling | Writes to Accommodation or to foundation rows |

Contract category code `transportation` in the Contracts module is a **legal category**, not this domain. A Vehicle may reference a Contract read-only.

## Personas

Existing personas only. Access is permission-based (`transportation.*`). Being a Driver, a Transport Supervisor, or a Pilgrim Group Supervisor grants **no** permission. Only `tenant_owner` receives the catalog by default; other role grants await the role matrix.

## Module placement

| Layer | Path |
|---|---|
| Backend | `backend/app/Modules/Transportation/` |
| Frontend | `frontend/src/modules/transportation/` (not built) |
| Docs | `docs/09-modules/17-transportation-and-fleet/` |

## Integration

- Reads Season, SeasonPilgrim, SeasonProgram, PilgrimGroup, FamilyGroup (foundation), Employee, Asset, Contract through same-tenant references. Never writes them.
- Implements the foundation contracts `SeasonPilgrimOperationalReferenceChecker`, `SeasonClosingReadinessContributor`, `SeasonResourceDeletionChecker`, and listens to `SeasonPilgrimCancelled`.
- Never writes Accommodation. Transport arrival does not check a pilgrim in.

## Documents

[BUSINESS_RULES.md](BUSINESS_RULES.md) · [DATA_MODEL.md](DATA_MODEL.md) · [API.md](API.md) · [PERMISSIONS.md](PERMISSIONS.md) · [UI.md](UI.md) · [TEST_PLAN.md](TEST_PLAN.md) · [ACCEPTANCE_CRITERIA.md](ACCEPTANCE_CRITERIA.md)
