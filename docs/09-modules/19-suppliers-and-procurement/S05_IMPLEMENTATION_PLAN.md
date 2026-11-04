# S05 — Implementation Plan (Parts 0–6)

> **Status:** Parts **0–6 Owner Accepted** — **S05 FUNCTIONALLY FINAL CLOSED (2026-11-03)**. GitHub CI / PR review **Pending**. Not merged / not deployed.  
> **Baseline reference:** `develop` @ `2d128a7` (S04 functionally closed).  
> **Design pack:** [PHASE_S05_PROCUREMENT_CYCLE_DESIGN.md](PHASE_S05_PROCUREMENT_CYCLE_DESIGN.md)  
> **Does not:** auto-start S06 · authorize prod migrations · merge/deploy without Owner act

---

## Part 0 — Documentation (this delivery)

| | |
|---|---|
| **Objective** | Freeze S05 design inside module 19 docs |
| **Deliverables** | Design pack + §S05 in BUSINESS_RULES, DATA_MODEL, API, PERMISSIONS, UI, TEST_PLAN, ACCEPTANCE_CRITERIA + this plan |
| **Code / DB** | None |
| **DoD** | Checklists in design pack §10; no S04 behavior rewritten |

---

## Part 1 — RFQ foundation + engagement writer

| | |
|---|---|
| **Status** | **Owner Accepted (2026-11-02)** — Part 1 closed functionally; historical AC-S05-R\* evidence retained |
| **Objective** | Create RFQ from **approved PR**; publish writes `procurement_engaged_*`; freeze DG membership via existing guard |
| **Backend** | Models/Actions/Policies/Requests/Resources for RFQ + lines + invitations; engagement writer Action; PermissionCatalog `rfq.*`; routes |
| **Database** | RFQ/lines/invitations + sequences migrations (applied only on `erp_hajj_testing` via RefreshDatabase) |
| **Frontend** | RFQ list/detail/create from PR; show engagement on PR detail |
| **Permissions** | `rfq.view`, `rfq.create`, `rfq.update`, `rfq.publish`, `rfq.cancel` (see PERMISSIONS §S05) |
| **Required tests** | Happy publish; reject non-approved PR; engagement blocks `cancel_approved`; DG membership locked; 401/403/422; cross-tenant 404; S04 Phase3/5 regression |
| **Dependencies** | Part 0; S04 closed |
| **Acceptance** | AC-S05-R01…R08 in ACCEPTANCE_CRITERIA §S05 |

---

## Part 2 — Offers, comparison, selection

| | |
|---|---|
| **Status** | **Owner Accepted (2026-11-03)** — Part 2 closed functionally; historical AC-S05-O\* evidence retained |
| **Objective** | Capture supplier offers; compare; select one supplier with rationale |
| **Backend** | Offer aggregates; submit/withdraw; selection Action; eligibility recheck Active+eligible at invite & award |
| **Database** | offers + offer lines (+ selection fields/table) |
| **Frontend** | Invite UI; offer entry; comparison table; award confirmation |
| **Permissions** | `rfq.invite`, `rfq.record_offer`, `rfq.select_supplier` (+ view) |
| **Required tests** | Ineligible supplier rejected; single winner; rationale required; audit; cross-tenant 404 |
| **Dependencies** | Part 1 |
| **Acceptance** | AC-S05-O01…O08 |

---

## Part 3 — Purchase orders + optional contract link

| | |
|---|---|
| **Status** | **Owner Accepted (2026-11-03)** — Part 3 closed functionally; historical AC-S05-P\* evidence retained |
| **Objective** | Issue PO from award; optional `contract_id`; optional nullable `contracts.supplier_id` without backfill |
| **Backend** | PO header/lines; issue/cancel-before-receipt; Policies; no payment fields; close endpoint delivered in Part 5 |
| **Database** | `purchase_orders` + lines + number sequences; nullable `contracts.supplier_id` (no backfill) |
| **Frontend** | PO list/create-from-award/detail/issue/cancel; optional contract picker; PR↔RFQ↔PO links |
| **Permissions** | `purchase_orders.view/create/update/issue/cancel` (`issue`/`cancel` sensitive explicit-only) |
| **Required tests** | PO supplier = award; non-awarded RFQ → 422; duplicate PO → 422; cancel after receipt → 422; contract cross-tenant 404; concurrent create; Contracts regression |
| **Dependencies** | Part 2 |
| **Acceptance** | AC-S05-P01…P08 |

