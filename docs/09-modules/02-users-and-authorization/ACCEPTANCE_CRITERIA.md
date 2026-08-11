# Users and Authorization — Acceptance Criteria

> **Status:** Sprint 006 — **Implemented**
> **Last updated:** 2026-08-08

## Functional

- [x] Tenant users can be listed, viewed, created, updated, enabled, disabled within tenant scope
- [x] Roles are tenant-scoped, dynamic, and provisioned from templates without hardcoding `rafee`
- [x] Permissions are global catalog; tenants assign via roles only (no direct user permissions)
- [x] Multiple roles per user; effective permissions = union of active roles
- [x] User-role and role-permission assignment APIs work with full replace semantics
- [x] `/auth/me` returns `roles` + sorted `permissions`
- [x] Permission matrix UI groups by module (Arabic RTL)
- [x] Sidebar shows Users / Roles only when permitted
- [x] Authenticated unauthorized routes → `/app/403` (not login)
- [x] Platform users never appear in tenant user lists
- [x] No employee/department fields on User

## Security

- [x] Every endpoint Policy/Gate enforced; no role-name string checks
- [x] Cross-tenant user/role access → 404
- [x] Cannot attach foreign-tenant role IDs
- [x] Cannot assign `platform_tenants.*` to tenant roles
- [x] Self-escalation blocked (subset + Owner assign rules)
- [x] Last Tenant Owner protected (roles + disable)
- [x] Self-disable forbidden
- [x] No hard-delete user endpoint
- [x] Passwords/hashes/tokens never in audit or API resources
- [x] `tenant_id` never accepted from request body

## Audit

- [x] USER_* and ROLE_* events listed in BUSINESS_RULES emitted with actor, tenant, correlation ID, before/after

## Quality gates

- [x] Pest green (including cross-tenant attack suite)
- [x] Vitest green
- [x] Pint / `tsc` / frontend build green
- [x] MySQL migrations verified
- [x] Module docs updated to Implemented only after code lands

## Explicit non-goals (must remain undone)

- [x] No Spatie package
- [x] No direct user permissions
- [x] No full platform tenant admin UI (unless separately approved)
- [x] No employee module fields
