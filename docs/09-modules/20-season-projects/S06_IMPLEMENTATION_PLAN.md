# S06 — Implementation Plan (Parts 1–N)

> **Status:** **FUNCTIONALLY_ACCEPTED / TECHNICAL_VALIDATION_DEFERRED** (2026-11-07)
> Part 0 Owner-approved · Parts 1–6 functionally accepted (Owner) · MySQL 8 **DEFERRED** · MERGE/DEPLOY **NOT READY**
> Closure: [S06_FINAL_CLOSURE_REPORT.md](S06_FINAL_CLOSURE_REPORT.md)
>
> **Financial Owner locks:** **F-Q01=A**, **F-Q02=A**, **F-Q03=A**, **F-Q04=A**
> **Approved engineering locked:** E-Q01, E-Q03–E-Q14 (except E-Q02); **C-Q01**
> **Deferred:** E-Q02 reopen; soft-delete restore; future financial adjustment workflow (post F-Q04)

---

## Part 0 — Documentation (this package)

**Goal:** Design + module docs + ROADMAP/MATRIX status with approved E-Qs incorporated.
**Exit:** Owner marks design **FINALLY APPROVED** — **done**.
**Owner status:** **Approved**.

---

## Part 1 — Schema + domain skeleton (backend)

> **Status:** Implemented · functionally accepted · MySQL 8 **DEFERRED**

**Goal:** Additive migrations + Eloquent models + enums + factories; **no public API routes** until Part 2.

Deliverables:

- Tables: `season_projects` (soft deletes), `season_project_phases`, `season_project_budget_po_links` with UNIQUE (`tenant_id`, `purchase_order_id`) (**E-Q06**), project number sequences (`next_value`, never reuse numbers after soft-delete).
- `tasks` nullable columns: `season_id`, `project_id`, `phase_id`, `delay_state`, `delay_calculated_at` (**E-Q01**, **E-Q05**).
- Models: TenantOwned + season alignment helpers; draft-only soft-delete guards (**E-Q14**).
- Factories for tests only.
- Feature schema tests: number generator + soft-delete guards + unique PO link + MVP Tasks null FKs.

**Evidence (local):** Pest sqlite `SeasonProjectPart1SchemaTest` 9 passed / 1 skipped (MySQL rollback); `TaskTest` 15 passed. MySQL 8 `erp_hajj_testing` rollback **NOT RUN** (deferred).

---

## Part 2 — Project CRUD + lifecycle API

> **Status:** Functionally accepted (Owner)

