# Out of Scope

> **Status:** Approved (amended 2026-10-29 for Full Phase 2 product scope)
> **Last updated:** 2026-10-29
> **Related:** [ADR-0020](../10-decisions/ADR-0020-FULL-PHASE2-OPERATIONAL-SCOPE.md) · [PHASE2_SCOPE_RECONCILIATION.md](PHASE2_SCOPE_RECONCILIATION.md) · [CHANGE_REQUEST_PHASE2_FOUNDATION.md](CHANGE_REQUEST_PHASE2_FOUNDATION.md)

## Purpose

List what is explicitly **not** part of:

1. The original **MVP** (21 modules), and
2. **Phase 3 / deferred** platform vision (not in Full Phase 2 product scope).

Do not implement, partially build, or design-for items in the Phase 3 list during MVP or Phase 2 without a new approved Change Request.

This file preserves **delivery history**. It is not rewritten as if the 2026-10-04 Foundation-only Phase 2 never existed.

## Historical note — Original Foundation Delivery Scope (2026-10-04)

On 2026-10-04, [CHANGE_REQUEST_PHASE2_FOUNDATION.md](CHANGE_REQUEST_PHASE2_FOUNDATION.md) and [ADR-0017](../10-decisions/ADR-0017-SEASON-PILGRIM-AND-PHASE2-BOUNDED-CONTEXTS.md) approved a **narrow Phase 2 delivery slice**: Season/Pilgrim foundation, Transportation & fleet, Accommodation & camps only. At that time, procurement, complaints, advanced projects, full seasonal cloning, advanced KPIs, GPS, finance, pilgrim apps, and related items were correctly listed as **outside that delivery scope**.

That historical delivery boundary remains true as a **record of what the Foundation CR authorized**. It is superseded **for product-phase membership** by ADR-0020 (below), not deleted.

## Full Approved Phase 2 Product Scope (2026-10-29)

[ADR-0020](../10-decisions/ADR-0020-FULL-PHASE2-OPERATIONAL-SCOPE.md) adopts **Full Phase 2 Operational Scope**: Foundation + Workflow **S01–S12** (transport, accommodation, suppliers/procurement, seasonal projects, risks/complaints, seasonal templates, KPIs, executive dashboard, integration tests, UAT).

Items below that now sit **inside** that product scope are **not** “out of Phase 2” anymore. They are typically **NOT_STARTED** and still require module design + engineering change control before code. See [PHASE2_SCOPE_MATRIX.md](PHASE2_SCOPE_MATRIX.md). Inclusion ≠ implementation.

**Still not rewritten:** [MVP_SCOPE.md](MVP_SCOPE.md) (21 modules).

## Phase 3 / Future Scope (not in the MVP; not in Full Phase 2 product scope)

### Mobile and external users

- Android application
- iOS application
- Pilgrim application (تطبيق الحجاج) — Phase 2 may hold **staff-facing** pilgrim **records** and workflows; a pilgrim-facing app remains out
- Pilgrim-app operational QR / SOS tied to that app

### Operations domains (remain deferred)

- Full financial management / accounting platform
- Advanced emergency and crisis management (beyond any narrowly scoped Phase 2 complaint/risk module design)
- Full / advanced Command and Control Center
- Smart GPS wristbands
- Health monitoring

### Intelligence and integrations

- Artificial intelligence
- Predictive analytics
- Ministry of Hajj integrations
- Other government integrations

### Platform capabilities

- Full offline mode as a **platform** capability (a later Accommodation field-operations slice may be designed under module 18 rules; it is not platform-wide offline and is not assumed implemented)
- Full dynamic form builder
- Full dynamic module builder
- Knowledge management
- Policies and procedures module (as a standalone advanced platform product)

### GPS and live tracking (special case — not “done”, not silently in S10)

- Present in the original functional vision.
- **Not implemented** (audit snapshot 2026-10-29).
- May support executive dashboard **maps/live views only after** an explicit integration decision (data source, tenancy, privacy, failure modes).
- Until then: **dependency-blocked / not Live**. No vendor or API is invented here.
- Not part of ADR-0018 Transportation acceptance.

## Inside Full Phase 2 product scope but not started (do not treat as Out of Scope)

The following were historically listed as outside the **Foundation delivery** Phase 2. Under ADR-0020 they are **in product scope**, awaiting design/engineering authorization:

| Theme | Workflow | Note |
|---|---|---|
| Suppliers & purchase requests | S04 | **FINAL FUNCTIONAL CLOSURE — OWNER GRANTED (2026-11-02)** — [S04-FINAL-FUNCTIONAL-CLOSURE-2026-11-02.md](../10-decisions/S04-FINAL-FUNCTIONAL-CLOSURE-2026-11-02.md); residual follow-ups preserved (`fr_count=2`; MariaDB 1213 3/112); commit/push/merge/deploy **NOT AUTHORIZED** by closure |
| Procurement cycle (RFQ / PO / receive) | S05 | **Implemented + Merged** on `develop` (PR #41 ancestry; evidence retained); Owner-acceptance certificate not located — do not invent |
| Seasonal projects & advanced tasking | S06 | **Implemented + Merged** via PR #45 @ `e25dc8f` ([20-season-projects](../09-modules/20-season-projects/README.md)). Stream status **FUNCTIONALLY_ACCEPTED / TECHNICAL_VALIDATION_DEFERRED** (2026-11-07) — **not** marked Owner Accepted / COMPLETE; MySQL 8 deferred; unsafe pilgrim/ops cloning remains forbidden elsewhere |
| Risks & complaints | S07 | **Implemented + Merged** (PR #44 @ `a21e4ca`); Owner final acceptance not assumed from merge |
| Seasonal templates (safe reference copy only) | S08 | **Implemented** on `feature/s08-seasonal-templates` ([20-seasonal-templates](../09-modules/20-seasonal-templates/)); **not** Owner Accepted / merged / deployed; unsafe pilgrim/ops cloning remains forbidden |
| Operational KPI engine (Phase 2 formulas) | S09 | NOT_STARTED (MVP dashboard counts are not Phase 2 KPIs) |
| Executive operations dashboard widgets | S10 | NOT_STARTED; maps/live per GPS special case |

## Rules

- Phase 3 items above require an approved **Change Request** before design or code.
- Never describe Phase 3 items as part of the current MVP or as **delivered** Phase 2.
- Never describe S04–S10 as implemented merely because they are in product scope.
- The API must not be shaped speculatively around Phase 3 features.
- Phase 2 module docs must not invent GPS vendors, finance ledgers, or pilgrim-app contracts.
- Moving work into the MVP requires updating [MVP_SCOPE.md](MVP_SCOPE.md).
