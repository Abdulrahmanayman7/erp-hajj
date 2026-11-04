# Module: Suppliers and Procurement (الموردون والمشتريات) — S04 slice

> **Status:** Overall S04 **FINAL FUNCTIONAL CLOSURE — OWNER GRANTED (2026-11-02)** — [S04-FINAL-FUNCTIONAL-CLOSURE-2026-11-02.md](../../10-decisions/S04-FINAL-FUNCTIONAL-CLOSURE-2026-11-02.md) on baseline `develop` @ `2d128a7`. Residual follow-ups preserved (`fr_count=2`; MariaDB 1213 3/112). **S05 Parts 0–6 Owner Accepted — FUNCTIONALLY FINAL CLOSED (2026-11-03)**. Merge / deploy / prod migrate **NOT AUTHORIZED** until PR review + CI. Do not start S06. See [S05_IMPLEMENTATION_PLAN.md](S05_IMPLEMENTATION_PLAN.md) · [S05_FINAL_READINESS_REPORT.md](../../00-project/S05_FINAL_READINESS_REPORT.md).
> **Last updated:** 2026-11-02
> **Phase I acceptance:** [S04_PHASE_I_ACCEPTANCE_STATUS.md](../../00-project/S04_PHASE_I_ACCEPTANCE_STATUS.md)
> **Phase II-A acceptance:** [S04_PHASE_II_A_ACCEPTANCE_STATUS.md](../../00-project/S04_PHASE_II_A_ACCEPTANCE_STATUS.md)
> **Phase II-B decisions:** [S04-PHASE-II-B-OWNER-DECISIONS-2026-11-01.md](../../10-decisions/S04-PHASE-II-B-OWNER-DECISIONS-2026-11-01.md) (**IIB-D1–IIB-D12 APPROVED**)
> **Phase II-B design approval:** [S04-PHASE-II-B-DESIGN-APPROVAL-2026-11-01.md](../../10-decisions/S04-PHASE-II-B-DESIGN-APPROVAL-2026-11-01.md)
> **Phase II-B functional closure:** [S04_PHASE_II_B_ACCEPTANCE_STATUS.md](../../00-project/S04_PHASE_II_B_ACCEPTANCE_STATUS.md) · [S04-PHASE-II-B-FUNCTIONAL-CLOSURE-2026-11-01.md](../../10-decisions/S04-PHASE-II-B-FUNCTIONAL-CLOSURE-2026-11-01.md) · evidence [PHASE_II_B_WP05_ACCEPTANCE_EVIDENCE.md](PHASE_II_B_WP05_ACCEPTANCE_EVIDENCE.md)
> **Phase II-B design:** [PHASE_II_B_SUPPLIER_BANKING_DESIGN.md](PHASE_II_B_SUPPLIER_BANKING_DESIGN.md) · contracts [API.md §13](API.md) · doc closure [S04-PHASE-II-B-DOC-CLOSURE-M1-M3-2026-11-01.md](../../10-decisions/S04-PHASE-II-B-DOC-CLOSURE-M1-M3-2026-11-01.md)
> **Phase II-C decisions:** [S04-PHASE-II-C-OWNER-DECISIONS-2026-11-01.md](../../10-decisions/S04-PHASE-II-C-OWNER-DECISIONS-2026-11-01.md) (**IIC-D1–D8 + IIC-F1–F3 APPROVED** — 11/11)
> **Phase II-C functional acceptance:** [S04-PHASE-II-C-FINAL-FUNCTIONAL-ACCEPTANCE-2026-11-01.md](../../10-decisions/S04-PHASE-II-C-FINAL-FUNCTIONAL-ACCEPTANCE-2026-11-01.md) — **FINAL** · [S04_PHASE_II_C_ACCEPTANCE_STATUS.md](../../00-project/S04_PHASE_II_C_ACCEPTANCE_STATUS.md)
> **Phase II-C design / evidence:** [PHASE_II_C_SUPPLIER_EVALUATIONS_DESIGN.md](PHASE_II_C_SUPPLIER_EVALUATIONS_DESIGN.md) · [PHASE_II_C_WP01_ACCEPTANCE_EVIDENCE.md](PHASE_II_C_WP01_ACCEPTANCE_EVIDENCE.md) · [PHASE_II_C_WP02_TO_06_ACCEPTANCE_EVIDENCE.md](PHASE_II_C_WP02_TO_06_ACCEPTANCE_EVIDENCE.md) · contracts [API.md §14](API.md)
> **Phase III decisions:** [S04-PHASE-III-OWNER-DECISIONS-2026-11-01.md](../../10-decisions/S04-PHASE-III-OWNER-DECISIONS-2026-11-01.md) (**III-D1–D8 APPROVED**)
> **Phase III acceptance:** [S04_PHASE_III_ACCEPTANCE_STATUS.md](../../00-project/S04_PHASE_III_ACCEPTANCE_STATUS.md) · evidence [PHASE_III_WP01_TO_05_ACCEPTANCE_EVIDENCE.md](PHASE_III_WP01_TO_05_ACCEPTANCE_EVIDENCE.md)
> **Phase III design:** [PHASE_III_PURCHASE_REQUESTS_DESIGN.md](PHASE_III_PURCHASE_REQUESTS_DESIGN.md) · contracts [API.md §15](API.md) — **OWNER ACCEPTED — FUNCTIONAL**
> **Phase IV decisions:** [S04-PHASE-IV-OWNER-DECISIONS-2026-11-01.md](../../10-decisions/S04-PHASE-IV-OWNER-DECISIONS-2026-11-01.md) (**IV-D1–D15 APPROVED** — 15/15)
> **Phase IV design acceptance:** [S04-PHASE-IV-DESIGN-ACCEPTANCE-2026-11-01.md](../../10-decisions/S04-PHASE-IV-DESIGN-ACCEPTANCE-2026-11-01.md) — **FINAL DESIGN OWNER ACCEPTED**
> **Phase IV functional acceptance:** [S04-PHASE-IV-FUNCTIONAL-ACCEPTANCE-2026-11-01.md](../../10-decisions/S04-PHASE-IV-FUNCTIONAL-ACCEPTANCE-2026-11-01.md) — **FINAL**; Gate Q/C [PHASE_IV_QUEUE_CONCURRENCY_VERIFICATION_EVIDENCE.md](PHASE_IV_QUEUE_CONCURRENCY_VERIFICATION_EVIDENCE.md)
> **Phase IV design / evidence:** [PHASE_IV_APPROVAL_ENGINE_DESIGN.md](PHASE_IV_APPROVAL_ENGINE_DESIGN.md) · [PHASE_IV_FINAL_ACCEPTANCE_EVIDENCE.md](PHASE_IV_FINAL_ACCEPTANCE_EVIDENCE.md)
> **Phase V Owner decisions:** [S04-PHASE-V-OWNER-DECISIONS-2026-11-01.md](../../10-decisions/S04-PHASE-V-OWNER-DECISIONS-2026-11-01.md) (**V-D1…V-D8 APPROVED** — incl. **V-D8=A CSV**)
> **Phase V design:** [PHASE_V_DETAILED_DESIGN.md](PHASE_V_DETAILED_DESIGN.md) · [PHASE_V_OWNER_DECISION_REGISTER.md](PHASE_V_OWNER_DECISION_REGISTER.md) · [PHASE_V_FAST_TRACK_IMPLEMENTATION_PLAN.md](PHASE_V_FAST_TRACK_IMPLEMENTATION_PLAN.md) · [PHASE_V_READINESS_AUDIT.md](PHASE_V_READINESS_AUDIT.md)
> **Phase V functional acceptance:** [S04-PHASE-V-FINAL-FUNCTIONAL-ACCEPTANCE-2026-11-01.md](../../10-decisions/S04-PHASE-V-FINAL-FUNCTIONAL-ACCEPTANCE-2026-11-01.md) — **FINAL**
> **Phase V implementation evidence:** [PHASE_V_FINAL_IMPLEMENTATION_AND_ACCEPTANCE_EVIDENCE.md](PHASE_V_FINAL_IMPLEMENTATION_AND_ACCEPTANCE_EVIDENCE.md) · [PHASE_V_MULTIPROCESS_CONCURRENCY_VERIFICATION_EVIDENCE.md](PHASE_V_MULTIPROCESS_CONCURRENCY_VERIFICATION_EVIDENCE.md)
> **Sprint gaps:** [S04_SPRINT_COMPLETION_GAP_REPORT.md](../../00-project/S04_SPRINT_COMPLETION_GAP_REPORT.md)
> **Phase II clarifications:** [S04-PHASE-II-OWNER-CLARIFICATIONS-2026-10-31.md](../../10-decisions/S04-PHASE-II-OWNER-CLARIFICATIONS-2026-10-31.md) (II-D1–II-D4)
> **II-A design approval:** [S04-PHASE-II-A-DESIGN-APPROVAL-2026-10-31.md](../../10-decisions/S04-PHASE-II-A-DESIGN-APPROVAL-2026-10-31.md)
> **II-A engineering gate:** [S04-PHASE-II-A-ENGINEERING-GATE-2026-10-31.md](../../10-decisions/S04-PHASE-II-A-ENGINEERING-GATE-2026-10-31.md)
> **II-A design:** [PHASE_II_A_ELIGIBILITY_DESIGN.md](PHASE_II_A_ELIGIBILITY_DESIGN.md) · contracts [API.md §12](API.md)
> **Functional ADR:** [ADR-0022](../../10-decisions/ADR-0022-S04-SUPPLIERS-AND-PURCHASE-REQUESTS-FUNCTIONAL-DECISIONS.md) (`APPROVED — FUNCTIONAL DECISIONS`)
> **Engineering ADR:** [ADR-0023](../../10-decisions/ADR-0023-S04-ENGINEERING-DESIGN.md) (`APPROVED — ENGINEERING DESIGN`, Owner 2026-10-31)
> **Change Request:** [CHANGE_REQUEST_S04_SUPPLIERS_AND_PROCUREMENT.md](../../00-project/CHANGE_REQUEST_S04_SUPPLIERS_AND_PROCUREMENT.md) (design/scope approved; Phase I–V functionally accepted; S04 FINAL FUNCTIONAL CLOSURE Owner-granted 2026-11-02; S05 implementation / release acts unauthorized)
> **Product scope parent:** [ADR-0020](../../10-decisions/ADR-0020-FULL-PHASE2-OPERATIONAL-SCOPE.md) §S04 / §S05
> **G1 Owner design decisions D1–D5:** Approved 2026-10-31 (geo, mandatory financial review, approved-PR cancel, sensitive permission seeding, **financial reviewer ≠ final approver hard SoD**).