**Goal:** Create/update/list/show; activate (**D05**); complete (**D04**/**E-Q10** freeze); cancel; owner reassign; draft soft-delete; audit; policies.

Deliverables:

- Form Requests, Actions, Controller, Resources, routes under `/api/v1/season-projects`.
- Permissions registered in PermissionCatalog (**F-Q03**: budget/link not sensitiveExplicitOnly; complete/cancel remain sensitive).
- Feature tests: 401/403/404; activation without owner → 422; E-Q11; soft-delete non-draft → 422 (**E-Q14**).

**Evidence (local):** Pest sqlite SeasonProjects **24 passed** / 1 skipped at Part 2 close. Complete freeze finalized in Part 3. MySQL 8 **NOT RUN / DEFERRED**.

**Remaining non-blocking:** Restore-after-soft-delete API — not implemented; cancel does not auto-close tasks (C-Q01 blocks cancel while open).

---

## Part 3 — Phases + project task extension

> **Status:** Functionally accepted (Owner)

**Goal:** Phase CRUD; project tasks via extended Tasks; progress rollup; delay sync on write + scheduled command (**E-Q05**); complete + E-Q10 freeze; C-Q01 cancel guard.

Deliverables:

- Nested phase routes (informational labels — **E-Q03 = A**).
- Tasks Actions accept project/season/phase when authorized (**E-Q12 = A**).
- `tasks:recompute-delay` command with batch locks; idempotent projection (**E-Q05**).
- Freeze allowlist enforcement for tasks under completed projects (**E-Q10**).
- Cancel rejects while open project tasks exist (**C-Q01 = A**).

**Evidence (local):** Pest sqlite SeasonProjects+TaskTest **49 passed** / 1 skipped (historical Part 3 rollup). MySQL 8 **NOT RUN / DEFERRED**.
**Ops gate:** production `schedule:run` / cron for `tasks:recompute-delay` — **NOT DEPLOYED**.

---

## Part 4 — Budget ↔ PO linkage (D02 / E-Q06)

> **Status:** Functionally accepted (Owner) — F-Q01/F-Q02/F-Q03

**Goal:** Link/unlink PO headers; unique enforcement; cancel PO zeroing; tenant isolation; transaction boundaries; live E-Q07/E-Q08 summary.

Deliverables:

- Budget GET/PATCH + PO link/unlink under `/season-projects/{id}/budget`.
- Link actions with PO row lock + unique constraint; E-Q04/E-Q09 gates.
- Live financials: posted GRN × PO unit_price; outstanding open qty × price; remaining without double count.
- Tests: currency mismatch, cross-season same-tenant, duplicate link, cancelled zeroing, GRN actuals.

**Evidence (local):** Pest sqlite SeasonProjects+TaskTest **55 passed** / 1 skipped (historical Part 4). MySQL 8 **NOT RUN / DEFERRED**.

---

## Part 5 — Frontend (Vue)

> **Status:** Functionally accepted (Owner)

**Goal:** Projects list/detail; phases; task panel reuse; delay badges; completion warning for open tasks; PO link UI; draft delete only.

**Deliverables:**

- Routes `/app/season-projects`, `/app/season-projects/:id` + Sidebar nav.
- List/create/edit/detail; lifecycle confirmations; phases CRUD; project tasks; budget GET + manage; PO link/unlink; Arabic locale.
- Focused Vitest validation coverage; typecheck/lint/build as available.

**Evidence:** `PART5_IMPLEMENTATION_EVIDENCE.md`. MySQL 8 **NOT RUN / DEFERRED**. Browser E2E **NOT RUN**.

---

## Part 6 — Hardening, concurrency, browser UAT, docs freeze

> **Status:** Functionally accepted (Owner) · F-Q04=A recorded · technical validation deferred
> Evidence: `PART6_HARDENING_EVIDENCE.md`, `S06_UAT_REPORT.md`, `S06_READINESS_REPORT.md`, `S06_FINAL_CLOSURE_REPORT.md`

**Goal:** Permission/financial/lifecycle hardening; delay schedule registration; integration tests; UAT matrix; readiness assessment.

**Delivered:**

- F-Q03 catalog regression (owner has budget/link; complete/cancel sensitive).
- **F-Q04=A:** fail-closed unlink after posted GRN (`SEASON_PROJECT_PO_UNLINK_HAS_POSTED_RECEIPTS`) — Owner-approved; no role bypass.
- Terminal projects cannot link PO; daily `tasks:recompute-delay` schedule registration.
- Automated UAT scenarios 1–12 (sqlite); browser E2E **NOT RUN**; MySQL 8 **DEFERRED**.
- Manifest CRLF hygiene via `.gitattributes` `*.webmanifest text eol=lf`.

**E-Q02:** reopen not implemented / not claimed.

**STOP:** MERGE/DEPLOY **not** ready while MySQL 8 gate deferred; worktree uncommitted; no deploy/scheduler ops activation.

---

## Dependency graph

```
Part0 → Final Owner APPROVAL
  → Part1 (E-Q01/05/06/14 locked)
    → Part2 [E-Q11]
      → Part3 [E-Q03, E-Q12, C-Q01]
      → Part4 [E-Q04,07,08,09,13 + F-Q01–03]
    → Part5 after Parts 2–4
  → Part6 after 3+4+5 [F-Q04]
  → Documentation closure (this task) — no merge/deploy
```

## Risk buffers / remaining gates

- MySQL 8 dialect validation unpaid.
- Browser E2E unpaid.
- Production scheduler activation unpaid.
- E-Q02 reopen + soft-delete restore + financial adjustment workflow deferred (future Owner CRs).
