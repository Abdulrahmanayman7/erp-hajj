# Accommodation Sprint 3 — Gate C Verification (Operations Backend & Capacity Release)

> **Date:** 2026-10-19  
> **Scope:** Gate C only — arrive / check-in / check-out / relocate / staff cancel APIs; occupancy filter includes `completed`; MariaDB feature + independent-connection InnoDB races.  
> **Out of scope:** Frontend ops (Gate D); Gate E residual acceptance; `SeasonPilgrimCancelled` listeners; automatic draft cancel; stay extension; historical correction; post-Closed writes; role-template grants; ADR acceptance.  
> **ADR-0019 status:** unchanged — **PROPOSED / NOT ACCEPTED**  
> **Verdict:** **PASS**

Gate D was **not** started.

---

## 1. Environment safety

| Check | Result |
|---|---|
| Test database | `erp_hajj_testing` only (`phpunit.mysql.xml` forces `DB_DATABASE`) |
| Pest preflight | Concurrency `beforeEach` asserts `SELECT DATABASE() = erp_hajj_testing` |
| Destructive ops | No `migrate:fresh` / rollback against `erp_hajj` |
| TEMP/TMP | `D:\erp-hajj-1\.tmp` (C: ~205 MB free; D: ample) |
| Foundation / Transportation | Business logic untouched |

---

## 2. Files created / modified (Gate C)

### Created (application)

| File | Role |
|---|---|
| `backend/app/Modules/Accommodation/Actions/AccommodationOperationsActions.php` | Arrive, check-in, check-out, cancel, relocate |
| `backend/app/Modules/Accommodation/Support/AccommodationEventWriter.php` | Idempotent `accommodation_events` append |
| `backend/app/Modules/Accommodation/Requests/AccommodationOpsOccurredAtRequest.php` | Arrive / check-in / check-out body |
| `backend/app/Modules/Accommodation/Requests/AccommodationRelocateRequest.php` | Relocate body |
| `backend/app/Modules/Accommodation/Requests/AccommodationCancelAssignmentRequest.php` | Staff cancel body |

### Created (tests / report)

| File | Role |
|---|---|
| `backend/tests/Feature/Accommodation/AccommodationSprint3GateCTest.php` | Feature coverage for ops + occupancy |
| `backend/tests/Concurrency/AccommodationOpsConcurrencyTest.php` | Independent-connection InnoDB races |
| `docs/reviews/ACCOMMODATION_SPRINT3_GATE_C_VERIFICATION.md` | This report |

### Modified (application)

| File | Change |
|---|---|
| `backend/app/Modules/Accommodation/Support/AccommodationCapacityGuard.php` | Reserving / overlap queries use `booking_status IN (confirmed, completed)` on `[starts_at, effective_ends_at)`; cancelled excluded; position deactivation still blocks **active** `confirmed` only |
| `backend/app/Modules/Accommodation/Controllers/AccommodationAssignmentController.php` | Ops endpoints |
| `backend/app/Modules/Accommodation/Policies/AccommodationAssignmentPolicy.php` | Ops policy verbs + dual-facility relocate |
| `backend/app/Modules/Accommodation/Resources/AccommodationAssignmentResource.php` | Ops fields (`actual_departed_at`, `relocated_from_assignment_id`, `capacity_reserved`) |
| `backend/app/Modules/Accommodation/Exceptions/AccommodationDomainException.php` | `ACCOMMODATION_ILLEGAL_TRANSITION`, `ACCOMMODATION_CHECKOUT_BEFORE_START`, `ACCOMMODATION_RELOCATE_INTERVAL_INVALID` |
| `backend/app/Core/Authorization/Events/AuthorizationSecurityEvent.php` | Ops audit event constants |
| `backend/app/Core/Authorization/PermissionCatalog.php` | Five ops verbs (Gate B); still **zero** role-template grants |
| `backend/routes/api.php` | Five ops routes under season middleware |
| `backend/tests/Concurrency/accommodation_confirm_worker.php` | Worker actions: `check_out`, `cancel`, `relocate` |
| `backend/tests/Feature/Accommodation/Helpers.php` | Shared Gate C world helpers |
| Module 18 docs (`README`, `API`, `BUSINESS_RULES`, `DATA_MODEL`, `PERMISSIONS`, `TEST_PLAN`, `ACCEPTANCE_CRITERIA`) | Gate C implemented status |