## Purpose

Provide a **tenant-isolated** supplier master, supplier evaluation, purchase-request (PR) lifecycle, and **multi-level PR approvals** for Hajj campaign companies — so procurement can later continue into RFQ/PO in **S05** without inventing a vendor registry inside Contracts or Inventory.

## S04 vs S05 (binding)

| In **S04** (delivered / Owner-closed) | In **S05** (Part 0 designed; Parts 1–6 not coded) |
|---|---|
| Supplier registry & classifications | RFQ issuance from **approved PR** |
| Supplier evaluation | Supplier quotations & comparison + selection |
| Purchase requests (PR) + multi-level approvals | Purchase orders (PO) |
| Planning demand groups + control reports | PO-based goods receipt (partial/full) + cycle close |
| Engagement **readers**/guards on PR | Engagement **writers** on RFQ publish |

**S04 code must not ship RFQ/PO/GRN.** S05 design for PO↔inventory linkage: [PHASE_S05_PROCUREMENT_CYCLE_DESIGN.md](PHASE_S05_PROCUREMENT_CYCLE_DESIGN.md) (extends [ADR-0011](../../10-decisions/ADR-0011-INVENTORY-LEDGER-BALANCE-AND-TRANSFER.md) without replacing manual `ReceiveStock`).

### S05 operational cycle (binding for design)

