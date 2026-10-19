# Accommodation and Camps — Acceptance Criteria

> **Status:** Sprint 1 **ACCEPTED**. Sprint 2 **ACCEPTED**. Sprint 3 **ACCEPTED** (`docs/reviews/ACCOMMODATION_SPRINT3_ACCEPTANCE.md`). ADR-0019 remains **PROPOSED / NOT ACCEPTED**.
> **Last updated:** 2026-10-19

## Documentation sprint (already recorded)

- [x] Accommodation is the occupancy source of truth.
- [x] Time-based sequential stays and both place modes are required as occupancy rules (enforced at confirmation in Sprint 2).
- [x] Male / Female / Family audience; Family Group is not owned here.
- [x] Overbooking is forbidden once confirmation exists (Sprint 2).
- [x] Assigned / arrived / checked-in / checked-out stay distinct from `SeasonPilgrim.status`.
- [x] Transport arrival does not check in.
- [x] Relocation and check-in stay distinct events when that slice exists.
- [x] `occurred_at` and `recorded_at` are required on Sprint 3 operational timeline events (Gate B+ `accommodation_events`).
- [x] Operational timeline is not the audit trail.
- [x] No current-room columns on Pilgrim or SeasonPilgrim.
- [x] Offline and QR stay later slices.
- [x] Notifications stay the existing in-app channel.

## Sprint 1 (accepted)

- [x] Ten tables, five verbs (no role-template grants), drafts non-reserving, scope bootstrap, Phase 3 UI.
- [x] Acceptance: `docs/reviews/ACCOMMODATION_SPRINT1_ACCEPTANCE.md`.

## Sprint 2 (scoped-approved — Gate A)

Authority: product-owner decisions D1–D7.

- [x] D1: confirm only `registered` / `active`; reject `completed` / `cancelled`.
- [x] D2: Family unit requires non-null valid family group; exclusivity; atomic family confirm.
- [x] D3: position-mode operational capacity = enabled positions subject to `capacity` ceiling; position exclusivity.
- [x] D4: cross-season detection-only fail-closed; no sharing formula.
- [x] D5: reject capacity decrease / position deactivation conflicting with confirmed; Module 18 reconciled.
- [x] D6: scope limited to confirmation & capacity integrity; ADR stays PROPOSED.
- [x] D7: stable codes `ACCOMMODATION_PILGRIM_OVERLAP`, `ACCOMMODATION_CAPACITY_CONFLICT`, `ACCOMMODATION_FAMILY_EXCLUSIVITY`.
- [x] Gate B–E implementation and acceptance (`ACCOMMODATION_SPRINT2_ACCEPTANCE.md`).

## Sprint 3 (scoped-approved — Gate A)

Authority: product-owner decisions B1–B5.

- [x] B1: reject checkout before `starts_at`; do not treat as cancel.
- [x] B2: late departure recording allowed; no auto capacity extension; `actual_departed_at`; `effective_ends_at` never auto-exceeds `original_ends_at`.
- [x] B3: atomic relocate with source exclusion for self-overlap; revalidate others; preserve source history.
- [x] B4: staff pre-check-in cancel in first package; defer SeasonPilgrim cancellation listener.
- [x] B5: drafts unchanged; no automatic draft cancel.
- [x] Gate B schema / events / ops permission catalog (**PASS**).
- [x] Gate C ops backend (arrive / check-in / check-out / relocate / staff cancel) (**PASS**).
- [x] Gate D frontend ops actions (**PASS**).
- [x] Gate E final acceptance (**ACCEPTED** — `docs/reviews/ACCOMMODATION_SPRINT3_ACCEPTANCE.md`).

## Not done / excluded

- [ ] Acceptance of ADR-0019 (still PROPOSED / NOT ACCEPTED).
- [ ] Extension, historical correction, post-Closed writes.
- [ ] `SeasonPilgrimCancelled` listener (deferred B4).
- [ ] Cross-season numeric sharing formula / `physical_capacity`.
- [ ] Browser E2E journeys (verification debt in Sprint 3 acceptance).
