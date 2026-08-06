# Tenancy — Test Plan

> **Status:** Draft
> **Last updated:** 2026-08-06

- **Cross-tenant isolation (mandatory):** with two seeded tenants, verify tenant A cannot read, list, update, or delete tenant B's records in any tenant-owned module; lookups return `404`.
- Scoping: unscoped access through the model layer is impossible; created records carry the correct `tenant_id`.
- Payload injection: sending a foreign `tenant_id` in create/update payloads never changes ownership.
- Jobs: queued jobs operate on the originating tenant only.
- Cache: values cached for tenant A are not served to tenant B.
- Settings: permission matrix for `tenant_settings.view`/`update`; audit record on update.
- Super Admin: unauthorized tenant-data access is denied; authorized access produces an audit record.

Fixtures: at least two tenants with parallel data sets (see [ENVIRONMENTS.md](../../08-deployment/ENVIRONMENTS.md)).
