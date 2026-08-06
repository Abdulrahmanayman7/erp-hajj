# ADR-0003: Multi-Tenancy from Day One — Single Database, Shared Schema

> **Status:** Accepted
> **Last updated:** 2026-08-06

## Context

ERP Hajj serves multiple independent Hajj campaign companies (tenants) on one SaaS-ready platform. Retrofitting tenancy is costly and error-prone, and the project requires that cross-tenant access be impossible. The team is two developers who need a strategy that is operationally simple and testable.

## Decision

The platform is **multi-tenant from day one**, using:

- **Single application, single database, shared schema.**
- **`tenant_id` column on all tenant-owned tables**; central platform tables may omit it where appropriate.
- **Automatic tenant scoping** enforced centrally at the data-access layer (global scope/trait in `Core/Tenancy`) — never left to per-query discipline.
- **Tenant context resolved after authentication**; `tenant_id` is never trusted from request payloads.
- Each tenant user belongs to one tenant in the MVP; multi-organization membership is future scope.
- Tenant context propagated to background jobs, cache keys, file storage paths, audit logs, notifications, and exports.
- Every tenant-owned module ships explicit cross-tenant isolation tests.
- Platform Super Admin access to tenant data is exceptional, permission-controlled, and audited.

Schema-per-tenant and database-per-tenant approaches are rejected for the MVP.

## Consequences

### Positive

- One migration path and one operational surface — simplest for a two-developer team.
- Isolation is centrally enforced and cheap to test per module.
- SaaS-ready: tenant provisioning is a data operation, not an infrastructure operation.

### Negative / Accepted Trade-offs

- Isolation depends on application-layer enforcement — hence the mandatory automatic scoping and isolation tests.
- Very large tenants share database resources; partitioning strategies would need a future ADR if ever required.
- Unique constraints and indexes must consistently include `tenant_id` where needed.

## Notes

- Operational rules are detailed in [MULTI_TENANCY.md](../02-architecture/MULTI_TENANCY.md) and [docs/09-modules/00-tenancy/](../09-modules/00-tenancy/).
