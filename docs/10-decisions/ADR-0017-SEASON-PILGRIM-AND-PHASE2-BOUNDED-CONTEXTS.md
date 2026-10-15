# ADR-0017 — Season, Pilgrim Foundation, and Phase 2 Bounded Contexts

> **Status:** Accepted — Season & Pilgrim foundation **implemented (backend)** 2026-10-05; Transportation backend sprint 1 implemented per [ADR-0018](ADR-0018-TRANSPORTATION-MOVEMENTS-RUNS-AND-FLEET.md); Accommodation **not implemented**
> **Date:** 2026-10-04
> **Deciders:** Phase 2 documentation sprint (approved Change Request)

## Context

The original MVP treats each Tenant as one campaign company and lists pilgrim management, transportation, and accommodation as out of scope. Phase 2 needs seasonal operations without a new Tenant per Hajj year, without a second copy of people or organization structure, and without Transportation and Accommodation writing each other’s rows.

[ADR-0003](ADR-0003-MULTI-TENANCY.md) remains binding: one database, shared schema, `tenant_id` from `TenantContext` only. This ADR does not replace it. It adds a season boundary **inside** the tenant.

[ADR-0012](ADR-0012-ASSET-CUSTODY-AND-OWNERSHIP.md) still excludes fleet operations and GPS from the Assets module. Transportation may **reference** an Asset later. It does not move custody rules into Transportation.

[ADR-0013](ADR-0013-IN-APP-NOTIFICATION-OWNERSHIP-AND-DELIVERY.md) remains the only delivery channel. Accommodation may later configure **which business events** notify whom. That configuration is not a new channel.

[ADR-0015](ADR-0015-SEMANTIC-AUDIT-TRAIL-AND-IMMUTABLE-HISTORY.md) remains the system audit trail. Accommodation also requires its own operational timeline. The two records answer different questions and must not be collapsed.

## Decision

### 1. Tenant and Season

- **Tenant** is the permanent company / campaign organization.
- A Tenant contains **many Seasons**. Do not create a Tenant per Hajj season.
- Permanent organization data (users, roles, organization units, employees, and other MVP tenant records) stays tenant-level.
- Season-specific operational data belongs to a Season.
- Season lifecycle: **Draft → Active → Closed**.
  - Draft = preparation / setup.
  - Active = operational season.
  - Closed = completed historical season.
- A tenant may keep many Closed seasons. Closing a season does not delete it.
- **At most one Active Season per tenant.**

Reverse transitions, who may change status, season identity fields, and date bounds are **TBD**.

### 2. Pilgrim and SeasonPilgrim

- **Pilgrim** is the stable identity inside the Tenant (current personal information).
- **SeasonPilgrim** is that Pilgrim’s participation in one Season.
- Transportation and Accommodation seasonal operations reference **SeasonPilgrim**, not the permanent Pilgrim row, when the operation belongs to a season.
- At most one SeasonPilgrim per Pilgrim per Season: conceptually `UNIQUE (tenant, season, pilgrim)`.
- Package or group changes update that same participation. Do not model a second registration in the same season.
- SeasonPilgrim stores a **historical identity snapshot** of values that must stay as they were during that season. Pilgrim holds current values. Do not copy every Pilgrim field. The snapshot field list is **TBD**.
- SeasonPilgrim lifecycle: **Registered → Active → Completed**, with side path **Cancelled**.
  - Registered = added to the season, still in preparation.
  - Active = participating operationally in the season.
  - Completed = participation finished; history remains.
  - Cancelled = participation will not continue, under the cancellation rules below.
- `SeasonPilgrim.status` must not encode visa, finance, accommodation, transportation, document, or payment state. Those domains own their own statuses. Transportation and Accommodation may **read** SeasonPilgrim status. They must not overwrite its lifecycle.
- Mistaken SeasonPilgrim rows that have **never** been referenced by an operational workflow, reference, or history may be deleted with the appropriate permission (permission name **TBD**).
- Once operationally referenced: hard delete is forbidden; **cancellation** is used instead and records reason, actor, and timestamp. Cancellation does not delete the Pilgrim and does not erase Transportation or Accommodation history.
- What counts as “operationally referenced”, which statuses may cancel, and the exact stable-field list are **TBD**.

### 3. Family Group

- Family Group is **season-specific** and owned by the Season / Pilgrim foundation, not by Accommodation.
- Conceptually: Season → Family Groups → SeasonPilgrims.
- Membership is optional. A pilgrim who is alone does not get a fake one-person group.
- A SeasonPilgrim belongs to **at most one** Family Group in that Season.
- A Family Group may have a **Primary Member**.
- Moving a member between groups is auditable.
- Removing or cancelling one member does not automatically delete the Family Group.
- Transportation and Accommodation consume the Family Group reference. They do not own family identity.
- How the primary member is stored, and membership history shape, are **TBD**.

