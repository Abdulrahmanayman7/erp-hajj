# [Change Request] Phase 2 — Season and Pilgrim foundation, Transportation, Accommodation

> Status: **Approved** (2026-10-04) — **Original Foundation Delivery Scope**
> Decided: 2026-10-04
> Template: `.github/ISSUE_TEMPLATE/change-request.md`
> Binding ADR (foundation bounded contexts): [ADR-0017](../10-decisions/ADR-0017-SEASON-PILGRIM-AND-PHASE2-BOUNDED-CONTEXTS.md)
> **Product-scope supersession (2026-10-29):** Full Phase 2 operational breadth is governed by [ADR-0020](../10-decisions/ADR-0020-FULL-PHASE2-OPERATIONAL-SCOPE.md). This Change Request remains the historical authorization for the Foundation + Transport + Accommodation **delivery slice**; it is not deleted. See [PHASE2_SCOPE_RECONCILIATION.md](PHASE2_SCOPE_RECONCILIATION.md).

## Requested Change

Formally bring the following into **Phase 2** scope (not into the original 21-module MVP) — **Original Foundation Delivery Scope**:

1. Season foundation (multiple Hajj seasons under one Tenant).
2. Pilgrim foundation required by Phase 2 (Pilgrim, SeasonPilgrim, season-specific Family Group).
3. Transportation and fleet as an independent bounded context.
4. Accommodation and camps as an independent bounded context and the source of truth for accommodation occupancy.

Approved product rules are **F1–F10** (foundation), Transportation architectural boundaries (discovery decisions 1–30 are approved, but their verbatim text is not in this repository), and Accommodation discovery boundaries (decisions 31–100) as written in the module docs. Anything not explicit there is **TBD**.

## Type

- [x] New feature outside current MVP scope
- [ ] Change to an approved requirement or workflow
- [x] Change to an architecture decision (requires ADR update/supersede)
- [ ] Removal/descoping

## Justification

Operational Hajj work is seasonal. Creating a new Tenant per season would break the permanent company boundary. Transportation and accommodation need a stable pilgrim participation key (`SeasonPilgrim`) and must not own each other’s data or duplicate current-room state on the pilgrim.

## Impact Assessment

- Affected modules (documentation only, not implemented):
  - [16-season-and-pilgrims](../09-modules/16-season-and-pilgrims/)
  - [17-transportation-and-fleet](../09-modules/17-transportation-and-fleet/)
  - [18-accommodation-and-camps](../09-modules/18-accommodation-and-camps/)
- Affected documents: `OUT_OF_SCOPE.md`, `ROADMAP.md`, `ARCHITECTURE.md`, `BACKEND_STRUCTURE.md`, `FRONTEND_STRUCTURE.md`, `GLOSSARY.md`, `MVP_KNOWN_LIMITATIONS.md`, root `README.md`, dashboard out-of-scope bullet, ADR-0017.
- Database / API / UI impact: **none in this change.** Future work follows existing `/api/v1`, `tenant_id` from context only, Policies, and audit. Schemas, payloads, and pages stay TBD until a later implementation sprint.
- Tenant isolation, permissions, or audit impact: future tenant-owned tables only. No `PermissionCatalog` change now. No persona bypass.

## Explicitly not brought into scope *(as of this 2026-10-04 Foundation CR)*

The following were **out of this Foundation delivery Change Request**. Several are later reclassified for **product membership** by ADR-0020; Phase 3 items stay deferred. See amended notes.

- Finance / accounting → remains **Phase 3** (ADR-0020)
- Pilgrim mobile application (تطبيق الحجاج) and other native mobile apps → remains **Phase 3**
- Government / Ministry of Hajj integrations → remains **Phase 3**
- AI / predictive analytics → remains **Phase 3**
- GPS bus tracking and smart GPS wristbands → **special case** under ADR-0020 (not implemented; needs integration decision; not Live; not part of ADR-0018)
- New notification delivery channels (email, SMS, WhatsApp, Web Push) → remain outside unless a later CR says otherwise ([ADR-0013](../10-decisions/ADR-0013-IN-APP-NOTIFICATION-OWNERSHIP-AND-DELIVERY.md) channel model)
- Full offline mode as a platform capability (a later Accommodation field-operations slice is documented only) → platform-wide offline remains **Phase 3**
- Procurement → **was** out of this Foundation CR; **now in Full Phase 2 product scope (S04–S05)** per ADR-0020, **NOT_STARTED**, needs design before code
- Crisis management / advanced command center → advanced suites remain **Phase 3**; Phase 2 may later include a bounded risks/complaints stream (S07) under ADR-0020 without becoming a full command center
- Dynamic builders → remain **Phase 3**
- Full seasonal cloning automation → unsafe cloning remains forbidden; **S08 safe templates** enter Full Phase 2 product scope (ADR-0020), **NOT_STARTED**
- Complaints module (الشكاوى) → **was** out of this Foundation CR; **now in Full Phase 2 product scope (S07)** per ADR-0020, **NOT_STARTED**
- Advanced KPI engine / executive live dashboard → **S09–S10** in Full Phase 2 product scope per ADR-0020, **NOT_STARTED**
- Rewriting the original MVP as if these domains had always been modules 1–21 → still forbidden

## Decision

- [x] Approved
- [ ] Rejected
- [ ] Deferred to roadmap

**Decided by:** Product decision recorded in the Phase 2 documentation sprint — **Date:** 2026-10-04

---

## Amendment — Full Phase 2 Operational Scope (2026-10-29)

| Field | Value |
|---|---|
| Decision | [ADR-0020](../10-decisions/ADR-0020-FULL-PHASE2-OPERATIONAL-SCOPE.md) — Full Phase 2 Operational Scope Adoption |
| Status | `APPROVED — PRODUCT SCOPE` |
| Effect on this CR | Does **not** void the 2026-10-04 approval. Widens **product-phase membership** beyond Foundation + Transport + Accommodation to Foundation + S01–S12. |
| Effect on engineering | Does **not** authorize S04–S10 migrations/code without stream design, permissions, tests, and normal Change Request / ADR practice. Does **not** accept [ADR-0019](../10-decisions/ADR-0019-ACCOMMODATION-CAMPS-DRAFT.md). |
| Matrix / conflicts | [PHASE2_SCOPE_MATRIX.md](PHASE2_SCOPE_MATRIX.md) · [PHASE2_SCOPE_RECONCILIATION.md](PHASE2_SCOPE_RECONCILIATION.md) · [OUT_OF_SCOPE.md](OUT_OF_SCOPE.md) · [ROADMAP.md](ROADMAP.md) |
