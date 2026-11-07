# Season Projects — API (Proposed)

> **Status:** UPDATED — AWAITING FINAL OWNER APPROVAL
> Base: `/api/v1` · Auth: Sanctum · Headers: tenant resolution + `X-Season-Id`
> Envelope: existing API success/error shapes · Correlation ID echoed

## 1. Projects

| Method | Path | Permission | Notes |
|---|---|---|---|
| GET | `/season-projects` | `season_projects.view` | Filter status, owner, q; exclude soft-deleted by default |
| POST | `/season-projects` | `season_projects.create` | Creates `draft`; **no PO required** |
| GET | `/season-projects/{id}` | `season_projects.view` | Phases summary, progress; budget links if any |
| PATCH | `/season-projects/{id}` | `season_projects.update` | Metadata; not status |
| POST | `/season-projects/{id}/activate` | `season_projects.activate` | D05 owner required; E-Q11 gates eligibility rules |
| POST | `/season-projects/{id}/complete` | `season_projects.complete` | D04 + E-Q10 freeze open tasks |
| POST | `/season-projects/{id}/cancel` | `season_projects.cancel` | **C-Q01:** reject while any open project task exists (`SEASON_PROJECT_CANCEL_HAS_OPEN_TASKS`); PO untouched; no auto-cancel |
| POST | `/season-projects/{id}/reassign-owner` | `season_projects.reassign_owner` | body: `owner_employee_id` |
| DELETE | `/season-projects/{id}` | `season_projects.update` or dedicated | Soft-delete **draft only** (E-Q14); 422 otherwise |

**No restore endpoint** until restore policy Owner-approved.

**404:** unknown id, other tenant, other season, soft-deleted (default).
**422:** activation without owner; invalid transitions; soft-delete non-draft; mutate frozen task fields.

## 2. Phases

| Method | Path | Permission |
|---|---|---|
| GET/POST | `/season-projects/{id}/phases` | view / `manage_phases` |
| PATCH/DELETE | `/season-projects/{id}/phases/{phaseId}` | `manage_phases` |

Phases are informational labels only (**E-Q03 = A**). Delete unlinks tasks (`phase_id` → NULL); no auto-complete/cancel of tasks.

## 3. Budget / PO links (E-Q06)

| Method | Path | Permission |
|---|---|---|
| GET | `/season-projects/{id}/budget` | `view` |
| PATCH | `/season-projects/{id}/budget` | `manage_budget` |
| POST | `/season-projects/{id}/budget/po-links` | `link_po` (+ `purchase_orders.view`) |
| DELETE | `/season-projects/{id}/budget/po-links/{linkId}` | `link_po` |

Body link: `{ "purchase_order_id": n }` — **header only**.
Reject draft/cancelled PO; reject already-linked PO (unique); cross-tenant → 404/validation fail.
E-Q04: same tenant, seasons may differ. E-Q09: currency mismatch → `SEASON_PROJECT_PO_CURRENCY_MISMATCH`.
Financials: live `outstanding_commitment` / `actual_spending` / `remaining` with `available` flag; valuation basis `posted_grn_qty_x_po_unit_price` (**F-Q01/F-Q02/E-Q07/E-Q08**).

### Unlink contract (**F-Q04 = A**)

`DELETE /season-projects/{id}/budget/po-links/{linkId}`:

| Condition | Result |
|---|---|
| Missing/unauthorized link or wrong season/tenant | 404 / 403 as applicable |
| Project terminal | 422 `SEASON_PROJECT_PO_UNLINK_NOT_ALLOWED` |
| Linked PO has **any** posted GRN | **422** `SEASON_PROJECT_PO_UNLINK_HAS_POSTED_RECEIPTS` (all roles; `link_po` / `tenant_owner` cannot bypass) |
| No posted GRN; draft/active project | 200; association removed; PO/GRN rows preserved |

Future correction of posted-receipt linkage requires a separately Owner-approved financial adjustment workflow (not S06).

## 4. Tasks (extension of existing module)

Existing `/tasks` endpoints remain. Additive filters/body:

- Query: `project_id`, `season_id`, `delay_state`, `phase_id`
- Create/update: optional `project_id`, `phase_id`

After project complete: only complete/cancel allowed on open project tasks (**E-Q10**).
Permission composition for project tasks: **E-Q12 = A** (`tasks.*` + project policy).

## 5. Error codes (stable, proposed)

`SEASON_PROJECT_INVALID_TRANSITION`
`SEASON_PROJECT_OWNER_REQUIRED`
`SEASON_PROJECT_OWNER_INVALID`
`SEASON_PROJECT_TASK_FROZEN`
`SEASON_PROJECT_TASK_PROJECT_INVALID`
`SEASON_PROJECT_TASK_PHASE_INVALID`
`SEASON_PROJECT_TASK_CREATE_NOT_ALLOWED`
`SEASON_PROJECT_CANCEL_HAS_OPEN_TASKS`
`SEASON_PROJECT_PHASE_SORT_CONFLICT`
`SEASON_PROJECT_PO_NOT_LINKABLE`
`SEASON_PROJECT_PO_ALREADY_LINKED`
`SEASON_PROJECT_PO_CURRENCY_MISMATCH`
`SEASON_PROJECT_BUDGET_UPDATE_NOT_ALLOWED`
`SEASON_PROJECT_BUDGET_CURRENCY_LOCKED`
`SEASON_PROJECT_PO_UNLINK_NOT_ALLOWED`
`SEASON_PROJECT_PO_UNLINK_HAS_POSTED_RECEIPTS` — **F-Q04 = A** (Owner-approved; permanent under normal project/PO-link ops)
`SEASON_PROJECT_DELETE_NOT_ALLOWED`
`SEASON_PROJECT_DELETE_HAS_CHILDREN`

## 6. Resources (implemented fields)

`SeasonProjectResource` (list/show): id, number, title, description, status, season_id, owner_employee_id, owner_employee, budget_amount, currency_code, progress_percent, delay_summary, activated_at/completed_at/cancelled_at, timestamps.

Budget GET payload: budget_amount, currency_code, `financials.{available,unavailable_reason,outstanding_commitment,actual_spending,remaining,valuation_basis}`, `po_links[]`.