### 4. Bounded contexts

| Context | Owns | Must not |
|---|---|---|
| Season / Pilgrim foundation | Season, Pilgrim, SeasonPilgrim, Family Group | Transportation or accommodation operational state |
| Transportation | Transportation operational state | Pilgrim, Season, Accommodation rows, org-structure duplicates, a second personnel model |
| Accommodation | Accommodation operational state and occupancy | Pilgrim identity, Family Group identity, Transportation rows, `current_room_id` / `current_bed_id` / `current_accommodation_id` on Pilgrim |

No domain directly mutates another domain’s owned records. Integration is an explicit application contract, event, or read model.

### 5. Accommodation occupancy

- Accommodation is the **only source of truth** for accommodation state.
- Occupancy is **time-based**. The same place may be used by different SeasonPilgrims in non-overlapping periods. One SeasonPilgrim may have sequential assignments (for example Makkah hotel, then Mina camp and bed, then Madinah hotel).
- Do not model occupancy as a single current-room foreign key on the pilgrim.
- Support **capacity-based** places (a capacity count, no bed rows required) and **position-based** places (explicit bed/place identifiers). Both respect time-based availability.
- Allocation audience includes **Male**, **Female**, and **Family**. Family identity is the foundation Family Group.
- Intentional overbooking is forbidden. A new assignment must not exceed actual capacity. A later capacity reduction may reveal an already over-capacity state; that does not authorize another assignment.
- Operational distinction, owned by Accommodation (not by `SeasonPilgrim.status`): **Assigned**, **Arrived at Facility**, **Checked-in**, **Checked-out**.
- Transport arrival does **not** automatically check the pilgrim into accommodation.
- If check-in happens at a different place than the confirmed assignment, the user workflow may relocate and check in together, but history keeps **relocation** and **check-in** as distinct events.
- Accommodation operational events may carry `occurred_at` (when it happened) and `recorded_at` (when it was entered). Business validation uses the operational occurrence time where appropriate.
- Other modules read a **Current Accommodation Read Model** produced by Accommodation. It distinguishes planned assignment from confirmed operational state (assigned but not arrived, arrived but not checked in, currently checked in, checked out with a future next assignment). It is not a writable copy on Pilgrim.
- Accommodation keeps an **operational timeline** of what happened to a pilgrim’s accommodation. That timeline is not the generic audit trail and is not full event sourcing. Relevant events are historical and must not be silently rewritten.
- Offline field operations (local queue, idempotency, pending sync, server validation, sync conflict, no last-write-wins for occupancy) are an **approved later slice**. Pending offline state on a device is not global truth until the server accepts it. Not part of the first implementation.
- QR for pilgrim, accommodation space, and position (where applicable) is an approved direction. Payload and security are **TBD**.
- Demand, phases, allocation queue, draft versus confirmed assignment, auto and incremental allocation, reserved family capacity, relocation, bulk relocation, extension, and cancellation before check-in are approved **concepts**. Exact tables and fields are **TBD** until specified without invention.

### 6. Transportation

Transportation is tenant-scoped and season-aware. It references SeasonPilgrim for seasonal pilgrim operations, existing Employee / Supervisor concepts for staff, and may reference Asset where a later rule says so. It does not own Season, Pilgrim, or Accommodation and does not mutate accommodation state. GPS and trip details are **TBD** because discovery decisions 1–30 are not transcribed in this repository.

> **Superseded detail (2026-10-05):** Transportation's entities, lifecycles, and foundation integration are now decided by approved decisions T1–T10 in [ADR-0018](ADR-0018-TRANSPORTATION-MOVEMENTS-RUNS-AND-FLEET.md). The boundaries in this section still apply. GPS remains out of scope.

### 7. What this ADR does not do

- It does not implement schema, routes, permissions, or UI.
- It does not expand MVP_SCOPE modules 1–21.
- It does not approve finance, pilgrim mobile apps, government integrations, AI, GPS tracking, or extra notification channels.

## Consequences

- `OUT_OF_SCOPE.md` no longer forbids Season/Pilgrim foundation, Transportation, or Accommodation. GPS tracking, pilgrim apps, finance, and unrelated platform items stay deferred.
- The first implementation sprint must not start while blocking TBDs in the module `DATA_MODEL.md` / `PERMISSIONS.md` files are still open (field lists, permission verbs, “operationally referenced”).
- Cross-module code must not add accommodation columns to `pilgrims` or call another module’s write actions to change owned rows.

