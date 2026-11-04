# Suppliers & Procurement (S04) — Business Rules

> **Status:** Phase I–V rules Owner-functionally accepted; S04 **FINAL FUNCTIONAL CLOSURE — OWNER GRANTED (2026-11-02)**. Residual S04 follow-ups preserved. **S05 Part 0 design docs COMPLETE (2026-11-02)** — [PHASE_S05_PROCUREMENT_CYCLE_DESIGN.md](PHASE_S05_PROCUREMENT_CYCLE_DESIGN.md). **S05 Parts 1–6 coding / release acts NOT AUTHORIZED**.  
> **Last updated:** 2026-11-02
> Binding functional decisions: [ADR-0022](../../10-decisions/ADR-0022-S04-SUPPLIERS-AND-PURCHASE-REQUESTS-FUNCTIONAL-DECISIONS.md) **101–130** (S04) · [ADR-0020](../../10-decisions/ADR-0020-FULL-PHASE2-OPERATIONAL-SCOPE.md) §S05 (cycle membership)
> Engineering proposals: [ADR-0023](../../10-decisions/ADR-0023-S04-ENGINEERING-DESIGN.md) (S04) · S05 design pack (this folder)

Legend: **(Fn)** = functional decision n. Labels **ENGINEERING PROPOSAL** / **OWNER DECISION REQUIRED** / **DEFERRED** mark non-frozen implementation choices.

---

## 0. Global invariants

1. Every business row is **tenant-owned**; `tenant_id` from `TenantContext` only — never from client payload (**102**, ADR-0003).
2. Cross-tenant lookups → **404** (not 403).
3. Deny-by-default; Tenant Owner does **not** bypass Policies.
4. No RFQ / quotation / PO / inventory receive-against-PR in S04 (**S05**, ADR-0020).
5. No payments, accounting postings, or budget reservations (**110**, **120**).
6. Contracts category `supplier` ≠ supplier master (**111**).
7. Critical actions audited with correlation ID; bank secrets never stored in audit values (**130**, ADR-0015).

---

## 1. Supplier registry (101–111)

### 1.1 Identity

- One **unified** supplier record per tenant; many-to-many with service categories (**101**).
- Server-generated immutable internal number: **ENGINEERING PROPOSAL** `SUP-######` via per-tenant sequence + `FOR UPDATE` (Contracts pattern).
- Legal identifier uniqueness: tenant-scoped unique on `(tenant_id, legal_id_type, legal_id_normalized)` when legal id present (**103**).
- Name similarity warnings: **ENGINEERING PROPOSAL** — non-blocking check (normalized Arabic/Latin name) on create/update; does not replace legal-id uniqueness.

### 1.2 Types & categories

- Types (**105**): `company` | `establishment` | `individual` | `government_nonprofit` | `international`. Required legal fields differ by type + country — matrix is **tenant-configurable defaults** seeded for Hajj; exact seed set is **OWNER DECISION REQUIRED** only if defaults must be mandated platform-wide (otherwise ENGINEERING: seed suggested Arabic Hajj categories, tenant-editable).
- Categories (**104**): tenant-owned catalog; deactivate instead of hard-delete when linked; hard-delete only when unused.

### 1.3 Geographic coverage (**106**) — Owner **D1**

- Coverage is per **(supplier, category)** → one or more geo places from the **minimal geographic catalog**.
- Country codes reuse ISO-style codes already used on pilgrims (`nationality` / issuing country patterns).
- No ad-hoc free-text location lists as the system of record.
- **Initial catalog scope (D1):** Makkah, Madinah, Jeddah, and Hajj holy sites Mina, Arafat, Muzdalifah — plus parent country SA as needed for hierarchy. Catalog is **extensible**; uniqueness prevents duplicate places.
- Exact seed row identifiers/codes must be documented and validated against authoritative geographic references **before implementation** (engineering checklist; not an excuse to invent a world gazetteer).

### 1.4 Lifecycle (**107**, **108**)

| Status | May use on **new** PRs as selected supplier?* |
|---|---|
| `draft` | No |
| `under_review` | No |
| `active` | Yes, if **eligible** (documents + not blocked) |
| `temporarily_suspended` | No |
| `blocked` | No |
| `archived` | No |

\*PR creation does **not** require selecting a supplier (**ADR-0022 §5**). When a supplier **is** referenced (future optional link or planning fields), eligibility applies.

Transitions: permission-gated; each transition audited. Qualification gate: cannot move to `active` while mandatory documents missing/expired (**107**, **109**).

