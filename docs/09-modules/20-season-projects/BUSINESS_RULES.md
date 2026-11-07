# Season Projects — Business Rules

> **Status:** FUNCTIONALLY_ACCEPTED / TECHNICAL_VALIDATION_DEFERRED (2026-11-07)
> **Binding product decisions:** D01–D06 (immutable)
> **Binding engineering:** E-Q01, E-Q03–E-Q14 (except E-Q02); **C-Q01**
> **Binding financial:** **F-Q01=A**, **F-Q02=A**, **F-Q03=A**, **F-Q04=A**
> Deferred: **E-Q02** reopen, soft-delete restore, future financial adjustment workflow (post F-Q04).
> Does **not** imply MERGE READY or DEPLOY READY (MySQL 8 deferred).

## BR-01 Identity and isolation

1. Every project belongs to exactly one `tenant_id` and one `season_id`.
2. Requests outside tenant or season context that do not match the row → **404**.
3. `tenant_id` / `season_id` never accepted as authorization from client body for ownership.

## BR-02 Lifecycle

1. New projects start as `draft`.
2. `draft → active` requires non-null `owner_employee_id` (**D05**); owner must be **ACTIVE** same-tenant employee (**E-Q11 = A**).
3. `active → completed` allowed with open tasks (**D04**); open tasks become completion-restricted (**E-Q10 = A**) — **no auto-cancel**.
4. `draft|active → cancelled` allowed only when **no open project tasks** exist (**C-Q01 = A**); does not cancel linked POs; does **not** auto-cancel tasks — actor must complete/cancel open tasks first.
5. Terminal states for v1: `completed`, `cancelled` unless **E-Q02** later approves reopen (deferred).
6. Soft delete (**E-Q14 = B**): **draft only**; active/completed/cancelled → reject. Restore policy **unresolved**.

## BR-03 Owner employee

1. Owner must be an Employee in the same tenant; at activation must be **ACTIVE** (**E-Q11 = A**).
2. Reassignment while `active` (proposed engineering; audit required).
3. Owner may be set on draft; activation fails if missing.

## BR-04 Phases

1. Phases are optional ordered children of a project — **planning/organization labels only** (**E-Q03 = A**).
2. **No** automatic phase completion; status labels are informational only.
3. On phase delete: linked tasks keep `project_id`/`season_id`; `phase_id` → **NULL**. Do not delete or cancel tasks.

## BR-05 Tasks (D01 / E-Q01 = A)

1. MVP tasks: `project_id` IS NULL — existing rules, permissions, lifecycle **unchanged**.
2. Project tasks: `project_id` NOT NULL; `season_id` = project.season_id.
3. Creating a project task on `completed`/`cancelled` project → **422**.
4. After project **complete**: open tasks may only **complete** or **cancel** (**E-Q10**); other mutations → **422** / policy deny.
5. Progress rollup excludes `cancelled` tasks; completed projects may show progress &lt; 100%.

## BR-06 Delay (D03 / E-Q05 = A)

1. Calculated delay = past due and not terminal status — **authoritative**.
2. Persisted `delay_state` ∈ {`none`,`delayed`} is a **synchronized projection** only.
3. **No** sticky manual delay override.
4. Recalculate on task writes + scheduled idempotent job; complete/cancel → `none`.
5. Concurrency: row locks on job batches; drift healed on next pass.

## BR-07 Budget and POs (D02 / E-Q06 = A)

1. Project may exist **without** any PO; create/activate does **not** require a PO.
2. When attaching procurement to project budget, link **PO header**; UNIQUE (`tenant_id`, `purchase_order_id`) — at most one project.
3. Unlinked POs remain valid S05 documents.
4. Cancelled PO contributes zero to project figures; project cancel does not mutate PO status.
5. **Actual spending (E-Q07 = A / F-Q01 = A):** Σ (`posted` GRN line qty × PO line `unit_price`); draft GRNs excluded; posted ≡ financially accepted; no invoices.
6. **Outstanding / remaining (E-Q08 = A / F-Q02 = A):** outstanding = unreceived linked PO value (**closed PO → 0**); remaining = budget − outstanding − actual (no double count).
7. **Currency (E-Q09 = A):** reject link when PO `currency` ≠ project `currency_code` → HTTP 422; no FX.
8. **Season (E-Q04 = A):** same tenant required; seasons may differ.
9. **Permissions (E-Q13 = A / F-Q03 = A):** `manage_budget` / `link_po` independent; `tenant_owner` receives both via catalog (not `sensitiveExplicitOnly`); complete/cancel remain sensitiveExplicitOnly; not on operational templates.
10. **Unlink after posted GRN (F-Q04 = A):** once a linked PO has **any** posted GRN, normal unlink is **prohibited** for all actors (including `tenant_owner` with `link_po`). Reject with `SEASON_PROJECT_PO_UNLINK_HAS_POSTED_RECEIPTS`. Future correction requires a separately Owner-approved financial adjustment workflow — **not** in S06 scope.

## BR-08 Soft delete protections (E-Q14)

1. Soft-delete draft only.
2. Block soft-delete while project tasks or PO links exist (unlink/resolve first).
3. Audit rows never deleted.
4. Restore = unresolved — no approved restore behavior.

## BR-09 Audit

Critical transitions (create/activate/complete/cancel/owner change/PO link/unlink/task freeze/delay state change/soft-delete) emit append-only audit with `tenant_id` + correlation ID.

## BR-10 Concurrency

Activate, complete, cancel, soft-delete, PO link, and number allocation use row locks / unique constraints; conflicting concurrent operations → one success and one conflict/422.
