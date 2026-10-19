# Module: Accommodation and Camps (الإسكان والمخيمات)

> **Status:** Sprint 1 **ACCEPTED**. Sprint 2 **ACCEPTED**. Sprint 3 **ACCEPTED** ([acceptance](../../reviews/ACCOMMODATION_SPRINT3_ACCEPTANCE.md)) — Gates A–E complete. ADR-0019 remains **PROPOSED / NOT ACCEPTED**.
> **Last updated:** 2026-10-19
> Binding: [ADR-0017](../../10-decisions/ADR-0017-SEASON-PILGRIM-AND-PHASE2-BOUNDED-CONTEXTS.md). ADR-0019 is not accepted; Sprint 2 and Sprint 3 are **narrow scoped approvals**, not full ADR acceptance.

## Purpose

Accommodation is an independent bounded context and the **source of truth** for accommodation operational state: where a SeasonPilgrim is assigned and what has operationally happened (arrival, check-in, check-out, relocation) over time.

Other modules, including Transportation, do not write this state. They use explicit contracts or the **Current Accommodation Read Model**.

## Sprint 1 (accepted)

Stable facilities, optional structures, units, and positions; season allocations; non-reserving drafts; facility and supervisor scope; Phase 3 preparation UI. See [Sprint 1 acceptance](../../reviews/ACCOMMODATION_SPRINT1_ACCEPTANCE.md).

## Sprint 2 (scoped-approved)

Confirmation of eligible drafts, peak/position capacity, family exclusivity, atomic family confirm, cross-season detection-only fail-closed, capacity-decrease safety, `accommodation.confirm`, and D7 error codes. See [BUSINESS_RULES.md](BUSINESS_RULES.md) and [scoped approval](../../reviews/ACCOMMODATION_SPRINT2_SCOPED_APPROVAL.md).

**Not** in Sprint 2: check-in/out, arrival, relocation, cancellation listeners, staff cancel of confirmed, `physical_capacity` formula, role-template grants.

## Sprint 3 (ACCEPTED)

Final acceptance: [ACCOMMODATION_SPRINT3_ACCEPTANCE.md](../../reviews/ACCOMMODATION_SPRINT3_ACCEPTANCE.md).  
Gate A: [scoped approval](../../reviews/ACCOMMODATION_SPRINT3_SCOPED_APPROVAL.md).  
Gate B–D: [B](../../reviews/ACCOMMODATION_SPRINT3_GATE_B_VERIFICATION.md) · [C](../../reviews/ACCOMMODATION_SPRINT3_GATE_C_VERIFICATION.md) · [D](../../reviews/ACCOMMODATION_SPRINT3_GATE_D_VERIFICATION.md) — all **PASS**.

**Not** in Sprint 3: `SeasonPilgrimCancelled` listener, automatic draft cancel, extension, historical correction, post-Closed writes, role-template grants, ADR acceptance.

## Out of scope (platform)

- `pilgrims.current_room_id` / equivalent on SeasonPilgrim.
- Owning Family Group, Pilgrim, or Season; mutating Transportation.
- Automatic check-in when transport arrives; intentional overbooking.
- New notification channels; full platform offline mode; finance; pilgrim mobile app.

## Personas

Existing templates only. Sprint 1, Sprint 2, and Sprint 3 verbs are granted to **no** role template unless a separate approval adds grants.

## Module docs

| Doc | Role |
|---|---|
| [BUSINESS_RULES.md](BUSINESS_RULES.md) | Sprint 1 + Sprint 2 + Sprint 3 (Gate A) product rules |
| [DATA_MODEL.md](DATA_MODEL.md) | Tables and CHECKs |
| [API.md](API.md) | Routes and error codes |
| [PERMISSIONS.md](PERMISSIONS.md) | Verbs |
| [UI.md](UI.md) | Screens |
| [TEST_PLAN.md](TEST_PLAN.md) | Cases |
| [ACCEPTANCE_CRITERIA.md](ACCEPTANCE_CRITERIA.md) | Checklist |