### 1.5 Documents (**109**)

- Requirements by type × country × (optional) category.
- File bytes via Documents module (ADR-0010 immutable files).
- Eligibility metadata (requirement key, `expires_at`, linked `document_id`, status) lives in supplier-domain tables — **not** by mutating Document versioning (forbidden by ADR-0010).
- New file upload = new Document + new eligibility row / supersede prior row (**ENGINEERING PROPOSAL**).
- Missing/expired mandatory docs → supplier not eligible for activation / new PR supplier use.

### 1.6 Contacts & bank accounts (**110**)

- Multiple contacts (Phase I **implemented**).
- Multiple bank-account **versions** per supplier (Phase II-B **FUNCTIONALLY CLOSED — OWNER ACCEPTED** 2026-11-01 — [PHASE_II_B_SUPPLIER_BANKING_DESIGN.md](PHASE_II_B_SUPPLIER_BANKING_DESIGN.md) · [S04_PHASE_II_B_ACCEPTANCE_STATUS.md](../../00-project/S04_PHASE_II_B_ACCEPTANCE_STATUS.md)):
  - Mandatory account holder name (**IIB-D1**); free-text bank name (**IIB-D2**); ≥1 of IBAN or local account (**IIB-D3**); SAR only (**IIB-D10**).
  - Statuses: `pending` → `verified` \| `rejected`; `verified` → `revoked` (**IIB-D7**).
  - Verified identifiers immutable; changes via supersession to a new pending version (**IIB-D8**); prior verified is **not** auto-revoked on supersede. Pending versions may PATCH identifiers with claim maintenance (**M1**).
  - Only **verified** may be primary; reject/revoke clears primary; **no** auto-promote (**IIB-D9**).
  - **IIB-D11=A:** superseding a verified primary **retains** primary on the old verified version; pending never auto-primary; verifying the replacement does **not** transfer primary; transfer only via explicit authorized `set-primary`.
  - Creator/all editors cannot verify that version (**IIB-D4**). Optional Document evidence (**IIB-D5**).
  - Tenant IBAN exclusivity (**IIB-D6**) via **claims table** (not unique historical fingerprints): one owning family per fingerprint; same-family supersession allowed; IBAN still held by a verified primary remains claimed even if that row is not family head; pending PATCH IBAN change acquires/releases claims; safe release **DELETE**s the claim row with ownership checks (**M1**/**M2**; design §2.4).
  - List/show always masked; reveal only via audited action with `view_banking` + trimmed reason ≥10 chars (**IIB-D12**, **E1**/**E4**/**M3**). Revoke = `manage_banking` (**E2**). Supersede requires full identifier input — no silent ciphertext copy (**E5**). Ciphertext at rest (ADR-0023). **No payments.**
  - **II-D2:** verified bank account is **not** required for supplier activation.

### 1.7 Contracts / ops link (**111**)

- S04 may document optional nullable `contracts.supplier_id` as **future** integration.
- **Not** in S04 mandatory migrations unless Owner approves separate Contracts CR.
- No forced backfill of historical contracts.

---

## 2. Supplier evaluation (112–116) — Phase II-C

> Design pack: [PHASE_II_C_SUPPLIER_EVALUATIONS_DESIGN.md](PHASE_II_C_SUPPLIER_EVALUATIONS_DESIGN.md) · Owner decisions **IIC-D1–D8** · API [API.md §14](API.md). **Not implemented.**

- Multi-criteria weighted score 0–100; weights by service category (**112**, **IIC-D2**).
- Types: qualification | periodic | operational | post_season; **`post_season` requires same-tenant season**; other kinds optional season (**IIC-F1**, **113**).
- Criteria packs: draft → independent publish → immutable (**IIC-D1**, **II-D4**); no platform default pack.
- Snapshot criteria at **submit**; approved scores immutable (**IIC-D2**).
- History via version family; drafts editable by preparer.
- Drafter ≠ approver (**114**).
- Preparer may **propose** `monitoring_recommended`; approver sets **final** on approve; notify in-app **only** after approve when final true, to holders of `supplier_evaluations.approve` (**IIC-D3**, **IIC-D7**, **IIC-F2**, **IIC-F3**); **no auto suspend** (**115**, **II-D3**).
- Appeals register; accepted correction → new evaluation version (**116**, **IIC-D4**). No supplier portal.
- Optional evidence Documents (**IIC-D8**).

---

## 3. Purchase requests (117–123)

> **Phase III binding:** [PHASE_III_PURCHASE_REQUESTS_DESIGN.md](PHASE_III_PURCHASE_REQUESTS_DESIGN.md) · **III-D1–D8 APPROVED**. Coding **NOT AUTHORIZED** until detailed design Owner acceptance + separate coding auth. Phase IV owns approve/reject/return/`cancel_approved`.

### 3.1 Header

- Decentralized create by authorized users; store `requested_by`, `beneficiary_organization_unit_id` (**117**).
- Beneficiary org unit: **active** same-tenant unit only; **no** requester membership / row-level org ACL (**III-D5**).
- `request_kind`: `seasonal` | `administrative` (**118**).
  - `seasonal` → `season_id` **required** (same-tenant Season).
  - `administrative` → `season_id` null.
- Priority: `normal` | `urgent` | `emergency`; urgency justification required if not normal (**122**).
- Supplier on PR: **optional**; if set → same-tenant, not archived, eligibility enforced (**III-D4**; ADR-0022 §5). Cross-tenant → **404**; ineligible → **422**.

### 3.2 Lines (**119**)

- ≥1 line on submit.
- Each line: description/specs, qty, unit, need_date, optional attachments, optional `inventory_item_id` **or** free text.
- Catalog FK does **not** reserve or mutate stock.

### 3.3 Estimates & financial review (**120**) — Owner **D2** + **III-D2** / **III-D3**

- Per-line optional `unit_estimate` / `line_estimate`; `unpriced_reason` required when price absent.
- Header shows partial priced total + `has_unpriced_lines`.
- Currency default **SAR**.
- **Financial review records (Phase III):** may be written on **`draft` or `submitted`** independently of the approval engine (**III-D2**). Persist `reviewer_id`, `outcome` (`cleared` | `not_cleared` | `cleared_with_conditions`), `reviewed_at`, `justification`, `version_no`. Creator **may** review if they hold `financial_review` (**III-D3**).
- Review does **not** change PR status in Phase III and does **not** create accounting/budget/payment effects.
- **Final approval gate (Phase IV / D2):** blocked unless current version’s latest review ∈ {`cleared`, `cleared_with_conditions`}.
- **Financial reviewer ≠ final approver (D5):** enforced at Phase IV final approve — see §4.3.1.

### 3.4 Lifecycle & versions (**121**, **128**) — **III-D1**, **III-D7**

| Status | Meaning | Phase III writer? |
|---|---|---|
| `draft` | Editable | Yes |
| `submitted` | Submitted; **pending Phase IV approval engine** (**III-D1**) — not “approved” | Yes (via submit) |
| `under_review` | In approval workflow | **No** (Phase IV) |
| `returned` | Returned for revision | **No** (Phase IV sets); editable when present |
| `approved` / `rejected` | Terminal approval outcomes | **No** (Phase IV) |
| `cancelled` | Cancelled | Yes (non-approved cancel) |

Rules:

- **Submit (III-D1):** `draft`/`returned` → `submitted`; create **structured** immutable version header+lines (**III-D7**); **do not** create matrix snapshot routing, approval instances, or steps in Phase III (`matrix_id` remains null).
- No direct field edit while `submitted` / `under_review` / terminal (except system/approval actions in IV).
- Return → `returned` (IV); author edits; resubmit → new `version_no` + new structured snapshot; prior approval steps invalidated (**128**, IV).
- **Cancel:**
  - Non-approved (`draft` / `submitted` / later `returned` / `under_review`): `purchase_requests.cancel` + reason + audit.
  - **Approved:** `purchase_requests.cancel_approved` (**D3**) — **Phase IV+** only.
  - Engagement lock `procurement_engaged_*` blocks cancel when set (S05 writers; S04 leaves unset).

Numbering: `PR-######` per tenant, sequence `FOR UPDATE`.

### 3.5 Duplicates & consolidation (**123**) — **III-D6**

- Soft warning when similar item/season/need_date windows match; submit requires **explicit acknowledgement** (**III-D6**). Similarity alone must not hard-block.
- Planning-only **demand group** (**Phase V**, Owner **V-D1…V-D5**): links **approved** PR **headers**; ≤1 active membership per PR; optional season/OU with mixed-scope warnings; lifecycle `draft→active→frozen→closed`; `cancel_approved` **auto-unlinks** (history retained); procurement engagement **freezes** membership edits; Phase V **never** writes engagement fields; **no** RFQ/PO merge (S05). Detail: [PHASE_V_DETAILED_DESIGN.md](PHASE_V_DETAILED_DESIGN.md).

---

## 4. Approvals (124–130) — **Phase IV** (not Phase III)

> Phase III ends at `submitted` + financial-review **records** (**III-D1**/**III-D2**). Section 4 is binding for **Phase IV** design/coding when separately authorized. Do not implement approve/reject/return/matrix routing in Phase III (**III-D8**).

