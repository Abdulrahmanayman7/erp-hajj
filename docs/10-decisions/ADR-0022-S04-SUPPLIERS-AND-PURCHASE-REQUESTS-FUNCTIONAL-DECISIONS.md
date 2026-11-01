# ADR-0022 — S04 Suppliers and Purchase Requests — Functional Decisions (101–130)

> **Status:** `APPROVED — FUNCTIONAL DECISIONS`
> **Date:** 2026-10-31
> **Deciders:** Project Owner
> **Title slug:** `S04-SUPPLIERS-AND-PURCHASE-REQUESTS-FUNCTIONAL-DECISIONS`
> **Builds on:** [ADR-0020](ADR-0020-FULL-PHASE2-OPERATIONAL-SCOPE.md) §S04
> **Does not:** authorize application code, migrations, database changes, deployment, or S05; accept [ADR-0019](ADR-0019-ACCOMMODATION-CAMPS-DRAFT.md); invent RFQ/PO/receiving schemas; replace Contracts or Inventory as a vendor/procurement substitute.

## 0. Authorization boundary

This ADR records **Owner-approved functional decisions only** for Sprint **S04** (suppliers, supplier evaluation, purchase requests, multi-level PR approvals).

- **Does not** authorize G1 module-pack completion as implementation.
- **Does not** authorize S04 application development or any S05 work.
- Engineering schema, API contracts, permission machine names, and migrations remain for **G1 design** and a later explicit Owner implementation authorization.
- G0 filing of this ADR (2026-10-31) closes documentation preparation for the decision register; it is **not** implementation readiness.

## 1. Scope and sprint separation

- **S04:** Supplier registry and classifications; supplier evaluation; purchase requests (PR); multi-level PR approval paths.
- **S05 only:** RFQ; supplier offers and comparison; supplier selection; purchase orders (PO); receiving and procurement cycle close.
- No payments, executive accounting, or actual budget reservation in S04.
- No forced rewrite of historical Contracts records or of the current inventory receive path.

**Product-scope alignment:** Matches [ADR-0020](ADR-0020-FULL-PHASE2-OPERATIONAL-SCOPE.md) §S04 / §S05. This ADR **narrows** S04 functional gaps; it does not widen Phase 2 product membership beyond ADR-0020.

## 2. Approved functional decisions (101–130)

Source: Owner sequential approvals consolidated 2026-10-31 (choice C for each decision). Meaning of each decision below is **binding** and must not be reopened during G0/G1 documentation without a new Owner decision.

### A. Supplier registry (101–111)

**101 — Unified supplier record with multiple service categories.** A single supplier record is created per tenant organization; the supplier may be linked to more than one service category without duplicating supplier identity per category.

**102 — Strict tenant isolation of suppliers.** Each Tenant has an independent supplier registry with strict data isolation; a future shared reference identity may be considered later without sharing tenant data or breaking isolation.

**103 — Supplier identity and duplicate prevention.** Stable internal identifier; legal identity fields according to supplier type and country; prevent duplicate legal identifiers within the tenant; warn on name similarity without relying on name alone.

**104 — Supplier categories.** Default categories suitable for Hajj services, customizable per tenant under controlled permissions; a category linked to a supplier must not be hard-deleted.

**105 — Supplier types.** Support companies, establishments, individuals, governmental/non-profit entities, and international suppliers; required identity fields differ by type and country.

**106 — Geographic coverage.** Supplier coverage areas per service category use the platform’s approved geographic references — not conflicting ad-hoc location lists.
*(G0 engineering direction for implementing this decision: see §3.1.)*

**107 — Qualification before activation.** Allow an initial record with basic data; complete legal documents and verification before the supplier may be activated for use on new purchase requests.

**108 — Supplier lifecycle.** Statuses: Draft, Under Review, Active, Temporarily Suspended, Blocked, Archived. Transitions are permission- and audit-controlled; an unqualified supplier must not be used on new purchase requests.

**109 — Supplier documents.** Document requirements by supplier type, country, and service category; track validity, versions, and alerts; missing or expired mandatory documents block eligibility for new purchase requests.

**110 — Contacts and bank accounts.** Multiple contacts and bank accounts with a primary account; verification and approval of sensitive bank changes with audit; **no payments** within S04.