Approved PR → RFQ → supplier offers → compare & select → PO → partial/full receipt → close  
Integrations: suppliers (S04) · optional contracts link · warehouses/inventory posting on GRN.

## Domain separations

| Concept | This module | Not this module |
|---|---|---|
| **Supplier** | Tenant vendor/service provider master | Contracts `counterparty_name` / category code `supplier` |
| **Purchase request (PR)** | Internal need / requisition | Purchase order, invoice, payment |
| **Evaluation** | Weighted scored history | Automatic supplier suspension |
| **Financial review** | Manual review outcome on PR | Accounting entries / budget reservation |
| **Approval matrix** | Tenant-configurable PR routing | Decision/Contract single-step approve reuse as-is |

## Dependencies

| Dependency | Use |
|---|---|
| Tenancy (ADR-0003) | `TenantOwned` + `UsesTenantScope`; cross-tenant → 404 |
| RBAC (`PermissionCatalog`) | Deny-by-default Policies |
| Audit (ADR-0015) | Immutable app audit via existing recorder patterns |
| Documents (ADR-0010) | Private files; eligibility metadata beside immutable blobs |
| Notifications (ADR-0013) | In-app alerts for approvals / SLA / low evaluation |
| Organization units | Requester / beneficiary department |
| Seasons (ADR-0017) | Required `season_id` for seasonal PRs only |
| Inventory items (optional FK) | Catalog reference on PR lines — no stock mutation in S04; S05 GRN may post stock for linked items |
| Contracts | Optional gradual link (**111**): PO.`contract_id` and/or nullable `contracts.supplier_id` without backfill — S05 Part 3 design |