### 4.1 Dynamic routes (**124**) — **IV-D1** / **EC-01**

- Tenant configures matrices by estimated value band, department, request_kind, optional season, and **unpriced path** flags (**IV-D2** / **EC-03**).
- Phase III submit/resubmit sets `submitted` only (**III-D1**). Phase IV **after-commit queued** routing (**EC-01**) snapshots the selected published matrix version onto the PR (**125**) and creates instance/steps, or fails closed (**IV-D6**).
- Later matrix edits do not rewrite in-flight snapshots.
- Incomplete config / empty eligible path → remain `submitted` with durable block reason; safe retry after repair.

### 4.2 Authority matrix (**125**)

- Bands and levels are tenant data.
- **No default numeric thresholds are approved** in ADR-0022 — tenants must configure before production use (**OWNER DECISION REQUIRED** per tenant go-live; platform may ship empty matrix requiring setup).
- User cannot approve above their level’s ceiling.
- Unpriced/mixed PRs: never treat null as zero; never use priced subtotal alone to select a lower path (**IV-D2** / **EC-03**). Authority rows carry `covers_unpriced` for unpriced-path eligibility.

### 4.3 Segregation & conflicts (**126**)

- Creator cannot approve own PR at any level (**IV-D5**).
- Conflict detection: same user as requester; optional same org-unit dual-control rules (**ENGINEERING PROPOSAL**: minimum = creator ≠ approver; extended COI rules tenant flags).
- Pool model (**IV-D4**): exclude conflicted users from the eligible pool; do not skip the level.

