# S06 Part 5 — Implementation Evidence (Frontend)

> **Status:** `IMPLEMENTED — PENDING OWNER REVIEW`
> **Date:** 2026-11-07
> **Worktree:** `D:\erp-hajj-s06-season-projects` @ `feature/s06-season-projects`
> **Does not:** claim Owner acceptance, Part 6, commit/push/merge/deploy, MySQL 8, browser E2E

## Git / worktree safety

| Check | Result |
|---|---|
| Worktree | `D:/erp-hajj-s06-season-projects` |
| Branch | `feature/s06-season-projects` |
| HEAD (at Part 5 start) | `e71d7d9841d156988fecfc62034b03127d2d70e0` |
| Preserve Parts 0–4 | Yes — no reset/clean/stash/rebase/merge/switch |
| Shared DB / staging / prod | Not accessed |
| Backend schema/migrations | Not modified in Part 5 |
| Commit/push/PR | Not performed |

## Owner financial decisions (UI)

| Decision | Frontend behavior |
|---|---|
| **F-Q01 = A** | Displays backend `financials` / `valuation_basis` only |
| **F-Q02 = A** | Outstanding/remaining from GET budget only |
| **F-Q03 = A** | Verified: `tenant_owner` template includes `season_projects.manage_budget` + `link_po`; both **not** in `sensitiveExplicitOnly`; UI gates by permission (no client grant) |

### F-Q03 catalog verification (local PHP bootstrap)

```
manage_budget_in_owner=YES
link_po_in_owner=YES
complete_in_owner=NO
budget_sensitive=NO
link_po_sensitive=NO
```

## Contract matrix (actual)

| Screen / action | Endpoint | Permission | Lifecycle | Key response fields |
|---|---|---|---|---|
| List | `GET /api/v1/season-projects?status&owner_employee_id&q&page` | `season_projects.view` | season-scoped | id, number, title, status, owner_employee, progress_percent, delay_summary, budget_amount, currency_code |
| Create draft | `POST /api/v1/season-projects` | `season_projects.create` | → draft | SeasonProjectResource |
| Update | `PATCH /api/v1/season-projects/{id}` | `season_projects.update` | draft/active fields | SeasonProjectResource |
| Soft delete | `DELETE /api/v1/season-projects/{id}` | `season_projects.update` | draft only | empty / domain errors |
| Activate | `POST .../activate` | `season_projects.activate` | draft → active | owner required/invalid codes |
| Complete | `POST .../complete` | `season_projects.complete` | active → completed | freeze tasks E-Q10 |
| Cancel | `POST .../cancel` | `season_projects.cancel` | draft/active | `SEASON_PROJECT_CANCEL_HAS_OPEN_TASKS` |
| Reassign | `POST .../reassign-owner` | `season_projects.reassign_owner` | active | owner_employee |
| Phases CRUD | `/season-projects/{id}/phases` | `manage_phases` | non-terminal | phase resource |
| Tasks list/create | `/api/v1/tasks?project_id=` / `POST /tasks` | `tasks.*` + project policy | active create; completed complete/cancel only | delay_state, project_id, phase_id |
| Budget get/update | `GET/PATCH .../budget` | view / `manage_budget` | update draft/active | financials.available, outstanding, actual, remaining, valuation_basis, po_links |
| PO link/unlink | `POST/DELETE .../budget/po-links` | `link_po` (+ PO view) | draft/active | budget payload; currency mismatch 422 |

Header: `X-Season-Id` for season-project routes (via `seasonRequestOptions`).

## Routes & Sidebar

- `/app/season-projects` → `SeasonProjectsPage.vue`
- `/app/season-projects/:id` → `SeasonProjectDetailPage.vue`
- Nav: governance group → `nav.seasonProjects` when `season_projects.view`

## Files created/modified (Part 5 frontend + docs)

### Created

- `frontend/src/modules/season-projects/**` (api, types, queries, mutations, validation, pages)
- `frontend/src/locales/seasonProjectsAr.ts`
- `docs/09-modules/20-season-projects/PART5_IMPLEMENTATION_EVIDENCE.md`

### Modified

- `frontend/src/app/router/index.ts`
- `frontend/src/app/providers/index.ts`
- `frontend/src/shared/composables/useAppNavigation.ts`
- `frontend/src/modules/tasks/types/tasks.ts`
- `frontend/src/modules/tasks/queries/useTasksQuery.ts`
- `frontend/src/locales/__tests__/localizationCoverage.test.ts`
- `docs/09-modules/20-season-projects/UI.md`
- `docs/09-modules/20-season-projects/S06_IMPLEMENTATION_PLAN.md`
- `docs/09-modules/20-season-projects/PART4_IMPLEMENTATION_EVIDENCE.md` (F-Q01–03 note)
- `backend/app/Core/Authorization/PermissionCatalog.php` (F-Q03: manage_budget/link_po not sensitiveExplicitOnly — required for owner policy before exposing UI)

## Blockers / not implemented (no invented contracts)

| Item | Status |
|---|---|
| Delayed-only list filter | Absent from List API — not invented |
| List `open_tasks_count` / `delayed_tasks_count` | Absent from SeasonProjectResource — use delay_summary + tasks panel |
| Restore / reopen | Forbidden |
| Audit timeline widget | No project audit list API |
| Browser E2E | NOT RUN |
| MySQL 8 | DEFERRED (unchanged) |
| Part 6 | NOT STARTED |

## Frontend checks (executed)

| Check | Result |
|---|---|
| `npm run type-check` (`vue-tsc -b`) | **PASS** |
| ESLint script | **NOT RUN** — no `lint` script in `frontend/package.json` |
| `npm run build` | **PASS** |
| Vitest focused (`season-projects` + localizationCoverage + nav) | **PASS** |
| Vitest full suite | **105 files / 575 tests PASSED** |
| Browser E2E | **NOT RUN** |
| MySQL 8 | **DEFERRED** (unchanged) |
