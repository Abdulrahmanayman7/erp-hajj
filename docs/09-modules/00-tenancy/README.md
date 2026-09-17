# Module: Multi-Tenant Foundation (التأسيس متعدد المستأجرين)

> **Status:** Core implemented (Sprint 004). Platform Tenant Management (registry + lifecycle + First Setup + ProvisionTenant + Ownership V1) **implemented**. Tenant settings API/UI: Sprint 020 + ADR-0016. **Still deferred:** exceptional `platform_tenants.access_data` HTTP path into tenant business data (catalogued, not granted to default Super Admin).
> **Last updated:** 2026-09-17

## Purpose

Provide the tenancy foundation every other module depends on: the tenant (organization) entity with its immutable `tenant_code` (first tenant: **رفيع** / `rafee`), the four-state lifecycle at platform level, automatic tenant scoping, and tenant context propagation to jobs, cache, storage, audit, notifications, and exports.

## Specification map

| Concern | Where specified |
|---|---|
| Strategy, `tenant_code` rules, lifecycle, TenantContext + PlatformContext, exceptions, Resolver, middleware separation, `TenantOwned`/`UsesTenantScope`, binding, validation, queues, cache, storage, notifications, exports, correlation ID, performance | [MULTI_TENANCY.md](../../02-architecture/MULTI_TENANCY.md) |
| Tables, columns, indexes, uniques, FKs, status enum, deletion strategy, migration order, expected first-tenant values | [DATA_MODEL.md](DATA_MODEL.md) + [DATABASE_PRINCIPLES.md](../../03-database/DATABASE_PRINCIPLES.md) |
| Lifecycle rules, suspension behavior, platform admin boundaries, audited events | [BUSINESS_RULES.md](BUSINESS_RULES.md) |
| Endpoints and stable error codes | [API.md](API.md) |
| Permission catalog (`tenant_settings.*`, `platform_tenants.*`) | [PERMISSIONS.md](PERMISSIONS.md) |
| Threat model (attack → control) | [SECURITY_BASELINE.md](../../06-security/SECURITY_BASELINE.md) |
| Correlation ID and audit record design | [AUDIT_TRAIL.md](../../06-security/AUDIT_TRAIL.md) · [14-audit-trail/](../14-audit-trail/) · [ADR-0015](../../10-decisions/ADR-0015-SEMANTIC-AUDIT-TRAIL-AND-IMMUTABLE-HISTORY.md) |
| Complete Pest matrix | [TEST_PLAN.md](TEST_PLAN.md) |
| Definition of done | [ACCEPTANCE_CRITERIA.md](ACCEPTANCE_CRITERIA.md) + [DEFINITION_OF_DONE.md](../../00-project/DEFINITION_OF_DONE.md) |
| Hostinger First Setup runbook | [HOSTINGER_FIRST_SETUP.md](../../08-deployment/HOSTINGER_FIRST_SETUP.md) |

## Implementation order (each phase ≤ one sprint, independently mergeable)

1. **Phase 1 — Data layer:** ✅ Implemented. `tenants` migration (with `tenant_code`, `locale`, `timezone`, `suspended_at`, `archived_at`) + `TenantStatus` enum + `Tenant` model + `users.tenant_id` migration + factories + `rafee` seeder.
2. **Phase 2 — Contexts and resolution:** ✅ Implemented except correlation ID middleware and login-time checks (both land with their owning modules — Audit and Authentication). `TenantContext` + `PlatformContext` + the five exceptions + `TenantResolver`/`AuthenticatedUserTenantResolver` + `ResolveTenantContext` + `EnsureTenantIsActive`. Test series C, PC, R green.
3. **Phase 3 — Scoping stack:** ✅ Implemented except tenant-settings endpoints (require RBAC). `TenantScope` + `TenantOwned` + `UsesTenantScope` (fail-closed, force-set, immutability) + route-binding behavior + `TenantExists`/`TenantUnique` rules + `tenant_settings` table. Test series S, B, V green.
4. **Phase 4 — Propagation and platform:** ✅ Isolation primitives implemented (job capture + job middleware + classification, `TenantCache`, `TenantStorage`; test series Q, K, F green). ✅ **Platform registry:** `/api/v1/platform/setup*`, `/api/v1/platform/tenants*` (CRUD + activate/suspend/archive + transfer-ownership + list users), Platform RBAC tables, First Setup bootstrap, `ProvisionTenant`, Ownership V1. Exceptional `access_data` remains catalog-only (no default grant / no business-data endpoints).

Phases 1→4 are strictly ordered. Business modules may start only after Phase 3.

## Out of scope

- Multi-organization membership for one user (future scope).
- Tenant self-signup/billing (commercial model not confirmed — TBD in [BUSINESS_RULES.md](BUSINESS_RULES.md)).
- Read-only suspension, `archived → active` recovery, physical purge of archived tenants, per-tenant domains/subdomains, API-token/SSO tenant resolution (each requires a Change Request / ADR).
- `currency` / `trade_name` on tenants (requires Change Request).

## References

- [ADR-0003](../../10-decisions/ADR-0003-MULTI-TENANCY.md) · [MULTI_TENANCY.md](../../02-architecture/MULTI_TENANCY.md) · [ENGINEERING_PRINCIPLES.md](../../02-architecture/ENGINEERING_PRINCIPLES.md)
