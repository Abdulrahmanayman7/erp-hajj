# Accommodation Sprint 2 — Gate C Verification (Confirmation Backend & Capacity Integrity)

> **Date:** 2026-10-19  
> **Scope:** Gate C only — `accommodation.confirm`, individual + atomic family confirmation, capacity/position/cross-season/pilgrim/family integrity, D5 inventory guards, D7 error codes, MariaDB concurrency.  
> **Out of scope:** Gate D UI, Gate E acceptance packaging, check-in/out, arrival, relocate, extend, staff cancel of confirmed, `SeasonPilgrimCancelled` listeners, role-template grants, `accommodation_events` timeline table, ADR acceptance.  
> **ADR-0019 status:** unchanged — **PROPOSED / NOT ACCEPTED**  
> **Verdict:** **PASS**

Gate D was **not** started.

---

## 1. Environment safety

| Check | Result |
|---|---|
| Test database | `erp_hajj_testing` only (`phpunit.mysql.xml` forces `DB_DATABASE`) |
| Preflight `SELECT VERSION()` | MariaDB **10.4.27** |
| Development DB `erp_hajj` | Table count left at **85** (unchanged; no migrate/rollback against it) |
| `erp_hajj_testing` tables | **96** after suite |
| Disk | TEMP/TMP redirected to `D:\erp-hajj-1\.tmp` |

No `migrate:fresh` against `erp_hajj`. Feature tests use `RefreshDatabase`; concurrency uses `DatabaseTruncation` on the testing connection only.

---

## 2. Files created / modified

### Created

| File | Role |
|---|---|
| `backend/app/Modules/Accommodation/Actions/AccommodationConfirmationActions.php` | Individual + atomic family confirm |
| `backend/app/Modules/Accommodation/Support/AccommodationCapacityGuard.php` | Peak / position / D3–D5 / pilgrim / family checks |
| `backend/app/Modules/Accommodation/Requests/AccommodationConfirmFamilyRequest.php` | Frozen family payload |
| `backend/tests/Feature/Accommodation/AccommodationGateCTest.php` | Feature coverage for Gate C |
| `backend/tests/Concurrency/AccommodationConfirmConcurrencyTest.php` | Two-process InnoDB races |
| `backend/tests/Concurrency/accommodation_confirm_worker.php` | Independent PDO/Laravel worker |
| `docs/reviews/ACCOMMODATION_SPRINT2_GATE_C_VERIFICATION.md` | This report |

### Modified

| File | Change |
|---|---|
| `backend/app/Core/Authorization/PermissionCatalog.php` | Seed `accommodation.confirm` (no role-template grants) |
| `backend/app/Core/Authorization/Events/AuthorizationSecurityEvent.php` | `ACCOMMODATION_ASSIGNMENT_CONFIRMED` |
| `backend/app/Modules/Accommodation/Exceptions/AccommodationDomainException.php` | D7 helpers + `errorCode()` / `errors()` |
| `backend/app/Modules/Accommodation/Actions/AccommodationSeasonUnitActions.php` | D5 capacity/deactivation guards + lock order |
| `backend/app/Modules/Accommodation/Actions/AccommodationSeasonPositionActions.php` | D5 position deactivate/delete guards |
| `backend/app/Modules/Accommodation/Policies/AccommodationAssignmentPolicy.php` | `confirm` / `confirmFamily` |
| `backend/app/Modules/Accommodation/Controllers/AccommodationAssignmentController.php` | Confirm endpoints |
| `backend/app/Modules/Accommodation/Resources/AccommodationAssignmentResource.php` | `capacity_reserved` true when confirmed |
| `backend/routes/api.php` | Confirm routes (family before `{assignment}`) |
| `backend/tests/Feature/Accommodation/AccommodationSprint1Test.php` | Six verbs; allow confirm routes |
| `backend/tests/Feature/Accommodation/AccommodationSchemaTest.php` | Expect `accommodation.confirm` |
| `docs/09-modules/18-accommodation-and-camps/API.md` | Gate C implemented + frozen family body |

Gate B schema/locks were reused unchanged (`AccommodationTransaction`, `AccommodationLockOrder`, `accommodation_pilgrim_locks`, `acc_asg_booking_ck`).

---

## 3. Permission catalog

| Verb | Role-template grants | Notes |
|---|---|---|
| `accommodation.confirm` | **None** | Catalog only; fixtures grant directly in tests |

Supervisor / facility scope still required for confirm writes; scope is re-checked with `lockForUpdate` inside the confirmation transaction.

---

## 4. Endpoints implemented

| Method | Path | Verb | Season |
|---|---|---|---|
| `POST` | `/api/v1/accommodation/assignments/{assignment}/confirm` | `accommodation.confirm` | Active (`SeasonGuard::assertActive`) |
| `POST` | `/api/v1/accommodation/assignments/confirm-family` | `accommodation.confirm` | Active; atomic |

**Family body (frozen):**

```json
{
  "season_family_group_id": 1,
  "assignment_ids": [10, 11]
}
```

No cancel / check-in / check-out / relocate / extend routes.

### D7 codes

| Code | HTTP | Used for |
|---|---|---|
| `ACCOMMODATION_PILGRIM_OVERLAP` | 422 | Same SeasonPilgrim overlapping confirmed intervals |
| `ACCOMMODATION_CAPACITY_CONFLICT` | 422 | Peak / position / D3 ceiling / D4 cross-season / D5 admin |
| `ACCOMMODATION_FAMILY_EXCLUSIVITY` | 422 | Missing/invalid family group; different family overlap; family batch mismatch |

