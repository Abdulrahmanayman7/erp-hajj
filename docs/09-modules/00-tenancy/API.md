# Tenancy — API (planned)

> **Status:** Planned contract — no endpoints exist yet
> **Last updated:** 2026-08-06

All endpoints follow [API_STANDARDS.md](../../04-api/API_STANDARDS.md) (`/api/v1`, standardized envelope).

## Tenant settings (tenant-level)

```text
GET   /api/v1/tenant-settings          # requires tenant_settings.view
PATCH /api/v1/tenant-settings          # requires tenant_settings.update (audited)
```

## Platform tenant management (Super Admin)

Planned capability: list/create/activate/suspend tenants. Endpoint paths and whether they live under `/api/v1` or a platform-scoped prefix: **TBD**.

## TBD

- Settings keys required by MVP modules (collected as modules are designed): TBD.
- Platform endpoints structure: TBD.