**111 — Integration with contracts and existing operations.** Gradual linkage of the supplier registry to contracts and operational units without breaking current behavior or forcing historical contract migration; any change to existing modules needs independent engineering review and approval.

### B. Supplier evaluation (112–116)

**112 — Weighted evaluation.** Multi-criteria evaluation with weights that vary by service category; score 0–100; evaluation date and supporting evidence.

**113 — Evaluation timing.** Support qualification, periodic, operational, and post-season evaluations; optional season link where applicable; keep history without replacing prior evaluations.

**114 — Separation of draft vs approval.** Operations prepares the evaluation draft; an independent authorized reviewer approves; segregation of duties and audit trail are mandatory.

**115 — Low evaluation handling.** Generates alerts and escalation; does **not** auto-suspend; an authorized official decides continued monitoring, suspension, or block. “Under monitoring” is an evaluation descriptor — **not** a new supplier lifecycle status.

**116 — Objection and correction.** Independent review of objections/corrections; evaluation versions retained with origin and evidence; no independent supplier self-service portal in S04.

### C. Purchase requests — PR (117–123)

**117 — Creating purchase requests.** Decentralized creation by authorized departments; record requester and beneficiary unit; procurement receives **approved** requests to continue the S05 cycle later.

**118 — Seasonal vs administrative requests.** A PR tied to Hajj seasonal need **must** link to a Hajj season; a general administrative PR may have no season; record request type and purpose.

**119 — PR line items.** A PR supports multiple lines; each line has specifications, quantity, unit, need date, and optional attachments; may reference a catalog item or a free-text description.

**120 — Financial estimate and budget.** Estimated prices per line where available; expected total, currency, and estimate source; a line may be submitted without price with a stated reason; financial review and budget-availability review before final approval per permissions. Partial totals must be clearly distinguished from complete totals. **No** actual budget reservation or financial commitment in S04; integration with unavailable finance systems must not be assumed ready.
*(G0 engineering direction for implementing this decision: see §3.2.)*

**121 — PR lifecycle and versions.** Draft, Submitted, Under Review, Returned for Revision, Approved, Rejected, Cancelled; no direct edit while under review; return to owner for revision; keep versions; re-run affected approvals; cancellation subject to permissions, stage, and audit.

**122 — Priority and urgent escalation.** Normal, Urgent, Emergency; mandatory justification for urgency and need date; authorized verification of priority; alert/escalation path **without** bypassing mandatory approvals.

**123 — Duplicate detection and need consolidation.** Warn on similar items, seasons, and need dates; allow an authorized official to consolidate approved needs for **planning only**, while each PR remains independent for quantities, department, and approvals; **no** consolidated RFQ or PO in S04.

### D. Approval paths and controls (124–130)

**124 — Dynamic approval paths.** Multi-level paths configurable per tenant by estimated value, department, need type, and season when applicable; no bypass of mandatory reviews.

**125 — Financial authority matrix.** Financial bands and approval levels per tenant; changes controlled by review, approval, effective date, and audit; no approval above the user’s authority ceiling. Persist the matrix snapshot used to route the request; matrix changes do not apply retroactively by default. **Example band numbers used in prior discussions are illustrative and are not approved limits.**

**126 — Segregation of duties and conflicts of interest.** A person must not approve a request they created; check conflicts and multi-role overlap at each level; route to an eligible independent approver when conflicted, record the reason, and do not drop a mandatory approval level.

**127 — Controlled delegation.** Temporary delegation to an eligible approver for a bounded period within approved authority; delegation approved by an authorized party; conflict checks; action logging; automatic expiry or revocation by an authorized party.

**128 — Rejection vs return for revision.** Distinguish final rejection from return for revision, with mandatory reason; on resubmit of a new version, re-evaluate the approval path and affected approvals; retain prior approval history without assuming it remains valid for the revised version.

**129 — SLA, alerts, and escalation.** Target approval durations by priority and level; reminders before/after deadline, then controlled escalation; **no** automatic approval and **no** bypass of mandatory levels. **Detailed SLA durations are not approved here and must be configured later.**

**130 — Audit log and control reports.** Application-immutable audit of material events linking actor, time, PR version, and approval rule; includes rejection, revision, delegation, and escalation; reports on approval latency, delays, request outcomes, and compliance; tenant-scoped access. Detailed retention/protection policy is an engineering matter.

## 3. G0 Owner-approved engineering directions (constraints — not schemas)

