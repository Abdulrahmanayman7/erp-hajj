# Accommodation Sprint 2 — Final Acceptance Review

> **Date:** 2026-10-19  
> **Scope:** Integrated acceptance of the explicitly approved Sprint 2 slice (Gates A–D): confirmation, capacity integrity, D1–D7, MariaDB concurrency, and confirmation frontend.  
> **Out of scope:** Sprint 3; check-in/out; arrival; relocate/extend; staff cancel of confirmed; `SeasonPilgrimCancelled` listeners; role-template grants; `physical_capacity` formula; ADR acceptance.  
> **ADR-0019 status:** unchanged — **PROPOSED / NOT ACCEPTED**  
> **Final verdict:** **ACCEPTED** (with documented verification debt below)

This review does **not** accept ADR-0019 and does **not** authorize Sprint 3.

---

## 1. Environment and database

| Check | Result |
|---|---|
| Server | MariaDB **10.4.27** |
| PHP | 8.3.33 |
| Disposable test DB | `erp_hajj_testing` only (`phpunit.mysql.xml` forces `DB_DATABASE`) |
| Pre/post active database | `erp_hajj_testing` |
| Development DB `erp_hajj` | Table count **85** before and after (untouched) |
| `erp_hajj_testing` tables | **96** after suite |
| Destructive commands on staging/prod | Not run |
| Disk (C:) | ~226–323 MB free during this pass; TEMP/TMP → `D:\erp-hajj-1\.tmp` |
| Production frontend build | **NOT RUN** (below 2 GB headroom) |

---

## 2. Acceptance matrix

Legend:

| Evidence type | Meaning |
|---|---|
| **Browser-executed** | Real UI against live API in a browser |
| **API integration** | Pest/HTTP feature tests on MariaDB |
| **Automated frontend** | Vitest / type-check |
| **Code-reviewed only** | Static contract / source review |
| **Not executed** | Not run in this pass |

| # | Journey | Verdict | Evidence type | Notes |
|---|---|---|---|---|
| 1 | Individual draft confirmation | **PASS** | API integration | `AccommodationGateCTest` success + audit; FE wired (Vitest for API/mutation) |
| 2 | Atomic family-group confirmation | **PASS** | API integration | Family batch success path |
| 3 | Failed family confirmation rolls back all | **PASS** | API integration | Partial failure leaves all drafts |
| 4 | Same-pilgrim confirmed overlap rejection | **PASS** | API + concurrency | Feature + two-process race |
| 5 | Numeric peak-capacity enforcement | **PASS** | API + concurrency | Peak + last-capacity race |
| 6 | Position exclusivity + enabled-position ceiling | **PASS** | API + concurrency | Feature D3 + same-position race |
| 7 | Cross-season physical-unit overlap | **PASS** | API integration | D4 detection-only |
| 8 | Family exclusivity | **PASS** | API + concurrency | Feature + different-families race |
| 9 | Gender/audience confirmation restrictions | **PASS** | API integration | Unknown gender + audience mismatch |
| 10 | Draft / Closed season confirmation rejection | **PASS** | API integration | `SEASON_NOT_ACTIVE` / `SEASON_CLOSED` |
| 11 | Permission + facility-scope authorization | **PASS** | API integration | Missing confirm → 403; scope required |
| 12 | Revocation blocking new confirmation | **PASS** | API integration | Revoke then confirm forbidden |
| 13 | Capacity decrease / position deactivation protection | **PASS** | API + concurrency | D5 feature + admin races |
| 14 | Duplicate confirmation behavior | **PASS** | API integration | Idempotent re-confirm; no second audit |
| 15 | UTC half-open interval boundaries | **PASS** | API integration | Adjacent endpoint allowed; overlap rejected |
| 16 | Confirmation audit event integrity | **PASS** | API integration | `ACCOMMODATION_ASSIGNMENT_CONFIRMED` on commit only |
| — | Full browser UI journeys (1–16 in browser) | **Not executed** | Not executed | No browser E2E in this agent environment — **not** claimed as browser PASS |
| — | Production `vite build` | **Not executed** | Not executed | Disk debt; see §7 |

---

## 3. Concurrency evidence (independent connections)

All races use separate PHP processes + MariaDB InnoDB on `erp_hajj_testing` (`AccommodationConfirmConcurrencyTest` / worker).

| Race | Ran? | Result |
|---|---|---|
| Last available numeric capacity (capacity=1, two confirms) | **Yes** | One OK; loser `ACCOMMODATION_CAPACITY_CONFLICT` |
| Same pilgrim, different units | **Yes** | One OK; loser `ACCOMMODATION_PILGRIM_OVERLAP` |
| Same season position | **Yes** | One OK; loser `ACCOMMODATION_CAPACITY_CONFLICT` |
| Different families, same Family unit | **Yes** | One OK; loser `ACCOMMODATION_FAMILY_EXCLUSIVITY` |
| Confirmation vs capacity reduction | **Yes** | Exactly one succeeds; invalid (confirmed + capacity 0) never observed |
| Confirmation vs position deactivation | **Yes** | Exactly one succeeds after D5 locking-read fix |
| Gate B pilgrim-lock unique insert | **Yes** | One insert wins |