Gate B migrations / schema were **not** reopened.

---

## 3. Endpoints and permissions

| Method | Path | Permission |
|---|---|---|
| `POST` | `/api/v1/accommodation/assignments/{id}/arrive` | `accommodation.arrive` |
| `POST` | `/api/v1/accommodation/assignments/{id}/check-in` | `accommodation.check_in` |
| `POST` | `/api/v1/accommodation/assignments/{id}/check-out` | `accommodation.check_out` |
| `POST` | `/api/v1/accommodation/assignments/{id}/relocate` | `accommodation.relocate` |
| `POST` | `/api/v1/accommodation/assignments/{id}/cancel` | `accommodation.cancel_assignment` |

**Enforcement (every ops write):** `auth:sanctum` → tenant active → `season.context` → `SeasonGuard::assertActive` → permission → live facility scope or season supervisor scope (relocate: **source and destination**). Scope and assignment state rechecked inside the transaction after locks.

**Role templates:** no grants added for the five ops verbs.

---

## 4. State transitions

| From | Action | To | Notes |
|---|---|---|---|
| confirmed / null\|assigned | arrive | confirmed / arrived | Optional; repeat → idempotent |
| confirmed / null\|assigned\|arrived | check-in | confirmed / checked_in | Arrival not required; repeat → idempotent |
| confirmed / checked_in | check-out | completed / checked_out | B1/B2 clocks; staff `checkout_source` |
| confirmed / null\|assigned\|arrived | cancel | cancelled / (ops unchanged) | Pre-check-in only; drafts unchanged |
| confirmed / checked_in | cancel | **Reject** | `ACCOMMODATION_ILLEGAL_TRANSITION` |
| confirmed / * (non-terminal) | relocate | Source closed + new confirmed destination | Checked-in source → completed/checked_out; else cancelled; `relocated_from_assignment_id` on destination |
| relocate + `check_in_after` | — | Destination checked_in | Two events: `relocated` + `checked_in` |
| draft / completed / cancelled | ops (except idempotent cancel/checkout) | **Reject** | Illegal transition |

Stable errors: `ACCOMMODATION_ILLEGAL_TRANSITION`, `ACCOMMODATION_CHECKOUT_BEFORE_START`, `ACCOMMODATION_RELOCATE_INTERVAL_INVALID`, plus Sprint 2 D7 codes on destination inventory.

---

## 5. Occupancy and release logic

**Reserving filter (confirmation, peak, pilgrim overlap, position exclusivity, family, cross-season):**

`booking_status IN ('confirmed', 'completed')` overlapping `[starts_at, effective_ends_at)`.

| Case | Capacity effect |
|---|---|
| Early checkout | `effective_ends_at` ← `occurred_at`; future interval free; history kept as completed |
| Late departure | `actual_departed_at` recorded; `effective_ends_at` stays `original_ends_at` (no auto-extend) |
| Staff cancel (pre-check-in) | `cancelled` — excluded from reserving filter |
| Completed historical stay | Still occupies through `effective_ends_at` |
| Relocate | Source closed to relocate `occurred_at`; destination reserved for remaining interval |
| Position deactivation | Still rejects when an **active** `confirmed` uses the position (completed history does not block deactivation) |

---

## 6. Locking and idempotency

