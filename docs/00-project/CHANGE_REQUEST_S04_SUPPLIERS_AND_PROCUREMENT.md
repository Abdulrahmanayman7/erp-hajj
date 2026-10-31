# [Change Request] Phase 2 — S04 Suppliers & Purchase Requests (Engineering)

> **Status:** `APPROVED — DESIGN AND SCOPE ONLY; IMPLEMENTATION NOT AUTHORIZED`  
> **Prepared:** 2026-10-31  
> **Owner design/scope approval date:** 2026-10-31  
> **Template:** `.github/ISSUE_TEMPLATE/change-request.md`  
> **Functional authority:** [ADR-0022](../10-decisions/ADR-0022-S04-SUPPLIERS-AND-PURCHASE-REQUESTS-FUNCTIONAL-DECISIONS.md) (APPROVED — FUNCTIONAL DECISIONS)  
> **Engineering design:** [ADR-0023](../10-decisions/ADR-0023-S04-ENGINEERING-DESIGN.md) (`APPROVED — ENGINEERING DESIGN`)  
> **Module pack:** [docs/09-modules/19-suppliers-and-procurement/](../09-modules/19-suppliers-and-procurement/)  
> **Product parent:** [ADR-0020](../10-decisions/ADR-0020-FULL-PHASE2-OPERATIONAL-SCOPE.md) §S04  

**This Change Request approves S04 engineering design and delivery scope documentation only.**  
It does **not** authorize application code, migrations, seeding, deployment, commits, or G2 implementation. A **separate** explicit Project Owner implementation authorization is required before any coding or database work.

---

## Requested Change

Deliver (when Owner later issues implementation go-ahead) **S04 — Suppliers & Purchase Requests** inside Phase 2 product scope:

1. Tenant-isolated supplier registry (multi-category, lifecycle, documents eligibility, contacts, bank accounts — no payments).  
2. Supplier evaluations (weighted, versioned, SoD).  
3. Purchase requests (seasonal/admin, multi-line, estimates, **mandatory** manual financial review).  
4. Multi-level dynamic PR approvals (matrices, authority, delegation, SLA reminders without auto-approve).  
5. Supporting in-app notifications, audit, and control reports.  
6. Minimal platform geographic reference catalog for coverage.  
7. Hard SoD: financial reviewer ≠ final PR approver (**D5**).

## Type

- [x] New feature outside current MVP scope (Phase 2 stream; MVP 21 modules unchanged)  
- [ ] Change to an approved requirement or workflow  
- [x] Change to an architecture decision (ADR-0023 accepted)  
- [ ] Removal/descoping  

## Exact S04 scope

As ADR-0020 §S04 + ADR-0022 decisions **101–130** + G0 directions for **106** / **120** + Owner design decisions **D1–D5** (2026-10-31):

| ID | Approved design rule |
|---|---|
| **D1** | Limited geo catalog: Makkah, Madinah, Jeddah, Mina, Arafat, Muzdalifah; extensible; validate before implement |
| **D2** | Mandatory financial review for every PR before final approval (incl. unpriced); no ledger/reservation/payment |
| **D3** | Approved-PR cancel via `cancel_approved` + reason + audit; blocked when future procurement engagement lock set |
| **D4** | Role templates proposed; sensitive permissions **not** auto-granted |
| **D5** | Financial reviewer ≠ final approver — hard enforce; multi-role/delegation cannot bypass; reassign or escalate; never auto-approve |

## Explicit S05 exclusions

Must **not** be delivered under this CR:

- RFQ issuance  
- Supplier quotations / comparison / selection  
- Purchase orders (PO)  
- PO-based inventory receipt / goods receipt against PO  
- Purchasing cycle close  
- Payments / accounting postings / budget reservations  

## Justification

Operational Hajj companies need a real supplier master and controlled internal purchase requests before S05 procurement cycle. Contracts `counterparty_name` and Inventory manual receive are insufficient and must not be overloaded (ADR-0022 constraints).

## Impact Assessment

### Documentation (approved design package)

- Module pack `19-suppliers-and-procurement`  
- ADR-0023 **APPROVED — ENGINEERING DESIGN** (2026-10-31)  
- This CR **APPROVED — DESIGN AND SCOPE ONLY** (2026-10-31)  

### Future implementation (still **not** authorized)

- **Backend:** `app/Modules/SuppliersAndProcurement` (final name TBD), Actions, Policies, Form Requests, Resources, routes under `/api/v1`, PermissionCatalog entries, factories, Pest tests.  
- **Frontend:** Vue module(s) RTL pages per UI.md, Vitest, locale strings.  
- **Migrations (proposed titles only — files must not be created until G2 authorized):** geo_places; supplier_* ; supplier_evaluation_* ; purchase_approval_* ; purchase_request_* ; sequences; optional later `contracts.supplier_id` via **separate** CR.  
- **Jobs/commands:** SLA reminder/escalation scheduler.  
- **Documents:** morph alias extensions.  
- **Notifications:** new event types (ADR-0013 channel).  

### Security / tenancy / audit

- TenantOwned everywhere for business data; deny-by-default; SoD including **D5** (financial reviewer ≠ final approver — hard block, reassignment/escalation, audit); banking least privilege; audit without secrets; cross-tenant 404.  
- Delegation must not bypass D5.  
- No auto-approve / waive / skip of mandatory steps.

