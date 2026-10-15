# Roadmap

> **Status:** Draft (delivery order aligned to completed / next sprints)
> **Last updated:** 2026-10-04

## Purpose

Separate the committed MVP delivery from the long-term platform vision. Only the MVP is committed; everything else is indicative and TBD.

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

## Phase 2 — approved direction (not part of the original MVP, not implemented)

Approved 2026-10-04 by [CHANGE_REQUEST_PHASE2_FOUNDATION.md](CHANGE_REQUEST_PHASE2_FOUNDATION.md) and [ADR-0017](../10-decisions/ADR-0017-SEASON-PILGRIM-AND-PHASE2-BOUNDED-CONTEXTS.md).

| Area | Doc | Code |
|---|---|---|
| Season + Pilgrim foundation (including season-specific Family Group) | [16-season-and-pilgrims](../09-modules/16-season-and-pilgrims/) | Backend implemented (frontend not built) |
| Transportation and fleet | [17-transportation-and-fleet](../09-modules/17-transportation-and-fleet/) | Backend sprint 1 implemented per [ADR-0018](../10-decisions/ADR-0018-TRANSPORTATION-MOVEMENTS-RUNS-AND-FLEET.md) (frontend not built) |
| Accommodation and camps | [18-accommodation-and-camps](../09-modules/18-accommodation-and-camps/) | Not started |

These were previously listed as unscheduled future scope. They are now specified. They were **not** part of MVP modules 1–21, and [MVP_SCOPE.md](MVP_SCOPE.md) is unchanged on purpose.

Transportation discovery details are resolved by approved decisions T1–T10 (ADR-0018). Accommodation implementation remains blocked while its module docs still mark schemas and permission verbs as TBD. Offline accommodation field operations and QR are later slices inside Accommodation, not the first sprint.

## Later — Future Vision (NOT part of the MVP or Phase 2)

The deferred list is [OUT_OF_SCOPE.md](OUT_OF_SCOPE.md): native and pilgrim mobile apps, GPS bus tracking, procurement, finance, crisis management, command center, AI/analytics, government integrations, full offline mode, dynamic builders, and more. Those items are not approved or scheduled.

Authentication-related future items (not Sprint 005): MFA, SSO, email verification, Bearer/PAT login for mobile, `logout-all`.

## Rules

- Nothing in the "Later" section may be implemented or partially built during the MVP or as part of Phase 2.
- Moving an item from "Later" into the MVP "Now" list requires an approved Change Request and an update to [MVP_SCOPE.md](MVP_SCOPE.md).
- Phase 2 was added as its own section by Change Request. It does not expand the 21-module MVP.
