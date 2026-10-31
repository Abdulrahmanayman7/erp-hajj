# ADR-0020 — Full Phase 2 Operational Scope Adoption

> **Status:** `APPROVED — PRODUCT SCOPE`
> **Date:** 2026-10-29
> **Deciders:** Project Owner
> **Decision name:** Full Phase 2 Operational Scope Adoption
> **Does not:** rewrite the original 21-module MVP; accept ADR-0019; invent integrations, vendors, or schemas; authorize implementation sprints without separate engineering design/CR where required.

## 1. Context

The repository previously treated **approved Phase 2** as a **Foundation delivery slice**: Season & Pilgrim foundation, Transportation & fleet, and Accommodation & camps ([CHANGE_REQUEST_PHASE2_FOUNDATION.md](../00-project/CHANGE_REQUEST_PHASE2_FOUNDATION.md), [ADR-0017](ADR-0017-SEASON-PILGRIM-AND-PHASE2-BOUNDED-CONTEXTS.md)). Procurement, seasonal projects, risks/complaints, seasonal templates, operational KPIs, and an executive live dashboard remained listed as future / out of Phase 2 in [OUT_OF_SCOPE.md](../00-project/OUT_OF_SCOPE.md) and the Foundation Change Request.

Separately, the Phase 2 Workflow reference (`ERP_Hajj_Phase_2_Workflow_Reviewed.pdf`, as transcribed in [PHASE2_IMPLEMENTATION_AUDIT.md](../audits/PHASE2_IMPLEMENTATION_AUDIT.md)) structures delivery as **Foundation + S01–S12**, including procurement through UAT. That created a **governance conflict** between product Workflow intent and in-repo scope documents.

On **2026-10-29**, the Project Owner adopted the **full operational Phase 2 product scope** aligned to that Workflow framing (and to the executive/product references where available), while keeping Phase 3 exclusions and existing multi-tenant / security engineering constraints.

## 2. Decision

**Adopt Full Phase 2 Operational Scope** as the **approved product scope** for Phase 2.

- This ADR expands **what the product phase may include**.
- It does **not** automatically cancel prior **engineering**, **security**, **tenancy**, or **bounded-context** ADRs.
- It does **not** mark any workstream complete.
- It does **not** start S04–S10 implementation; those streams still need requirements freeze, design, permissions model, and (where they conflict with older “future scope” wording) explicit engineering change control before code.
- Sprint 0 (governance / recovery / alignment) is **not** a closed delivery sprint and does **not** close S03.

## 3. Approved Scope

### Foundation — Seasons and pilgrims (not Workflow S01)

- Season and pilgrim operations required for the season.
- Groups and season linkage.
- Staff operational UI completion where still missing.
- Data integration with Transportation and Accommodation.

Foundation is a **prerequisite**, not an independent Workflow sprint number.

### S01 — Transportation and fleet

- Vehicles and drivers; fleet data; transport points and routes; capacity; permissions; UI; integrations within Transportation boundaries ([ADR-0018](ADR-0018-TRANSPORTATION-MOVEMENTS-RUNS-AND-FLEET.md)).

### S02 — Movements and convoy operations (التفويج)

- Movements and vehicle runs; pilgrim assignment; conflict prevention; operational states; schedules and follow-up.

### S03 — Accommodation and camps

- Hotels, facilities, camps; structures, units, rooms/positions; season allocation; draft/confirm/arrive/check-in/check-out; capacity and double-booking prevention; permissions and operational timeline ([ADR-0017](ADR-0017-SEASON-PILGRIM-AND-PHASE2-BOUNDED-CONTEXTS.md) occupancy rules; [ADR-0019](ADR-0019-ACCOMMODATION-CAMPS-DRAFT.md) remains **PROPOSED / NOT ACCEPTED** until separately accepted).

### S04 — Suppliers and purchase requests

- Supplier registry and classifications; supplier evaluation; purchase requests; approval levels.

### S05 — Procurement cycle

- RFQ; supplier offers and comparison; supplier selection; purchase orders (PO); receiving and cycle close.

### S06 — Projects and tasks (seasonal)

- Season-linked projects; budgets and phases; tasks and responsibilities; progress and delays.
  Distinct from MVP Tasks module reuse: Phase 2 needs an explicit seasonal projects model (design TBD).

### S07 — Risks and complaints

- Risk register; likelihood/impact; treatment plans; complaint intake and follow-up; escalation, resolution, closure.

### S08 — Seasonal templates

- Reuse of allowed templates/plans; copy of permitted reference data; season date adjustment; **forbid unsafe copy** of pilgrim PII and operational transactional history.

### S09 — Performance indicators

- Transportation, accommodation, procurement, projects, risks/complaints KPIs; explicit data sources and formulas; company/season/permission scoping.

