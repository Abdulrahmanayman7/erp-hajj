# Phase 2 Scope Matrix

> **Status:** Living governance matrix (product scope per [ADR-0020](../10-decisions/ADR-0020-FULL-PHASE2-OPERATIONAL-SCOPE.md); product clarifications per [ADR-0021](../10-decisions/ADR-0021-PHASE2-PRODUCT-DECISIONS-AND-DELIVERY-BOUNDARIES.md))
> **Last updated:** 2026-10-29
> **Current Status column:** Snapshot from [PHASE2_IMPLEMENTATION_AUDIT.md](../audits/PHASE2_IMPLEMENTATION_AUDIT.md) dated **2026-10-29** — do **not** promote `IMPLEMENTED_UNVERIFIED` to `COMPLETE`.
> **Post-audit note:** Accommodation shared wiring was surgically restored after the audit (`CODE VERIFIED` only). That does **not** close S03, Sprint 0, or UAT.
> **Planning note (ADR-0021 Decision 04):** Executive-plan “11 operational units” = high-level functional classification (not 11 screens/sprints). “8 weeks” = initial planning horizon (not a fixed contractual date from that decision). Delivery taxonomy remains Foundation + S01–S12.

## Purpose

Map Full Phase 2 workstreams to Workflow sprints, dependencies, audit status, and evidence pointers.

## Matrix

