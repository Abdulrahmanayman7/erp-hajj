# Module: Users and Authorization (المستخدمون والأدوار والصلاحيات)

> **Status:** Implemented (Sprint 006). Backend API + SPA UI + Pest/Vitest green. **RBAC is live.**
> **Last updated:** 2026-08-08
> **Sprint:** 006

## Purpose

Provide tenant-scoped **user administration** and a **dynamic RBAC** system (roles + central permission catalog) that authorizes every protected business operation. Backend Policies/Gates are authoritative; frontend permission checks are UX only.

## Scope (Sprint 006)

| Area | In scope |
|---|---|
| Tenant users | List, view, create, update identity, enable/disable, assign/remove roles |
| Roles | Tenant-owned dynamic roles; create/update/activate/deactivate; safe delete when unused |
| Permissions | Global catalog (read-only in tenant UI); assign to roles via matrix |
| Authorization | Policies, effective-permission helpers, cache, extend `/auth/me` |
| Frontend | Users pages, roles pages, permission matrix, sidebar, `can()` / PermissionGuard |
| Security | Cross-tenant isolation, self-escalation guards, last Tenant Owner protection, audit events (`USER_*` / `ROLE_*` → Audit Trail ADR-0015) |

## Out of scope (Sprint 006)

- Employee / department / org-structure fields on User
- Direct user↔permission grants
- Self-registration
- Hard-delete of users in normal UI
- Full platform tenant-administration UI (platform permission catalog is defined; UI remains Tenancy/platform sprint)
- Seeding permissions for unimplemented business modules (contracts, inventory, …)
- Spatie or other RBAC packages (see architecture decision below)
- Delegation / temporary grants / time-boxed roles
- Multi-tenant membership for one user

## Specification map

| Concern | Document |
|---|---|
| Business rules, personas vs roles, Owner, escalation | [BUSINESS_RULES.md](BUSINESS_RULES.md) |
| Permission catalog (Sprint 006 slice + rules) | [PERMISSIONS.md](PERMISSIONS.md) · [PERMISSION_MODEL.md](../../06-security/PERMISSION_MODEL.md) |
| API contracts + error codes | [API.md](API.md) |
| Tables, FKs, pivots, migration order | [DATA_MODEL.md](DATA_MODEL.md) |
| Users / roles / matrix UI, nav, guards | [UI.md](UI.md) |
| Definition of Done | [ACCEPTANCE_CRITERIA.md](ACCEPTANCE_CRITERIA.md) |
| Pest + Vitest matrices | [TEST_PLAN.md](TEST_PLAN.md) |

## Implementation order (recommended)

1. Migrations: `permissions` → `roles` → `user_roles` → `role_permissions`.
2. Models + `TenantOwned` on `Role`; global `Permission`; pivot integrity.
3. Permission catalog synchronizer (idempotent seeder for **implemented** modules only).
4. Authorization helpers + Policies + TenantCache for effective permissions.
5. Users/Roles/Permissions API + Form Requests + Resources.
6. Extend `GET /api/v1/auth/me` with roles + permissions.
7. Frontend modules + sidebar + PermissionGuard + `/app/403`.
8. Provisioning for `rafee` (default role templates + first Owner) without lockout.
9. Full Pest/Vitest matrices.

## Operational: bootstrap Tenant Owner

CLI only — `php artisan tenant:bootstrap-owner` (`App\Core\Authorization\Console\BootstrapTenantOwnerCommand`).

Reuses `PermissionCatalogSynchronizer` + `ProvisionDefaultTenantRoles`. Creates or assigns an active `tenant_owner` for any tenant (example default prompt: `rafee`). No HTTP/UI surface. No default or committed credentials. See root [README.md](../../../README.md) § Bootstrap first Tenant Owner.

`RBAC_INITIAL_OWNER_EMAIL` (optional seeder hint) remains supported for assigning Owner when that user already exists; it cannot create users.

## Package decision (final)

**Hand-rolled RBAC under `App\Core\Authorization` (and module Actions)** — do **not** add `spatie/laravel-permission` in Sprint 006.

Why: the project already has strict TenantContext / fail-closed scoping; a third-party package that assumes global roles or soft tenancy would fight ADR-0003. A thin in-house layer keeps permission names, pivots, and cache under project control. Revisit via ADR only if maintenance cost proves high.

## Dependencies

| Depends on | Status |
|---|---|
| Tenant Foundation | Implemented |
| Authentication (`UserStatus`, Sanctum, `/auth/me`) | Implemented |
| `TenantCache` | Implemented — reuse for effective-permission cache |
| Mail (invite / set-password) | Same as Auth password-reset infrastructure |

## References

- [USERS_AND_PERSONAS.md](../../01-business/USERS_AND_PERSONAS.md) · [PERMISSION_MODEL.md](../../06-security/PERMISSION_MODEL.md) · [SECURITY_BASELINE.md](../../06-security/SECURITY_BASELINE.md) · [AUDIT_TRAIL.md](../../06-security/AUDIT_TRAIL.md) · [01-authentication/](../01-authentication/) · [00-tenancy/PERMISSIONS.md](../00-tenancy/PERMISSIONS.md)