### S10 — Executive operations dashboard

- Unified operational indicators; season status; occupancy and progress; alerts and reports.
- Maps / live tracking **only** when a trustworthy integration exists (see §4).

### S11 — Integration and tests

- Backend/Frontend integration; tenant isolation; season isolation; RBAC; concurrency; audit trail; regression; performance; RTL/Arabic.

### S12 — UAT and phase handoff

- User acceptance scenarios; finding remediation; test evidence; phase closure record.

Workstream ↔ sprint mapping and audit statuses: [PHASE2_SCOPE_MATRIX.md](../00-project/PHASE2_SCOPE_MATRIX.md).

## 4. Out of Scope (remain Phase 3 / deferred unless a later CR says otherwise)

Do **not** pull into Phase 2 by default:

- Independent pilgrim mobile application.
- Pilgrim-app operational QR (staff/facility QR concepts may remain TBD inside Accommodation design; pilgrim-app QR stays out).
- Advanced SOS / crisis emergency suite.
- Full finance / accounting platform.
- Advanced command-and-control center.
- AI and predictive analytics.
- Full dynamic platform configuration builders.

### GPS and live tracking (special case)

- Present in the original functional vision and **in Phase 2 product scope** per [ADR-0021](ADR-0021-PHASE2-PRODUCT-DECISIONS-AND-DELIVERY-BOUNDARIES.md) Decision 01 (2026-10-29).
- **Not implemented** per [PHASE2_IMPLEMENTATION_AUDIT.md](../audits/PHASE2_IMPLEMENTATION_AUDIT.md) (snapshot 2026-10-29).
- Completion remains **gated**: approved real GPS/location source, documented accuracy/update timing, tenant/season isolation, RBAC, last-known update UX, stale/unavailable handling, integration tests, and UAT.
- Must not be described as Live/complete; static or mock coordinates are not acceptance.
- No vendor, API, or protocol is invented by this ADR.
- Does **not** cancel [ADR-0018](ADR-0018-TRANSPORTATION-MOVEMENTS-RUNS-AND-FLEET.md); engineering conflicts need a separate Engineering ADR/CR.
- Binding: location/ops data with **S01/S02**; executive live map with **S10**.

## Approved Product Decisions — 2026-10-29

Owner-approved clarifications are recorded in [ADR-0021](ADR-0021-PHASE2-PRODUCT-DECISIONS-AND-DELIVERY-BOUNDARIES.md) (`APPROVED — PRODUCT DECISIONS`):

1. **GPS & live operations map** — in Phase 2; completion gated (not Phase 3; not complete).  
2. **Independent staff Pilgrim Management UI** — Foundation scope (backend-only ≠ Foundation done).  
3. **Transport breakdowns & replacement vehicle** — S02 operational scope (not Phase 3 crisis suite); design + ADR-0018 review still required.  
4. **“11 units” / “8 weeks”** — functional classification + planning horizon; not 11 sprints and not a fixed contract date from this decision.

## 5. Relationship to Existing ADRs

| ADR | Relationship |
|---|---|
| [ADR-0003](ADR-0003-MULTI-TENANCY.md) | **Still binding.** Product scope expansion never weakens tenant isolation. |
| [ADR-0014](ADR-0014-PERMISSION-AWARE-DASHBOARD-AGGREGATION.md) | MVP dashboard remains; S09–S10 build **on** permission-aware aggregation, not a parallel insecure dashboard. |
| [ADR-0015](ADR-0015-SEMANTIC-AUDIT-TRAIL-AND-IMMUTABLE-HISTORY.md) | Still binding for system audit; domain operational timelines stay separate where already required. |
| [ADR-0017](ADR-0017-SEASON-PILGRIM-AND-PHASE2-BOUNDED-CONTEXTS.md) | Remains the **bounded-context** foundation. This ADR **widens product phase membership** beyond Foundation+Transport+Accommodation; it does not replace ADR-0017’s isolation rules. |
| [ADR-0018](ADR-0018-TRANSPORTATION-MOVEMENTS-RUNS-AND-FLEET.md) | Remains accepted Transportation rules for S01–S02. GPS product-in-scope (ADR-0021) does **not** by itself amend ADR-0018; engineering follow-up may be required. |
| [ADR-0021](ADR-0021-PHASE2-PRODUCT-DECISIONS-AND-DELIVERY-BOUNDARIES.md) | Product decisions (GPS, Pilgrim UI, breakdowns, 11/8 planning interpretation) narrowing ADR-0020 open gaps. |
| [ADR-0019](ADR-0019-ACCOMMODATION-CAMPS-DRAFT.md) | Status stays **PROPOSED / NOT ACCEPTED**. Product inclusion of S03 does not auto-accept ADR-0019. |
| [CHANGE_REQUEST_PHASE2_FOUNDATION.md](../00-project/CHANGE_REQUEST_PHASE2_FOUNDATION.md) | Historical **Original Foundation Delivery Scope** (2026-10-04). Superseded **for product breadth** by this ADR; preserved as delivery history. |

