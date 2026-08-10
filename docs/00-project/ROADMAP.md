# Roadmap

> **Status:** Draft (delivery order aligned to completed / next sprints)
> **Last updated:** 2026-08-10

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
| Sprint 011 — Decisions | **Spec complete** — implementation pending |
| Remaining MVP modules | Next: Decisions implementation, then Tasks |
Natural dependency order remains: **tenancy → authentication → users/roles/permissions → organizational structure → employees → contracts → meetings → decisions → tasks → remaining modules**.

## Later — Future Vision (NOT part of the MVP, all TBD)

The complete list is maintained in [OUT_OF_SCOPE.md](OUT_OF_SCOPE.md): mobile/pilgrim apps, transportation and GPS tracking, accommodation, procurement, finance, crisis management, command center, AI/analytics, government integrations, offline mode, dynamic builders, and more. None of these is approved, designed, or scheduled.

Authentication-related future items (not Sprint 005): MFA, SSO, email verification, Bearer/PAT login for mobile, `logout-all`.

## Rules

- Nothing in the "Later" section may be implemented or partially built during the MVP.
- Moving an item from "Later" to "Now" requires an approved Change Request and an update to [MVP_SCOPE.md](MVP_SCOPE.md).
