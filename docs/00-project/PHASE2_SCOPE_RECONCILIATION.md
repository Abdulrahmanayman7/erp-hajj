# Phase 2 Scope Reconciliation Log

> **Status:** Governance log
> **Last updated:** 2026-10-29
> **Binding product decision:** [ADR-0020](../10-decisions/ADR-0020-FULL-PHASE2-OPERATIONAL-SCOPE.md) — Full Phase 2 Operational Scope Adoption
> **Product clarifications:** [ADR-0021](../10-decisions/ADR-0021-PHASE2-PRODUCT-DECISIONS-AND-DELIVERY-BOUNDARIES.md) — GPS, Pilgrim UI, breakdowns, 11/8 planning interpretation
> **Audit snapshot (not a scope authority):** [PHASE2_IMPLEMENTATION_AUDIT.md](../audits/PHASE2_IMPLEMENTATION_AUDIT.md)

## Purpose

Record conflicts between older in-repo Phase 2 wording and the **approved full operational product scope**, what was updated, and what remains open.

## Reference Gaps

| Reference | Status in this pass |
|---|---|
| الخطة التنفيذية للمشروع | Read in Sprint 0 verification (Cursor attachments); **not filed under `docs/`**. Phase 02 (~p.4) and mockups (~p.8–10) informed ADR-0021. |
| فكرة منصتي | Read as Cursor attachment; **not filed under `docs/`**. Functional depth for S04–S10 still TBD at design time. |
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
| [ADR-0019](../10-decisions/ADR-0019-ACCOMMODATION-CAMPS-DRAFT.md) | PROPOSED / NOT ACCEPTED | Draft engineering | S03 in product scope; ADR-0019 **must stay PROPOSED** until separate acceptance | Must not flip status in this pass | **No** — status unchanged | Accept/revise ADR-0019 |
| [MVP_SCOPE.md](MVP_SCOPE.md) | 21 MVP modules | Unchanged | Still unchanged — Phase 2 is not MVP rewrite | None | **No** | None |
| Module docs 16–18 | Living module specs | Foundation trio | Remain authoritative for their domains; not bulk-rewritten | Future stream docs for S04+ | **No bulk rewrite** (local Accommodation/Transport doc edits preserved) | New module folders for S04+ when designed |
| Audit §1 / §5 | Records Workflow vs OUT_OF_SCOPE conflict | Conflict open | Conflict **resolved at product level** by ADR-0020 | Audit file is historical — do not edit | **Audit left unchanged** | Re-audit after major deliveries |

## Resolved vs open

### Resolved (product governance)

- Whether S04–S10 belong in Phase 2 **product** scope → **Yes** (ADR-0020).
- Whether Foundation is Workflow S01 → **No**; Foundation is prerequisite.
- Whether ADR-0019 is accepted by scope expansion → **No**.
- Whether audit statuses become COMPLETE → **No**.

### Resolved Product Decisions (2026-10-29 — ADR-0021)

| ID | Decision | Resolution |
|---|---|---|
| D01 | GPS & live operations map | **In Phase 2**; completion gated (real source, isolation, RBAC, last-update, stale handling, integration tests, UAT). Not Phase 3. Not complete. Mock ≠ Live. |
| D02 | Independent Pilgrim Management UI | **Foundation** staff UI required; backend-only ≠ Foundation closed. Not pilgrim mobile app. |
| D03 | Transport breakdowns & replacement | **S02** operational scope; not Phase 3 crisis suite. Design + ADR-0018 review still required. |
| D04 | “11 units” / “8 weeks” | Classification + **planning horizon**; not 11 sprints; not fixed contractual date from this decision. |

### Open Engineering Decisions (not closed by ADR-0021)

1. GPS **provider** / data source selection and location update contract.  
2. Location integration architecture (ingest, storage, retention, privacy).  
3. Breakdown/replacement **domain model** and workflow detail.  
4. Formal **ADR-0018** alignment / possible engineering addendum for GPS and breakdowns.  
5. Pilgrim UI **exact field set** and sensitive document display rules vs approved schema.  
6. **Actual delivery schedule** after estimation (8 weeks remains a horizon only).  
7. File الخطة التنفيذية / فكرة منصتي under `docs/` for long-term traceability.  
8. Per-stream MODULE_TEMPLATE packs + engineering CRs for **S04–S10**.  
9. ADR-0019 acceptance path for Accommodation governance close.  
10. Transport staff UI completion (S01–S02 product close) beyond GPS/breakdowns.  
11. Phase 2 UAT script ownership (S12).

## Sprint 0 / recovery note

Sprint 0 documentation alignment, Accommodation wiring recovery, and ADR-0021 product decisions are **governance hygiene**, not sprint closure for S03 and not Phase 2 UAT. Sprint 0 remains **open**.
