# Phase 2 Scope Reconciliation Log

> **Status:** Governance log
> **Last updated:** 2026-10-31
> **Binding product decision:** [ADR-0020](../10-decisions/ADR-0020-FULL-PHASE2-OPERATIONAL-SCOPE.md) — Full Phase 2 Operational Scope Adoption
> **Product clarifications:** [ADR-0021](../10-decisions/ADR-0021-PHASE2-PRODUCT-DECISIONS-AND-DELIVERY-BOUNDARIES.md) — GPS, Pilgrim UI, breakdowns, 11/8 planning interpretation
> **S04 functional decisions:** [ADR-0022](../10-decisions/ADR-0022-S04-SUPPLIERS-AND-PURCHASE-REQUESTS-FUNCTIONAL-DECISIONS.md) — decisions 101–130 (`APPROVED — FUNCTIONAL DECISIONS`; not implementation authorization)  
> **S04 engineering design:** [ADR-0023](../10-decisions/ADR-0023-S04-ENGINEERING-DESIGN.md) (`APPROVED — ENGINEERING DESIGN`, 2026-10-31) + [CHANGE_REQUEST_S04_SUPPLIERS_AND_PROCUREMENT.md](CHANGE_REQUEST_S04_SUPPLIERS_AND_PROCUREMENT.md)  
> **S04 G2 Phase I:** [S04_PHASE_I_ACCEPTANCE_STATUS.md](S04_PHASE_I_ACCEPTANCE_STATUS.md) (`COMPLETE — OWNER ACCEPTED`, 2026-10-31); **II-A DESIGN APPROVED**; **ENGINEERING PRE-IMPLEMENTATION GATE COMPLETE** ([gate](../10-decisions/S04-PHASE-II-A-ENGINEERING-GATE-2026-10-31.md); [approval](../10-decisions/S04-PHASE-II-A-DESIGN-APPROVAL-2026-10-31.md); [design](../09-modules/19-suppliers-and-procurement/PHASE_II_A_ELIGIBILITY_DESIGN.md); [II-D1–II-D4](../10-decisions/S04-PHASE-II-OWNER-CLARIFICATIONS-2026-10-31.md)); coding **NOT AUTHORIZED**; Phase II implementation **NOT_STARTED**
> **Audit snapshot (not a scope authority):** [PHASE2_IMPLEMENTATION_AUDIT.md](../audits/PHASE2_IMPLEMENTATION_AUDIT.md)

## Purpose

Record conflicts between older in-repo Phase 2 wording and the **approved full operational product scope**, what was updated, and what remains open.

## Reference Gaps

| Reference | Status in this pass |
|---|---|
| الخطة التنفيذية للمشروع | Read in Sprint 0 verification (Cursor attachments); **not filed under `docs/`**. Phase 02 (~p.4) and mockups (~p.8–10) informed ADR-0021. |
| فكرة منصتي | Read as Cursor attachment; **not filed under `docs/`**. S04 functional depth now frozen in ADR-0022; S05–S10 still TBD at design time. |
| `ERP_Hajj_Phase_2_Workflow_Reviewed.pdf` | External / attachments; sprint taxonomy S01–S12. Not copied into `docs/`. |
| PHASE2_IMPLEMENTATION_AUDIT.md | Available; **status snapshot only** — intentionally **not edited**. |

## Conflict register