| Workstream | Sprint | Functional Requirements | Dependencies | Current Status | Acceptance Evidence | Source Reference |
|---|---|---|---|---|---|---|
| Season & Pilgrim foundation | Foundation (not Workflow S01) | Seasons; pilgrims; SeasonPilgrim; groups; **independent staff Pilgrim Management UI** (list/search/detail/season link/group-supervisor/transport-accommodation summaries/ops status per ADR-0021 D02); integration keys for Transport/Accommodation | ADR-0003 tenancy; Season Context; MVP Users/Employees; approved schema/RBAC for any sensitive fields | **COMPLETE — ACCEPTED** (Owner 2026-10-30). Staff UI + enrollment/groups/programs/families. FE Vitest 24 PASS; SeasonPilgrims Pest 134 PASS; browser UAT 12/12 on `erp_hajj_foundation_uat` — `docs/audits/foundation-browser-uat/REPORT.md`. Non-blocking: UI season-close click not always exercised (API close + `SEASON_CLOSED` verified). Transport/Accommodation summaries remain unavailable until S01/S03 read contracts. | `Modules/SeasonPilgrims`; FE `modules/season-pilgrims`; Pest; UAT evidence. Owner-accepted 2026-10-30 | ADR-0017; ADR-0021 D02; module `16-season-and-pilgrims`; Audit §5 Foundation |
| Transportation registry & fleet | **S01** | Vehicles; drivers; season fleet; transport points; capacity; permissions; UI; in-boundary integration; **GPS/location data readiness as gated Phase 2 requirement** (ADR-0021 D01 — not complete without real source) | Foundation SeasonPilgrim/Season Context; ADR-0018; GPS provider/source TBD (engineering) | **COMPLETE — ACCEPTED** (Owner 2026-10-30). Browser UAT 14/14 on `erp_hajj_s01_uat` — `docs/audits/s01-fleet-browser-uat/REPORT.md`; Pest 24; Vitest 19. GPS live **not** part of S01 acceptance — remains open Phase 2 obligation. Ready to Close: **Yes** (registry/fleet). GPS live **not** COMPLETE | `Modules/Transportation` registry/fleet; FE S01 pages; Pest; Vitest; UAT evidence. **GPS:** provider/source + fields still required before Phase 2 GPS readiness can be COMPLETE | ADR-0018; ADR-0021 D01; module `17-transportation-and-fleet`; Audit §5 S01 |
| Movements & convoy ops | **S02** | Movements; runs; manifests; conflict prevention; operational states; schedules/follow-up; UI; **vehicle breakdown + replacement + capacity/conflict-safe reassignment** (ADR-0021 D03); GPS/location ops data feed toward S10 (gated) | S01 COMPLETE; Foundation Active SeasonPilgrim; ADR-0018 T7 | **COMPLETE — ACCEPTED** (Owner 2026-10-30). UAT 16/16 on `erp_hajj_s02_uat`; Pest breakdown 8; MySQL concurrency 7 after locking-read fix. GPS still gated (not part of S02) | FE + BE S02; `S02_ACCEPTANCE_STATUS.md`; `docs/audits/s02-transport-browser-uat/REPORT.md` | ADR-0018; ADR-0021 D01/D03 |
| Accommodation & camps | **S03** | Facilities/camps; structures/units/positions; season allocation; draft→confirm→arrive→check-in/out; capacity; scopes; timeline | Foundation; Season Context; ADR-0017 occupancy; ADR-0019 still **PROPOSED** | **IMPLEMENTED — ACCEPTANCE PENDING** (2026-10-30). Browser UAT 16/16 PASS (`erp_hajj_s03_uat`); Pest 63 PASS / concurrency 14 PASS; Vitest 46 PASS. Ready for Owner Acceptance: **Yes**. Not COMPLETE until Owner. ADR-0019 §11 Family/cross-season capacity still open | Module 18 + FE `modules/accommodation`; `docs/audits/s03-accommodation-browser-uat/REPORT.md` | Module `18-accommodation-and-camps`; ADR-0017/0019 |
| Suppliers & purchase requests | **S04** | Supplier registry/classifications; evaluation; purchase requests; approval levels | Tenant RBAC; design/CR before code; Contracts MVP is **not** a substitute | **NOT_STARTED** (~0%) | None | ADR-0020; Workflow S04; Audit §5 S04 |
| Procurement cycle | **S05** | RFQ; offers/comparison; selection; PO; receiving; cycle close | S04; inventory/warehouse touchpoints TBD in design | **NOT_STARTED** (~0%) | None | ADR-0020; Workflow S05; Audit §5 S05 |
| Seasonal projects & tasks | **S06** | Season-linked projects; budgets/phases; tasks; progress/delays | Foundation season; MVP Tasks may inform UX but Phase 2 projects model TBD | **NOT_STARTED** for Phase 2 (~5% if MVP Tasks counted as dependency only) | MVP `Modules/Tasks` only | ADR-0020; Workflow S06; Audit §5 S06 |
| Risks & complaints | **S07** | Risk register; likelihood/impact; treatments; complaints; escalation/close | Tenant RBAC; audit; notifications channel remains ADR-0013 | **NOT_STARTED** (~0%) | None | ADR-0020; Workflow S07; Audit §5 S07 |
| Seasonal templates | **S08** | Allowed template/plan reuse; safe reference-data copy; season dates; forbid unsafe pilgrim/ops copy | Foundation seasons; explicit copy rules | **NOT_STARTED** (~0%) | None (full cloning previously out of Phase 2 delivery) | ADR-0020; Workflow S08; Audit §5 S08 |
| Operational KPIs | **S09** | Transport/accommodation/procurement/projects/risks KPIs; formulas; season/company/permission scope | Domain modules as data sources; ADR-0014 patterns | **NOT_STARTED** for Phase 2 (~5% MVP dashboard counts only) | MVP Dashboard only | ADR-0020; Workflow S09; Audit §5 S09 |
| Executive ops dashboard | **S10** | Unified ops indicators; season status; occupancy/progress; alerts/reports; **live operations map** in Phase 2 product scope (ADR-0021 D01) — completion gated on real GPS/location source (not invented vendor; not mock-as-live) | S09 metrics; S01/S02 location/ops data; GPS provider/source TBD | **NOT_STARTED** for Phase 2 (~10% MVP admin dashboard). Live map **not** COMPLETE | MVP Dashboard README (no Phase 2 widgets/GPS). **Acceptance evidence (when built):** consistent figures + RBAC + last-update/stale handling + integration/UAT; static map ≠ live tracking | ADR-0020; ADR-0021 D01; Workflow S10; Audit §5 S10 |
| Integration & hardened tests | **S11** | BE/FE integration; tenant/season isolation; RBAC; concurrency; audit; regression; performance; RTL/Arabic | Prior workstreams in approved slices | **PARTIAL** (~25%) | CI MySQL transport job; accommodation reviews; no unified Phase 2 closure report | ADR-0020; Workflow S11; Audit §5 S11; DEFINITION_OF_DONE |
| UAT & phase handoff | **S12** | UAT scenarios; remediation; evidence pack; phase closure minutes | S01–S11 per agreed exit criteria | **NOT_STARTED** (~0%) | No Phase 2 UAT minutes | ADR-0020; Workflow S12; Audit §5 S12 |

## Sprint closure reminder

Per [ADR-0020](../10-decisions/ADR-0020-FULL-PHASE2-OPERATIONAL-SCOPE.md) §9 and [DEFINITION_OF_DONE.md](DEFINITION_OF_DONE.md): requirements, BE, FE (if required), permissions/isolation, tests, evidence, acceptance, dual review.
`CODE VERIFIED` ≠ `UAT PASSED`. Wiring recovery ≠ S03/Sprint 0 closed.

## Reference Gaps

| Reference | Availability for this matrix |
|---|---|
| `ERP_Hajj_Phase_2_Workflow_Reviewed.pdf` | External / attachments; not copied under `docs/` |
| فكرة منصتي | External / attachments; not filed under `docs/` |
| الخطة التنفيذية | Read in Sprint 0 verification session (attachments); not filed under `docs/` — Phase 02 p.4 informs ADR-0021 |
| PHASE2_IMPLEMENTATION_AUDIT.md | Used for **Current Status** only (historical snapshot; not edited) |
