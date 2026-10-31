# Roadmap

> **Status:** Draft (delivery order aligned to completed / next sprints)
> **Last updated:** 2026-10-30
> **Phase 2 product scope:** [ADR-0020](../10-decisions/ADR-0020-FULL-PHASE2-OPERATIONAL-SCOPE.md) (Full Phase 2 Operational Scope Adoption). Matrix: [PHASE2_SCOPE_MATRIX.md](PHASE2_SCOPE_MATRIX.md). Reconciliation: [PHASE2_SCOPE_RECONCILIATION.md](PHASE2_SCOPE_RECONCILIATION.md).

## Purpose

Separate the committed MVP delivery from Phase 2 operational delivery and the long-term (Phase 3) platform vision. The original 21-module MVP remains unchanged. Phase 2 product breadth is governed by ADR-0020; implementation status is tracked separately and must not be inferred from scope alone.

## Now — MVP (committed)

The 21 modules and four core workflows defined in [MVP_SCOPE.md](MVP_SCOPE.md), delivered as a web platform on the approved stack.

### Foundation progress

| Milestone | Status |
|---|---|
| Repository structure, documentation, standards, templates | Done |
| Module-level documentation under `docs/09-modules/` | Done (living docs) |
| Backend + frontend scaffolding (health, RTL shell, CI) | Done |
| Sprint 004 — Tenant Foundation (data + core scoping/isolation) | Done (implementation) |
| Sprint 005 — Authentication (API + SPA UI) | **Done** |
| Sprint 006 — Users / roles / permissions (RBAC) | **Done** (implementation) |
| Sprint 007 — Organization Structure | **Done** (implementation) |
| Sprint 008 — Employees + Supervisors | **Done** (implementation) |
| Sprint 009 — Contracts | **Done** (implementation) |
| Sprint 010 — Meetings | **Done** (implementation) |
| Sprint 011 — Decisions | **Done** (implementation) |
| Sprint 012 — Tasks & Assignments | **Done** (implementation) |
| Sprint 013 — Documents & Archiving | **Done** (implementation) |
| Sprint 014 — Warehouses & Inventory | **Done** (implementation) |
| Sprint 015 — Assets & Custodies | **Done** (implementation) |
| Sprint 016 — Notifications | **Done** (implementation) |
| Sprint 017 — Dashboard | **Done** (implementation) |
| Sprint 018 — Audit Trail | **Done** (implementation; ADR-0015) |
| Sprint 019 — MVP Integration & Release Hardening | **Done** (merged; deploy/UAT checklists) |
| Sprint 020 — System Settings | **Done** (implementation; ADR-0016) |
| Remaining MVP work | UAT; production deploy |

Natural dependency order remains: **tenancy → authentication → users/roles/permissions → … → audit trail → system settings → release**.

## Release posture

| Track | Status |
|---|---|
| MVP modules 004–020 code | Implemented (Settings closes module 21) |
| System Settings (module 21) | **Implemented** |
| UAT | Automated/API evidence on RC `release/0.1.0-uat` — **interactive browser UAT still pending** |
| Production deployment | Pending ops + infrastructure |
| Recommended first tag | `v0.1.0` (create only after approval — not auto-created) |

## Phase 2 — Full Operational Scope (not part of the original 21-module MVP)

### Historical record — Original Foundation Delivery Scope (2026-10-04)

Approved 2026-10-04 by [CHANGE_REQUEST_PHASE2_FOUNDATION.md](CHANGE_REQUEST_PHASE2_FOUNDATION.md) and [ADR-0017](../10-decisions/ADR-0017-SEASON-PILGRIM-AND-PHASE2-BOUNDED-CONTEXTS.md) as the **first** Phase 2 delivery slice (documentation then implementation of Foundation + Transport + Accommodation only). That history is preserved; it is **not** erased by later product expansion.

| Area | Doc | Notes (historical CR) |
|---|---|---|
| Season + Pilgrim foundation | [16-season-and-pilgrims](../09-modules/16-season-and-pilgrims/) | Bounded context per ADR-0017 |
| Transportation and fleet | [17-transportation-and-fleet](../09-modules/17-transportation-and-fleet/) | ADR-0018 (T1–T10) |
| Accommodation and camps | [18-accommodation-and-camps](../09-modules/18-accommodation-and-camps/) | ADR-0019 remains **PROPOSED / NOT ACCEPTED** |

### Full Approved Phase 2 Product Scope (2026-10-29)

Adopted by Project Owner via [ADR-0020](../10-decisions/ADR-0020-FULL-PHASE2-OPERATIONAL-SCOPE.md). Product clarifications (GPS gated-in-scope, staff Pilgrim UI, S02 breakdowns, “11 units”/“8 weeks” interpretation): [ADR-0021](../10-decisions/ADR-0021-PHASE2-PRODUCT-DECISIONS-AND-DELIVERY-BOUNDARIES.md). Workflow framing: **Foundation** (prerequisite, not S01) + **S01–S12**. Detailed requirements and audit statuses: [PHASE2_SCOPE_MATRIX.md](PHASE2_SCOPE_MATRIX.md).