---

## Part 4 — Goods receipt + warehouse posting + anti over/duplicate

| | |
|---|---|
| **Status** | **Owner Accepted (2026-11-03)** — Part 4 closed functionally; historical AC-S05-G\* evidence retained |
| **Objective** | Partial/full GRN; stock post for catalog lines; block over-receipt & duplicate post |
| **Backend** | GRN Actions; row lock; call `ReceiveStock` with PO/GRN FKs; warehouse validation; PO qty/status update in same transaction |
| **Database** | `goods_receipts` + lines + sequences; nullable PO/GRN FKs on `inventory_movements`; UNIQUE per GRN line post |
| **Frontend** | GRN list/create-from-PO/detail/post; remaining qty; PR↔RFQ↔PO↔GRN links |
| **Permissions** | `goods_receipts.view/create/post` (`post` sensitive explicit-only) |
| **Required tests** | Partial OK; over-qty 422; double-post reject; concurrency two posters; manual ReceiveStock independent; cross-tenant 404 |
| **Dependencies** | Part 3 |
| **Acceptance** | AC-S05-G01…G10 |

---

## Part 5 — Cycle close

| | |
|---|---|
| **Status** | **Owner Accepted (2026-11-03)** — Part 5 closed functionally; historical AC-S05-C\* evidence retained |
| **Objective** | Close cycle when fully received or short-close remainder with reason; block further GRN |
| **Backend** | `ClosePurchaseOrder`; status transitions; row locks vs GRN post; audit `PO_CLOSED` |
| **Database** | Uses existing PO `closed_at` / `closed_by` / `close_reason` (Part 3 migration) |
| **Frontend** | PO detail close CTA + remainder summary + mandatory short-close reason; hide receive after close |
| **Permissions** | `purchase_orders.close` (sensitive explicit-only) |
| **Required tests** | Close full; short-close; post-close GRN create/post 422; audit; concurrency close vs post; qty precision; RBAC/cross-tenant |
| **Dependencies** | Part 4 |
| **Acceptance** | AC-S05-C01…C05 |

---

## Part 6 — Integrated UI, hardening, regression

| | |
|---|---|
| **Status** | **Owner Accepted (2026-11-03)** — Part 6 closed functionally; S05 Functionally Final Closed pending GitHub CI/PR review |
| **Objective** | E2E cycle tests, nav/next-action polish, notifications, control visibility, CI green |
| **Backend** | `ProcurementCycleNotifier` (requester lifecycle); E2E + sensitive-template tests |
| **Frontend** | Next-action hints on PR/RFQ/PO/GRN; demand-group planning banner retained; Vitest hints |
| **Permissions** | Sensitive S05 set verified not in default templates |
| **Required tests** | E2E full/short-close; Vitest; full Pest S05 + S04/Inventory/Contracts; concurrency; tsc/build |
| **Dependencies** | Parts 1–5 |
| **Acceptance** | AC-S05-N01…N06; DEFINITION_OF_DONE checklist |
| **Evidence** | [S05_FINAL_READINESS_REPORT.md](../../00-project/S05_FINAL_READINESS_REPORT.md) |

---

## Authorization gate (binding)

| Act | Status |
|---|---|
| Part 0 documentation | **COMPLETE** (2026-11-02) |
| Parts 1–6 application implementation | **Owner Accepted — FUNCTIONALLY FINAL CLOSED (2026-11-03)** |
| Git branch / commit / push / PR | **AUTHORIZED** (Git finalization) — merge awaits review + CI |
| Production migrate / deploy / Git merge | **NOT AUTHORIZED** until Owner merge act |
| Sprint S06 | **NOT AUTHORIZED** |

**Do not start S06 automatically after S05.**