| Document | Section / prior text | Prior scope reading | New approved product scope | Doc-only vs engineering CR? | Updated? | Open decision? |
|---|---|---|---|---|---|---|
| [CHANGE_REQUEST_PHASE2_FOUNDATION.md](CHANGE_REQUEST_PHASE2_FOUNDATION.md) | Approved 2026-10-04 Phase 2 = Foundation + Transport + Accommodation only; lists procurement, GPS, complaints, cloning, etc. as not in scope | **Original Foundation Delivery Scope** | Full Phase 2 includes S04–S12 per ADR-0020; Phase 3 list narrowed | **Documentation amendment** recording supersession for product breadth; each new module still needs implementation CR/design before code | **Yes** — historical block preserved + pointer to ADR-0020 | Per-stream design for S04–S10 |
| [OUT_OF_SCOPE.md](OUT_OF_SCOPE.md) | Procurement, advanced projects, complaints, full seasonal cloning, advanced KPI engine, GPS, command center listed as not in MVP **and** not in approved Phase 2 | Those items forbidden for Phase 2 delivery | S04–S10 **in** Phase 2 product scope but **not started**; GPS **in Phase 2** per ADR-0021 (gated, not complete); finance/AI/pilgrim app/command center stay Phase 3 | Documentation; engineering CR before implementing each stream | **Yes** — distinguish historical vs full product scope | GPS **provider**/contract (engineering); S08 safe-copy rules |
| [ROADMAP.md](ROADMAP.md) | Phase 2 = three areas only; “Later” lists procurement/GPS/etc. as not approved | Narrow Phase 2 | Full Phase 2 workstreams S01–S12 + Foundation; Later = Phase 3 | Documentation | **Yes** | Sequencing/capacity |
| [ADR-0017](../10-decisions/ADR-0017-SEASON-PILGRIM-AND-PHASE2-BOUNDED-CONTEXTS.md) | Phase 2 bounded contexts Foundation/Transport/Accommodation; does not approve procurement etc. | Technical BC ADR for foundation trio | Still valid for BC rules; **product membership** widened by ADR-0020 without rewriting ADR-0017 body | No ADR-0017 rewrite in this pass | **No body rewrite** — relationship documented in ADR-0020 | None for BC rules |
| [ADR-0018](../10-decisions/ADR-0018-TRANSPORTATION-MOVEMENTS-RUNS-AND-FLEET.md) | GPS remains out of Transportation ADR text | GPS not specified in ADR-0018 | GPS **product-in-scope** (ADR-0021) without auto-amending ADR-0018; breakdowns in S02 need design + ADR-0018 review | Engineering ADR/CR if conflict | **No body rewrite** (local Transport doc edits left untouched) | Provider; breakdown model; possible ADR-0018 addendum |
| [ADR-0019](../10-decisions/ADR-0019-ACCOMMODATION-CAMPS-DRAFT.md) | PROPOSED / NOT ACCEPTED | Draft engineering | S03 in product scope; ADR-0019 **must stay PROPOSED** until separate acceptance | Must not flip status | **No** — status unchanged (S03 Owner acceptance 2026-10-31 does **not** accept ADR-0019) | Accept/revise ADR-0019 |
| [ADR-0022](../10-decisions/ADR-0022-S04-SUPPLIERS-AND-PURCHASE-REQUESTS-FUNCTIONAL-DECISIONS.md) | (new) | S04 functional depth open | Decisions 101–130 approved; G0 engineering directions for geo (106) and manual financial review (120) | Documentation (G0); G1 MODULE_TEMPLATE + engineering CR before code | **Yes** — filed 2026-10-31 | Implementation authorization still open |
| [ADR-0023](../10-decisions/ADR-0023-S04-ENGINEERING-DESIGN.md) | PROPOSED | Engineering design open | Engineering design **APPROVED** 2026-10-31 (D1–D5); design status unchanged | Documentation | **Yes** — approved 2026-10-31 | Phase II–V coding still open |
| [CHANGE_REQUEST_S04_SUPPLIERS_AND_PROCUREMENT.md](CHANGE_REQUEST_S04_SUPPLIERS_AND_PROCUREMENT.md) | DRAFT | Scope/design CR open | Design/scope APPROVED; **G2 Phase I accepted** 2026-10-31; II-D1–II-D4 recorded; Phase II–V coding unauthorized | Documentation + Phase I code | **Yes** — 2026-10-31 | Separate Phase II / II-A coding authorization |
| [S04_PHASE_I_ACCEPTANCE_STATUS.md](S04_PHASE_I_ACCEPTANCE_STATUS.md) | (new) | Phase I delivery open | Phase I **COMPLETE — OWNER ACCEPTED** 2026-10-31 | Implementation record | **Yes** — 2026-10-31 | Unchanged by Phase II docs |
| [S04-PHASE-II-OWNER-CLARIFICATIONS-2026-10-31.md](../10-decisions/S04-PHASE-II-OWNER-CLARIFICATIONS-2026-10-31.md) | (new) | Phase II eligibility/banking/suspension/eval criteria open | II-D1–II-D4 **APPROVED** (docs); coding **NOT GRANTED** | Documentation | **Yes** — 2026-10-31 | II-A coding authorization |
| [S04-PHASE-II-A-DESIGN-APPROVAL-2026-10-31.md](../10-decisions/S04-PHASE-II-A-DESIGN-APPROVAL-2026-10-31.md) | (new) | II-A design approval open | **II-A DESIGN APPROVED — IMPLEMENTATION NOT AUTHORIZED** (C1–C6) | Documentation | **Yes** — 2026-10-31 | Separate II-A coding auth |
| [S04-PHASE-II-A-ENGINEERING-GATE-2026-10-31.md](../10-decisions/S04-PHASE-II-A-ENGINEERING-GATE-2026-10-31.md) | (new) | Engineering pre-impl open | **GATE COMPLETE**; coding **NOT GRANTED** | Documentation | **Yes** — 2026-10-31 | II-A coding authorization |
| [PHASE_II_A_ELIGIBILITY_DESIGN.md](../09-modules/19-suppliers-and-procurement/PHASE_II_A_ELIGIBILITY_DESIGN.md) | (new) | II-A design open | **DESIGN APPROVED** + eng gate **COMPLETE**; coding **NOT GRANTED** | Documentation | **Yes** — 2026-10-31 | II-A coding auth |
| [S03_ACCEPTANCE_STATUS.md](S03_ACCEPTANCE_STATUS.md) | ACCEPTANCE PENDING | S03 not Owner-closed | Owner **COMPLETE — ACCEPTED** 2026-10-31 on existing evidence only | Documentation | **Yes** — 2026-10-31 | None for S03 delivery close; ADR-0019 still open |
| [MVP_SCOPE.md](MVP_SCOPE.md) | 21 MVP modules | Unchanged | Still unchanged — Phase 2 is not MVP rewrite | None | **No** | None |
| Module docs 16–18 | Living module specs | Foundation trio | Remain authoritative for their domains; not bulk-rewritten | Future stream docs for S04+ | **No bulk rewrite** (local Accommodation/Transport doc edits preserved) | New module folder for S04 in **G1** |
| Audit §1 / §5 | Records Workflow vs OUT_OF_SCOPE conflict | Conflict open | Conflict **resolved at product level** by ADR-0020 | Audit file is historical — do not edit | **Audit left unchanged** | Re-audit after major deliveries |

