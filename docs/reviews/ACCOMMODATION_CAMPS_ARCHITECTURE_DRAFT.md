# Accommodation & Camps — Architecture Draft

**Status:** PROPOSED — REVIEW REQUIRED; NOT AN APPROVED ADR; NO IMPLEMENTATION AUTHORIZED  
**Date:** 2026-10-15  
**Basis:** 13 preliminary product decision groups agreed in conversation; earlier pre-implementation audit as described in conversation. Repository schema and existing extension-point signatures must be verified before implementation.

## 1. Goals and boundaries

Design multi-tenant, season-aware accommodation for hotels and camps, with room/tent capacity-based or explicit position-based assignment, non-overlapping confirmed stays, family restrictions, auditable operational transitions, and concurrency-safe booking. No QR, offline, notifications, transportation-triggered check-in, or UI implementation in the initial backend slice. Do not modify locked Season & Pilgrims Foundation contracts.

## 2. Agreed preliminary product decisions

1. Hotels: rooms with numeric capacity or explicit beds. Camps: tents with numeric capacity or explicit positions. Buildings and floors optional.
2. Draft assignments do not consume capacity; no temporary holds.
3. Gregorian date-time storage with optional Hijri display; no overlapping confirmed accommodation for one pilgrim across facilities; gaps permitted. Proposed interval semantics: [start_at, end_at).
4. Facility arrival is optional before check-in. Cancellation of an assignment permitted before check-in, even after arrival. Checkout requires prior check-in. Combined relocation and check-in supported, recorded as distinct events.
5. Season participation cancellation: before check-in cancel confirmed assignment; after check-in automatically checkout and release capacity immediately, even without physical departure confirmation. Preserve history and record administrative source. Family bulk assignment creates individual assignments linked to the family group. Unknown gender blocks confirmation, including Family units.
6. Draft season: facility preparation and assignment drafts only; active season: operational confirmation and movements; closed season: read-only except separately controlled historical corrections. Open checked-in stays and unresolved critical operations block season closure.
7. Draft and confirm permissions separate; check-in, checkout, relocation separate; staff limited to assigned facilities or season-wide supervisor scope; historical correction requires independent permission, mandatory reason and audit.
8. Reject capacity conflicts; group confirmation atomic; disallow capacity reductions that conflict with any affected confirmed stay; historical correction is append-only.
9. Family units allow male/female pilgrims from same family; family may span multiple units; unknown gender cannot confirm; audience restriction defined at room/tent, with optional facility restrictions.
10. Facility and room/tent identities persist across seasons; each season allocates a subset of units with its own capacity and audience configuration; historical seasons retain their configuration.
11. Early checkout releases future reservation immediately while preserving original end; relocation atomic; extension requires capacity, overlap, and authorization checks; historical period correction through append-only correction event.
12. Family units may not be shared by different families during overlapping stays; one family member alone may occupy a Family unit; later family membership changes trigger compatibility review, not automatic cancellation.
13. Staff assignment scope is tenant + season + facility; supervisor scope is tenant + season; relocation requires source and destination scope or supervisor scope; revocation affects new operations immediately.

## 3. Proposed domain model (conceptual, not approved tables)

- Facility: tenant-owned stable hotel/camp identity.
- FacilityStructure: optional building/floor organization, with flexible parent hierarchy or typed nodes; validate against existing conventions.
- AccommodationUnit: stable room/tent identity under a facility, with unit type.
- UnitPosition: stable bed/tent-position identity where explicitly modeled.
- SeasonFacilityAllocation and SeasonUnitAllocation: select which facilities/units operate in a season; season-specific capacity mode, capacity, audience, status, and optional position availability.
- Assignment: one SeasonPilgrim, one season-unit allocation, interval with original and effective boundaries, draft/confirmed/cancelled booking state, operational state, optional explicit position, family-group reference/snapshot. Do not put current-room fields on SeasonPilgrim.
- AccommodationEvent: append-only operational timeline with occurred_at, recorded_at, actor/source, reason and correlation/idempotency key; includes arrival, check-in, checkout, relocation, cancellation, correction.
- FamilyCompatibilityReview: pending/resolved review on membership changes; reference confirmed assignments.
- StaffFacilityScope: grants tenant+season+facility access; separate season-wide supervisor scope and verb permissions.
- OperationalFailure / processing state: reliable, retryable processing of SeasonPilgrimCancelled, subject to audit of existing event delivery conventions.

**Model constraints:** All tenant-bound relations use tenant_id and composite referential checks consistent with the repository. Season-scoped entities include season_id. Prefer RESTRICT over cascading deletion for referenced operational history. Avoid unverified physical names until repo inspection.

## 4. Booking and occupancy invariants

- A draft is non-reserving. Confirmation revalidates every invariant under transactional locks.
- One pilgrim cannot have two overlapping confirmed effective intervals, even across facilities.
- A unit cannot exceed effective capacity at any instant; position-based units additionally prohibit concurrent use of the same position.
- A Family unit cannot have overlapping confirmed stays belonging to different family groups, even when numeric capacity remains.
- Check eligibility: active season, valid SeasonPilgrim status, gender known, audience compatibility, allocated unit, authorized verb and facility scope.
- The same physical unit cannot be overbooked across overlapping seasons; season-local capacity alone is insufficient.
- Group confirmation is all-or-nothing. Deterministically order locks by tenant, stable unit, pilgrim and position keys; review precise lock graph and deadlock retries against MySQL.
- Reduction of capacity or position availability must check all affected confirmed future intervals and cross-season allocations.

