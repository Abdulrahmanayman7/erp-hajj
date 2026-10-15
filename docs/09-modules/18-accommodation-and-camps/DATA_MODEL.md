# Accommodation and Camps — Data Model

> **Status:** Conceptual. **No migration.** Do not treat concept names as physical tables.
> **Last updated:** 2026-10-04

Binding: [DATABASE_PRINCIPLES.md](../../03-database/DATABASE_PRINCIPLES.md) · [ADR-0017](../../10-decisions/ADR-0017-SEASON-PILGRIM-AND-PHASE2-BOUNDED-CONTEXTS.md).

## Classification

All accommodation business rows are **tenant-owned** and, when they describe a stay or plan for a season, **season-scoped** via the foundation Season. `tenant_id` immutable, FK RESTRICT. Client never supplies `tenant_id`.

No platform table.

## Conceptual records (columns TBD)

| Concept | Role | Approved constraints |
|---|---|---|
| Facility / place | Hotel, camp, room, tent, or unit — **taxonomy TBD** | Capacity-based and/or position-based |
| Position | Explicit bed or place under a position-based parent | Absent when the parent is capacity-only |
| Assignment interval | SeasonPilgrim on a place or position for a time range | Non-overlapping for the same place/position; sequential stays allowed for one SeasonPilgrim |
| Assignment operational state | Assigned, arrived at facility, checked-in, checked-out | Not stored on SeasonPilgrim |
| Operational timeline event | What happened operationally | Historical; not silently rewritten; may include `occurred_at` and `recorded_at`; relocation and check-in are distinct events |
| Demand, phase, allocation, queue, draft vs confirmed | Planning | Structures **TBD** |
| Current Accommodation read model | Derived view for other modules | Not a column on Pilgrim; pending offline work excluded until server accept |
| Notification configuration | Which business events are selected | Not a new delivery channel; schema **TBD** |
| Offline queue / conflict | Later slice | Idempotency key; not global truth while pending |
| QR | Later / TBD payload | Pilgrim, space, and position where applicable |

## References (same tenant, read/FK only)

| Target | Owner | This module writes it? |
|---|---|---|
| Season, SeasonPilgrim | Foundation | No |
| Family Group | Foundation | No |
| Employee / organization unit | Existing MVP | No, unless a future rule adds a responsible employee **TBD** |
| Transportation movement | Transportation | **No** |

## Forbidden columns

On Pilgrim or SeasonPilgrim:

- `current_room_id`
- `current_bed_id`
- `current_accommodation_id`

Any equivalent mutable “current accommodation” cache owned outside this module.

## Integrity to preserve when tables exist

- New assignment must not exceed actual capacity (no intentional overbooking).
- Overlap of two assignments on the same place or position is rejected. Enforcement (exclusion constraint vs application lock) is **TBD**.
- Capacity decrease does not delete history and does not permit a further over-capacity insert.
- Timeline rows are not updated in place to hide a past event. Correction policy **TBD**.

## Audit

`Core/Audit` for system changes (actor, before/after, correlation id). Operational timeline is additional business history, not a replacement.

## Deployment

No migration now. Future order **TBD** after columns exist: foundation Season and SeasonPilgrim tables first, then accommodation structures. Rollback **TBD** with that migration.