#### 4.3.1 Financial reviewer ≠ final approver (**D5** / **IV-D10=C**)

Hard enforcement (not a warning) on every PR version:

1. The `reviewer_id` of the current version’s financial review **must differ** from the user who completes **final** approval.
2. Applies even when the user holds multiple roles/permissions.
3. **Delegation must not bypass** — the acting user (delegate) is checked against `reviewer_id`.
4. **IV-D10=C:** automatically **exclude** the financial reviewer from the final-approver pool; another eligible actor may approve without manual reassignment.
5. If no eligible final approver exists, **block** final approval and **notify** holders of `purchase_requests.manage_approval_config` (**EC-04**) — never auto-approve, waive, or skip mandatory steps.
6. Audit conflict detection, pool exclusion, escalation, and blocked attempts.

### 4.4 Delegation (**127** / **IV-D11** / **EC-02**)

- Time-bounded **depth-1** delegation from approver A → B within A’s **direct** authority ceiling.
- Self-delegation (A creates A→B) requires A’s `approve` + direct authority; admin create/revoke uses `manage_delegations`.
- No re-delegation of delegated authority; `from ≠ to`; no overlapping active identical pairs.
- Conflict/SoD checks on **acting** user B; auto-expire; immediate revoke.
- Audit records delegator, delegate, authorizer, and acting user on decisions.
- **D5 / IV-D10:** delegate who is the financial reviewer for the PR version cannot complete final approval.

### 4.5 SLA (**129** / **IV-D12**)

- Target durations by priority × level are **tenant-configurable**; no approved global durations.
- Reminders then escalation **notifications** to eligible pool + `manage_approval_config` holders (**EC-04**) — not role-name fanout.
- **Never** auto-approve, skip, or reassign on timeout.

### 4.6 Audit & reports (**130**)

- All material events append-only via platform audit + domain transition tables.
- Control reports (**Phase V**, **V-D6=A** / **V-D8=A**): RPT-V01 latency, RPT-V02 overdue, RPT-V03 outcomes, RPT-V04 compliance — JSON + **CSV export for all four**; `purchase_requests.view_control_reports`; tenant-scoped; no bank secrets; formula-injection safe CSV; UTF-8 BOM for Arabic Excel.
- Retention policy detail: **DEFERRED** to ops/security baseline unless Owner sets a number.

---

## 5. Notifications (supporting)