## Proposed implementation phases (after **future** G2 authorization only)

| Phase | Content |
|---|---|
| I | Geo catalog + suppliers + categories + coverage + contacts + docs eligibility |
| II | Banking + evaluations |
| III | PR header/lines/versions + financial review + **D5** SoD gates |
| IV | Approval matrices, authority, steps, delegation, SLA |
| V | Planning groups + control reports + FE polish + hardening tests |

Each phase still needs dual review per project workflow; no self-merge.

## Dependencies and risks

| Item | Note |
|---|---|
| Separate G2 coding authorization | **Still required** — this CR does not grant it |
| Empty matrix / SLA | Tenants must configure before production use |
| First multi-level approval engine | Schedule/test risk |
| D5 dual-actor requirement | Tenants need independent reviewer vs final approver |
| ADR-0010 immutability | Eligibility metadata must not fork Documents versioning |
| Contracts linkage | Separate CR; no forced backfill |
| Inventory | No PR receive path |

## Acceptance criteria and testing gates

Per [ACCEPTANCE_CRITERIA.md](../09-modules/19-suppliers-and-procurement/ACCEPTANCE_CRITERIA.md) and [TEST_PLAN.md](../09-modules/19-suppliers-and-procurement/TEST_PLAN.md): Pest (incl. cross-tenant, concurrency, **D5** negative paths), Vitest, `tsc`, audit assertions, regression Contracts/Inventory, **zero** S05 endpoints.

## Rollback / deployment considerations

- Feature flag or permission-gated nav until matrices configured (**ENGINEERING PROPOSAL**).  
- Migrations reversible where non-destructive; encrypted bank columns require key availability — document ops runbook before prod.  
- No production data use in tests.  
- Deploy only after Owner **implementation** authorization and dual review.

## Required owner approvals (checklist)

- [x] Resolve G1 design questions **D1–D4** (2026-10-31)  
- [x] Approve **ADR-0023** as `APPROVED — ENGINEERING DESIGN` (2026-10-31)  
- [x] Approve **D5** financial-reviewer ≠ final-approver hard SoD (2026-10-31)  
- [x] Flip this CR to **APPROVED — DESIGN AND SCOPE ONLY; IMPLEMENTATION NOT AUTHORIZED** (2026-10-31)  
- [x] Issue **explicit G2 Phase I implementation authorization** (Owner 2026-10-31)  
- [x] Owner-accept **G2 Phase I** delivery (2026-10-31) — [S04_PHASE_I_ACCEPTANCE_STATUS.md](S04_PHASE_I_ACCEPTANCE_STATUS.md)  
- [x] Record Phase II Owner clarifications **II-D1–II-D4** (2026-10-31) — [S04-PHASE-II-OWNER-CLARIFICATIONS-2026-10-31.md](../10-decisions/S04-PHASE-II-OWNER-CLARIFICATIONS-2026-10-31.md) (**documentation only**)  
- [x] Owner-approve **II-A Eligibility & Documents design** including controls **C1–C6** (2026-10-31) — [S04-PHASE-II-A-DESIGN-APPROVAL-2026-10-31.md](../10-decisions/S04-PHASE-II-A-DESIGN-APPROVAL-2026-10-31.md); [PHASE_II_A_ELIGIBILITY_DESIGN.md](../09-modules/19-suppliers-and-procurement/PHASE_II_A_ELIGIBILITY_DESIGN.md)  
- [x] Complete **II-A engineering pre-implementation gate** (2026-10-31) — [S04-PHASE-II-A-ENGINEERING-GATE-2026-10-31.md](../10-decisions/S04-PHASE-II-A-ENGINEERING-GATE-2026-10-31.md); API.md §12; DATA_MODEL constraints (**coding still NOT GRANTED**)  
- [ ] Authorize **Phase II / II-A** implementation (separate Owner act)  
- [ ] Authorize **Phase II-B–V** implementation (separate Owner acts)  
- [ ] At later phases seed PR: continue **D4** templates (non-sensitive only) + explicit sensitive grants process; **never** template-seed `approve_eligibility_config` (**C6**)  

## Decision

- [x] Approved — **design and scope**  
- [x] **G2 Phase I implementation authorized and Owner-accepted** (2026-10-31)  
- [x] **Phase II clarifications II-D1–II-D4 approved** (2026-10-31) — not coding authorization  
- [x] **II-A DESIGN APPROVED — IMPLEMENTATION NOT AUTHORIZED** (2026-10-31)  
- [x] **II-A ENGINEERING PRE-IMPLEMENTATION GATE — COMPLETE** (2026-10-31)  
- [ ] Rejected  
- [ ] Deferred  

**Decided by:** Project Owner — **Date:** 2026-10-31  

**Implementation authorization:** **Phase I GRANTED + ACCEPTED** (2026-10-31). **Phase II–V NOT GRANTED.** II-A: design **APPROVED**; engineering gate **COMPLETE**; coding **NOT AUTHORIZED**.

---

## Notes

- G0 COMPLETE (ADR-0022 + S03 acceptance 2026-10-31).  
- G1 design APPROVED (ADR-0023 + this CR design/scope).  
- S04 overall implementation status: **IN PROGRESS** (Phase I complete; Phase II implementation **NOT_STARTED**; II-A design **APPROVED**; II-A engineering gate **COMPLETE**).  
- Phase II–V coding remains forbidden until a separate Owner authorization.
