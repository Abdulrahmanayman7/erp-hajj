# Accommodation and Camps — UI

> **Status:** Sprint 1–3 UI implemented. Sprint 3 **ACCEPTED** (`docs/reviews/ACCOMMODATION_SPRINT3_ACCEPTANCE.md`). ADR-0019 remains **PROPOSED / NOT ACCEPTED**.
> **Last updated:** 2026-10-19

Sprint 1 Phase 3 wires Arabic RTL screens into the existing app shell: sidebar entry, router routes, `can()` / `permissionsAny` visibility, TanStack Query, and season context (`X-Season-Id`).

## Sprint 1 surfaces (implemented)

- **Overview** — facilities list/create/activate; list reads require `accommodation.view` or `accommodation.draft` plus live scope; create uses `accommodation.manage_facilities` without a prior scope row.
- **Facility details** — structures, units, and positions. Direct reads require `view` or `draft` plus scope.
- **Season allocation** — season facilities, units, and positions; after Sprint 2 backend, capacity/position edits that conflict with confirmed stays surface `ACCOMMODATION_CAPACITY_CONFLICT`.
- **Draft assignments** — create/edit/list/delete non-reserving drafts; overlapping drafts allowed.
- **Scopes** — grant/revoke facility and season supervisor scopes.

Season bar: Draft/Active writable for preparation; Closed read-only. Confirm actions require Active season (backend `assertActive`).

## Sprint 2 surfaces (Gate D — implemented)

- Confirm action on an eligible draft (`accommodation.confirm`) on the assignments page; Active season only; explicit confirm dialog; no client `booking_status` writes.
- Atomic family-group confirmation entry point (`season_family_group_id` + `assignment_ids`).
- Map API codes: `ACCOMMODATION_PILGRIM_OVERLAP`, `ACCOMMODATION_CAPACITY_CONFLICT`, `ACCOMMODATION_FAMILY_EXCLUSIVITY`.
- Draft vs Confirmed badges; capacity reserved label after confirm.
- No check-in, check-out, arrival, relocation, or confirmed-cancel UI in the Sprint 2 slice (Sprint 3 Gate D).

## Sprint 3 surfaces (Gate D — implemented)

- Arrive / Check-in / Check-out / Relocate / Cancel (pre-check-in) on assignments, behind the matching verbs and Active season.
- Booking + operational badges; original / effective / actual departure display; historical vs reserved capacity labels (no client occupancy math).
- Relocate drawer with destination unit/position, optional `occurred_at`, optional `check_in_after`; atomic transfer copy; source/destination history after success.
- Checkout drawer explains early release vs late departure (no auto-extend).
- Map Sprint 3 codes: `ACCOMMODATION_ILLEGAL_TRANSITION`, `ACCOMMODATION_CHECKOUT_BEFORE_START`, `ACCOMMODATION_RELOCATE_INTERVAL_INVALID`, plus D7 on relocate destination conflicts.

## Explicitly absent

- Extension, historical correction, listener-driven cancel UI.
- Demand/queue/auto-allocation screens.
- Offline pending/conflict UI and QR scan.
- Notification configuration beyond existing in-app channels.

## Lookup usability notes

Season pilgrim and staff pickers use existing list APIs when the actor has `season_pilgrims.view` / `users.view`. Without those verbs, the UI falls back to numeric IDs. Season list requires `seasons.view`; otherwise the Active default season is used. Do not invent accommodation-specific lookup endpoints to bypass authorization.

## Later surfaces (not wireframes)

- Planning: demand vs capacity; allocation queue.
- Bulk operational entry point (**TBD**).
- Full timeline viewer for one SeasonPilgrim’s accommodation (distinct from the audit viewer).
- Current Accommodation Read Model surfaces for other modules.

Do not auto-check-in from a transportation screen.
