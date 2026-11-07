# S06 — Seasonal Projects & Tasks — Design Pack (Part 0)

> **Status (closure 2026-11-07):** Part 0 design **Owner-approved**; Parts 1–6 **FUNCTIONALLY_ACCEPTED / TECHNICAL_VALIDATION_DEFERRED**.
> Canonical current rules: [BUSINESS_RULES.md](BUSINESS_RULES.md) · [S06_FINAL_CLOSURE_REPORT.md](S06_FINAL_CLOSURE_REPORT.md)
> **Baseline tip:** `e71d7d9` · **Does not** authorize merge/deploy/MySQL 8.
> **Historical note:** Sections below retain Part 0 design narrative. Later Owner locks supersede any “deferred” wording here: E-Q03–E-Q14 (except E-Q02), C-Q01, F-Q01–**F-Q04=A**. Still deferred: E-Q02 reopen, soft-delete restore, financial adjustment workflow.

---

## 1. Purpose and ADR-0020 compatibility

ADR-0020 §S06 requires: season-linked projects; budgets/phases; tasks and responsibilities; progress and delays.

### 1.1 Documented tension (ADR text unchanged)

| Source | Prior wording | Owner D01 |
|---|---|---|
| ADR-0020 §S06 | “Distinct from MVP Tasks module reuse: Phase 2 needs an explicit seasonal projects model” | Extend **existing** MVP Tasks for season-linked **project tasks** |
| PHASE2_SCOPE_MATRIX S06 (historical) | “MVP Tasks may inform UX but Phase 2 projects model TBD” | Same |

**ADR-0020 is not rewritten by this pack.** Compatibility is recorded here for Owner awareness.

### 1.2 Approved resolution — E-Q01 = A

Interpret the need for an “explicit seasonal projects model” as a **separate Project aggregate** (`season_projects` + phases + budget/PO links), while **task execution** reuses and **extends** `Modules/Tasks` with **nullable** season/project foreign keys:

- existing MVP tasks (`project_id` NULL, `season_id` NULL) keep permissions, lifecycle, and data unchanged (**D01**);
- project tasks are Tasks rows with non-null `project_id` (+ season alignment);
- S06 does **not** replace governance Tasks linked to Decisions.

---

## 2. Approved Owner product decisions (immutable)

| ID | Decision | Design implication |
|---|---|---|
| **D01** | Extend MVP Tasks for season-linked project tasks; preserve MVP tasks/permissions/behavior/data | Nullable FKs; no rewrite of existing `tasks.*` semantics for non-project tasks |
| **D02** | Project budgets **must** link to S05 Purchase Orders; design mandatory linkage + financial semantics; do **not** assume all POs belong to projects | Link table; PO may remain unlinked; **budget commitment lines** require a PO link — **not** “PO required at project create” |
| **D03** | Both **calculated** delay and a **persisted** delayed status/indicator | Dual signals; sync per §6 (E-Q05 = A) |
| **D04** | Project **may complete** while some tasks remain open | Completion allowed; open tasks restricted per §8 (E-Q10 = A) |
| **D05** | Employee owner **mandatory at activation** | Activation guard; eligibility detail deferred (E-Q11) |
| **D06** | Part 0 documentation **only**; stop for Owner review before implementation | This pack; no code until final design approval + Part authorization |

---

## 3. Approved engineering decisions (this revision)

| ID | Choice | Summary |
|---|---|---|
| **E-Q01** | **A** | Separate `season_projects` aggregate + extend MVP Tasks with nullable FKs |
| **E-Q05** | **A** | Calculated delay authoritative; `delay_state` is synchronized projection; **no** sticky manual override |
| **E-Q06** | **A** | Link **complete PO header** to at most one project budget; UNIQUE (`tenant_id`, `purchase_order_id`) |
| **E-Q10** | **A** | On project complete: open tasks stay open but restricted to **complete** or **cancel** only; **no** auto-cancel |
| **E-Q14** | **B** | Soft-delete **draft** projects only; active/completed/cancelled **cannot** be deleted |

---

## 4. In scope / out of scope

### In scope (S06)

