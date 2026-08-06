# Module Template

> **Status:** Approved
> **Last updated:** 2026-08-06

Every MVP module must satisfy this checklist before implementation starts and keep it satisfied as it evolves. The written artifacts live in `docs/09-modules/<module>/` (README, BUSINESS_RULES, PERMISSIONS, API, DATA_MODEL, UI, ACCEPTANCE_CRITERIA, TEST_PLAN). **Not every code folder is mandatory** — a module contains only what it needs ([BACKEND_STRUCTURE.md](BACKEND_STRUCTURE.md)); this checklist is about *decisions being made*, not folders existing.

## Definition checklist

| # | Item | Must answer |
|---|---|---|
| 1 | **Purpose** | Why the module exists; which business capability it delivers. |
| 2 | **Scope** | What is included in the MVP for this module. |
| 3 | **Out of scope** | What is explicitly excluded or future scope. |
| 4 | **Personas** | Which personas use it and how ([USERS_AND_PERSONAS.md](../01-business/USERS_AND_PERSONAS.md)). |
| 5 | **Permissions** | Full `module.action` list ([PERMISSION_MODEL.md](../06-security/PERMISSION_MODEL.md)). |
| 6 | **Business rules** | Domain rules, invariants, and edge-case decisions (or explicit TBD). |
| 7 | **Entities** | Tables/models with platform vs. tenant-owned classification. |
| 8 | **Relationships** | FKs, ownership, cascade/restrict decisions. |
| 9 | **State transitions** | Status enums with the allowed-transition graph and who may trigger each. |
| 10 | **API** | Endpoints, payloads, error codes, action endpoints for transitions. |
| 11 | **UI pages** | Pages/flows with RTL and the shared design system. |
| 12 | **Audit events** | Which operations produce audit records ([AUDIT_TRAIL.md](../06-security/AUDIT_TRAIL.md)). |
| 13 | **Tenant isolation** | Confirmation every entity is scoped; any platform table justified. |
| 14 | **Validation** | Form Request rules incl. tenant-scoped `TenantExists`/`TenantUnique`. |
| 15 | **Error codes** | Stable machine-readable codes introduced by the module. |
| 16 | **Notifications** | What notifies whom, tenant-scoped ([MULTI_TENANCY.md](MULTI_TENANCY.md) §12). |
| 17 | **Tests** | TEST_PLAN with positive/negative/edge/security incl. mandatory cross-tenant cases. |
| 18 | **Acceptance criteria** | Verifiable definition of done for the module. |
| 19 | **Documentation** | All eight module docs updated in the same PRs as the code. |
| 20 | **Deployment concerns** | Migrations order, seeds, config/env additions, queue/scheduler needs. |
| 21 | **Rollback concerns** | What reverting a deploy of this module requires; destructive-migration plan. |
| 22 | **Performance concerns** | Expected volumes, tenant-leading indexes, pagination/chunking decisions. |
| 23 | **Security threats** | Module-specific threats beyond the baseline and their controls. |

## Rules

- A module with unanswered blocking items does not start implementation; unresolved business questions are TBD with recommendation and impact.
- The tenancy module ([docs/09-modules/00-tenancy/](../09-modules/00-tenancy/)) is the reference example of a completed template.
