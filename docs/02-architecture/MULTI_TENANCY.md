# Multi-Tenancy

> **Status:** Approved (strategy decided)
> **Last updated:** 2026-08-06

## Purpose

Define the tenancy strategy and the non-negotiable isolation rules. See [ADR-0003](../10-decisions/ADR-0003-MULTI-TENANCY.md).

## Terminology

- **Tenant** (مستأجر) is the technical term.
- **Organization / Campaign Company** (منظمة / شركة حملة) is the business-facing term.

## Decided Strategy

- **Single application, single database, shared schema.**
- **`tenant_id` on all tenant-owned tables.** Central platform tables (e.g. tenants registry, platform settings) may omit `tenant_id` where appropriate.
- **Tenant context is resolved after authentication** — derived from the authenticated user, never from client input.
- Each tenant user belongs to **one tenant** in the MVP. Multi-organization membership for one user is future scope unless approved later.

## Non-negotiable Rules

1. **Cross-tenant access must be impossible.**
2. Tenant-owned queries must be **automatically scoped** (global scope/trait) — never by remembering `where` clauses.
3. **`tenant_id` must never be trusted from request payloads.** It comes only from the authenticated context.
4. Route model binding must respect tenant scope; records from other tenants behave as nonexistent (`404`).
5. Unique constraints should include `tenant_id` where needed (e.g. employee number unique per tenant).
6. Background jobs must carry and enforce tenant context.
7. Cache keys must include tenant context.
8. Files must use tenant-isolated storage paths.
9. Audit logs must include `tenant_id`.
10. Notifications must not cross tenants.
11. Exports must include only current tenant data.
12. Tests must verify tenant isolation — every tenant-owned module has at least one explicit cross-tenant access test.
13. **Platform Super Admin access to tenant data is exceptional**: explicitly permission-controlled and audited, never automatic.

## Module Documentation

Tenancy foundation details (tenant entity, resolution, lifecycle) are documented in [docs/09-modules/00-tenancy/](../09-modules/00-tenancy/).

## TBD

- Tenant resolution transport details for the SPA (Sanctum mode interaction): TBD with authentication implementation.
- Tenant provisioning/onboarding flow details: TBD.