- Season-scoped projects with lifecycle, owner employee, progress rollup.
- Optional ordered phases under a project.
- Budget envelope; **when** committing budget to procurement spend, linkage to S05 POs is mandatory (D02 / E-Q06).
- Project tasks via extended Tasks module (D01 / E-Q01).
- Delay: calculated + persisted projection (D03 / E-Q05).
- RBAC, tenant + season isolation, audit, concurrency, FE list/detail flows, tests/UAT gates.

### Out of scope

- Full finance/accounting, payments, FX engine (E-Q09 deferred).
- Recurring seasonal automation, Gantt/resource leveling, subtasks trees.
- S07 risks/complaints, S08 templates.
- Changing S05 PO state machine except link-table relationships.
- Pilgrim PII in project payloads.
- Silent rewrite of ADR-0020.

### Clarification — D02 vs project create

- Creating a **draft/active project does not require** any linked PO.
- Defining/attaching **budget commitment via procurement** requires linking an eligible PO (E-Q06).
- Unlinked POs remain valid S05 documents forever unless explicitly linked.

---

## 5. Aggregates and workflows

### 5.1 Season project lifecycle

```
draft → active → completed
  │        │
  └────────┴→ cancelled
```

| Transition | Guard |
|---|---|
| draft → active | D05: `owner_employee_id` set; employee eligible (E-Q11 **deferred** — gate Part 2); season context matches; title present |
| active → completed | D04 + E-Q10: allowed with open tasks; apply restriction policy (§8) |
| draft\|active → cancelled | Allowed only if **no open project tasks** (**C-Q01 = A**); does **not** cancel linked POs; does **not** auto-cancel tasks |
| Soft delete | E-Q14: **draft only**; restore policy **unresolved** (§5.3) |
| completed/cancelled | Terminal for v1 header unless E-Q02 later allows reopen (**deferred**) |

### 5.2 Phases

- Ordered list (`sort_order`) under a project; optional dates.
- Task may reference `phase_id` nullable.
- Phase semantics: **E-Q03 = A** — planning labels only; no automatic phase completion; delete unlinks tasks (`phase_id` → NULL) preserving project/season.

### 5.3 Soft delete and restore (E-Q14 = B)

| Rule | Detail |
|---|---|
| Soft-delete allowed | `status = draft` only |
| Soft-delete forbidden | `active`, `completed`, `cancelled` → **422** |
| Associated tasks | Draft projects should have no activated operational expectation; if draft project tasks exist, soft-delete is **blocked** or tasks must be detached/cancelled first (**implementation guard** — prefer block while `project_id` tasks exist) |
| PO links | Draft with PO links: soft-delete **blocked** while links exist (unlink first) — protects UNIQUE PO assignment and audit clarity |
| Audit records | Never deleted; remain append-only |
| **Restore** | **Unresolved / not Owner-approved** — document as TBD before exposing restore API (flag for Part 2 if restore endpoint desired) |

### 5.4 Project task workflow (extends Tasks)

Reuse existing TaskLifecycle:

`draft → assigned → in_progress → completed | cancelled`

Additive nullable fields for MVP compatibility: `season_id`, `project_id`, `phase_id`, `delay_state`, `delay_calculated_at`.

---

## 6. Tenant / season isolation

| Rule | Behavior |
|---|---|
| All new tables | `tenant_id` NOT NULL; `UsesTenantScope` / `TenantOwned` |
| Season ownership | `season_id` NOT NULL on projects/phases/budget links; align with `X-Season-Id` / `SeasonContext` |
| Tasks extension | `season_id`/`project_id` NULL for legacy MVP tasks; when `project_id` set, `season_id` MUST equal project.season_id |
| Cross-tenant | **404** |
| Cross-season (wrong header vs row) | **404** |
| PO link | PO `tenant_id` must match project; season match on PO: **E-Q04 deferred** — gate Part 4 (until then: tenant-only check as working assumption, **not approved as final product rule**) |

---

## 7. Delay model (D03 + E-Q05 = A)

### 7.1 Signals

| Signal | Definition |
|---|---|
| **Calculated delay** | `due_date < today(tenant timezone)` AND status ∉ {`completed`,`cancelled`} |
| **Persisted `delay_state`** | Enum: `none` \| `delayed` — **projection only** of calculated delay |

No `cleared_manual` / sticky override in v1 (**E-Q05 = A**).

### 7.2 Precedence

