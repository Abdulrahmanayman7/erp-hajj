# [Change Request] Phase 2 — Season and Pilgrim foundation, Transportation, Accommodation

> Status: **Approved** — documentation and architecture alignment only. No application code, migrations, or permission seeding in this change.
> Decided: 2026-10-04
> Template: `.github/ISSUE_TEMPLATE/change-request.md`
> Binding ADR: [ADR-0017](../10-decisions/ADR-0017-SEASON-PILGRIM-AND-PHASE2-BOUNDED-CONTEXTS.md)

## Requested Change

Formally bring the following into **Phase 2** scope (not into the original 21-module MVP):

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

## Explicitly not brought into scope

- Finance / accounting
- Pilgrim mobile application (تطبيق الحجاج) and other native mobile apps
- Government / Ministry of Hajj integrations
- AI / predictive analytics
- GPS bus tracking and smart GPS wristbands (not specified in the approved text available here)
- New notification delivery channels (email, SMS, WhatsApp, Web Push)
- Full offline mode as a platform capability (a later Accommodation field-operations slice is documented only)
- Procurement, crisis management, command center, dynamic builders
- Full seasonal cloning automation
- Rewriting the original MVP as if these domains had always been modules 1–21

## Decision

- [x] Approved
- [ ] Rejected
- [ ] Deferred to roadmap

**Decided by:** Product decision recorded in the Phase 2 documentation sprint — **Date:** 2026-10-04