## Resolved vs open

### Resolved (product governance)

- Whether S04–S10 belong in Phase 2 **product** scope → **Yes** (ADR-0020).
- Whether Foundation is Workflow S01 → **No**; Foundation is prerequisite.
- Whether ADR-0019 is accepted by scope expansion → **No**.
- Whether audit statuses become COMPLETE → **No** (Owner acceptance is separate from the historical audit file).
- Whether S03 delivery is Owner-accepted → **Yes** (2026-10-31; [S03_ACCEPTANCE_STATUS.md](S03_ACCEPTANCE_STATUS.md)); ADR-0019 still PROPOSED.
- Whether S04 functional requirements 101–130 are frozen → **Yes** (ADR-0022); implementation still **not** authorized.  
- Whether S04 engineering design is Owner-approved → **Yes** (ADR-0023 + CR design/scope, 2026-10-31).  
- Whether S04 G2 Phase I is Owner-accepted → **Yes** (2026-10-31); Phase II–V coding still **not** authorized.  
- Whether Phase II Owner clarifications II-D1–II-D4 are recorded → **Yes** (2026-10-31); they are **not** implementation authorization.  
- Whether II-A Eligibility & Documents design (C1–C6) is Owner-approved → **Yes** (2026-10-31); **implementation still not authorized**.  
- Whether II-A engineering pre-implementation gate is complete → **Yes** (2026-10-31); coding still **not** authorized.  
- Whether financial reviewer may also final-approve → **No** (D5 hard SoD).