**Planning horizon:** Executive-plan “8 weeks” for Phase 02 is an **initial planning target** (ADR-0021 Decision 04), not a new contractual delivery date invented here. Actual schedule follows approved estimation. “11 operational units” ≠ 11 sprints.

| Workstream | Sprint | Product status reminder |
|---|---|---|
| Season & pilgrims | Foundation | In scope; **COMPLETE — ACCEPTED** (Owner 2026-10-30). Evidence: Pest 134, Vitest 24, tsc PASS, browser UAT 12/12 on `erp_hajj_foundation_uat` — `docs/audits/foundation-browser-uat/REPORT.md` |
| Transport registry/fleet | S01 | In scope; **COMPLETE — ACCEPTED** (Owner 2026-10-30). Evidence: Pest 24, Vitest fleetValidation 19, browser UAT 14/14 on `erp_hajj_s01_uat` — `docs/audits/s01-fleet-browser-uat/REPORT.md`. GPS live integration remains an **open Phase 2 obligation** (not accepted as part of S01) |
| Movements & convoy ops | S02 | In scope; **COMPLETE — ACCEPTED** (Owner 2026-10-30). Evidence: Breakdown Pest 8, MySQL concurrency 7, browser UAT 16/16 on `erp_hajj_s02_uat` — `docs/audits/s02-transport-browser-uat/REPORT.md` / `S02_ACCEPTANCE_STATUS.md`. GPS live remains open Phase 2 obligation |

| Accommodation & camps | S03 | In scope; **IMPLEMENTED — ACCEPTANCE PENDING** (2026-10-30). Ops journey UAT 16/16 PASS on `erp_hajj_s03_uat`; Pest Feature/Unit 63 PASS (+2 MySQL-skip); MySQL concurrency 14 PASS; Vitest 46 PASS. ADR-0019 still **PROPOSED**. Evidence: `docs/audits/s03-accommodation-browser-uat/REPORT.md`. Awaiting Owner acceptance — not COMPLETE |
| Suppliers & purchase requests | S04 | **In Phase 2 product scope; NOT_STARTED** — needs design + engineering CR before code |
| Procurement cycle (RFQ/PO/receive) | S05 | **In Phase 2; NOT_STARTED** |
| Seasonal projects & tasks | S06 | **In Phase 2; NOT_STARTED** (MVP Tasks ≠ Phase 2 projects) |
| Risks & complaints | S07 | **In Phase 2; NOT_STARTED** |
| Seasonal templates (safe copy) | S08 | **In Phase 2; NOT_STARTED** |
| Operational KPIs | S09 | **In Phase 2; NOT_STARTED** |
| Executive ops dashboard | S10 | **In Phase 2; NOT_STARTED**; live ops map **in product scope** (ADR-0021) — not complete without real GPS/source |

| Integration & tests | S11 | In scope; audit **PARTIAL** |
| UAT & handoff | S12 | In scope; **NOT_STARTED** |

They were **not** part of MVP modules 1–21; [MVP_SCOPE.md](MVP_SCOPE.md) stays unchanged on purpose. Inclusion in this table does **not** mean implemented.

Transportation business rules for S01–S02 remain ADR-0018. GPS is **product-in-scope** (ADR-0021) but **not** specified as complete in ADR-0018; engineering follow-up may be required. Offline accommodation field ops and pilgrim-app QR remain constrained per ADR-0017 / Phase 3 lists.

Sprint closure rules: ADR-0020 §9 + [DEFINITION_OF_DONE.md](DEFINITION_OF_DONE.md). Sprint 0 is governance alignment only — **not closed as a delivery sprint**.

## Later — Phase 3 / Future Vision (NOT part of the MVP; NOT in Full Phase 2 unless a later CR says so)

See [OUT_OF_SCOPE.md](OUT_OF_SCOPE.md) for the deferred list. Representative exclusions: native/pilgrim mobile apps, full finance/accounting, advanced SOS/crisis suite, advanced command center, AI/predictive analytics, government integrations, full offline platform mode, full dynamic builders.

**GPS / live tracking:** in Phase 2 product scope (ADR-0021); **not implemented**; completion gated on real source + isolation/RBAC/UAT. Do not treat as Live. Pilgrim mobile GPS/SOS remain Phase 3.

Authentication-related future items (not Sprint 005): MFA, SSO, email verification, Bearer/PAT login for mobile, `logout-all`.

## Rules

- Nothing in the Phase 3 / “Later” section may be implemented during MVP or Phase 2 without a new approved Change Request.
- Moving an item into the MVP “Now” list requires an approved Change Request and an update to [MVP_SCOPE.md](MVP_SCOPE.md).
- Phase 2 does not expand the 21-module MVP.
- Product-scope approval (ADR-0020) is not implementation authorization for S04–S10 code without stream design and engineering change control.
