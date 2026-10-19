# Accommodation Sprint 3 — Final Acceptance Review

> **Date:** 2026-10-19  
> **Scope:** Integrated acceptance of the explicitly approved Sprint 3 slice (Gates A–E): ops schema, arrive / check-in / check-out / relocate / staff cancel, occupancy including completed stays, MariaDB concurrency, and ops frontend.  
> **Prerequisites:** Sprint 1 **ACCEPTED**; Sprint 2 **ACCEPTED**; Gates B–D verification **PASS**.  
> **Out of scope:** ADR-0019 acceptance; `SeasonPilgrimCancelled` listeners; automatic draft cancel; stay extension; historical correction; post-Closed ops writes; role-template grants; `physical_capacity` formula; next sprint/module.  
> **ADR-0019 status:** unchanged — **PROPOSED / NOT ACCEPTED**  
> **Final verdict:** **ACCEPTED** (with documented verification debt below)

This review does **not** accept ADR-0019 and does **not** authorize a subsequent sprint.

---

## 1. Environment and database

| Check | Result |
|---|---|
| Server | MariaDB **10.4.27** |
| Disposable test DB | `erp_hajj_testing` only (`phpunit.mysql.xml` forces `DB_DATABASE`) |
| Preflight `erp_hajj` tables | **85** |
| Preflight `erp_hajj_testing` tables | **97** |
| Post-suite `erp_hajj` tables | **85** (untouched) |
| Post-suite `erp_hajj_testing` tables | **97** |
| Destructive commands on `erp_hajj` / staging / prod | **Not run** |
| Disk C: | ~3.9 GB free |
| Disk D: | ~20.1 GB free |
| TEMP / TMP | `D:\erp-hajj-1\.tmp` |
| npm cache | `D:\erp-hajj-1\.npm-cache` |
| Automatic cleanup | **Not performed** |

---

## 2. Commands executed and results

### Backend (MariaDB / `erp_hajj_testing`)

```text
cd D:\erp-hajj-1\backend
php vendor/bin/pest -c phpunit.mysql.xml `
  tests/Feature/Accommodation `
  tests/Concurrency/AccommodationConfirmConcurrencyTest.php `
  tests/Concurrency/AccommodationOpsConcurrencyTest.php `
  tests/Concurrency/AccommodationPilgrimLockConcurrencyTest.php --compact
```

| Metric | Result |
|---|---|
| Exit code | **0** |
| Tests | **60 passed** (775 assertions) |
| Duration | ~392 s |
| Log | `D:\erp-hajj-1\.tmp\gate-e-backend.txt` |

### Frontend

```text
cd D:\erp-hajj-1\frontend
npx vitest run src/modules/accommodation
npm run type-check
npm run build
```

| Command | Exit | Result |
|---|---|---|
| Vitest | **0** | **29/29** passed |
| `vue-tsc -b` | **0** | Clean |
| `vite build` | **0** | `✓ built in 5.49s` |