## Personas (expected consumers)

| Persona | Typical use |
|---|---|
| Department requester | Create/edit draft PR; respond to return-for-revision |
| Procurement officer | Supplier registry, categories, eligibility, planning consolidation |
| Financial reviewer | Manual financial / budget-availability review on PR |
| Approver (matrix levels) | Approve / reject / return within authority |
| Evaluator | Draft supplier evaluations |
| Evaluation approver | Approve evaluations (≠ drafter) |
| Auditor | View audit / compliance reports |
| Tenant Owner / GM | Configuration of matrices, categories, SLA targets (via permissions — no bypass) |

## Documents in this folder

| File | Role |
|---|---|
| [BUSINESS_RULES.md](BUSINESS_RULES.md) | Domain rules (S04 101–130 + **§7 S05**) |
| [DATA_MODEL.md](DATA_MODEL.md) | Tables (S04 implemented + **§10 S05 design**) |
| [PERMISSIONS.md](PERMISSIONS.md) | Catalog (S04 seeded + **§6 S05 design**) |
| [API.md](API.md) | REST (S04 live + **§16 S05 design**) |
| [UI.md](UI.md) | Arabic RTL UX (S04 + **§10 S05 design**) |
| [TEST_PLAN.md](TEST_PLAN.md) | Pest / Vitest (S04 + **§12 S05 design**) |
| [ACCEPTANCE_CRITERIA.md](ACCEPTANCE_CRITERIA.md) | DoD (S04 met + **§G S05 drafted**) |
| [PHASE_S05_PROCUREMENT_CYCLE_DESIGN.md](PHASE_S05_PROCUREMENT_CYCLE_DESIGN.md) | **S05 Part 0** detailed design pack |
| [S05_IMPLEMENTATION_PLAN.md](S05_IMPLEMENTATION_PLAN.md) | Parts 0–6 plan + acceptance mapping |
| [PHASE_II_A_ELIGIBILITY_DESIGN.md](PHASE_II_A_ELIGIBILITY_DESIGN.md) | II-A eligibility + Documents (**COMPLETE — OWNER ACCEPTED** 2026-11-01; C1–C6; M1=A) |
| [PHASE_II_B_SUPPLIER_BANKING_DESIGN.md](PHASE_II_B_SUPPLIER_BANKING_DESIGN.md) | II-B supplier banking design (**OWNER APPROVED**; implementation **FUNCTIONALLY CLOSED — OWNER ACCEPTED** 2026-11-01) |
| [PHASE_II_B_WP05_ACCEPTANCE_EVIDENCE.md](PHASE_II_B_WP05_ACCEPTANCE_EVIDENCE.md) | II-B WP-05 acceptance evidence matrix (**OWNER ACCEPTED** with Phase II-B closure) |
| [PHASE_II_C_SUPPLIER_EVALUATIONS_DESIGN.md](PHASE_II_C_SUPPLIER_EVALUATIONS_DESIGN.md) | II-C supplier evaluations design (**DRAFT**; **IIC-D1–D8** approved; detailed design **not** Owner-accepted) |

