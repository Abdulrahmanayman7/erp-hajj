# Tenancy — Acceptance Criteria

> **Status:** Approved
> **Last updated:** 2026-08-06

## Data layer (Phase 1)

- [ ] `tenants` table exists per [DATA_MODEL.md](DATA_MODEL.md): required columns (`id`, `tenant_code`, `name`, `status`, `locale`, `timezone`, `suspended_at`, `archived_at`, timestamps), unique `tenant_code` + `name`, defaults (`pending`, `ar`, `Asia/Riyadh`), no soft delete.
- [ ] `tenant_code` validation enforces `/^[a-z0-9]+(?:-[a-z0-9]+)*$/`, max 63, lowercase storage; the value is immutable after creation at both validation and model layers.
- [ ] `users.tenant_id` exists: nullable, FK `ON DELETE RESTRICT`, indexed; `email` remains globally unique.
- [ ] Factories/seeders provide at least two active tenants with parallel users and data, plus one `pending`, one `suspended` tenant, and one platform user. (The real first tenant **رفيع**/`rafee` is provisioned later through the platform flow — not seeded by tests.)

## Contexts and resolution (Phase 2)

- [ ] `TenantContext` implements the exact public API in [MULTI_TENANCY.md](../../02-architecture/MULTI_TENANCY.md) §3.1 (scoped singleton; `runAsTenant` restores state even on exception; conflicting set throws).
- [ ] `PlatformContext` exists with its deliberate entry point; unavailable to tenant requests; entering it improperly throws `UnauthorizedPlatformContextException`; there is **no `runWithoutTenancy()` or any other public bypass**.
- [ ] The five exceptions exist with the documented HTTP mappings.
- [ ] Tenant context is resolved only from the authenticated user via `TenantResolver`; no payload/header/code-based transport exists.
- [ ] `ResolveTenantContext` clears context in a `finally` block; `EnsureTenantIsActive` performs no resolution and returns the stable codes (`TENANT_CONTEXT_MISSING`/`TENANT_CONTEXT_INVALID`/`TENANT_PENDING`/`TENANT_SUSPENDED`/`TENANT_ARCHIVED`).
- [ ] Lifecycle transitions enforce the allowed graph (no `archived → active`); every transition audit record contains actor, timestamp, old/new status, reason, correlation ID, context type.
- [ ] Every request receives a correlation ID (validated-or-regenerated, echoed in response headers) per [AUDIT_TRAIL.md](../../06-security/AUDIT_TRAIL.md).

## Scoping stack (Phase 3)

- [ ] Every tenant-owned model implements `TenantOwned` and uses `UsesTenantScope`; a scoped query with no context and no `runAsTenant` **throws** (never returns rows).
- [ ] Creating tenant-owned records force-sets the context tenant; `tenant_id` in payloads is ignored everywhere; mutating `tenant_id` throws.
- [ ] Route model binding returns `404` for another tenant's records — indistinguishable from a nonexistent id; nested bindings verify the parent chain; archived/soft-deleted rows stay tenant-scoped.
- [ ] `TenantExists`/`TenantUnique` builders are the only way modules declare tenant-owned `exists`/`unique` rules; `ignore()` stays tenant-scoped.
- [ ] `tenant_settings` works end-to-end with `tenant_settings.view`/`update`, per-tenant key uniqueness, and audit records on update.

## Propagation and platform (Phase 4)

- [ ] Jobs carry server-generated tenant metadata, restore context via job middleware, clear it in `finally`; classification behavior enforced (business jobs released for `pending`/`suspended`, cancelled for `archived`; maintenance jobs run); failed jobs preserve tenant identification.
- [ ] Scheduled all-tenant work chunks tenants and wraps each in `runAsTenant`; no global context for all-tenant commands.
- [ ] Cache keys come only from the shared namespace helper (`tenant:{tenant_id}:`); platform cache uses `platform:`; whole-tenant invalidation works.
- [ ] Files live under `tenants/{tenant_id}/...` per the final layout on a private disk; downloads are policy-checked; signed URLs do not bypass authorization; file metadata includes `tenant_id`.
- [ ] `/api/v1/platform/tenants` endpoints run inside `PlatformContext`, enforce `platform_tenants.*`, audit transitions with reason + correlation ID; invalid transitions return `422 INVALID_TENANT_TRANSITION`; `tenant_code` is immutable via the API.
- [ ] Exceptional access requires `platform_tenants.access_data`, runs via `runAsTenant()`, and writes an audit record carrying the **target tenant's id**.

## Global

- [ ] The complete Pest matrix in [TEST_PLAN.md](TEST_PLAN.md) is green in CI; the cross-tenant attack suite is never skipped.
- [ ] All tenancy audit events listed in [BUSINESS_RULES.md](BUSINESS_RULES.md) are produced.
- [ ] The feature meets [DEFINITION_OF_DONE.md](../../00-project/DEFINITION_OF_DONE.md).
