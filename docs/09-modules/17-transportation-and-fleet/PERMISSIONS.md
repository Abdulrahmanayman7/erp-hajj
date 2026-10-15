# Transportation and Fleet — Permissions

> **Status:** Seeded in `PermissionCatalog` (backend sprint 1).
> **Last updated:** 2026-10-05

| Permission | Purpose | High-risk |
|---|---|---|
| `transportation.view` | Read registry, fleet, entitlements, movements, runs, manifests, timeline, reviews | |
| `transportation.manage_registry` | Transport Points, Vehicles (incl. Asset/Contract links), ExternalDrivers | |
| `transportation.manage_season_fleet` | SeasonVehicles and default crew | |
| `transportation.manage_entitlements` | Program transport entitlements | |
| `transportation.manage_movements` | Create/edit/plan/cancel Movements; create/edit Runs | |
| `transportation.operate_runs` | Start/complete Movements; Run lifecycle; crew/vehicle substitution; passenger board/no-show/arrive | |
| `transportation.manage_manifest` | Add passengers; cancel passengers | |
| `transportation.override_entitlement` | Entitlement override (reason + audit) | Yes |
| `transportation.correct_history` | Backdated events and timeline corrections | Yes |
| `transportation.resolve_reviews` | Resolve review items | |

## Rules

- Deny by default; every endpoint is policy-gated. No wildcards.
- `tenant_owner` receives the full catalog. No other role template is granted Transportation permissions in this sprint (TBD: role matrix).
- Being a Driver, a Transport Supervisor, or a Pilgrim Group Supervisor grants nothing.
- Transportation resources expose no pilgrim identity numbers; identity data remains behind `season_pilgrims.view_sensitive` in the foundation.