## 5. State machine proposal

Booking: draft -> confirmed -> cancelled (only pre-check-in); confirmed -> completed following checkout. Keep booking and operational dimensions distinct. Operational: assigned -> arrived -> checked_in -> checked_out, or assigned -> checked_in -> checked_out. 'Arrived' is optional. Cancellation of season participation while checked-in triggers administrative checkout and immediate release by explicit product decision. Do not infer physical departure. Capture checkout_source=automatic_cancellation or equivalent. Idempotent cancellation must not emit duplicate checkout events.

Early checkout ends effective future occupancy at actual checkout time while preserving original end. Relocation atomically closes old effective interval and opens new interval, with distinct timeline events; combined relocation+check-in produces separate recorded events in one transaction. Corrections append events and update a controlled projection without mutating history.

## 6. Authorization

Separate verbs for view, facilities management, draft, confirm, arrive, checkin, checkout, relocate, cancel assignment, correction, and facility scope administration. Final permission strings must be aligned with existing repo conventions. Facility scope is enforced server-side on reads and writes; supervisor grants broader **scope**, not new verbs. Revocation is checked again at the transactional mutation boundary. Batch operations must be atomic when group confirmation is requested.

## 7. Foundation integration

- Read Season, SeasonPilgrim, Pilgrim gender and family-group membership; do not mutate Foundation-owned records.
- Integrate season closing readiness via existing registered checker contract after verifying actual signature.
- Use existing SeasonPilgrimCancelled after-commit event with reliable/idempotent handler. Since cancellation is after commit, eventual processing and an explicit pending/failed critical-operation blocker are needed; do not pretend Foundation cancellation and accommodation checkout share one transaction.
- Do not let Transportation arrival auto-check-in Accommodation.
- Use existing operational/deletion reference checkers as appropriate after verifying semantics.

## 8. Season lifecycle and closure

Draft season allows setup and planning only. Active permits confirmation and operations. Closed permits reads, with proposed narrowly scoped post-close correction mechanism. Proposed closure blockers: checked-in stays, unresolved critical cancellations/transfers, unresolved family compatibility reviews, and inconsistent occupancy projections. Draft assignments generate warnings, not blockers (proposed default; decision group 14 was NOT approved). Post-close correction is append-only, separately authorized and auditable (proposed default; NOT approved).

## 9. Known risks and proposed mitigations

- **Administrative checkout without physical departure:** the system releases capacity even if the person remains onsite. This is an explicitly accepted product risk. Record source, expose an operational warning/report, and do not label it a verified physical departure.
- **Cross-season physical capacity:** lock and validate stable physical unit, not only season allocation.
- **Overlapping intervals:** MySQL uniqueness constraints do not solve arbitrary time overlaps. Validate inside transactions with shared lock protocol and independent MySQL multi-process tests.
- **Historical correction:** immutable timeline + explicit projection reconciliation, versioning and audit; corrections must not silently violate capacity.
- **Family changes:** preserve confirmed assignment; queue review; exact gating of subsequent operations is a proposed default needing documentation.
- **Optional building/floor:** avoid mandatory foreign keys on room/tent records.
- **Late checkout / backdated events:** reject impossible interval mutations or require privileged correction; define event-time and recorded-time semantics.

## 10. Proposed first backend slice

1. Repository contract verification (existing schemas, tenant/season scoping, permissions, event/closing checker signatures).
2. Facility/unit master data and season allocation; optional positions.
3. Draft and individual confirm with MySQL transactional capacity/overlap/family invariants.
4. Append-only timeline and arrival/check-in/checkout; administrative cancellation integration.
5. Scope authorization and closure readiness integration.
6. Unit/integration/concurrency tests; then family batch confirmation, relocation, extension and correction projections in separately reviewed slices.

**Not in initial slice:** UI, QR, offline, notification channels, transport automatic check-in, complex queue/hold mechanics.

## 11. Verification checklist before ADR approval

- Confirm exact table names, constraints and extension hooks from repository; do not treat conceptual names as approved schema.
- Resolve precise physical capacity semantics when the same unit is allocated to overlapping seasons with different season-specific capacities.
- Verify family-group change event availability; if none, propose reconciliation strategy rather than inventing event contracts.
- Define effective interval treatment for early checkout before planned start, late checkout, retroactive correction, and relocation into a room occupied by same pilgrim.
- Confirm post-close correction permissions and impact on closure-readiness reports; decision 14 remains unapproved.
- Specify deadlock retry, deterministic lock order, idempotency keys and multi-process MySQL concurrency tests.
- Confirm whether position inventory can change within a season and how capacity changes are historically versioned.

## 12. Approval gate

This is a design draft, **not** an approved ADR. No migrations, code, API, permissions, or UI changes are authorized by this document. After repo verification, produce a reviewable ADR and bounded-context specification, explicitly marking all remaining assumptions; request a single design approval before implementation.