**Calculated delay is authoritative.** On every sync, `delay_state` is set to `delayed` if calculated delayed, else `none`. Manual UI override is not offered.

### 7.3 Recalculation triggers

1. Task writes: create/update dates, assign, start, progress, complete, cancel.
2. Scheduled command/job: tenant/season chunked recompute for open project tasks (and optionally all open tasks carrying delay columns).
3. Project completion freeze does **not** clear delay until task completes/cancels (past-due open frozen tasks remain `delayed`).

### 7.4 Idempotency, concurrency, recovery

- Recompute is idempotent: same inputs → same `delay_state`.
- Job batches use `SELECT … FOR UPDATE` per task id; concurrent task write + job → last committed row wins; next job pass heals drift.
- **Recovery after task complete/cancel:** force `delay_state = none`, set `delay_calculated_at`, audit `TASK_DELAY_STATE_CHANGED` when value changes.
- MVP tasks with NULL project: columns default `none`; behavior remains derived overdue in MVP docs unless same projection is applied uniformly (projection columns present but MVP UX may keep derived-only display — no lifecycle change).

---

## 8. Budget ↔ S05 PO linkage (D02 + E-Q06 = A)

### 8.1 Principles

- Budgets belong to a season project (`budget_amount` / `currency_code` may exist without links).
- **Mandatory linkage for commitment lines:** each budget–procurement attachment references exactly one **PO header**.
- A given PO links to **at most one** project budget: UNIQUE (`tenant_id`, `purchase_order_id`).
- Existing/historical POs may remain **unlinked**.
- **Do not** treat deferred E-Q07/08/09 formulas as approved.

### 8.2 Link eligibility (engineering; amounts formulas deferred)

| PO status | May attach? | Notes |
|---|---|---|
| `draft` | No | |
| `issued` | Yes | |
| `partially_received` | Yes | |
| `received` | Yes | |
| `closed` | Yes (retain read-only) | |
| `cancelled` | No new link; existing → contribution **zeroed** (unlink or retain zeroed — prefer retain + zero for audit) | |

Commitment vs actual **numeric rules** = **E-Q07/E-Q08 deferred** (gate Part 4). Part 4 **must stop** until those are Owner-approved.

### 8.3 Double counting / transactions / deletion

- UNIQUE (`tenant_id`, `purchase_order_id`) enforces one project link.
- Attach: transaction locks PO row + project row; insert link; conflict → 422 `SEASON_PROJECT_PO_ALREADY_LINKED`.
- Cross-tenant PO id → 404.
- Unlink: allowed per permissions when no posted GRN; does not delete PO.
- **F-Q04 = A (Owner locked):** once the linked PO has **any** posted GRN, normal unlink is **prohibited** for all roles (`SEASON_PROJECT_PO_UNLINK_HAS_POSTED_RECEIPTS`). Future adjustment workflow is out of S06.
- Soft-delete draft project blocked while links exist (§5.3).
- Project cancel/complete does not delete links or POs.

### 8.4 Currency

**E-Q09 = A** (Owner locked): currency mismatch → HTTP 422. No FX invented.

> **Closure note:** E-Q04/E-Q07/E-Q08/E-Q09 and F-Q01–F-Q04 are Owner-approved and implemented in Parts 4–6. Any “deferred” formula wording above is historical Part 0 narrative — see BUSINESS_RULES / FINAL_CLOSURE.

---

## 9. Project completion with open tasks (D04 + E-Q10 = A)

On `active → completed`:

1. Project `status = completed`; `completed_at` set; audit `SEASON_PROJECT_COMPLETED`.
2. **Open** project tasks (`draft`/`assigned`/`in_progress`) **remain in their status** — **not** auto-cancelled.
3. Each open task becomes **completion-restricted** (`completion_frozen` policy flag or equivalent):
   - **Allowed:** transition to `completed` or `cancelled` by authorized actors; audit as today.
   - **Forbidden:** edit title/description/priority/dates/assignee/progress (except as required by complete/cancel endpoints); create new tasks on this project; reopen project (E-Q02 deferred).
4. Progress rollup: average of non-cancelled task `progress_percent`; completed project **may show &lt; 100%** while open tasks remain.
5. Reporting/UI: list open tasks with badge «مشروع مكتمل — مهمة مفتوحة».
6. Audit: project completion + per-task freeze event `TASK_FROZEN_AFTER_PROJECT_COMPLETE`.

