# ADR-0021 — Phase 2 Product Decisions and Delivery Boundaries

> **Status:** `APPROVED — PRODUCT DECISIONS`  
> **Date:** 2026-10-29  
> **Deciders:** Project Owner  
> **Title slug:** `PHASE2-PRODUCT-DECISIONS-AND-DELIVERY-BOUNDARIES`  
> **Builds on:** [ADR-0020](ADR-0020-FULL-PHASE2-OPERATIONAL-SCOPE.md)  
> **Does not:** accept ADR-0019; invent a GPS vendor; rewrite ADR-0018; mark any sprint COMPLETE; authorize production migrations.

## 1. Context

After Full Phase 2 product-scope adoption ([ADR-0020](ADR-0020-FULL-PHASE2-OPERATIONAL-SCOPE.md)) and the Sprint 0 final scope verification (`ALIGNED WITH GAPS`), four product ambiguities remained:

1. Whether GPS / live operations map belong in Phase 2 or stay deferred.  
2. Whether staff Pilgrim Management needs an independent UI in Foundation.  
3. Whether vehicle breakdown and replacement belong in S02 or in Phase 3 crisis suites.  
4. How to interpret the executive plan’s “11 operational units” and “8 weeks”.

The Project Owner resolved these as **product decisions** on 2026-10-29. Engineering design, provider selection, and ADR-0018 alignment remain open where noted below.

Sources informing the decisions (read where available in the verification session): executive plan Phase 02 (~p.4) and delivery mockups (~p.8–10); فكرة منصتي (transport/accommodation/dashboard units); `ERP_Hajj_Phase_2_Workflow_Reviewed.pdf`; in-repo ADR-0020 / matrix / reconciliation.

## 2. Four Approved Decisions

### Decision 01 — GPS & Live Operations Map

**Status:** `APPROVED — PRODUCT SCOPE`

GPS, live tracking, and the operational live map are **in Phase 2 product scope** (aligned with the executive plan Phase 02 screens).

They are **not complete** and must not be claimed Live/complete until all of the following hold:

- An approved GPS provider or other real location data source.  
- Documented coordinate source, accuracy expectations, and update timing.  
- Tenant isolation and season isolation on location data.  
- RBAC on location views and related APIs.  
- Display of last-known update time.  
- Explicit handling of stale or unavailable data.  
- Real integration tests against the chosen source.  
- User acceptance of the feature.

A static map or mock/demo coordinates is **not** accepted as completed live tracking.

**Sprint binding:** location/fleet data readiness relates to **S01/S02**; executive presentation relates to **S10**.

This product decision **does not** cancel [ADR-0018](ADR-0018-TRANSPORTATION-MOVEMENTS-RUNS-AND-FLEET.md) or prior engineering constraints. Any engineering conflict requires a separate Engineering ADR or Change Request before implementation. **Do not move GPS to Phase 3** under this decision; **do not treat it as implemented**.

### Decision 02 — Independent Pilgrim Management UI

**Status:** `APPROVED — FOUNDATION SCOPE`

An independent **staff/admin** Pilgrim Management UI (including pilgrim profile) is required within Phase 2 **Foundation**. Backend alone does **not** close Foundation.

Functional scope includes:

- Pilgrim list; search and filters.  
- Pilgrim detail / profile.  
- Core identity fields already supported by the approved data model.  
- Season linkage (`SeasonPilgrim`).  
- Group and supervisor display when relationships exist.  
- Available transport and accommodation summary information (read models; no cross-domain writes).  
- Operational status display.  
- RBAC + tenant isolation + season isolation.

Passport, visa, or other sensitive document fields may appear **only** if present in the approved model and permitted by RBAC — do not assume backend readiness for undocumented fields.

This is **not** the Phase 3 pilgrim-facing mobile application.

### Decision 03 — Transport Breakdowns & Replacement Vehicle

**Status:** `APPROVED — S02 SCOPE`

Vehicle breakdown recording and operational replacement belong in **S02** (movements / runs / التفويج), not in the Phase 3 full crisis / ops-room suite.

Functional scope includes:

- Record a vehicle fault linked to a movement or run.  
- Fault status, time, and responsible actor.  
- Select an eligible replacement vehicle.  
- Validate replacement capacity.  
- Prevent vehicle assignment conflicts.  
- Reassign pilgrims / transfer assignments per the approved operating model.  
- Retain change history / audit.  
- RBAC + tenant/season isolation.  
- Acceptance cases and tests for conflict and capacity.

**Out of this decision:** comprehensive emergency management, SOS, and advanced command/crisis rooms (Phase 3).

Product approval **does not** freeze workflow detail, data model, or inter-run transfer rules. Detailed design and **ADR-0018 review/alignment** are required before coding.

### Decision 04 — 11 Functional Units / 8-Week Planning Horizon

**Status:** `APPROVED — PLANNING INTERPRETATION`

- “**11 operational units**” in the executive plan is a **high-level functional classification**, not necessarily 11 screens or 11 sprints.  
- **Foundation + S01–S12** is the delivery/execution breakdown; it does not redefine the executive plan’s functional count.  
- **8 weeks** is an **initial planning horizon**, not a fixed contractual delivery commitment by this decision.  
- Actual schedule follows effort, dependencies, tests, and resources under an approved plan.  
- Do not invent a new delivery date in docs without an approved schedule.  
- Preserve the executive plan’s original wording and this interpretation; do not rewrite contractual instruments here.

