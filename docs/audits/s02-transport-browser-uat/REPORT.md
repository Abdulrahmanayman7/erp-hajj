# S02 Transportation Trips & Dispatch — Browser UAT Evidence Pack

**Date:** 2026-10-30  
**Technical verdict:** `READY FOR OWNER ACCEPTANCE` (automated UAT)  
**Owner decision:** `COMPLETE — ACCEPTED` (Project Owner, 2026-10-30)  
**Status doc:** `docs/00-project/S02_ACCEPTANCE_STATUS.md`

## Isolation (no secrets)

| Item | Value |
|---|---|
| Database | MySQL schema `erp_hajj_s02_uat` (dedicated empty schema for this UAT) |
| Production / `erp_hajj` | Not used for writes/seeds/migrations in this run |
| `.env` | Untouched — API process used shell `DB_DATABASE=erp_hajj_s02_uat` override only |
| Frontend | Vite `http://127.0.0.1:5173` |
| API | `http://127.0.0.1:8000` bound to UAT DB |
| Browser | Playwright `playwright-core` + installed Chrome channel |

Test accounts (local UAT only): `uat.owner@example.com`, `uat.viewer@example.com` (view-only).

## Backend regression (gate)

| Gate | Result |
|---|---|
| Transportation Feature Pest (MySQL `erp_hajj_testing`) | **PASS** (full Feature/Transportation suite in regression run; Breakdown/Passenger/Assignment re-confirmed 29 PASS after concurrency locking fix) |
| Transport Concurrency Pest (MySQL workers) | **7 PASS** after REPEATABLE READ locking-read fix |
| Frontend Vitest breakdown API/validation | **5 PASS** (prior gate) |
| `vue-tsc` | **PASS** (prior gate) |

### Concurrency fix applied during acceptance (blocking)

Under MySQL `REPEATABLE READ`, a plain `COUNT` / `EXISTS` after waiting on row locks could still see a **stale snapshot** and allow overbooking / dual vehicle-driver assignment.

Fixed locally (no weakened tests):

- `AddPassengers` — locking occupancy read (`lockForUpdate`) before capacity/duplicate checks
- `ResourceOverlapGuard` — `lockForUpdate` on overlap candidates
- `ExecuteReplacement` — locking occupancy reads for source/target capacity

`ConcurrencyTestCase` + `Pest.php` keep Concurrency fixtures **committed** (no wrapping transaction) so worker processes can see rows. This does not weaken assertions.

## Journey results

Latest machine report: `evidence/2026-10-30T21-37-03-316Z_report.json`  
**Verdict in JSON:** `READY FOR OWNER ACCEPTANCE`  
Product JS `pageerror`s: **0** · HTTP 5xx: **0**

| Step | Result |
|---|---|
| 0. Environment boot | PASS |
| 0b. API fixtures (season/fleet/crew/pilgrims) | PASS |
| 1. Login + RTL | PASS |
| 2. Create movement | PASS |
| 3. Create run + vehicle | PASS |
| 4. Assign crew | PASS |
| 5. Assign pilgrims | PASS |
| 6. Manifest / run detail UI | PASS |
| 7. Lifecycle boarding | PASS |
| 8. Record breakdown | PASS |
| 9. Breakdown detail UI | PASS |
| 10. Preview replacement | PASS |
| 11. Successful replacement (capacity OK) | PASS (`vehicle` matches replacement SV; T7 `vehicle_history` length 2) |
| 12. Duplicate/idempotent replace | PASS |
| 13a. Insufficient capacity without plan | PASS (`TRANSPORT_REPLACEMENT_CAPACITY_PLAN_REQUIRED`) |
| 13b. Explicit reassignment plan | PASS (`passengers_reassigned=2`) |
| 14. Invalid plan full rollback | PASS (`TRANSPORT_REPLACEMENT_PLAN_INVALID`; vehicle + open status unchanged) |
| 15. Limited user RBAC | PASS (API `403`) |
| 16. RTL on breakdowns | PASS |

Screenshots: `evidence/2026-10-30T21-37-03-316Z_*.png`.

## Safety assertions

| Check | Result |
|---|---|
| Passenger count preserved on capacity-OK replace | PASS |
| No silent passenger drop | PASS |
| Capacity never exceeded | PASS |
| Current vehicle = replacement SV | PASS |
| T7 `vehicle_history` appended | PASS (≥2 rows) |
| Plan moves land on target run | PASS |
| Invalid plan leaves breakdown open + vehicle unchanged | PASS |
| Audit log readable (breakdown/replacement events present) | PASS |

## GPS / live map (ADR-0021)

- Live GPS provider integration remains **out of scope** for S02 Owner acceptance.
- No fake live GPS/map was introduced in this UAT.
- Open Phase 2 obligation toward S10 once a real source exists.

## Harness notes (not product defects)

- Season close of a prior Active season with in-progress movements may be blocked by readiness; harness settles/cancels transport ops or reuses Active season.
- Activation of SeasonPilgrims requires a pilgrim group (`PILGRIM_GROUP_REQUIRED`).
- Transport point types are `airport|station|gathering_point|custom` (not `camp`/`hotel`).

## Owner action

**Done.** Project Owner accepted S02 on **2026-10-30** as `COMPLETE — ACCEPTED`. GPS live integration remains an open Phase 2 obligation (not part of S02 acceptance).