- Deterministic lock order via `AccommodationLockOrder` (units → pilgrim anchors → season units → positions → assignments).
- Relocate locks source + destination units/season-units/positions; rechecks source booking after locks.
- B3: destination conflict checks exclude **only** the source assignment id inside the relocate transaction.
- Application rejects `relocated_from_assignment_id === destination.id` (MariaDB CHECK cannot enforce self-ref on `AUTO_INCREMENT` id).
- Events: unique `(tenant_id, idempotency_key)`; `AccommodationEventWriter::appendOnce` treats duplicates as no-ops.
- Ops verbs use stable idempotency keys (`{type}:{assignmentId}:{occurredAt}`); repeated arrive / check-in / check-out do not duplicate events.
- All writes (assignment, events, audit) run in `AccommodationTransaction`; failures leave no partial side effects.

---

## 7. Tests and commands executed

```text
# Force testing DB in process
$env:DB_DATABASE='erp_hajj_testing'; php .tmp/acc_gatec_db_check.php
# → erp_hajj_testing

cd backend
$env:TEMP='D:\erp-hajj-1\.tmp'; $env:TMP='D:\erp-hajj-1\.tmp'
php vendor/bin/pest -c phpunit.mysql.xml `
  tests/Feature/Accommodation `
  tests/Concurrency/AccommodationOpsConcurrencyTest.php --compact
# → 53 passed (707 assertions)
```

| Suite | Result |
|---|---|
| Feature Accommodation (MariaDB, includes Gate C) | **Passed** (part of 53) |
| `AccommodationSprint3GateCTest` | Arrive/idempotent, check-in ± arrival, checkout B1/B2, cancel B4, relocate B3 + dual scope, revoked scope, completed occupancy, position/audience/cross-season on relocate, event/audit rollback |
| Ops concurrency (independent connections) | **6 passed** (see §8) |

---

## 8. Independent InnoDB race results

Workers: `tests/Concurrency/accommodation_confirm_worker.php` — separate PHP processes, each with its own DB connection to `erp_hajj_testing`. Barrier sync (`ACC_OPS_BARRIER_SEC = 15`) + commit hold (`ACC_OPS_HOLD_MS = 1200`).

| Race | Result |
|---|---|
| Checkout vs confirmation (last capacity) | **Pass** — no over-capacity confirmed set |
| Cancellation vs confirmation | **Pass** — ≤1 active confirmed on unit |
| Relocate vs confirmation into destination | **Pass** — exactly one confirmed at destination |
| Relocate vs relocate (same destination capacity) | **Pass** — 1 ok / 1 fail; dest confirmed = 1 |
| Relocate vs capacity reduction | **Pass** — never `capacity=0` with confirmed dest |
| Relocate vs destination position deactivation | **Pass** — never confirmed stay on deactivated position |

No race claimed without independent connections.

---

## 9. Defects fixed during Gate C

| Issue | Fix |
|---|---|
| Occupancy counting only `confirmed` | CapacityGuard reserving / overlap queries include `completed` |
| Ops concurrency missing env/constants after Gate B worker reuse | Restored `ACC_OPS_*` + `withTenant` post-asserts; barrier extended to 15s under suite load |
| Missing relocate-vs-position-deactivation race | Added independent-connection race |
| Feature gaps (position/audience/cross-season on relocate; event/audit rollback) | Added focused Gate C feature cases |

---

## 10. Remaining risks

| Risk | Note |
|---|---|
| Gate D frontend not started | Ops verbs unused by UI until Gate D |
| Zero role-template grants | Ops require explicit grants in environments |
| Audience mismatch code | Surfaces as `ACCOMMODATION_INVALID_REFERENCE` (same as confirm) |
| MySQL 8.0 | Not separately tested (MariaDB 10.4.x) |
| C: disk pressure | Continues; TEMP/TMP on D: |
| ADR-0019 | Still PROPOSED — no status change |

---

## 11. Final verdict

### **PASS**

Sprint 3 Gate C operations backend and capacity-release semantics are verified on MariaDB / `erp_hajj_testing` with feature coverage and independent InnoDB races.

Do **not** start Gate D automatically.
