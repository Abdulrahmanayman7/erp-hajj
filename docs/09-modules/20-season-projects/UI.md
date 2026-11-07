# Season Projects — UI

> **Status:** Part 5 **FUNCTIONALLY ACCEPTED** (Owner); Browser E2E **NOT RUN** (2026-11-07)
> Arabic-first, RTL, season chrome. Reuses Tasks / SeasonWorkspaceBar / AppRemoteSelect patterns.
> Financial totals are **backend-only** (F-Q01/F-Q02/E-Q07/E-Q08). Unavailable financials show reason, never zero.

## Owner financial decisions (UI must honor)

| Decision | UI behavior |
|---|---|
| **F-Q01 = A** | Display `valuation_basis` / actual from GET budget — no JS GRN valuation |
| **F-Q02 = A** | Outstanding/remaining from backend (closed PO → 0 outstanding server-side) |
| **F-Q03 = A** | `manage_budget` / `link_po` gated by permission; tenant_owner receives them via catalog (not simulated client-side) |
| **F-Q04 = A** | Unlink after posted GRN fails with `SEASON_PROJECT_PO_UNLINK_HAS_POSTED_RECEIPTS`; UI surfaces server error — no client bypass |

## 1. Navigation

- Sidebar (governance): **مشاريع الموسم** → `/app/season-projects` (`season_projects.view`).
- Season switcher (`SeasonWorkspaceBar` + `X-Season-Id`) required.
- Routes: list `/app/season-projects`, detail `/app/season-projects/:id` (auth + permission meta).

## 2. List page

- Columns: number, title, status, owner, progress %, delay_summary, budget_amount + currency when present.
- Filters supported by backend only: `status`, `owner_employee_id`, `q`, pagination.
- Soft-deleted drafts hidden by default (server).
- CTA: إنشاء مشروع → draft (**no PO required**).
- States: need_season / loading / error+retry / empty / ready.

## 3. Detail page

1. **Overview** — تفعيل / إكمال / إلغاء; owner reassignment; soft-delete only when draft; no restore/reopen.
2. **Phases** — CRUD when `manage_phases`; labels only (**E-Q03**); delete confirms tasks remain (unlink phase).
3. **Tasks** — filtered by `project_id` via Tasks API; delay badge from `delay_state`; create when project active + `tasks.create`.
4. **Budget / PO links** — GET `/budget`; edit with `manage_budget`; link/unlink with `link_po`; cross-season PO selection allowed (**E-Q04**); currency mismatch toast `SEASON_PROJECT_PO_CURRENCY_MISMATCH`; posted-GRN unlink lock (**F-Q04**) via server 422.

### Completion UX (D04 / E-Q10)

- Confirm: open tasks remain open and freeze except complete/cancel.
- After complete: banner + frozen create/edit/assign/start/progress/delete; complete/cancel still offered.

### Activation UX (D05 / E-Q11)

- Activate disabled until owner selected; server validates active same-tenant employee.

### Soft delete (E-Q14 = B)

- Delete control only on draft; no restore; server may reject with `SEASON_PROJECT_DELETE_HAS_CHILDREN`.

### Cancel (C-Q01)

- Confirm; handle `409 SEASON_PROJECT_CANCEL_HAS_OPEN_TASKS`.

## 4. MVP Tasks pages

- Unchanged for tasks without project.
- Project association from Season Project detail only in Part 5.
- Do not force project field on legacy create.

## 5. Empty / error / loading

Shared ERP patterns; cross-season/tenant mismatch → not-found (404).
Direct URL access protected by router `meta.permission`.

## 6. Blocked / not invented

| Item | Reason |
|---|---|
| Delayed-only list filter | Not in ListSeasonProjects API |
| `open_tasks_count` / `delayed_tasks_count` on list resource | Not on SeasonProjectResource (use delay_summary + tasks list) |
| Restore / reopen | Out of policy |
| Client-side remaining/commitment/actual calc | Forbidden — backend only |
| Audit activity timeline on detail | Not exposed by project APIs |
| Part 6 hardening | Not started |