---

## 5. Capacity and lock strategy

**Lock order (unchanged from Gate B):** stable `accommodation_units` → `accommodation_pilgrim_locks` → season units → season positions → assignments. Facility/supervisor scope rows locked via `AccommodationAccess::lockAndAssert`.

**Numeric mode:** peak concurrent **confirmed** occupancy over half-open `[starts_at, effective_ends_at)` must not exceed season-unit `capacity`. Drafts do not count. Adjacent intervals that only touch at an endpoint are allowed.

**Position mode (D3):** assignment must target an enabled season position; that position must not have an overlapping confirmed stay; unit peak uses effective cap = `min(enabled_positions, capacity_ceiling)`.

**Cross-season (D4):** after locking the stable unit, reject any overlapping **confirmed** assignment on the same `unit_id` in another season. Detection-only; no sharing formula.

**Pilgrim:** overlapping confirmed stays for the same `season_pilgrim_id` rejected; serialization via pilgrim lock anchors.

**Family (D2):** Family audience requires non-null valid Foundation `season_family_group_id` snapshotted onto the assignment; different family groups cannot overlap in one Family unit; `confirm-family` is all-or-nothing.

**D5:** season-unit capacity decrease / deactivate / delete and season-position deactivate / delete that conflict with confirmed stays return `ACCOMMODATION_CAPACITY_CONFLICT` under the same lock order.

**MVCC fix:** overlap queries use `SELECT … FOR UPDATE` so concurrent confirmations under InnoDB REPEATABLE READ see the latest committed confirmed rows after waiting on shared locks.

**No temporary hold** — capacity is reserved only by the confirmed assignment state.

**Audit:** `ACCOMMODATION_ASSIGNMENT_CONFIRMED` only for committed transitions (idempotent re-confirm of already-confirmed drafts does not re-audit).

---

## 6. Tests and commands executed

```text
php vendor/bin/pest -c phpunit.mysql.xml tests/Feature/Accommodation
php vendor/bin/pest -c phpunit.mysql.xml tests/Concurrency/AccommodationConfirmConcurrencyTest.php
php vendor/bin/pest -c phpunit.mysql.xml tests/Concurrency/AccommodationPilgrimLockConcurrencyTest.php
```

Final Accommodation Feature + Concurrency run: **34 passed** (after SchemaTest permission expectation update).

### Feature cases covered

- Successful individual confirmation + audit + idempotent re-confirm  
- Atomic family confirm success  
- Partial family failure rolls back all  
- Same-pilgrim overlap  
- Numeric peak + UTC half-open boundaries  
- Position exclusivity + enabled-position ceiling  
- Cross-season physical-unit overlap  
- Family exclusivity + gender/audience + unknown gender  
- Closed / Draft season rejection  
- Missing confirm permission  
- Revoked facility scope  
- Capacity decrease protection  
- Position deactivation protection  
- Ineligible SeasonPilgrim status  

### MariaDB concurrency results

| Race | Result |
|---|---|
| Two processes confirm into capacity=1 same unit | One `ok`, one `ACCOMMODATION_CAPACITY_CONFLICT`; one confirmed row |
| Two processes confirm same pilgrim across two units | One `ok`, one `ACCOMMODATION_PILGRIM_OVERLAP`; one confirmed row |
| Gate B pilgrim-lock unique insert race | Still PASS |

---

## 7. Defects fixed during Gate C

1. **Peak / batch mates:** family/multi-draft confirm counts overlapping batch mates toward peak so batch members cannot overbook each other.  
2. **Cross-season query:** excludes context `season_id` so same-season confirmed rows are not treated as D4.  
3. **Batch position/pilgrim pending checks:** interval-aware (non-overlapping sequential stays allowed).  
4. **InnoDB RR race:** non-locking overlap SELECTs allowed both workers to pass; fixed with `lockForUpdate()` on confirmed overlap queries.  
5. **Permission/catalog tests:** Sprint 1 / schema expectations updated for six accommodation verbs including `confirm`.

---

## 8. Remaining risks

| Risk | Notes |
|---|---|
| No staff cancel / capacity release | Confirmed stays cannot be undone in this slice; D5 blocks conflicting decreases |
| One Active season per tenant | Cross-season tests close season A before activating B; production D4 still applies whenever two seasons hold confirmed intervals |
| Family membership rules | Confirm validates stated Foundation group + pilgrim membership; does not invent sync/membership APIs |
| Frontend | Gate D not started; UI does not yet call confirm endpoints |
| ADR-0019 | Still PROPOSED — Gate C does not accept it |

---

## 9. Completion report

| Item | Detail |
|---|---|
| Files changed | See §2 |
| Migrations added | None in Gate C (Gate B schema reused) |
| Endpoints added | `POST …/confirm`, `POST …/confirm-family` |
| Permissions added | `accommodation.confirm` (catalog only) |
| Audit events added | `ACCOMMODATION_ASSIGNMENT_CONFIRMED` |
| Tests added | Gate C feature + confirm concurrency suite |
| Documentation updated | This verification + Module 18 `API.md` Gate C note |
| Assumptions | Family confirm payload frozen as `season_family_group_id` + `assignment_ids`; no temporary holds |
| Remaining risks | See §8 |

---

## Final verdict

**PASS**

Do **not** start Gate D automatically.
