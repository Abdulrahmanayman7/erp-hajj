# S05 — Procurement Cycle Detailed Design (RFQ → PO → Receipt → Close)

> **Status:** `DESIGN PACK — PART 0 COMPLETE` (documentation only)  
> **Date:** 2026-11-02  
> **Module:** Same bounded context as S04 — `SuppliersAndProcurement` / `docs/09-modules/19-suppliers-and-procurement/` (**no separate module**)  
> **Product parents:** [ADR-0020](../../10-decisions/ADR-0020-FULL-PHASE2-OPERATIONAL-SCOPE.md) §S05 · [PHASE2_SCOPE_MATRIX.md](../../00-project/PHASE2_SCOPE_MATRIX.md)  
> **S04 precondition:** [S04-FINAL-FUNCTIONAL-CLOSURE-2026-11-02.md](../../10-decisions/S04-FINAL-FUNCTIONAL-CLOSURE-2026-11-02.md)  
> **S04 engineering parents (unchanged):** [ADR-0022](../../10-decisions/ADR-0022-S04-SUPPLIERS-AND-PURCHASE-REQUESTS-FUNCTIONAL-DECISIONS.md) · [ADR-0023](../../10-decisions/ADR-0023-S04-ENGINEERING-DESIGN.md)  
> **Implementation plan:** [S05_IMPLEMENTATION_PLAN.md](S05_IMPLEMENTATION_PLAN.md)  
> **Does not:** authorize Parts 1–6 coding · migrations · DB writes · commit/push/merge/deploy · payments · finance ledgers · rewrite S04 accepted behavior  
> **PDF sources:** `ERP_Hajj_Phase_2_Workflow_Reviewed.pdf` / `فكرة منصتي.pdf` are **not filed under `docs/`** (see PHASE2_SCOPE_RECONCILIATION). Cycle below aligns ADR-0020 §S05 + Owner-stated operational flow used for Part 0.

---

## 1. Purpose

Deliver the **operational procurement cycle** after an **approved** purchase request:

**Approved PR → RFQ (supplier invitations) → supplier offers → comparison & selection → Purchase Order (PO) → partial/full goods receipt → cycle close**

with controlled linkage to **suppliers** (S04), **contracts** (optional gradual link), and **warehouses/inventory** (PO receipt posting), without weakening S04 locks, tenancy, RBAC, or audit.

---

## 2. Binding cycle (states overview)

```text
PR(approved)
   │
   ▼
RFQ draft → RFQ published  ──writes──► purchase_requests.procurement_engaged_*
   │                         + DemandGroup engagement freeze (existing guard)
   ▼
Invitations → Offers (quotations)
   │
   ▼
Selection (award) ──► single winning supplier (+ rationale)
   │
   ▼
PO draft → PO issued
   │
   ▼
GRN / receipt (partial allowed) ──► inventory movement when item linked
   │
   ▼
Cycle close (fully received OR short-close remainder with reason)
```

Demand groups remain **planning-only** (ADR-0022 **123** / Phase V). They do **not** create RFQ/PO. RFQ upstream is **approved PR** only.

---

## 3. Non-goals (unchanged Phase 3 / S04 exclusions)

- Payments, accounting entries, budget reservation, e-invoice  
- Supplier self-service portal  
- Using Contracts module as a purchase-order substitute  
- Inventory mutation from PR (still forbidden)  
- Merging multiple PRs into one RFQ via demand group (forbidden by **123** in S04; not introduced here)  
- GPS / S06–S10 scope  

---

## 4. Engineering locks (from approved scope + stated cycle — not new product questions)

| Topic | Lock for S05 design |
|---|---|
| RFQ source | **Approved PR only** (1 RFQ may cover one PR; lines copy/adapt from PR lines) |
| Season | Inherit `season_id` when PR `request_kind=seasonal`; administrative PR → RFQ/PO without season |
| Engagement write | Set `procurement_engaged_at` + opaque `procurement_engagement_ref` on **RFQ publish**; clear only via controlled RFQ cancel-before-award rules (§6.2) |
| Comparison | Manual comparison UI + **mandatory selection rationale**; single-offer award allowed with audit |
| PO approval | **No** reuse of PR multi-level matrix for PO in first delivery; issue gated by `purchase_orders.issue` after selection |
| Eligibility | Supplier must be **Active** and **eligible** at invite **and** at award (S04 use-time spirit) |
| Inventory post | Post ledger movement for receipt lines that carry `inventory_item_id`; free-text-only lines → operational receipt without stock post |
| Over-receipt | Reject when cumulative received qty on PO line would exceed ordered qty |
| Duplicate receipt | Idempotency key and/or unique business key per GRN line post; row lock on PO line |
| Partial receipt | Allowed while open qty > 0 |
| Cycle close | Full receive **or** short-close remainder with mandatory reason; then no further GRN |
| Module packaging | Extend **same** Laravel module / docs pack 19 |
| Banking on PO | **No** banking fields / payments on PO |
| Contracts | Optional `contract_id` on PO (same tenant); optional later `contracts.supplier_id` remains gradual (**111**) — **no forced historical backfill** |

---

## 5. Integration boundaries (preserve S04)

### 5.1 Suppliers (S04) — REUSE

- Invite/award only against tenant suppliers.  
- Re-run eligibility evaluation at invite and award (EligibilityService pattern).  
- Evaluations history may be **shown** for comparison context; scores do **not** auto-select.  
- Do not change supplier lifecycle transition graph.

