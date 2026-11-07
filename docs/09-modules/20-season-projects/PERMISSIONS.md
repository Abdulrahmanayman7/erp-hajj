# Season Projects — Permissions

> **Status:** FUNCTIONALLY_ACCEPTED / TECHNICAL_VALIDATION_DEFERRED (2026-11-07)
> Deny by default. No persona bypasses policies. Additive only — do not weaken existing `tasks.*`.
> **E-Q12 = A** (tasks.* + project policy). **E-Q13 = A** / **F-Q03 = A** — independent `manage_budget` / `link_po`.
> **F-Q04 = A** — `link_po` does **not** override posted-GRN unlink prohibition.

## 1. New permissions

| Permission | Intent |
|---|---|
| `season_projects.view` | List/show projects, phases, budget/link read |
| `season_projects.create` | Create draft |
| `season_projects.update` | Edit draft/active metadata; soft-delete draft (E-Q14) |
| `season_projects.activate` | draft → active (D05) |
| `season_projects.complete` | active → completed (D04 / E-Q10) — **sensitiveExplicitOnly** |
| `season_projects.cancel` | cancel project — **sensitiveExplicitOnly** |
| `season_projects.manage_phases` | Phase CRUD |
| `season_projects.manage_budget` | Set budget_amount / currency (**F-Q03**: on `tenant_owner` template; not on operational templates) |
| `season_projects.link_po` | Attach/detach S05 PO headers when allowed (**F-Q03** / **F-Q04**) |
| `season_projects.reassign_owner` | Change owner while active |

## 2. Tasks interaction — E-Q12 = A

- Reuse existing `tasks.*` permissions together with project policy + tenant/season authorization.
- **Do not** create `tasks.manage_project_tasks` (or equivalent new permission).
- MVP non-project tasks: **unchanged** `tasks.*` checks.
- Restriction allowlist after project complete (E-Q10) is a **policy/domain constraint**, not a new permission.

## 3. Template grants — E-Q13 = A / F-Q03 = A

| Permission | `sensitiveExplicitOnly` | `tenant_owner` | Operational templates (GM, procurement, …) |
|---|---|---|---|
| `manage_budget` | **No** | **Yes** (via non-sensitive `$all`) | **No** |
| `link_po` | **No** | **Yes** | **No** |
| `complete` | **Yes** | **No** (explicit only) | **No** |
| `cancel` | **Yes** | **No** (explicit only) | **No** |

Sensitive financial/lifecycle operations remain auditable.

## 4. F-Q04 — `link_po` does not override posted-GRN unlink lock

- Endpoint `DELETE .../budget/po-links/{link}` still requires `link_po` (+ same-tenant policy) to attempt unlink.
- After authorization, domain rule **F-Q04=A** rejects unlink when the linked PO has **any** posted GRN:
  - HTTP **422**
  - code **`SEASON_PROJECT_PO_UNLINK_HAS_POSTED_RECEIPTS`**
- Applies to **all** roles, including `tenant_owner`. No permission bypass.
- Future financial adjustment workflow is **out of S06 scope**.

## 5. Registration

Parts 2–6 registered keys; Part 4/6 enforce budget/PO endpoints; Part 6 hardening + Owner F-Q04 confirmation.