## Implementation authorization

1. ~~Owner approval of ADR-0023~~ **Done** (2026-10-31).
2. ~~Owner approval of Engineering CR design/scope~~ **Done** (2026-10-31).
3. ~~G2 Phase I implementation authorization~~ **Granted** (2026-10-31) and **Owner-accepted** (2026-10-31) — see [S04_PHASE_I_ACCEPTANCE_STATUS.md](../../00-project/S04_PHASE_I_ACCEPTANCE_STATUS.md).
4. ~~Phase II Owner clarifications II-D1–II-D4~~ **Recorded** (2026-10-31) — documentation only; **not** coding authorization.
5. ~~II-A Eligibility & Documents design (controls C1–C6)~~ **Approved** (2026-10-31).
6. ~~II-A engineering pre-implementation gate~~ **COMPLETE** (2026-10-31).
7. ~~Phase II-A implementation + remediation (H1, M1=A, M2–M5)~~ **Owner-accepted** (2026-11-01) — [S04_PHASE_II_A_ACCEPTANCE_STATUS.md](../../00-project/S04_PHASE_II_A_ACCEPTANCE_STATUS.md).
8. Phase II-B Owner decisions **IIB-D1–IIB-D12** recorded/approved (2026-11-01); design pack **OWNER APPROVED**; documentation closure M1–M3 **VERIFIED**; WP-01–WP-05 **OWNER ACCEPTED**; Phase II-B **COMPLETE — FUNCTIONALLY CLOSED** (2026-11-01).
9. Phase II-C Owner decisions **IIC-D1–D8 + IIC-F1–F3** recorded/approved (2026-11-01; **11/11**); detailed design pack **DRAFT** (not Owner-accepted as final design); coding **NOT AUTHORIZED**.
10. **S04** is functionally closed (Owner 2026-11-02). **S05 Parts 0–6 Owner Accepted — functionally final closed (2026-11-03)**. Merge awaits PR review + CI. **Do not start S06** or deploy without separate Owner acts.

## References

- [MODULE_TEMPLATE.md](../../02-architecture/MODULE_TEMPLATE.md)
- [DEFINITION_OF_DONE.md](../../00-project/DEFINITION_OF_DONE.md)
- [PHASE2_SCOPE_MATRIX.md](../../00-project/PHASE2_SCOPE_MATRIX.md)
- [05-contracts/](../05-contracts/) · [10-warehouses-and-inventory/](../10-warehouses-and-inventory/) · [16-season-and-pilgrims/](../16-season-and-pilgrims/)