Document conflict resolution log: [PHASE2_SCOPE_RECONCILIATION.md](../00-project/PHASE2_SCOPE_RECONCILIATION.md).

## 6. Dependencies

- Season Context (`X-Season-Id` / Active season) for season-owned operations.
- `SeasonPilgrim` as seasonal participation key for Transport/Accommodation (and later seasonal domains as designed).
- RBAC via `PermissionCatalog` + Policies; deny by default; no persona bypass.
- Audit + correlation ID for critical actions.
- Per-stream module docs under `docs/09-modules/` before implementation (per MODULE_TEMPLATE).
- S04–S10: business rule freeze + engineering design ADR/CR as needed before migrations.
- GPS/live maps: provider/source + gates in ADR-0021 before claiming Live/complete (S01/S02 data; S10 display).

## 7. Risks

- Scope breadth vs team capacity; S04–S12 may stall if started without design.
- Confusing **product approval** with **implementation readiness**.
- Treating audit `IMPLEMENTED_UNVERIFIED` or wiring recovery as UAT/DoD complete.
- Auto-accepting ADR-0019 or inventing procurement/finance schemas.
- Weakening OUT_OF_SCOPE Phase 3 guardrails accidentally.
- GPS promised as Live without a real source (violates ADR-0021 gates).

## 8. Consequences

- [ROADMAP.md](../00-project/ROADMAP.md) and [OUT_OF_SCOPE.md](../00-project/OUT_OF_SCOPE.md) distinguish **Original Foundation Delivery Scope** vs **Full Approved Phase 2 Product Scope**.
- S04–S10 move from “forbidden in Phase 2” to “in Phase 2 product scope, not started / needs design”.
- Implementation of new modules still requires issues, module docs, tests, and review per [DEFINITION_OF_DONE.md](../00-project/DEFINITION_OF_DONE.md).
- [PHASE2_IMPLEMENTATION_AUDIT.md](../audits/PHASE2_IMPLEMENTATION_AUDIT.md) remains a **historical snapshot** (2026-10-29); it is not rewritten by this ADR.

## 9. Acceptance and Governance Rules

### Sprint closure (applies to every Phase 2 sprint / workstream)

A sprint is not closed unless:

1. Requirements are explicit (module docs; blocking TBDs resolved or explicitly deferred).
2. Backend complete for the sprint’s accepted slice.
3. Frontend complete where the sprint requires UI.
4. Permissions + tenant isolation + season isolation verified.
5. Appropriate automated tests exist and pass in an agreed environment.
6. Evidence is retained (tests, reviews, screenshots for UI).
7. Acceptance criteria are checked.
8. Review/approval by the other developer (no self-merge) per project workflow.

### Status vocabulary (do not collapse)

| Label | Meaning |
|---|---|
| `CODE VERIFIED` | Static/code-level recovery or review only |
| `IMPLEMENTED_UNVERIFIED` | Code present; tests/UAT not re-run in the citing audit |
| `BACKEND_ONLY` | API/domain without required staff UI |
| `PARTIAL` | Substantial gaps remain |
| `NOT_STARTED` | No Phase 2 module evidence |
| `UAT PASSED` | Explicit user acceptance evidence |
| `COMPLETE` / Ready to Close | Meets Definition of Done + sprint acceptance |

**`CODE VERIFIED` ≠ `UAT PASSED`.**
**Accommodation wiring recovery ≠ S03 closed and ≠ Sprint 0 closed.**

## 10. References

- Project Owner decision: Full Phase 2 Operational Scope Adoption (2026-10-29).
- [PHASE2_IMPLEMENTATION_AUDIT.md](../audits/PHASE2_IMPLEMENTATION_AUDIT.md) — execution snapshot only.
- [PHASE2_SCOPE_MATRIX.md](../00-project/PHASE2_SCOPE_MATRIX.md)
- [PHASE2_SCOPE_RECONCILIATION.md](../00-project/PHASE2_SCOPE_RECONCILIATION.md)
- Workflow PDF `ERP_Hajj_Phase_2_Workflow_Reviewed.pdf` (external; not stored under `docs/` at decision time — see Reference Gaps in the reconciliation log).
- [MVP_SCOPE.md](../00-project/MVP_SCOPE.md) — unchanged 21-module MVP.
- Modules 16–18 under `docs/09-modules/` (living docs; not bulk-rewritten by this ADR).
- [ADR-0021](ADR-0021-PHASE2-PRODUCT-DECISIONS-AND-DELIVERY-BOUNDARIES.md) — product decisions 2026-10-29.
