# Tenancy — API (planned contract)

> **Status:** Approved contract — no endpoints exist yet
> **Last updated:** 2026-08-06

All endpoints follow [API_STANDARDS.md](../../04-api/API_STANDARDS.md): `/api/v1`, standardized envelope, Form Request validation, Policy checks, audited transitions, correlation ID on every request.

## Tenant settings (tenant users)

```text
GET   /api/v1/tenant-settings          # tenant_settings.view
PATCH /api/v1/tenant-settings          # tenant_settings.update (audited: old/new values)
```

- Both operate on the authenticated user's tenant only (context-derived); there is no `{tenant}` parameter — a parameter would invite client-controlled tenancy.
- `PATCH` accepts a map of `key → value`; keys are validated against the registered settings catalog of the modules (keys TBD per module).

## Platform tenant management (platform users only, inside `PlatformContext`)

Lives under the **`/api/v1/platform/` prefix**: platform routes get their own middleware group (platform-user check, `PlatformContext` entry; no tenant context) and can never be confused with tenant-scoped resources. A separate version root (`/platform/v1`) was rejected — one API version discipline is simpler.

```text
GET   /api/v1/platform/tenants                      # platform_tenants.view (paginated; filter by status)
POST  /api/v1/platform/tenants                      # platform_tenants.create (audited; validates tenant_code pattern + global uniqueness)
GET   /api/v1/platform/tenants/{tenant}             # platform_tenants.view
PATCH /api/v1/platform/tenants/{tenant}             # platform_tenants.update (audited; tenant_code is IMMUTABLE — attempts to change it fail validation)
POST  /api/v1/platform/tenants/{tenant}/activate    # platform_tenants.activate (audited; pending→active, suspended→active)
POST  /api/v1/platform/tenants/{tenant}/suspend     # platform_tenants.suspend (audited; body: reason required)
POST  /api/v1/platform/tenants/{tenant}/archive     # platform_tenants.archive (audited; body: reason required; terminal)
```

- Lifecycle transitions are explicit action endpoints (project rule: no generic status PATCH). Allowed transitions per [DATA_MODEL.md](DATA_MODEL.md); invalid ones (including anything from `archived`) return `422 INVALID_TENANT_TRANSITION`.
- `tenant_code` on create: required, `/^[a-z0-9]+(?:-[a-z0-9]+)*$/`, max 63, lowercase enforced, globally unique. First tenant: `rafee` / **رفيع** (documented expected values — not seeded by this specification).
- `{tenant}` binds against the platform-scoped `tenants` table; tenant users receive `403` from the platform middleware before binding.

## Error codes introduced by this module

| Code | HTTP | Meaning |
|---|---|---|
| `TENANT_CONTEXT_MISSING` | 403 | Tenant-owned route reached without a tenant context (e.g. platform user) |
| `TENANT_CONTEXT_INVALID` | 403 | Authenticated user's tenant relationship is broken |
| `TENANT_PENDING` | 403 | Tenant exists but is not activated yet |
| `TENANT_SUSPENDED` | 403 | Tenant temporarily blocked (login and all protected requests) |
| `TENANT_ARCHIVED` | 403 | Tenant terminally closed |
| `INVALID_TENANT_TRANSITION` | 422 | Disallowed lifecycle transition |

**`403` vs `404`:** lifecycle blocking returns `403` with a stable code (the caller's own tenant state is not a secret from them); cross-tenant resource lookups always return `404` (foreign existence is a secret — enumeration risk).

## TBD

- **Settings keys catalog**: collected as modules are designed. **Recommended:** central registry in `Core/Tenancy`. **Impact:** validation layer only.
- **Exceptional-access read endpoints** (how `platform_tenants.access_data` is exercised): unresolved — support workflow not designed. **Recommended:** dedicated read-only platform endpoints per entity, added when support workflows demand them. **Impact:** additive; permission, `runAsTenant` mechanism, and audit path already specified.
- **Provisioning endpoint** (tenant + first Tenant Owner in one operation): pending the onboarding TBD in [BUSINESS_RULES.md](BUSINESS_RULES.md).
