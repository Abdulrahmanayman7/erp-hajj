# Tenancy — Acceptance Criteria

> **Status:** Draft
> **Last updated:** 2026-08-06

- [ ] Every tenant-owned model is automatically scoped; no unscoped tenant-owned query is possible through normal model usage.
- [ ] `tenant_id` from request payloads is ignored/rejected everywhere.
- [ ] Route model binding returns `404` for another tenant's records.
- [ ] Creating tenant-owned records automatically attaches the authenticated user's tenant.
- [ ] Background jobs execute within the originating tenant context.
- [ ] Cache keys and file storage paths include tenant context.
- [ ] Tenant settings are viewable/updatable only with the respective permissions; updates are audited.
- [ ] Suspended tenant behavior implemented as decided (TBD blocking: suspension behavior).
- [ ] Super Admin access to tenant data requires explicit permission and produces an audit record.