## Implementation addendum (2026-10-05, foundation sprint — F1–F35)

The Season & Pilgrim foundation backend is implemented in `backend/app/Modules/SeasonPilgrims/`. Technical decisions taken during implementation:

1. **Season Context** is a request-scoped `SeasonContext` service set by the `season.context` route middleware from the `X-Season-Id` header, defaulting to the Active season. The header is resolved only through the tenant-scoped `Season` query (another tenant's id → 404), so it cannot bypass tenancy. Season-owned route models from a different season → 404. `season_id` is never read from payloads. Jobs use `SeasonAware` + `RestoreSeasonContext`.
2. **Database-enforced invariants** use stored generated nullable flag columns + UNIQUE indexes (one Active season per tenant, one primary family member, one current primary supervisor) and composite foreign keys (`(tenant_id, x_id)` and `(season_id, x_id)`) to make cross-tenant and cross-season references impossible at the database level.
3. **Membership shape:** pilgrim group and family group membership are nullable columns on `season_pilgrims`; supervisor assignments and program changes are history rows.
4. **Extension points for Transportation/Accommodation** are container tags (`season_pilgrims.closing_readiness`, `season_pilgrims.activation_requirements`, `season_pilgrims.operational_reference_checkers`) and the after-commit event `SeasonPilgrimCancelled`. No fake checks are registered.
5. **Audit** uses the existing `AuthorizationSecurityEvent` → `Core/Audit` pipeline (no new audit system); identity document numbers are masked.
6. **Permissions** namespace `family_groups` became `season_family_groups`; `season_programs` and `season_pilgrim_groups` were added.

### Hardening pass (2026-10-05)

7. **Child tables** are tied to their parent by composite FKs: snapshot `(tenant_id, season_pilgrim_id)`, supervisors `(tenant_id, season_pilgrim_group_id)`, program history `(tenant_id, season_id, season_pilgrim_id)` + `(season_id, from/to program)`. `season_id` was added to program history for this purpose only. The snapshot's document FK and the supervisor's employee FK stay single-column (SET NULL vs NOT NULL `tenant_id`; no Employees schema change); the actions enforce them. Verified on MySQL 8.0.46 and MariaDB 10.4.32, with explicit short FK names because of the 64-character identifier limit.
8. **Family primary** is explicit only; a group may have no primary.
9. **Closed seasons** allow exactly one write, the audited, reasoned snapshot correction; the season stays Closed.
10. **Role templates:** only `tenant_owner` receives the module permissions; a role matrix for other templates is a pending business decision.

### Transportation integration (2026-10-05, ADR-0018 T10)

11. **Deletion-reference extension:** container tag `season_pilgrims.deletion_reference_checkers` → `SeasonResourceDeletionChecker::blocksDeletion(Season|SeasonProgram|SeasonPilgrimGroup|SeasonFamilyGroup)`. The Season, Program, Pilgrim Group, and Family Group delete actions consult it after their own checks and reject with `SEASON_RESOURCE_REFERENCED`. Deactivation is never consulted. The foundation imports no Transportation class.

## Rejected alternatives

| Alternative | Why rejected |
|---|---|
| New Tenant per Hajj season | Breaks the permanent company boundary (F1). |
| Store current room / bed on Pilgrim | Duplicates mutable occupancy; conflicts with time-based sequential stays. |
| Family Group owned by Accommodation | Family identity is seasonal participation data (F8). |
| Multiple SeasonPilgrim rows per pilgrim per season for different packages | Package changes stay on one participation (F5). |
| Put accommodation or transport status on `SeasonPilgrim.status` | Each domain owns its operational status (F10). |
| Last-write-wins for offline occupancy | Forbidden for sensitive occupancy rules; later slice only. |
| New notification channel for accommodation alerts | ADR-0013; only in-app delivery exists. |

## References

- [CHANGE_REQUEST_PHASE2_FOUNDATION.md](../00-project/CHANGE_REQUEST_PHASE2_FOUNDATION.md)
- [16-season-and-pilgrims/](../09-modules/16-season-and-pilgrims/)
- [17-transportation-and-fleet/](../09-modules/17-transportation-and-fleet/)
- [18-accommodation-and-camps/](../09-modules/18-accommodation-and-camps/)
- [ADR-0003](ADR-0003-MULTI-TENANCY.md) · [ADR-0012](ADR-0012-ASSET-CUSTODY-AND-OWNERSHIP.md) · [ADR-0013](ADR-0013-IN-APP-NOTIFICATION-OWNERSHIP-AND-DELIVERY.md) · [ADR-0015](ADR-0015-SEMANTIC-AUDIT-TRAIL-AND-IMMUTABLE-HISTORY.md)
