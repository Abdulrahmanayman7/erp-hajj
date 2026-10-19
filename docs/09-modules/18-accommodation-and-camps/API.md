# Accommodation and Camps — API

> **Status:** Sprint 1–3 APIs implemented. Sprint 3 **ACCEPTED** (`docs/reviews/ACCOMMODATION_SPRINT3_ACCEPTANCE.md`). ADR-0019 remains **PROPOSED / NOT ACCEPTED**.
> **Last updated:** 2026-10-19

Base `/api/v1`. [API_STANDARDS.md](../../04-api/API_STANDARDS.md). Authenticated tenant routes use `season.context` (`X-Season-Id`). No `tenant_id` in the body. 401 unauthenticated, 403 missing verb or same-tenant missing scope, 404 cross-tenant or cross-season, 422 Closed/non-Active season write or domain validation.

## Sprint 1 resources (implemented)

| Resource | Verb | Writes |
|---|---|---|
| Facilities, structures, units, positions | `accommodation.manage_facilities` | Draft or Active context only. No facility-scope row required. |
| Season facilities, season units, season positions | `accommodation.manage_season_allocation` | Same guard. Capacity and audience are stored. After Sprint 2, decreases that conflict with confirmed stays are rejected (D5). |
| Draft assignments | `accommodation.draft` for create, update, and delete. `accommodation.view` or `accommodation.draft` for read | Facility or supervisor scope. Draft writes keep `booking_status=draft`. |
| Facility scopes and supervisor scopes | `accommodation.manage_scopes` | Grant and revoke (`revoked_at`). |

Reads of facilities, allocations, and drafts use `accommodation.view` or `accommodation.draft` and are filtered by scope. `manage_facilities` alone does not grant that read.

Draft create and update do not reserve capacity and may overlap. Delete is allowed only while `booking_status` is `draft`.

## Sprint 2 confirmation (Gate C — implemented)

Authority: `docs/reviews/ACCOMMODATION_SPRINT2_SCOPED_APPROVAL.md` (D1–D7).

| Method | Path | Verb | Season |
|---|---|---|---|
| `POST` | `/accommodation/assignments/{id}/confirm` | `accommodation.confirm` | Active only (`assertActive`) |
| `POST` | `/accommodation/assignments/confirm-family` | `accommodation.confirm` | Active only; atomic all-or-nothing |

`confirm-family` body (frozen Gate C): `{ "season_family_group_id": int, "assignment_ids": int[] }`.

Confirm requires facility or supervisor scope for the assignment’s facility. Scope is re-checked inside the transaction. No role-template grants.

Confirm enforces: D1 statuses; known gender; audience; D2 family rules; pilgrim confirmed non-overlap; peak / position / D3; D4 cross-season detection. Body must not set arbitrary `booking_status` (server transitions `draft` → `confirmed`). `capacity_reserved` is `true` when `booking_status` is `confirmed`.

### Stable confirmation error codes (D7)

| Code | HTTP | Meaning |
|---|---|---|
| `ACCOMMODATION_PILGRIM_OVERLAP` | 422 | Confirmed interval would overlap another confirmed stay for the same SeasonPilgrim |
| `ACCOMMODATION_CAPACITY_CONFLICT` | 422 | Unit/position over-capacity or position conflict; D3 operational capacity; D4 cross-season confirmed overlap; D5 admin capacity/position change vs confirmed |
| `ACCOMMODATION_FAMILY_EXCLUSIVITY` | 422 | Family unit missing/invalid family group, or overlapping confirmed stay with a different family snapshot |

Reuse existing season/auth codes for Closed, non-Active, 401, 403, 404.

## Sprint 3 operations (Gate C — implemented)

Authority: `docs/reviews/ACCOMMODATION_SPRINT3_SCOPED_APPROVAL.md` (B1–B5). Active season only (`assertActive`). Zero role-template grants. Verification: `docs/reviews/ACCOMMODATION_SPRINT3_GATE_C_VERIFICATION.md`.

| Method | Path | Verb |
|---|---|---|
| `POST` | `/accommodation/assignments/{id}/arrive` | `accommodation.arrive` |
| `POST` | `/accommodation/assignments/{id}/check-in` | `accommodation.check_in` |
| `POST` | `/accommodation/assignments/{id}/check-out` | `accommodation.check_out` |
| `POST` | `/accommodation/assignments/{id}/relocate` | `accommodation.relocate` |
| `POST` | `/accommodation/assignments/{id}/cancel` | `accommodation.cancel_assignment` |

Bodies and resource fields: see scoped approval §9 (`occurred_at`, relocate destination ids, optional `check_in_after`, `actual_departed_at` on responses). Relocate requires source **and** destination facility scope (or supervisor).

### Sprint 3 error codes (in addition to D7)

| Code | HTTP | Meaning |
|---|---|---|
| `ACCOMMODATION_ILLEGAL_TRANSITION` | 422 | Wrong booking/ops state for the verb |
| `ACCOMMODATION_CHECKOUT_BEFORE_START` | 422 | Checkout before `starts_at` (B1) |
| `ACCOMMODATION_RELOCATE_INTERVAL_INVALID` | 422 | Relocate time outside `[starts_at, original_ends_at]` |

D7 codes still apply to destination inventory conflicts during relocate.

## Explicitly absent (still)

- Extend, historical correction, post-Closed ops writes.
- A handler for `SeasonPilgrimCancelled` (deferred B4).
- Demand, queue, and auto-allocation payloads.
- Notification configuration, offline sync, and QR.
- Any route that writes Transportation or `SeasonPilgrim.status`.

## Hard rules that still apply

- Do not set Pilgrim current-room fields.
- Do not set `SeasonPilgrim.status`.
- Do not mark check-in because a transportation arrival was saved.
- Do not auto-extend `effective_ends_at` past `original_ends_at` on late checkout (B2).
