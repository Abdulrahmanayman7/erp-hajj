# Accommodation Sprint 1 — Final Acceptance Review

> **Date:** 2026-10-19  
> **Scope:** Integrated acceptance of approved Accommodation Sprint 1 (Phases 1–3): schema, APIs, policies, audit, and preparation UI.  
> **Out of scope:** Confirmation, occupancy enforcement, check-in, check-out, relocation, cancellation handling, Sprint 2.  
> **ADR-0019 status:** unchanged — **PROPOSED / NOT ACCEPTED**  
> **Final verdict:** **ACCEPTED** (with documented verification gaps below)

This review does not accept ADR-0019 and does not authorize Sprint 2.

---

## 1. Environment safety

| Check | Result |
|---|---|
| Disposable test DB only | **PASS** — Pest used `phpunit.mysql.xml` with `DB_DATABASE=erp_hajj_testing` (`force="true"`) |
| Development DB untouched | **PASS** — `erp_hajj` table count **85** before and after Pest |
| Destructive commands on staging/prod | Not run |
| Disk space before builds/tests | C: ~145–161 MB free; D: ~20 GB free. TEMP/TMP redirected to `D:\erp-hajj-1\.tmp` for Node/PHP work. No project caches deleted without need; regenerable `frontend/dist` had already been cleared earlier in Phase 3. |
| Fresh production `vite build` this pass | **NOT RE-RUN** — C: free space below comfortable build headroom (~145 MB). Prior Phase 3 build passed; `vue-tsc` re-verified after defect fixes. |

Server for this pass: MariaDB 10.4.27 (same family as Phase 2 verification). MySQL 8.0 CI image **not** run on this machine.

---

## 2. End-to-end user journeys

Legend: **Browser** = real UI against live API · **API** = Pest / HTTP feature tests · **Code** = static contract / UI review.

| Journey | Verdict | Evidence mode | Notes |
|---|---|---|---|
| Create and manage a facility | **PASS** | API | `AccommodationSprint1Test` create/update/deactivate; FE create/list wired |
| Create structures, units, positions | **PASS** | API | Hierarchy + cycle/cross-facility rejection covered |
| Allocate inventory to Draft or Active season | **PASS** | API | Allocation test + Closed write rejected |
| Grant and revoke facility / supervisor scopes | **PASS** | API | Grant, duplicate, revoke, re-grant, post-revoke denial |
| Create, edit, list, delete draft assignments | **PASS** | API | Draft CRUD + audit events |
| Overlapping drafts allowed; no capacity reserve | **PASS** | API | Explicit overlap test; `capacity_reserved = false`; stored capacity unchanged |
| Closed season read-only | **PASS** | API + Code | BE `SEASON_CLOSED` 422; FE fail-closed store + closed banner when context known |
| Tenant and season isolation | **PASS** | API | Cross-tenant facility `404`; cross-season / wrong season binding covered |
| Permission-based nav and action visibility | **PASS** | Code | Router `permissionsAny`, nav Tent, subnav, `PermissionGuard`; not browser-proven |
| 403 / 404 / 422 error handling | **PASS** | API + Code | Feature tests + FE error code map; not browser-proven |
| Full browser UI journeys (all rows above in browser) | **BLOCKED** | — | No browser automation tooling in this agent environment; journeys were **not** executed in a browser. Do not treat as browser-passed. |

---

## 3. Frontend / API contract findings

### 3.1 Match summary

| Area | Result |
|---|---|
| Route paths and HTTP methods (facilities → scopes) | **MATCH** |
| Payload field names / Resources | **MATCH** |
| Season header `X-Season-Id` | **MATCH** (omitted → Active default) |
| Meta shape `current_page/per_page/total/last_page` | **MATCH** |
| Error codes used by FE (`SEASON_CLOSED`, `ACCOMMODATION_*`, …) | **MATCH** |
| Permission verbs (five Sprint 1 verbs) | **MATCH** |

### 3.2 Confirmed Sprint 1 defects fixed in this review

| Defect | Fix |
|---|---|
| List endpoints ignored `per_page` (FE requested 50; BE always 15) → silent truncation on facility details | `AccommodationPagination::perPage()` (1–100, default 15) on all Accommodation list controllers; Pest asserts `per_page=50` |
| Season store defaulted `isWritable = true` → fail-open writes if context 403/fails | Default and `clearSeasonDetails()` set `isWritable = false`; closed banner only when season is known |
| Facility details treated `manage_facilities` as sufficient for GET | Aligned with `PERMISSIONS.md`: reads require `view`\|`draft`; route meta and queries updated |

### 3.3 Usability gaps (not authorization bypasses)