### Resolved Product Decisions (2026-10-29 — ADR-0021)

| ID | Decision | Resolution |
|---|---|---|
| D01 | GPS & live operations map | **In Phase 2**; completion gated (real source, isolation, RBAC, last-update, stale handling, integration tests, UAT). Not Phase 3. Not complete. Mock ≠ Live. |
| D02 | Independent Pilgrim Management UI | **Foundation** staff UI required; backend-only ≠ Foundation closed. Not pilgrim mobile app. |
| D03 | Transport breakdowns & replacement | **S02** operational scope; not Phase 3 crisis suite. Design + ADR-0018 review still required. |
| D04 | “11 units” / “8 weeks” | Classification + **planning horizon**; not 11 sprints; not fixed contractual date from this decision. |

### Resolved S04 functional decisions (2026-10-31 — ADR-0022)

| ID range | Topic | Resolution |
|---|---|---|
| 101–111 | Supplier registry | Approved (unified multi-category supplier, tenant isolation, lifecycle, documents, contacts/banks, gradual contract link). |
| 112–116 | Supplier evaluation | Approved (weighted scores, timing, segregation, low-score handling, objections). |
| 117–123 | Purchase requests | Approved (decentralized PR, seasonal/admin, lines, estimates, lifecycle, priority, consolidation-for-planning). |
| 124–130 | Approvals & controls | Approved (dynamic paths, authority matrix, SoD, delegation, reject/return, SLA framework without fixed durations, audit/reports). |
| G0 · 106 | Geographic reference | Minimal standardized catalog; reuse platform references; **not** a full geo subsystem; schema deferred to G1. |
| G0 · 120 | Financial review | Manual review permission + recorded outcome; **no** accounting/reservations/payments; detail deferred to G1. |

### Open Engineering Decisions (not closed by ADR-0021 / ADR-0022)

1. GPS **provider** / data source selection and location update contract.  
2. Location integration architecture (ingest, storage, retention, privacy).  
3. Breakdown/replacement **domain model** and workflow detail.  
4. Formal **ADR-0018** alignment / possible engineering addendum for GPS and breakdowns.  
5. Pilgrim UI **exact field set** and sensitive document display rules vs approved schema.  
6. **Actual delivery schedule** after estimation (8 weeks remains a horizon only).  
7. File الخطة التنفيذية / فكرة منصتي under `docs/` for long-term traceability.  
8. Per-stream MODULE_TEMPLATE packs + engineering CRs for **S05–S10** (S04 G1 design approved; implementation still open).  
9. ADR-0019 acceptance path for Accommodation **engineering ADR** close (S03 delivery accepted; ADR-0019 still PROPOSED).  
10. Transport staff UI completion (S01–S02 product close) beyond GPS/breakdowns.  
11. Phase 2 UAT script ownership (S12).  
12. ~~S04 **G2 Phase I** Owner implementation authorization~~ **Done** (accepted 2026-10-31).  
13. S04 **Phase II / II-A** explicit Owner implementation authorization (when ready); then II-B–V separately.

## Sprint 0 / recovery note

Sprint 0 documentation alignment, Accommodation wiring recovery, and ADR-0021/ADR-0022 product/functional filings are **governance hygiene**. S03 delivery is Owner-accepted (2026-10-31). Sprint 0 remains **open** as a governance track; Phase 2 UAT (S12) is separate.