### 5.2 Purchase requests / demand groups (S04) — REUSE + WRITE ENGAGEMENT

- RFQ requires `purchase_requests.status = approved`.  
- S05 **writes** `procurement_engaged_*` (S04 only reads/guards).  
- Existing cancel-approved / DG membership guards stay fail-closed.  
- Demand group UI banner (no RFQ/PO) remains true for Phase V screens.

### 5.3 Contracts (MVP) — OPTIONAL LINK

- PO may reference `contract_id` (nullable, same tenant).  
- Contracts continue to use `counterparty_name` / `counterparty_kind`.  
- Adding nullable `contracts.supplier_id` is **allowed as a non-breaking optional bridge** under ADR-0022 **111** / DATA_MODEL foreshadow — **must not** require backfill; **must not** change Contracts workflow transitions.  
- Contract ≠ PO ≠ payment.

### 5.4 Warehouses & inventory (MVP) — RECEIPT PATH

- Manual `ReceiveStock` remains for non-PO receipts (ADR-0011).  
- S05 GRN posts inbound movements with stable reference to PO+GRN (and ideally FK columns added under Inventory CR **within** S05 Parts — design in DATA_MODEL §S05).  
- Parallel manual receive **must not** be treated as fulfilling PO qty (PO fulfillment only via GRN).  
- No negative stock; active warehouse + active item required for stock-posting lines.

### 5.5 Documents / notifications / audit / seasons

- Morph aliases for `rfq`, `purchase_order`, `goods_receipt` (names Eng).  
- In-app notifications for RFQ publish, offer deadline (if set), award, PO issue, receipt, close (ADR-0013).  
- Audit every material transition (ADR-0015).  
- Season Context: validate consistency when seasonal; do not invent new season model.

---

## 6. Domain rules (summary — full text in BUSINESS_RULES §S05)

### 6.1 RFQ

Statuses (Eng): `draft` → `published` → (`awarded` | `cancelled`).  
Publish requires ≥1 line and ≥1 invited supplier (or invite-after-publish allowed Eng: prefer invite before/at publish).  
Publish writes engagement on source PR.

### 6.2 Engagement unlock

If RFQ cancelled **before** selection/award: clear `procurement_engaged_*` on PR **only if** no other published/awarded RFQ remains for that PR; audit unlock.  
After award or PO issue: engagement remains until cycle close policy (Eng: keep engaged through close).

### 6.3 Offers & selection

Offer statuses: `draft`/`submitted`/`withdrawn`/`selected`/`rejected`.  
Exactly one selected supplier per awarded RFQ.  
Mandatory `selection_rationale`.

### 6.4 PO

Statuses: `draft` → `issued` → (`partially_received` | `received` | `closed` | `cancelled`).  
Cancelled issued PO rules: only if no posted GRN qty (or reverse path out of scope — forbid cancel after any receipt).  
Supplier on PO = awarded supplier.

### 6.5 Receipt & close

GRN statuses: `draft` → `posted` (immutable after post).  
Posted GRN increments received qty; may post inventory movements.  
Close: when open qty = 0 **or** short-close with reason setting remaining open qty to zero without stock.  
Reject over-receipt and duplicate post.

---

## 7. Entities (see DATA_MODEL §S05)

| Aggregate | Role |
|---|---|
| `procurement_rfqs` + lines + invitations | RFQ |
| `supplier_offers` + offer lines | Quotations |
| `procurement_selections` (or fields on RFQ) | Award record |
| `purchase_orders` + lines | PO |
| `goods_receipts` + lines | GRN |
| Link tables / FKs | PR, supplier, optional contract, inventory item, warehouse, movement |

---

## 8. Permissions / API / UI / tests / AC

Canonical catalogs live in:

- [PERMISSIONS.md](PERMISSIONS.md) §S05  
- [API.md](API.md) §16  
- [UI.md](UI.md) §S05  
- [TEST_PLAN.md](TEST_PLAN.md) §S05  
- [ACCEPTANCE_CRITERIA.md](ACCEPTANCE_CRITERIA.md) §S05  

Implementation slices: [S05_IMPLEMENTATION_PLAN.md](S05_IMPLEMENTATION_PLAN.md).

---

## 9. Code conflicts to resolve at implementation time (not Part 0 code)

| Observation at `2d128a7` | Design response |
|---|---|
| No RFQ/PO/GRN tables/routes/permissions | Add in Parts 1–5 |
| `procurement_engaged_*` unread in Resource/FE | Expose on PR resource when engagement written |
| `inventory_movements` lacks PO/GRN FK | Add nullable FKs or link table in Part 4 Inventory touch migration |
| Contracts lack `supplier_id` | Optional nullable column + no backfill (Part 3) **or** PO.`contract_id` only for first slice |
| Demand group banner forbids RFQ | Keep; RFQ lives on separate pages |
| TEST_PLAN §8 says “No new routes under `/rfqs`” for **S04** | Remains true for S04 regression; S05 adds routes under authorized Parts |

---

## 10. Part 0 completion checklist

- [x] Cycle documented in this pack  
- [x] BUSINESS_RULES / DATA_MODEL / API / PERMISSIONS / UI / TEST_PLAN / ACCEPTANCE_CRITERIA updated with §S05  
- [x] Implementation plan Parts 1–6 with acceptance criteria  
- [x] Integrations defined without changing S04 accepted behavior  
- [x] No application code / migrations / Git release ops in Part 0  
