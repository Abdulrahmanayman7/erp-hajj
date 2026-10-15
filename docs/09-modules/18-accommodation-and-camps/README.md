# Module: Accommodation and Camps (الإسكان والمخيمات)

> **Status:** Specified (Phase 2). **Not implemented.** Offline and QR are later slices.
> **Last updated:** 2026-10-04
> Binding: [ADR-0017](../../10-decisions/ADR-0017-SEASON-PILGRIM-AND-PHASE2-BOUNDED-CONTEXTS.md)

## Purpose

Accommodation is an independent bounded context and the **source of truth** for accommodation operational state: where a SeasonPilgrim is assigned and what has operationally happened (arrival, check-in, check-out, relocation) over time.

Other modules, including Transportation, do not write this state. They use explicit contracts or the **Current Accommodation Read Model**.

## Scope (approved concepts)

Documented in [BUSINESS_RULES.md](BUSINESS_RULES.md):

- Time-based occupancy (sequential stays, non-overlapping use of a place).
- Capacity-based and position-based places.
- Audience: Male, Female, Family (Family Group is consumed, not owned).
- Stages/phases, demand, allocation, queue, draft and confirmed assignments, availability, capacity planning.
- Auto allocation, incremental allocation, family-aware allocation, reserved family capacity, reuse of a previous allocation as preference.
- Relocation, bulk relocation, extension, cancellation before check-in.
- Facility arrival, check-in, check-out as distinct from mere assignment.
- `occurred_at` / `recorded_at` on operational events.
- Operational timeline separate from the audit trail.
- Current Accommodation read model (planned vs confirmed).
- Notification **configuration** (business rules only). Delivery stays the existing in-app channel.
- Later: offline field queue and QR. Not this implementation sprint.

Exact tables and fields for those concepts are **TBD** where the prompt does not list columns.

## Out of scope

- `pilgrims.current_room_id`, `current_bed_id`, `current_accommodation_id` (and the same idea on SeasonPilgrim as a mutable “current place” shortcut).
- Owning Family Group, Pilgrim, or Season.
- Mutating Transportation.
- Automatic check-in when transport arrives.
- Intentional overbooking.
- New notification channels (email, SMS, WhatsApp, push).
- Full platform offline mode. Selected accommodation field operations are a **later slice** only.
- Finance, government integrations, pilgrim mobile app.
- Full event sourcing.

Contract category `hotel` remains a Contracts legal category, not room inventory.

## Personas

Existing templates only. Which roles allocate, check in, or resolve sync conflicts is **TBD**.

## Module placement (not created now)

| Layer | Path |
|---|---|
| Backend | `backend/app/Modules/Accommodation/` |
| Frontend | `frontend/src/modules/accommodation/` |
| Docs | `docs/09-modules/18-accommodation-and-camps/` |

## Documents

[BUSINESS_RULES.md](BUSINESS_RULES.md) · [DATA_MODEL.md](DATA_MODEL.md) · [API.md](API.md) · [PERMISSIONS.md](PERMISSIONS.md) · [UI.md](UI.md) · [TEST_PLAN.md](TEST_PLAN.md) · [ACCEPTANCE_CRITERIA.md](ACCEPTANCE_CRITERIA.md)
