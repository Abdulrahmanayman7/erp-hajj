# Module 20 — Season Projects (S06)

> **Status:** **FUNCTIONALLY_ACCEPTED / TECHNICAL_VALIDATION_DEFERRED** (2026-11-07)
> Parts 0–6 Owner functionally accepted · MySQL 8 **DEFERRED** · Browser E2E **NOT RUN** · Scheduler **NOT DEPLOYED**
> **MERGE READY: NO** · **DEPLOY READY: NO**
> Closure: [S06_FINAL_CLOSURE_REPORT.md](S06_FINAL_CLOSURE_REPORT.md)
> **Phase:** Phase 2 / Sprint S06
> **Code module path:** `backend/app/Modules/SeasonProjects`
> **Related:** extends `docs/09-modules/08-tasks/`; consumes S05 POs (`19-suppliers-and-procurement`)

## Purpose

Manage **season-scoped operational projects**: ownership, phases, progress, delays, and budgets linked to Purchase Orders — without breaking MVP governance Tasks.

## Approved decisions

### Product (immutable) — D01–D06

- **D01** Extend MVP Tasks for project tasks; preserve MVP tasks.
- **D02** Budgets link to S05 POs when attaching procurement; not all POs are project POs; project create does not require a PO.
- **D03** Calculated + persisted delay.
- **D04** Project may complete with open tasks.
- **D05** Employee owner mandatory at activation.
- **D06** Documentation-first; separate review before implementation.

### Engineering (approved)

| ID | Choice |
|---|---|
| E-Q01 | **A** — `season_projects` aggregate + nullable Task FKs |
| E-Q03 | **A** — phases organizational / labels only |
| E-Q04 | **A** — same-tenant cross-season PO linkage allowed |
| E-Q05 | **A** — calculated authoritative; `delay_state` projection; no sticky override |
| E-Q06 | **A** — PO header → at most one project; UNIQUE (`tenant_id`, `purchase_order_id`) |
| E-Q07 | **A** — actual from posted GRNs |
| E-Q08 | **A** — remaining subtracts outstanding + actual without double count |
| E-Q09 | **A** — currency mismatch → HTTP 422 |
| E-Q10 | **A** — open tasks stay open; complete/cancel only after project complete |
| E-Q11 | **A** — activation owner = active same-tenant employee |
| E-Q12 | **A** — existing `tasks.*` + project policy |
| E-Q13 | **A** — `manage_budget` and `link_po` separate |
| E-Q14 | **B** — soft-delete draft only |
| C-Q01 | **A** — cancel blocked while any open project task exists |

### Financial (approved)

| ID | Choice |
|---|---|
| F-Q01 | **A** — posted GRN financially accepted; valued at PO-line `unit_price` |
| F-Q02 | **A** — closed PO → zero outstanding commitment |
| F-Q03 | **A** — `tenant_owner` has `manage_budget` + `link_po` (auditable) |
| F-Q04 | **A** — any posted GRN permanently blocks normal unlink (`SEASON_PROJECT_PO_UNLINK_HAS_POSTED_RECEIPTS`); no role bypass |

### Deferred (not approved / future)

- **E-Q02** project reopen
- Soft-delete restoration
- Financial adjustment workflow after posted GRN (out of S06)

## Package index

| Doc | Role |
|---|---|
| [PHASE_S06_SEASON_PROJECTS_DESIGN.md](PHASE_S06_SEASON_PROJECTS_DESIGN.md) | Central design |
| [S06_IMPLEMENTATION_PLAN.md](S06_IMPLEMENTATION_PLAN.md) | Parts 1–N + gates |
| [S06_FINAL_CLOSURE_REPORT.md](S06_FINAL_CLOSURE_REPORT.md) | Documentation closure + readiness |
| [BUSINESS_RULES.md](BUSINESS_RULES.md) | Rules |
| [DATA_MODEL.md](DATA_MODEL.md) | Schema + migrations design |
| [API.md](API.md) | REST contract |
| [PERMISSIONS.md](PERMISSIONS.md) | RBAC |
| [UI.md](UI.md) | Frontend |
| [TEST_PLAN.md](TEST_PLAN.md) | Tests |
| [ACCEPTANCE_CRITERIA.md](ACCEPTANCE_CRITERIA.md) | Gates |
| PART0–PART6 evidence + UAT/readiness | Historical counts preserved |

## Compatibility

ADR-0020 product wording vs D01 documented under design §1; **ADR product text not rewritten**. Resolution = E-Q01 = A. Status tracked in ROADMAP / PHASE2_SCOPE_MATRIX / this module pack.