Emit in-app notifications (ADR-0013) for Phase IV events per **IV-D14** / **EC-04** (pool entry, due/SLA, returned, rejected, approved, cancel-approved, routing/SoD admin alerts). Exact types: see [PHASE_IV_APPROVAL_ENGINE_DESIGN.md](PHASE_IV_APPROVAL_ENGINE_DESIGN.md) §8 / §19. Admin recipients = `purchase_requests.manage_approval_config` (never role-name Owner/GM broadcast).

---

## 6. Explicit non-goals (S04)

RFQ, quotes, vendor selection, PO, GRN against PO, payments, e-invoice, supplier self-service portal, forced contract backfill, inventory mutations from PR.

> **Note:** Items above remain **out of S04**. They are specified for **S05** in §7+ and the design pack — still **not coded** until Parts 1–6 are Owner-authorized.

---

## 7. S05 — Procurement cycle business rules

Binding narrative: [PHASE_S05_PROCUREMENT_CYCLE_DESIGN.md](PHASE_S05_PROCUREMENT_CYCLE_DESIGN.md). S04 invariants §0.1–0.3, 0.5–0.7 still apply. §0.4 (“No RFQ… in S04”) remains true for the S04 slice.

### 7.1 Upstream

1. RFQ may be created only from a same-tenant PR with status **`approved`**.
2. Demand groups **do not** create RFQ/PO (**123** preserved).
3. Seasonal PR → RFQ/PO carry the same `season_id`. Administrative PR → no season required on RFQ/PO.
4. RFQ lines derive from PR lines (qty/specs/unit/`inventory_item_id` where present); RFQ qty must not exceed open PR demand without explicit Eng short-rationale field (default: copy PR line qty 1:1 for first delivery).

### 7.2 Engagement lock (writers = S05)

1. On **RFQ publish**, set `purchase_requests.procurement_engaged_at = now()` and `procurement_engagement_ref` to an opaque RFQ business ref (e.g. RFQ number) — **no cross-schema FK required** in the lock columns.
2. Existing S04 cancel-approved / cancel / DG membership guards remain fail-closed.
3. Idempotent DG freeze via existing `PurchaseDemandGroupEngagementGuard` behavior.
4. **Unlock:** if RFQ is cancelled **before** selection/award **and** no other published/awarded RFQ exists for that PR → clear engagement fields + audit. After award/PO issue, keep engaged through cycle close.

### 7.3 Invitations, offers, selection

1. Invite only suppliers that are **Active** and **eligible** at invite time.
2. Re-check Active + eligible at **award**; failure → 422 stable code (e.g. `SUPPLIER_NOT_ELIGIBLE`).
3. Offers belong to one RFQ + one invited supplier; quantities/prices per line.
4. Selection: exactly **one** winning supplier per awarded RFQ; **mandatory** `selection_rationale`; single-offer RFQ may award with rationale.
5. Historical supplier evaluations may be displayed; they **never** auto-award.

### 7.4 Purchase orders

1. PO created from awarded RFQ; supplier = selected supplier; lines from awarded offer / RFQ lines.
2. Optional nullable `contract_id` (same tenant). Does not replace Contracts lifecycle.
3. Optional nullable `contracts.supplier_id` bridge allowed without historical backfill (**111**).
4. Issue requires `purchase_orders.issue`. No PR approval-matrix reuse for PO in first delivery.
5. Cancel issued PO only if **zero** posted received qty; otherwise 422.
6. No payment / banking / budget reservation fields on PO.

### 7.5 Goods receipt & inventory

1. GRN against **issued** (or partially received) PO only.
2. Partial receipt allowed while remaining qty > 0.
3. **Over-receipt forbidden:** `sum(posted GRN line qty) + new qty ≤ PO line qty` (decimal-safe compare).
4. **Duplicate post forbidden:** Idempotency-Key and/or unique `(tenant_id, goods_receipt_id)` post once; concurrent second post fails safely.
5. Lines with `inventory_item_id` + warehouse → post inbound `inventory_movements` in same transaction as GRN post (extend ledger; do not bypass ADR-0011 balance rules / no negative stock).
6. Lines without inventory item → operational receive only (no stock movement).
7. Manual Inventory `ReceiveStock` **does not** increase PO fulfilled qty.

### 7.6 Cycle close

1. Close when remaining qty = 0 (fully received) **or** short-close with mandatory reason zeroing remainder without stock.
2. After close: reject new GRN posts (422).
3. Audit close actor, timestamp, reason, remaining-before.

### 7.7 S05 non-goals

Payments, e-invoice, supplier portal, auto-suspend from award, consolidating multiple PRs into one RFQ via demand group, treating Contracts as PO.