**Unverified concurrency:** browser multi-tab races; MySQL 8.0 CI image on this machine; three-way (confirm + decrease + deactivate) composites.

---

## 4. Frontend / backend contract findings

### 4.1 Match summary

| Area | Result |
|---|---|
| `POST /api/v1/accommodation/assignments/{id}/confirm` | **MATCH** (body `{}`) |
| `POST /api/v1/accommodation/assignments/confirm-family` | **MATCH** (`season_family_group_id`, `assignment_ids`) |
| No client `booking_status` write | **MATCH** (prohibited on requests; FE never sends) |
| `capacity_reserved` / `booking_status` resource | **MATCH** |
| `X-Season-Id` | **MATCH** |
| `accommodation.confirm` vocabulary (FE + catalog; zero role templates) | **MATCH** |
| Active-season UI gate (`canConfirm`) vs BE `assertActive` | **MATCH** |
| D7 error localization | **MATCH** |
| Pending-submit disable + query invalidation | **MATCH** (Vitest) |
| Cancel / check-in / occupancy workflows | **ABSENT** as required |

### 4.2 Defects fixed during Gate E

| Defect | Fix |
|---|---|
| Confirm vs position-deactivation race: both could succeed under InnoDB REPEATABLE READ because D5 “no confirmed” checks used non-locking SELECTs | `lockForUpdate()` on D5 confirmed queries; confirm re-reads season position with `lockForUpdate()` |
| Gate E concurrency matrix incomplete vs approval | Added independent-process races: same position; different families; confirm vs capacity decrease; confirm vs position deactivate |

No Foundation or Transportation business logic changed.

---

## 5. Tests and commands executed

```text
# Preflight (read-only)
php .tmp-acc-gate-e-preflight.php
# → MariaDB 10.4.27; erp_hajj_tables=85; erp_hajj_testing active

# Backend (MariaDB / erp_hajj_testing only)
php vendor/bin/pest -c phpunit.mysql.xml tests/Feature/Accommodation \
  tests/Concurrency/AccommodationConfirmConcurrencyTest.php \
  tests/Concurrency/AccommodationPilgrimLockConcurrencyTest.php
# → 38 passed (473 assertions)

# Frontend
npm test -- src/modules/accommodation   # 17 passed
npm run type-check                      # PASS

# Production build
# NOT RUN — C: free ~323 MB < 2 GB threshold
```

Postflight: `erp_hajj` still **85** tables.

---

## 6. Unexecuted checks (verification debt)

| Item | Delivery impact |
|---|---|
| Browser E2E of confirm / family confirm UX | Manual QA still required before production cutover of UI operators |
| Production `vite build` artifact this pass | Rebuild when disk allows; type-check passed; prior Gate D also NOT RUN |
| MySQL 8.0 CI image | Local MariaDB 10.4.27 proven; CI image not re-run here |
| Role-template grants | Intentionally absent; operators need explicit grants |

These gaps do **not** reverse the ACCEPTED verdict for the scoped Sprint 2 invariants (capacity, overlap, tenancy/auth via API tests), but they remain delivery debt.

---

## 7. Remaining risks and delivery debt

| Risk / debt | Notes |
|---|---|
| No staff cancel / capacity release | Confirmed stays keep consuming capacity until a future approved release slice |
| Family UI membership assist | FE selects drafts + family id; membership validated only on server |
| Family checkbox list | Current assignments page only |
| ADR-0019 | Still PROPOSED — larger product surface not authorized |
| Browser + build debt | See §6 |

---

## 8. Gate trail

| Gate | Artifact | Verdict |
|---|---|---|
| A | `ACCOMMODATION_SPRINT2_SCOPED_APPROVAL.md` | Complete (docs) |
| B | `ACCOMMODATION_SPRINT2_GATE_B_VERIFICATION.md` | PASS |
| C | `ACCOMMODATION_SPRINT2_GATE_C_VERIFICATION.md` | PASS |
| D | `ACCOMMODATION_SPRINT2_GATE_D_VERIFICATION.md` | PASS |
| E | This file | **ACCEPTED** |

---

## 9. Completion report

| Item | Detail |
|---|---|
| Files changed (Gate E) | Concurrency worker/tests; CapacityGuard + ConfirmationActions locking-read fix; this acceptance doc |
| Migrations added | None |
| Endpoints added | None |
| Permissions added | None |
| Audit events added | None |
| Tests added | Four additional independent-process races |
| Documentation updated | This acceptance report |
| Assumptions | Evidence types above; browser/build not claimed PASS |
| Remaining risks | §7 |

---

## Final verdict

**ACCEPTED**

Critical capacity, overlap, tenant/season isolation (via existing suite), and authorization invariants for the scoped Sprint 2 confirmation slice **passed** on MariaDB `erp_hajj_testing`. Browser E2E and production build remain **verification debt**, not silent passes.

Do **not** start Sprint 3 automatically. Do **not** change ADR-0019 status.
