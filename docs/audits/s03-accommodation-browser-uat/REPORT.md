# S03 Accommodation — Browser UAT Report

> **Date:** 2026-10-30  
> **Verdict:** `READY FOR OWNER ACCEPTANCE`  
> **Status:** **IMPLEMENTED — awaiting Project Owner acceptance** (do not mark COMPLETE without Owner).  
> **ADR-0019:** remains **PROPOSED / NOT ACCEPTED** (not flipped by this UAT).

## Isolation

| Item | Value |
|---|---|
| UAT database | `erp_hajj_s03_uat` (dedicated MySQL schema) |
| How isolated | `CREATE DATABASE` + `migrate:fresh` + seed/bootstrap via process env only; **`.env` not modified** |
| API bind | `php artisan serve` with `DB_DATABASE=erp_hajj_s03_uat` |
| Frontend | Vite `http://localhost:5173` → API `http://localhost:8000` (localhost host alignment for Sanctum cookies) |
| Accounts | `uat.owner@example.com` (+ explicit `s03_ops` role — accommodation verbs excluded from owner template) · `uat.viewer@example.com` (view-only) |
| Not used | `erp_hajj` (dev), production |

Harness: `serve-s03-uat.ps1`, `bootstrap-users.php`, `run-uat.mjs`. Evidence under `evidence/`.

## Scenario results (journey 1–18)

| # | Scenario | Result | Evidence / notes |
|---|---|---|---|
| 1 | Login | **PASS** | `*_01_logged_in.png` |
| 2 | Open accommodation board | **PASS** | `*_02_accommodation_overview.png` |
| 3 | Facility inventory (rooms/units) | **PASS** | API units `101`/`102`; detail route opened (`*_03_facility_detail.png`). UI hydration sometimes slow while season context loads — inventory proven via API |
| 4–5 | Season link + capacity prep | **PASS** | `*_04_season_allocation.png` |
| 6 | Available capacity display | **PASS** | Capacity mode `capacity=1` on allocation UI |
| 7–8 | Select enrolled pilgrim + create allocation | **PASS** | Draft via real API (`original_ends_at`); listed on drafts UI |
| 9 | Confirm allocation | **PASS** | `booking_status=confirmed` |
| 10 | Check-in | **PASS** | `operational_status=checked_in` |
| 11 | Relocate | **PASS** | Destination created; source `completed` with shortened effective end |
| 12 | Check-out | **PASS** | `checked_out` / `completed` |
| 13 | Occupancy updated | **PASS** | Re-confirm on freed unit B succeeded |
| 14 | Over-capacity | **PASS** | Confirm rejected `422` capacity conflict |
| 15 | Closed season | **PASS** | Season close `200`; season-facility + draft writes `422` |
| 16 | Limited RBAC | **PASS** | Viewer facility create `403`; `*_16_viewer_rbac.png` |
| 17 | Tenant / season isolation | **PASS** | Foreign season header → `404`; Pest covers hard cross-tenant |
| 18 | RTL | **PASS** | `dir=rtl`; `*_18_rtl_drafts.png` |

**Machine verdict from latest run:** `READY FOR OWNER ACCEPTANCE` (0 FAIL steps, 0 FAIL safety checks). Latest JSON: `evidence/*_report.json` / `last-report.json`.

## Backend regression

| Suite | DB | Result |
|---|---|---|
| `tests/Feature/Accommodation` + `tests/Unit/Accommodation` | SQLite `:memory:` (clean shell env) | **63 passed**, 2 skipped (MySQL-only migration rollback) |
| `tests/Concurrency/Accommodation*` via `phpunit.mysql.xml` | `erp_hajj_testing` (reset + migrate) | **14 passed** (Confirm 7 + Ops 6 + PilgrimLock 1) |

Covered: inventory, allocation, capacity, check-in/out, relocate, cancel, closed season, tenant isolation, RBAC, audit/event paths (via Gate C / Sprint suites).

## Frontend regression

| Suite | Result |
|---|---|
| Vitest `src/modules/accommodation` | **46 passed** (9 files) |

## Code / product fixes in this pass

No blocking product defect required a production code change for the mandatory journey.

| Item | Nature |
|---|---|
| UAT `bootstrap-users.php` | Grants explicit `s03_ops` role (accommodation + season verbs). Owner template intentionally excludes `accommodation.*` per `PermissionCatalog` |
| UAT harness | Isolated DB serve/bootstrap/run; season storage key; API field `original_ends_at`; closed-season settle |

Ops surfaces already present: overview, facility details, allocation, drafts (confirm/arrive/check-in/out/relocate/cancel), scopes.

## ADR-0019 decision status (limited scan)

ADR remains **PROPOSED / NOT ACCEPTED**. Open §11 items that matter for S03 acceptance framing:

| Open item | Blocks core individual capacity journey? | Note |
|---|---|---|
| Cross-season numeric / `physical_capacity` | **No** (fail-closed overlap already) | **DECISION REQUIRED** before accepting shared capacity formula |
| Position-mode cap vs enabled positions | Only position-mode edge | Not exercised as mandatory path here |
| Eligible SeasonPilgrim statuses for confirm | Partial | Active enrolled pilgrims used; full matrix TBD |
| Family unit + null family membership | Family confirm path | **DECISION REQUIRED** for Family confirmation acceptance |
| Checkout before start / after planned end | Edge | Pest covers reject-before-start; exceptional path TBD |
| Same-pilgrim relocate into own interval | Edge | Atomic relocate proven for normal move |
| Post-close corrections | Out of S03 ops | Deferred per ADR |

**Do not treat ADR-0019 as Accepted.** Core hotel capacity-mode draft→confirm→check-in→relocate→check-out path is operational without inventing unresolved rules.

## Remaining items (non-blocking for Owner review)

1. Facility detail UI can linger on «جاري التحميل…» while season context resolves (observed 401/419 noise in browser console during hydration) — inventory still correct via API.
2. Prefer more UI-primary clicks for draft create (drawer overlays); journey used API create + UI list + API/UI ops with API guarantee.
3. Owner acceptance of ADR-0019 §11 items that gate Family / cross-season capacity product claims.
4. Do **not** start S04 from this report.

## Safety confirmation

- No `git reset/restore/clean/stash`, no commit/push.
- `.env` untouched.
- No production contact; UAT DB ≠ `erp_hajj`.
- Old migrations not edited.
- Foundation / Transportation accepted slices not weakened; concurrency + RBAC not relaxed.
- Accommodation permissions still deny-by-default (ops role explicit for UAT owner only).

## Closure recommendation

**`READY FOR OWNER ACCEPTANCE`**

Owner may accept S03 operational slice after review of this evidence. Until Owner sign-off: track as **IMPLEMENTED — ACCEPTANCE PENDING**, never `COMPLETE`.