## 3. Rationale and Source References

| Decision | Primary product rationale |
|---|---|
| 01 GPS | Executive plan Phase 02 lists GPS with fleet/ops; Workflow requires real source (no fake live). |
| 02 Pilgrim UI | Executive plan delivery mockups (~p.8–10) and Workflow open question on pilgrim screens; staff UI needed for S02/S03 linkage. |
| 03 Breakdowns | فكرة منصتي transport emergencies / alternate bus; keep operational replacement in S02 vs Phase 3 crisis suite. |
| 04 11 / 8 weeks | Workflow §14 + Owner interpretation: classification vs delivery taxonomy; planning vs contract. |

Also: [ADR-0020](ADR-0020-FULL-PHASE2-OPERATIONAL-SCOPE.md), [PHASE2_SCOPE_MATRIX.md](../00-project/PHASE2_SCOPE_MATRIX.md), [PHASE2_IMPLEMENTATION_AUDIT.md](../audits/PHASE2_IMPLEMENTATION_AUDIT.md) (snapshot only).

## 4. Functional Boundaries

| In Phase 2 (per these decisions) | Still Phase 3 / out |
|---|---|
| GPS + live ops map (gated by real integration) | Pilgrim mobile app; pilgrim-app QR/SOS |
| Staff Pilgrim Management UI | Full dynamic configuration product |
| Transport breakdown + replacement in S02 | Full emergency/crisis command suite |
| Planning interpretation of 11 units / 8 weeks | Fixed contractual calendar invented in this ADR |

## 5. Engineering Dependencies

- GPS: provider/source selection; integration contract; privacy; failure modes; Engineering ADR/CR if conflicting with ADR-0018 text.  
- Pilgrim UI: frontend against existing SeasonPilgrims APIs; field inventory vs schema; sensitive-field policy.  
- Breakdowns: domain design (entities, transitions); capacity/overlap reuse; ADR-0018 amendment or companion engineering ADR if needed.  
- Schedule: capacity plan after Foundation UI + S01–S03 close estimates.

## 6. Security and Data Isolation

- Deny by default; Policies/Gates for location, pilgrim profile, breakdown actions.  
- `tenant_id` from context only; season from Season Context / `X-Season-Id` — never from payload as authority.  
- Cross-tenant reads → **404**.  
- Location and sensitive pilgrim attributes never leak in unauthorized list/export/audit payloads.  
- Audit critical mutations (replacement, breakdown close, pilgrim profile changes where required) with correlation ID.

## 7. Acceptance Conditions

| Decision | Minimum acceptance (product) |
|---|---|
| 01 | Real source + isolation + RBAC + last-update UX + stale/unavailable handling + integration test + UAT; no mock-as-live |
| 02 | Staff list/detail/search/season linkage/related ops summaries under RBAC; Foundation not closed on backend-only |
| 03 | Fault + replace + capacity/conflict tests + history; no crisis-suite scope creep |
| 04 | Docs and plans use interpretation consistently; no silent 11-sprint or fixed 8-week contract claim |

Sprint closure still follows ADR-0020 §9 and [DEFINITION_OF_DONE.md](../00-project/DEFINITION_OF_DONE.md).

## 8. Relationship to ADR-0020

This ADR **narrows open product gaps** inside the Full Phase 2 scope. It does not replace ADR-0020. Where ADR-0020 described GPS as “special case / dependency-blocked optional,” **Decision 01** upgrades product membership to **in-scope gated requirement** (still not implemented; still not complete without integration).

## 9. Relationship to ADR-0017 / 0018 / 0019

| ADR | Effect |
|---|---|
| [ADR-0017](ADR-0017-SEASON-PILGRIM-AND-PHASE2-BOUNDED-CONTEXTS.md) | Still binding for Season/Pilgrim BC; Decision 02 consumes Foundation APIs/read models without Accommodation/Transport write ownership. |
| [ADR-0018](ADR-0018-TRANSPORTATION-MOVEMENTS-RUNS-AND-FLEET.md) | Remains accepted for movements/runs/fleet. Decisions 01 and 03 may require **engineering follow-up** (not silently changing ADR-0018 in this file). GPS product-in-scope ≠ ADR-0018 already specifies GPS. |
| [ADR-0019](ADR-0019-ACCOMMODATION-CAMPS-DRAFT.md) | Status remains **PROPOSED / NOT ACCEPTED**. |

## 10. Open Engineering Decisions

1. GPS provider / data source and update contract.  
2. Location integration architecture (ingest, storage, retention).  
3. Breakdown/replacement domain model and alignment with ADR-0018.  
4. Exact pilgrim UI field set and sensitive document display rules.  
5. Actual delivery schedule (post-estimation).  
6. Whether ADR-0018 needs a formal engineering addendum for GPS and breakdowns.

## 11. Consequences

- [PHASE2_SCOPE_MATRIX.md](../00-project/PHASE2_SCOPE_MATRIX.md) and [PHASE2_SCOPE_RECONCILIATION.md](../00-project/PHASE2_SCOPE_RECONCILIATION.md) record these product resolutions.  
- [OUT_OF_SCOPE.md](../00-project/OUT_OF_SCOPE.md) Phase 3 list still excludes pilgrim app / advanced crisis / finance / AI; GPS is **not** Phase 3 by Decision 01, but remains incomplete until integration gates pass.  
- No sprint status becomes COMPLETE by this ADR.  
- Sprint 0 remains open for remaining governance/handoff items.
