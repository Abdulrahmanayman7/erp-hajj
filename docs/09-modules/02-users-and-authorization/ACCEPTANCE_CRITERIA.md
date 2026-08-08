# Users and Authorization — Acceptance Criteria

> **Status:** Sprint 006 — specification complete; implementation pending  
> **Last updated:** 2026-08-08

## Functional

- [ ] Tenant users can be listed, viewed, created, updated, enabled, disabled within tenant scope
- [ ] Roles are tenant-scoped, dynamic, and provisioned from templates without hardcoding `rafee`
- [ ] Permissions are global catalog; tenants assign via roles only (no direct user permissions)
- [ ] Multiple roles per user; effective permissions = union of active roles
- [ ] User-role and role-permission assignment APIs work with full replace semantics
- [ ] `/auth/me` returns `roles` + sorted `permissions`
- [ ] Permission matrix UI groups by module (Arabic RTL)
- [ ] Sidebar shows Users / Roles only when permitted
- [ ] Authenticated unauthorized routes → `/app/403` (not login)
- [ ] Platform users never appear in tenant user lists
- [ ] No employee/department fields on User

## Security

- [ ] Every endpoint Policy/Gate enforced; no role-name string checks
- [ ] Cross-tenant user/role access → 404
- [ ] Cannot attach foreign-tenant role IDs
- [ ] Cannot assign `platform_tenants.*` to tenant roles
- [ ] Self-escalation blocked (subset + Owner assign rules)
- [ ] Last Tenant Owner protected (roles + disable)
- [ ] Self-disable forbidden
- [ ] No hard-delete user endpoint
- [ ] Passwords/hashes/tokens never in audit or API resources
- [ ] `tenant_id` never accepted from request body

## Audit

- [ ] USER_* and ROLE_* events listed in BUSINESS_RULES emitted with actor, tenant, correlation ID, before/after

## Quality gates

- [ ] Pest green (including cross-tenant attack suite)
- [ ] Vitest green
- [ ] Pint / `tsc` / frontend build green
- [ ] MySQL migrations verified
- [ ] Module docs updated to Implemented only after code lands

## Explicit non-goals (must remain undone)

- [ ] No Spatie package
- [ ] No direct user permissions
- [ ] No full platform tenant admin UI (unless separately approved)
- [ ] No employee module fields
