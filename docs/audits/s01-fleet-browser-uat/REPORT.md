# S01 Fleet & Registry — Browser UAT Evidence Pack

**Date:** 2026-10-30  
**Technical verdict:** `READY FOR OWNER ACCEPTANCE` (automated UAT)  
**Owner decision:** `COMPLETE — ACCEPTED` (Project Owner, 2026-10-30)  
**Status doc:** `docs/00-project/S01_ACCEPTANCE_STATUS.md`

## Isolation (no secrets)

| Item | Value |
|---|---|
| Database | MySQL schema `erp_hajj_s01_uat` (dedicated empty schema for this UAT) |
| Production / `erp_hajj` | Not used for writes/seeds/migrations in this run |
| `.env` | Untouched — API process used shell `DB_DATABASE` override only |
| Frontend | Vite `http://localhost:5173` |
| API | `http://127.0.0.1:8000` bound to UAT DB |
| Browser | Playwright `playwright-core` + installed Chrome channel |

Test accounts (local UAT only): `uat.owner@example.com`, `uat.viewer@example.com` (view-only).

## Backend regression (gate)

| Gate | Result |
|---|---|
| Transportation Pest (Registry / SeasonFleet / Tenancy) | **24 PASS** on SQLite `:memory:` (`force=true`; not `erp_hajj`) |
| Frontend Vitest `fleetValidation.test.ts` | **19 PASS** (includes numeric `operational_capacity` coercion) |

## Journey results

Latest machine report: `evidence/2026-10-30T20-00-59-401Z_report.json`  
**Verdict in JSON:** `READY FOR OWNER ACCEPTANCE`  
JS errors: **0** · HTTP 5xx: **0**

| Step | Result |
|---|---|
| 0. Environment boot | PASS |
| 0b. API fixtures (season/employee/asset/contract) | PASS |
| 1. Login + RTL shell | PASS |
| 2. Vehicles + GPS readiness notice (no live map) | PASS |
| 3. Create + edit vehicle | PASS |
| 4. Search vehicles | PASS |
| 5. Create + edit driver | PASS |
| 6. Create transport point | PASS |
| 7. Link vehicle asset/contract | PASS (`#9` / `#9`) |
| 8. Link vehicle to season fleet | PASS |
| 9. Assign default crew | PASS |
| 10. Prevent asset conflict | PASS (`422 TRANSPORT_VEHICLE_ASSET_TAKEN`) |
| 11. Closed season blocks fleet write | PASS (`200` close + `422 SEASON_CLOSED`) |
| 12. Limited user RBAC | PASS (CTA hidden + API `403`) |
| 13. RTL + validation errors | PASS |
| 14. No fake GPS live integration | PASS (notice only; no map widgets) |

Screenshots: `evidence/2026-10-30T20-00-59-401Z_*.png`.

## GPS / live map (ADR-0018 / ADR-0021)

- Live GPS provider integration is **out of scope** for S01 Owner acceptance.
- UI shows readiness notice only (`تجهيز التتبع (GPS)`); no Backend GPS fields; no leaflet/map widgets.
- Live map remains gated for later Phase 2 readiness (S10) once a real source exists.

## Product fixes applied during UAT (journey blockers)

1. **`operational_capacity.trim` TypeError** on season-fleet form when `type="number"` v-model yields a number.
   - `frontend/src/modules/transportation/validation/fleetValidation.ts` — coerce via `String(...).trim()`
   - `SeasonFleetPage.vue` — capacity input as `type="text"` + `inputmode="numeric"` (stable string v-model)
   - Regression: Vitest case for numeric capacity

## Harness notes (not product defects)

- Fixture asset/contract/employee names are unique per run (avoids duplicate-label select races).
- Prior Active season is closed before activating the UAT season (single-Active rule).
- Closed-season write lock asserts `422 SEASON_CLOSED` (domain envelope), not `403`.

## Owner action

**Done.** Project Owner accepted S01 on **2026-10-30** as `COMPLETE — ACCEPTED`. GPS live integration remains an open Phase 2 obligation (not part of S01 acceptance).