Logs: `gate-e-vitest.txt`, `gate-e-tsc.txt`, `gate-e-build.txt` under `D:\erp-hajj-1\.tmp\`.

---

## 3. Scenario-by-scenario evidence matrix

Legend:

| Evidence type | Meaning |
|---|---|
| **Browser E2E executed** | Real browser journey against live API |
| **API integration tested** | Pest/HTTP feature tests on MariaDB |
| **Independent InnoDB concurrency tested** | Separate PHP processes / DB connections |
| **Automated frontend tested** | Vitest / type-check / build |
| **Code-reviewed only** | Static contract / source review |
| **Not executed** | Not run in this pass |

| # | Scenario | Verdict | Evidence | Primary evidence |
|---|---|---|---|---|
| 1 | Draft assignment creation | **PASS** | API + FE | `AccommodationSprint1Test` draft CRUD; FE drafts API |
| 2 | Individual confirmation | **PASS** | API + FE | `AccommodationGateCTest` confirm + audit; FE confirm Vitest |
| 3 | Atomic family confirmation | **PASS** | API + FE | Family batch + rollback; FE family payload Vitest |
| 4 | Arrival recording | **PASS** | API + FE | `AccommodationSprint3GateCTest` arrive + idempotent event; FE arrive API |
| 5 | Check-in with arrival | **PASS** | API + FE | Gate C arrive → check-in path |
| 6 | Check-in without arrival | **PASS** | API + FE | Gate C direct check-in; FE check-in empty body |
| 7 | Early check-out + future capacity release | **PASS** | API + InnoDB | Gate C early checkout frees window; ops race checkout vs confirm |
| 8 | Late actual departure without auto-extend | **PASS** | API | Gate C late checkout: `actual_departed_at` set; `effective_ends_at = original_ends_at` |
| 9 | Staff cancel before check-in | **PASS** | API + InnoDB | Gate C cancel; ops race cancel vs confirm |
| 10 | Staff cancel after check-in rejected | **PASS** | API | Gate C → `ACCOMMODATION_ILLEGAL_TRANSITION` |
| 11 | Atomic relocate to another unit | **PASS** | API + FE | Gate C relocate + history FK; FE relocate payload |
| 12 | Relocate checked-in pilgrim | **PASS** | API | Gate C source → completed/checked_out; dest checked_in when `check_in_after` |
| 13 | Relocation rollback on destination conflict | **PASS** | API | Failed relocate (interval/position/audience) leaves no events/audit/partial rows |
| 14 | Same-pilgrim overlap prevention | **PASS** | API + InnoDB | Gate C feature + confirm concurrency pilgrim race |
| 15 | Numeric capacity integrity | **PASS** | API + InnoDB | Peak capacity + last-capacity confirm race |
| 16 | Position exclusivity | **PASS** | API + InnoDB | Gate C + S3 relocate position conflict; confirm position race |
| 17 | Family-unit exclusivity | **PASS** | API + InnoDB | Gate C family rules + different-families race |
| 18 | Cross-season physical-unit protection | **PASS** | API | Gate C D4 + S3 completed historical cross-season block |
| 19 | Capacity reduction protection | **PASS** | API + InnoDB | D5 feature; confirm vs decrease; relocate vs decrease |
| 20 | Position deactivation protection | **PASS** | API + InnoDB | D5 feature; confirm vs deactivate; relocate vs deactivate |
| 21 | Historical occupancy after completed stays | **PASS** | API | Gate C completed interval still blocks overlap until `effective_ends_at` |
| 22 | Cancelled assignment capacity release | **PASS** | API | Gate C cancel then confirm on capacity=1 |
| 23 | Permission enforcement | **PASS** | API + FE | Missing confirm/ops → 403; FE `PermissionGuard` + visibility helpers |
| 24 | Facility + supervisor scope | **PASS** | API | Sprint 1 scopes; confirm/ops require live scope; dual-facility relocate |
| 25 | Scope revocation behavior | **PASS** | API | Gate C revoke → confirm/ops forbidden |
| 26 | Active-only operational writes | **PASS** | API + FE | `assertActive`; `canOps` / `canConfirm` Active-only |
| 27 | Idempotent repeat requests | **PASS** | API | Arrive/check-in/check-out repeats; no duplicate events |
| 28 | Events and audit integrity | **PASS** | API | Events transactional; failed relocate no audit/event; confirm audit |
| 29 | Tenant and season isolation | **PASS** | API | Sprint 1 cross-tenant **404**; season binding; Gate B cross-tenant event FK reject |
| 30 | Frontend/backend API contract alignment | **PASS** | FE + Code | Gate D contract review + Vitest payload/error tests |

| Journey class | Status |
|---|---|
| Full browser UI E2E for scenarios 1–30 | **Not executed** — **not** claimed as browser PASS |

---

## 4. Independent InnoDB concurrency evidence

All races use separate PHP worker processes with independent connections to `erp_hajj_testing`.

### Confirmation races (`AccommodationConfirmConcurrencyTest`)

| Race | Result |
|---|---|
| Confirmation vs confirmation (capacity=1) | **Pass** — one OK / one capacity conflict |
| Same pilgrim across units | **Pass** — one OK / one pilgrim overlap |
| Same season position | **Pass** — one OK / one capacity conflict |
| Different families on Family unit | **Pass** — one OK / one family exclusivity |
| Confirmation vs capacity reduction | **Pass** — no invalid confirmed+capacity=0 state |
| Confirmation vs position deactivation | **Pass** — no confirmed on deactivated position |

### Ops races (`AccommodationOpsConcurrencyTest`)

| Race | Result |
|---|---|
| Check-out vs confirmation | **Pass** |
| Staff cancellation vs confirmation | **Pass** |
| Relocation vs confirmation (destination) | **Pass** |
| Relocation vs relocation (same dest capacity) | **Pass** — 1 ok / 1 fail; dest confirmed = 1 |
| Relocation vs capacity reduction | **Pass** |
| Relocation vs position deactivation | **Pass** |

### Pilgrim lock (`AccommodationPilgrimLockConcurrencyTest`)

| Race | Result |
|---|---|
| Concurrent pilgrim-lock insert | **Pass** — one insert wins |

**Historical occupancy under concurrency:** early checkout races prove future capacity can open without erasing completed interval contribution to peak/overlap checks (feature + race assertions).

---

## 5. Frontend / backend contract verification

| Area | Result |
|---|---|
| Ops endpoints + Gate C bodies | **MATCH** |
| Relocate `{ source, destination }` | **MATCH** |
| `X-Season-Id` | **MATCH** |
| Ops permission vocabulary (zero role templates) | **MATCH** |
| Active-season UI (`canOps`) vs BE `assertActive` | **MATCH** |
| Sprint 3 Arabic error codes | **MATCH** |
| Pending-submit + query invalidation | **MATCH** (Vitest) |
| Historical vs reserved capacity labels | **MATCH** |
| No client occupancy authority | **MATCH** |
| Sprint 2 confirm regression | **MATCH** (still green) |

---

## 6. Sprint 1 / Sprint 2 regression

| Suite slice | Result |
|---|---|
| Feature Accommodation (includes Sprint 1 + Gate B schema + Sprint 2 Gate C + Sprint 3 Gate B/C) | Contained in **60 passed** MariaDB suite |
| Confirm concurrency (Sprint 2) | Contained; all green |
| Frontend Accommodation Vitest (Sprint 2 confirm + Sprint 3 ops) | **29/29** |

No Sprint 1/2 regressions observed.

---

## 7. Defects fixed during Gate E

None. No confirmed capacity, overlap, tenant isolation, authorization, relocation atomicity, or historical-integrity failures.

---

## 8. Unexecuted browser journeys / verification debt

| Item | Note |
|---|---|
| Browser E2E | Not run in this agent environment; document as debt only |
| MySQL 8.0 CI image | Not run on this machine (MariaDB 10.4.27 verified) |
| Multi-tab browser races | Not run |
| Manual operator smoke of ops drawers | Optional; automated API + FE unit coverage present |

---

## 9. Remaining product limitations (approved exclusions)

- ADR-0019 still **PROPOSED / NOT ACCEPTED** (full ADR not product-accepted).
- No `SeasonPilgrimCancelled` → Accommodation listener (B4 deferred).
- No automatic draft cancellation (B5).
- No stay extension / historical correction / post-Closed ops writes.
- No role-template grants for accommodation.* verbs.
- No `physical_capacity` cross-season sharing formula (D4 detection-only remains).
- No Transport-triggered check-in.

---

## 10. Gate chain confirmation

| Gate | Verdict |
|---|---|
| A Scoped approval | Complete |
| B Schema / events / permissions | **PASS** |
| C Ops backend | **PASS** |
| D Ops frontend | **PASS** |
| E Final acceptance | **ACCEPTED** (this document) |

---

## 11. Final verdict

### **ACCEPTED**

Sprint 3 approved scope (operations backend + frontend, occupancy including completed stays, concurrency, and permission/scope rules) is accepted with the verification debt and product limitations above.

**ADR-0019 remains PROPOSED / NOT ACCEPTED.**

Do **not** start the next sprint or module automatically.