These directions were Owner-approved on **2026-10-31** for G0 documentation. They **constrain** later G1 design. They do **not** invent tables, APIs, or permission string names (deferred to G1).

### 3.1 Geographic reference — Decision 106

- Use standardized country and geographic references.
- Reuse existing canonical platform references when available (e.g. ISO-style country codes already used elsewhere).
- Design a **minimal** reference catalog only for regions/cities required by S04 supplier coverage — **not** a comprehensive geographic subsystem.
- Preserve tenant isolation and per service-category coverage linkage.
- Database schema and API details are deferred to **G1**.

### 3.2 Manual financial review — Decision 120

- Support a designated **financial reviewer** permission (exact `module.action` name deferred to G1).
- Record reviewer identity, review outcome, timestamp, and justification.
- Support estimated PR costs and explicitly identify unpriced line items.
- Do **not** assume a complete finance module exists.
- Do **not** create accounting entries, reserve budgets, or process payments.
- Detailed workflow, data model, and permission naming are deferred to **G1**.

## 4. Binding architectural and security constraints for design

1. Tenant data isolation; cross-tenant access blocked in UI, API, and logs ([ADR-0003](ADR-0003-MULTI-TENANCY.md)).
2. Deny-by-default permissions and segregation of duties; audit sensitive events without exposing full bank secrets ([PERMISSION_MODEL.md](../06-security/PERMISSION_MODEL.md), [ADR-0015](ADR-0015-SEMANTIC-AUDIT-TRAIL-AND-IMMUTABLE-HISTORY.md)).
3. Preserve current Contracts and Inventory behavior; Contracts category code `supplier` is **not** a supplier master ([ADR-0006](ADR-0006-CONTRACT-CATEGORIES-CATALOG.md)).
4. Do not build RFQ, offers, PO, or receiving engines in S04 (those are S05 per ADR-0020).
5. Re-evaluate approvals when PR content changes; no automatic approval on SLA breach or via delegation alone.
6. Financial estimates are not commitments or payments.
7. Document file storage remains governed by [ADR-0010](ADR-0010-DOCUMENT-STORAGE-AND-ENTITY-LINKS.md) (immutable files; eligibility/version metadata for suppliers is a design concern for G1, not a silent rewrite of ADR-0010).
8. Inventory receive remains Action-based ledger rules ([ADR-0011](ADR-0011-INVENTORY-LEDGER-BALANCE-AND-TRANSFER.md)); S04 must not introduce a parallel stock path.

## 5. Engineering detail explicitly deferred (not reopening 101–130)

- Table/column names, keys, indexes, migrations.
- API shapes; exact permission names; role matrix grants.
- Definition of approval-impacting PR field changes and version-diff mechanics.
- Estimate aggregation when some lines lack prices; wiring of manual financial review into the approval path (within §3.2).
- Supplier eligibility checks when a PR has **no** supplier selected (S04 does **not** require selecting a supplier on the PR).
- Default financial bands and SLA values (not approved in decisions 125/129).
- Audit retention policy detail; isolation/concurrency/security test plans.
- Module folder number under `docs/09-modules/` and any future **Engineering** ADR (proposed separately after G1 authorization).

## 6. Relationship to existing ADRs

| ADR | Relationship |
|---|---|
| [ADR-0020](ADR-0020-FULL-PHASE2-OPERATIONAL-SCOPE.md) | Parent product scope for S04/S05; this ADR freezes S04 functional decisions. |
| [ADR-0021](ADR-0021-PHASE2-PRODUCT-DECISIONS-AND-DELIVERY-BOUNDARIES.md) | Unrelated product clarifications (GPS/Pilgrim UI/breakdowns/planning); not superseded. |
| [ADR-0003](ADR-0003-MULTI-TENANCY.md) | Binding tenant isolation. |
| [ADR-0010](ADR-0010-DOCUMENT-STORAGE-AND-ENTITY-LINKS.md) | Binding document storage model; supplier eligibility metadata designed in G1 without contradicting immutability. |
| [ADR-0011](ADR-0011-INVENTORY-LEDGER-BALANCE-AND-TRANSFER.md) | Binding inventory ledger; no S04 receive-against-PO. |
| [ADR-0015](ADR-0015-SEMANTIC-AUDIT-TRAIL-AND-IMMUTABLE-HISTORY.md) | Binding audit semantics. |
| [ADR-0019](ADR-0019-ACCOMMODATION-CAMPS-DRAFT.md) | Unrelated; remains **PROPOSED / NOT ACCEPTED**. |