Authorization for complete/cancel on frozen tasks: existing task permissions subject to **E-Q12 deferred** (gate Part 3); until then Part 3 must not invent a second permission model beyond documenting the restriction allowlist.

---

## 10. Owner employee (D05)

| Rule | Detail |
|---|---|
| At `draft → active` | `owner_employee_id` required |
| Eligibility | **E-Q11 deferred** — gate Part 2 activation implementation |
| Reassignment | Allowed while `active` (proposed); audit old→new |
| Season | `SeasonContext` must match `project.season_id` |

---

## 11. RBAC (names proposed; grant policy partly deferred)

### New module permissions (`season_projects.*`)

`view`, `create`, `update`, `activate`, `complete`, `cancel`, `manage_phases`, `manage_budget`, `link_po`, `reassign_owner`

### Tasks

- Existing `tasks.*` **unchanged** for MVP tasks.
- Project-task permission composition = **E-Q12 deferred** (gate Part 3).
- Sensitive budget/link template grants = **E-Q13 deferred** (gate Part 4 / permission seeding for those actions).

---

## 12. Audit events (proposed codes)

`SEASON_PROJECT_CREATED|UPDATED|ACTIVATED|COMPLETED|CANCELLED|OWNER_REASSIGNED|SOFT_DELETED`
`SEASON_PROJECT_PHASE_*`
`SEASON_PROJECT_BUDGET_*` / `SEASON_PROJECT_PO_LINKED|UNLINKED`
`TASK_PROJECT_LINKED` / `TASK_DELAY_STATE_CHANGED` / `TASK_FROZEN_AFTER_PROJECT_COMPLETE`

Secrets never in audit values. Soft-deleted drafts leave audit intact.

---

## 13. Concurrency & transactions

- Project number sequences: `FOR UPDATE` per tenant(+season).
- Activate/complete/cancel/soft-delete: lock project row.
- PO link attach: lock PO + unique insert.
- Delay job: batched row locks; idempotent.
- Frozen task updates: reject fields outside allowlist.

---

## 14. Migration design (docs only)

See [DATA_MODEL.md](DATA_MODEL.md). Principles:

- Additive nullable Task columns (E-Q01 / E-Q05).
- New tables for projects/phases/budget_po_links with UNIQUE PO link (E-Q06).
- Soft deletes on projects for drafts only (E-Q14).
- Prefer link table only (no required FK on `purchase_orders`).
- No backfill of historical POs onto projects.

---

## 15. Deferred engineering questions (not approved)

| ID | Question | Blocks |
|---|---|---|
| E-Q02 | Reopen completed project? | Before lifecycle reopen feature / final acceptance if reopen claimed |
| E-Q03 | Phase completion / delete behavior | **Part 3** |
| E-Q04 | PO season compatibility | **Part 4** |
| E-Q07 | Actual spending calculation | **Part 4** |
| E-Q08 | Remaining budget formula | **Part 4** |
| E-Q09 | Multi-currency | **Part 4** |
| E-Q11 | Employee active eligibility | **Part 2** activation |
| E-Q12 | Project-task permissions model | **Part 3** Tasks integration |
| E-Q13 | Budget/PO permission grants | **Part 4** / seeding those perms |
| Restore after soft-delete | Restore API/policy | Before any restore endpoint (Part 2+) |
| Open tasks on **project cancel** | Cancel vs leave restricted | Before implementing project cancel task side-effects |

**Rule:** If a deferred question materially blocks a Part, that Part **stops** until Owner resolves it. Do not ship invented “defaults” as approved.

---

## 16. Acceptance gates (Part 0)

- [x] D01–D06 recorded immutable
- [x] E-Q01, E-Q05, E-Q06, E-Q10, E-Q14 incorporated
- [x] Deferred E-Qs gated by Part
- [ ] **Final Owner design approval** (blocker for Part 1 coding authorization)
- [x] ROADMAP/MATRIX reflect Part 0 updated / not implemented

Implementation Parts: [S06_IMPLEMENTATION_PLAN.md](S06_IMPLEMENTATION_PLAN.md).
