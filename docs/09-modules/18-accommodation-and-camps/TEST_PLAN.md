# Accommodation and Camps — Test Plan

> **Status:** Sprint 1 and Sprint 2 confirmation cases are implemented (Pest / MariaDB concurrency). Sprint 3 ops cases are specified under Gate A scoped approval; not implemented until Gates B–E.
> **Last updated:** 2026-10-19

Binding: [TESTING_STRATEGY.md](../../07-testing/TESTING_STRATEGY.md) · [Sprint 3 scoped approval](../../reviews/ACCOMMODATION_SPRINT3_SCOPED_APPROVAL.md).

## Sprint 1 (required — implemented)

| Case | Expectation |
|---|---|
| Another tenant’s facility, unit, allocation, draft, or scope id | 404 |
| Allocation, draft, or scope from another season than `X-Season-Id` | 404 |
| Unauthenticated | 401 |
| Missing verb | 403 |
| Same tenant, `view` or `draft`, no live scope | 403. Lists omit that facility. |
| `manage_facilities` / `manage_scopes` bootstrap without prior scope | Succeeds |
| Supervisor scope only | Does not allow `manage_facilities` |
| `revoked_at` committed | Next draft write 403 |
| Draft / Active / Closed season guards for preparation | As Sprint 1 |
| Two overlapping drafts | Both kept; capacity unchanged |
| Draft-only CHECK / API reject non-draft booking_status (until Sprint 2 CHECK widen) | Rejected |

Fixtures grant verbs directly. No role template is expected to hold them.

## Sprint 2 (scoped-approved — required when Gates B–E run)

Use MariaDB/`phpunit.mysql.xml` (`erp_hajj_testing` only) for capacity and locking. SQLite does not prove InnoDB locks.

| Case | Expectation |
|---|---|
| Confirm `registered` / `active` on Active season | Success → `confirmed` |
| Confirm `completed` / `cancelled` | Rejected (D1) |
| Unknown gender | Rejected |
| Family unit without valid family group | `ACCOMMODATION_FAMILY_EXCLUSIVITY` |
| Different family snapshots overlapping on Family unit | `ACCOMMODATION_FAMILY_EXCLUSIVITY` |
| Atomic family batch with one ineligible member | Full rollback |
| Peak capacity staggered / over-cap | Pass / `ACCOMMODATION_CAPACITY_CONFLICT` |
| Position disabled or double-booked | `ACCOMMODATION_CAPACITY_CONFLICT` |
| D3 operational capacity = min(enabled positions, capacity ceiling) | Enforced |
| Same pilgrim two units (multi-process) | One wins; loser `ACCOMMODATION_PILGRIM_OVERLAP` |
| Cross-season overlapping confirmed on same unit | `ACCOMMODATION_CAPACITY_CONFLICT` (D4) |
| Capacity decrease / position deactivate vs confirmed | `ACCOMMODATION_CAPACITY_CONFLICT` (D5) |
| Missing `confirm` / missing scope | 403 |
| Cross-tenant | 404 |
| Draft overlapping behavior unchanged | Sprint 1 preserved |
| FE maps D7 codes | Vitest |

## Sprint 3 (Gate C backend covered; Gate D FE pending)

| Case | Expectation |
|---|---|
| Arrive then check-in / check-in without arrive | Success; Active only |
| Checkout before `starts_at` | `ACCOMMODATION_CHECKOUT_BEFORE_START` (B1) |
| Early checkout | `effective_ends_at` moved; `original_ends_at` preserved; capacity freed |
| Late checkout after `original_ends_at` | `actual_departed_at` set; `effective_ends_at` not raised (B2) |
| Cancel confirmed pre-check-in | `cancelled`; capacity released; history kept (B4) |
| Cancel after check-in | `ACCOMMODATION_ILLEGAL_TRANSITION` |
| Relocate atomic + B3 self-overlap exclusion | Destination reserved; source history kept; no invalid pilgrim overlap |
| Relocate + check-in | Two timeline events |
| Relocate missing destination scope | 403 |
| Concurrent races C1–C9 | Per scoped approval §13 |
| Transport arrival | Does not create check-in |
| Drafts unchanged on any Foundation cancel path | B5 (no listener in Sprint 3) |
| FE maps Sprint 3 codes | Vitest |

## Later slices (not Sprint 1–3)

| Case | Expectation |
|---|---|
| SeasonPilgrim cancellation listener | Later (deferred B4) |
| Extension / historical correction | Later |
| Offline and QR | Later |