## 7. Gate status

### 7.1 As of G0 filing (2026-10-31) — historical snapshot

| Item | Status |
|---|---|
| Owner functional approvals 101–130 | **APPROVED** (30/30) |
| This ADR filed in repository | **FILED** (G0) |
| S03 Owner acceptance in governance docs | **COMPLETE — ACCEPTED** (Owner 2026-10-31) — see [S03_ACCEPTANCE_STATUS.md](../00-project/S03_ACCEPTANCE_STATUS.md) |
| S04 MODULE_TEMPLATE pack (`docs/09-modules/…`) | **PENDING** (G1) |
| Engineering ADR / implementation CR | **PENDING** |
| Authorization to start application implementation | **NOT GRANTED** |

### 7.2 Status clarification (dated 2026-10-31 — non-destructive)

The §7.1 rows above remain the **G0 filing snapshot**. Subsequent Owner-approved gates (without reopening 101–130):

| Item | Status (2026-10-31+) |
|---|---|
| S04 MODULE_TEMPLATE pack (`docs/09-modules/19-suppliers-and-procurement/`) | **COMPLETE** (G1) |
| Engineering ADR / design CR | **APPROVED** — [ADR-0023](ADR-0023-S04-ENGINEERING-DESIGN.md) + [CHANGE_REQUEST_S04_SUPPLIERS_AND_PROCUREMENT.md](../00-project/CHANGE_REQUEST_S04_SUPPLIERS_AND_PROCUREMENT.md) |
| G2 Phase I (geo + supplier registry) | **COMPLETE — OWNER ACCEPTED** — [S04_PHASE_I_ACCEPTANCE_STATUS.md](../00-project/S04_PHASE_I_ACCEPTANCE_STATUS.md); accepted commit `58e5b49` |
| Phase II Owner clarifications II-D1–II-D4 | **APPROVED** (documentation) — [S04-PHASE-II-OWNER-CLARIFICATIONS-2026-10-31.md](S04-PHASE-II-OWNER-CLARIFICATIONS-2026-10-31.md); **does not** authorize coding |
| II-A Eligibility & Documents design (C1–C6) | **APPROVED** — [S04-PHASE-II-A-DESIGN-APPROVAL-2026-10-31.md](S04-PHASE-II-A-DESIGN-APPROVAL-2026-10-31.md) (historical design record unchanged) |
| II-A engineering pre-implementation gate | **COMPLETE** — [S04-PHASE-II-A-ENGINEERING-GATE-2026-10-31.md](S04-PHASE-II-A-ENGINEERING-GATE-2026-10-31.md) (historical gate record unchanged) |
| Phase II-A application implementation | **COMPLETE — OWNER ACCEPTED** (2026-11-01) — [S04_PHASE_II_A_ACCEPTANCE_STATUS.md](../00-project/S04_PHASE_II_A_ACCEPTANCE_STATUS.md); remediation re-audit **ACCEPTABLE**; **M1=A** |
| Phase II-B / II-C / Phase III–V | **NOT_STARTED — NOT AUTHORIZED** |
| Overall S04 | **IN PROGRESS** |
| S05 | **NOT_STARTED** |
| ADR-0019 | **PROPOSED / NOT ACCEPTED** (unchanged) |

## 8. References

- Owner-approved functional register (conversation consolidation 2026-10-31).
- [ADR-0020](ADR-0020-FULL-PHASE2-OPERATIONAL-SCOPE.md)
- [PHASE2_SCOPE_MATRIX.md](../00-project/PHASE2_SCOPE_MATRIX.md)
- [PHASE2_SCOPE_RECONCILIATION.md](../00-project/PHASE2_SCOPE_RECONCILIATION.md)
- [DEFINITION_OF_DONE.md](../00-project/DEFINITION_OF_DONE.md)
- [MODULE_TEMPLATE.md](../02-architecture/MODULE_TEMPLATE.md)

---

**Documentation note:** Wording is the official unified statement of sequential Owner approvals, not a literal transcript of every UI choice screen. If a wording conflict with an original approval utterance is alleged, escalate to the Project Owner before changing decision meaning.
