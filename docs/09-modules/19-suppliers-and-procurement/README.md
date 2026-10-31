# Module: Suppliers and Procurement (الموردون والمشتريات) — S04 slice

> **Status:** Designed (G1 documentation) — **NOT IMPLEMENTED**  
> **Last updated:** 2026-10-31  
> **Functional ADR:** [ADR-0022](../../10-decisions/ADR-0022-S04-SUPPLIERS-AND-PURCHASE-REQUESTS-FUNCTIONAL-DECISIONS.md) (`APPROVED — FUNCTIONAL DECISIONS`)  
> **Engineering ADR:** [ADR-0023](../../10-decisions/ADR-0023-S04-ENGINEERING-DESIGN.md) (`APPROVED — ENGINEERING DESIGN`, Owner 2026-10-31)  
> **Change Request:** [CHANGE_REQUEST_S04_SUPPLIERS_AND_PROCUREMENT.md](../../00-project/CHANGE_REQUEST_S04_SUPPLIERS_AND_PROCUREMENT.md) (`APPROVED — DESIGN AND SCOPE ONLY; IMPLEMENTATION NOT AUTHORIZED`)  
> **Product scope parent:** [ADR-0020](../../10-decisions/ADR-0020-FULL-PHASE2-OPERATIONAL-SCOPE.md) §S04 / §S05  
> **G1 Owner design decisions D1–D5:** Approved 2026-10-31 (geo, mandatory financial review, approved-PR cancel, sensitive permission seeding, **financial reviewer ≠ final approver hard SoD**). Design approval ≠ G2 implementation authorization.

## Purpose

Provide a **tenant-isolated** supplier master, supplier evaluation, purchase-request (PR) lifecycle, and **multi-level PR approvals** for Hajj campaign companies — so procurement can later continue into RFQ/PO in **S05** without inventing a vendor registry inside Contracts or Inventory.

## S04 vs S05 (binding)

| In **S04** (this module pack) | Deferred to **S05** (out of this delivery) |
|---|---|
| Supplier registry & classifications | RFQ issuance |
| Supplier evaluation | Supplier quotations & comparison |
| Purchase requests (PR) | Supplier selection |
| Multi-level PR approvals | Purchase orders (PO) |
| Supporting notifications, audit, control reports | PO-based inventory receipt & cycle close |

**Do not** implement RFQ/PO/receive in S04. Inventory receipt remains the existing Inventory Actions path ([ADR-0011](../../10-decisions/ADR-0011-INVENTORY-LEDGER-BALANCE-AND-TRANSFER.md)) until S05 designs PO linkage.

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
| Inventory items (optional FK) | Catalog reference on PR lines — no stock mutation |
| Contracts | Optional **future** nullable `supplier_id` — separate CR; no forced historical migration |

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
| [BUSINESS_RULES.md](BUSINESS_RULES.md) | Domain rules & transitions (101–130) |
| [DATA_MODEL.md](DATA_MODEL.md) | Proposed tables / constraints (**ENGINEERING PROPOSAL**) |
| [PERMISSIONS.md](PERMISSIONS.md) | Proposed `module.action` catalog |
| [API.md](API.md) | Proposed REST contracts |
| [UI.md](UI.md) | Arabic RTL UX |
| [TEST_PLAN.md](TEST_PLAN.md) | Pest / Vitest / security |
| [ACCEPTANCE_CRITERIA.md](ACCEPTANCE_CRITERIA.md) | Verifiable DoD for S04 |

## Implementation authorization

This documentation package is **not** permission to code. ADR-0023 and the Engineering CR are approved for **design/scope only**. Implementation still requires:

1. ~~Owner approval of ADR-0023~~ **Done** (2026-10-31).  
2. ~~Owner approval of Engineering CR design/scope~~ **Done** (2026-10-31).  
3. A **separate** explicit Owner authorization to start application work (**G2+**) — **NOT GRANTED**.

## References

- [MODULE_TEMPLATE.md](../../02-architecture/MODULE_TEMPLATE.md)  
- [DEFINITION_OF_DONE.md](../../00-project/DEFINITION_OF_DONE.md)  
- [PHASE2_SCOPE_MATRIX.md](../../00-project/PHASE2_SCOPE_MATRIX.md)  
- [05-contracts/](../05-contracts/) · [10-warehouses-and-inventory/](../10-warehouses-and-inventory/) · [16-season-and-pilgrims/](../16-season-and-pilgrims/)