| Gap | Impact | Mitigation in UI |
|---|---|---|
| Missing `seasons.view` | Cannot list seasons; season-context authorize may fail | Active default when possible; context error copy; writes fail closed |
| Missing `season_pilgrims.view` | No pilgrim picker | Numeric `season_pilgrim_id` input |
| Missing `users.view` | No staff picker on scopes | Numeric `user_id` input |
| Client-side facility filters | Search/kind/status filter current page only | Documented in locale copy |
| `manage_facilities` without scope/`view` | Can create facilities but cannot open details until scope + read verb | By design (`PERMISSIONS.md` bootstrap) |

No accommodation-specific lookup APIs were invented.

---

## 4. UX and documentation

| Item | Action |
|---|---|
| Outdated “No Sprint 1 UI” in `UI.md` | **Updated** — Phase 3 preparation screens documented; Sprint 2 surfaces still absent |
| `ACCEPTANCE_CRITERIA.md` / `API.md` / `PERMISSIONS.md` status lines | **Aligned** with implemented Phases 1–3; ADR status untouched |
| ADR-0019 | **Not changed** — remains PROPOSED / NOT ACCEPTED |
| Arabic RTL / loading / empty / error / success | **Code-reviewed** only — pages use shared RTL shell, season bar, toasts, empty and error states; **not** browser-validated |

---

## 5. Regression and risk review

### 5.1 Commands executed (this pass)

```text
# Preflight (read-only)
php D:\erp-hajj-1\.tmp\acc_db_check.php
# → erp_hajj_testing present; erp_hajj_tables=85

# Backend (MariaDB / erp_hajj_testing only)
cd backend
php vendor/bin/pest -c phpunit.mysql.xml tests/Feature/Accommodation --compact
# → 17 passed (194 assertions)

# Frontend
cd frontend
npm test -- --run src/modules/accommodation
# → 9 passed (3 files)
npm run type-check
# → pass (after canRead rename fix)
```

Postflight: `erp_hajj_tables=85` unchanged.

### 5.2 Residual risks (unchanged / reaffirmed)

| Risk | Status |
|---|---|
| Scope revoke vs draft write concurrency (`EXISTS` + `FOR UPDATE`) | Residual — multi-process suite **not** executed |
| Structure re-parenting concurrency / cycle race | Residual — multi-worker race **not** executed |
| MySQL 8.0 CI image | **Not run** this pass; local proof remains MariaDB 10.4.27 |
| C: disk pressure | Limits large builds; use D: TEMP; free space before `vite build` |
| Browser E2E | **Unexecuted** — required for UI confidence beyond API + unit coverage |
| Capacity-decrease conflict | Out of Sprint 1 scope (preserved) |

### 5.3 Explicitly unexecuted checks

- Browser-based frontend↔backend journeys for every row in §2  
- Fresh production `vite build` on this acceptance pass  
- MySQL 8.0 CI matrix  
- Multi-process concurrency suites for scope revoke and structure re-parent  
- Manual Arabic visual QA in a real browser session  

---

## 6. Defects fixed (file list)

**Backend**

- `backend/app/Modules/Accommodation/Support/AccommodationPagination.php` (new)
- Controllers: MasterData, Allocation, Assignment, Scope — honor capped `per_page`
- `backend/tests/Feature/Accommodation/AccommodationSprint1Test.php` — `per_page=50` assertion

**Frontend**

- `seasonContextStore.ts` (+ unit tests) — fail closed
- `AccommodationSeasonBar.vue` — closed notice only when season known
- `FacilityDetailsPage.vue`, `useAccommodationQueries.ts`, `router/index.ts` — read gate = `view`\|`draft`

**Docs**

- `docs/09-modules/18-accommodation-and-camps/UI.md`
- `API.md`, `ACCEPTANCE_CRITERIA.md`, `PERMISSIONS.md` (status / UI wording only)
- This file

---

## 7. Final verdict

### **ACCEPTED**

Sprint 1 preparation scope (Phases 1–3) is accepted for merge/integration on the evidence above:

- MariaDB feature suite green on `erp_hajj_testing` only  
- Frontend unit/contract tests and TypeScript green after defect fixes  
- Documented UI.md now matches authorized Phase 3 preparation UI  
- ADR-0019 remains **PROPOSED / NOT ACCEPTED**  
- No Sprint 2 work started  

**Conditions / follow-ups (do not block this Sprint 1 acceptance):**

1. Execute browser E2E (or manual checklist) against a disposable tenant before production cutover.  
2. Re-run `vite build` when C: has adequate free space.  
3. Add MySQL 8.0 CI coverage when the shared pipeline is available.  
4. Optional hardening: replace scope `exists()+lockForUpdate` with row lock fetch if a concurrency suite ever fails.
