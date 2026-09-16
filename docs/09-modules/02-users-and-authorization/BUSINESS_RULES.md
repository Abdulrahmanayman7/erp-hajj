# Users and Authorization — Business Rules

> **Status:** Approved — implementation-ready (Sprint 006)
> **Last updated:** 2026-08-08

## 1. Personas vs roles (final)

Default personas in [USERS_AND_PERSONAS.md](../../01-business/USERS_AND_PERSONAS.md) are **role templates** only. They are **not** the only roles a tenant may have.

| Concept | Meaning |
|---|---|
| Persona / template | Business label + recommended default permission set used at **tenant provisioning** |
| Role record | Tenant-owned row in `roles` with immutable `code`, editable display `name` (unless system-protected), and assigned catalog permissions |
| System role | `is_system = true` — seeded from templates; protected against deletion; limited rename/code rules |

### Template set seeded per new tenant (and for `rafee` provisioning)

| Template (Arabic) | `code` | System? | Notes |
|---|---|---|---|
| مالك المنشأة | `tenant_owner` | Yes | Protected; multiple owners allowed |
| المدير العام | `general_manager` | Yes | |
| مدير الإدارة | `department_manager` | Yes | Org-structure permissions added when that module ships |
| المشرف | `supervisor` | Yes | |
| الموظف | `employee` | Yes | Minimal until task modules exist |
| المراجع / المدقق | `auditor` | Yes | Read-oriented |
| مستخدم اطلاع فقط | `read_only` | Yes | `dashboard.view` (+ later read perms) |

Tenant admins **may create custom roles** (`is_system = false`) with any subset of the **global catalog permissions currently seeded**.

| Action | System role | Custom role |
|---|---|---|
| Rename display `name` | Allowed (code immutable) | Allowed |
| Change `code` | Forbidden | Forbidden after create |
| Deactivate | Forbidden for `tenant_owner`; allowed for other system roles only if no last-owner impact | Allowed |
| Delete | Forbidden | Only if never assigned and never used historically (see §7) |
| Duplicate (copy permissions to new custom role) | Allowed as create-from-template | Allowed |

Platform Super Admin is **not** a tenant role — platform users use the separate platform permission layer ([00-tenancy/PERMISSIONS.md](../00-tenancy/PERMISSIONS.md)).

---

## 2. Multiple roles per user (final: B)

A tenant user **may hold multiple roles** simultaneously.

| Rule | Detail |
|---|---|
| Effective permissions | **Union** of permissions from all **active** assigned roles (inactive roles contribute nothing) |
| Duplicate assignment | Unique `(tenant_id, user_id, role_id)` — DB + validation prevent duplicates |
| Revocation | Removing a role drops only that role’s permissions from the union; cache invalidated |
| Empty roles | User with zero roles authenticates but is denied all permission-gated actions (except auth endpoints) |

---

## 3. Direct user permissions (final: NO)

Permissions are obtained **only through roles**. No `user_permissions` table. Future exceptions require an ADR + Change Request.

---

## 4. Permission catalog architecture (final)

| Entity | Ownership |
|---|---|
| `permissions` | **Platform-global catalog** (no `tenant_id`) |
| `roles` | **Tenant-owned** (`TenantOwned` + `UsesTenantScope`) |
| `role_permissions` | Links tenant role → global permission |
| `user_roles` | Links tenant user → tenant role (same tenant) |

**Why global catalog (not per-tenant permission rows):** one capability vocabulary for code/Policies; no drift of `users.view` meaning across tenants; smaller sync surface; tenants cannot invent capability strings that code does not implement.

**Naming:** `module.action` — lowercase, dot-separated, immutable machine name. Display names are localized separately (i18n / `display_name`).

**Sync strategy:** idempotent **Permission Catalog Synchronizer** runs on deploy/migrate seed. It upserts only permissions for **modules that exist in code**. Unimplemented modules are **not** seeded in Sprint 006.

**Rename:** never rename `name`; change `display_name` only. **Removal:** controlled migration + review; stale `role_permissions` rows cleaned; effective cache flushed.

**Disable flag on permissions:** not in MVP — absence from catalog / not seeded is enough.

---

## 5. Platform vs tenant authorization (final)

| Actor | Authorization |
|---|---|
| Tenant user | Tenant roles → global catalog permissions; Policies under TenantContext |
| Platform user (`tenant_id` NULL) | **Separate** platform permissions only (`platform_tenants.*` — **existing** Tenancy naming; not `platform.tenants.*`) |

Rules:

- Platform users are **never** listed in tenant user APIs.
- Tenant roles are **never** assigned to platform users.
- Platform permissions are **never** grantable to tenant roles/users (assignment rejected + audited).
- Holding `platform_tenants.*` never bypasses TenantScope; exceptional data access still requires `platform_tenants.access_data` + `runAsTenant` (Tenancy docs).

Sprint 006 **does not** ship platform tenant-admin UI; it only respects the catalog and rejects cross-assignment.

---

## 6. Tenant Owner strategy (final)

- At least **one** effective Tenant Owner must exist per tenant at all times (user holding active `tenant_owner` role, account `active`).
- **Multiple** Tenant Owners allowed (lockout prevention).
- `tenant_owner` is `is_system = true`.
- Owner does **not** bypass Policies — receives an explicit permission set (see [PERMISSIONS.md](PERMISSIONS.md)).
- Cannot delete the `tenant_owner` role.
- Cannot deactivate `tenant_owner` role definition.
- Cannot remove the **last** Owner assignment.
- Cannot disable the **last** Owner user account.
- Cannot assign/remove `tenant_owner` unless the actor **already holds** `tenant_owner`.

---

## 7. Role lifecycle (final)

### Fields (conceptual)

| Field | Rules |
|---|---|
| `id` | BIGINT PK |
| `tenant_id` | NOT NULL, immutable, scoped |
| `name` | Required; **unique per tenant** |
| `code` | Required; lowercase slug; **unique per tenant**; **immutable** after create |
| `description` | Optional |
| `is_system` | Bool; seeded templates |
| `is_active` | Bool; default true |
| `created_by` | Nullable FK users (actor at create) |
| timestamps | Yes |

### Behavior

| Topic | Decision |
|---|---|
| Inactive role still assigned | Allowed; contributes **no** permissions until reactivated |
| Prefer deactivate vs delete | **Yes** once the role was ever assigned |
| Hard delete | Only custom roles never present in `user_roles` (and optionally never in audit) |
| System delete | Forbidden → `ROLE_SYSTEM_PROTECTED` |

---

## 8. Self-privilege escalation (final)

### Forbidden

- Assign self (or anyone) a role the actor is not allowed to assign
- Attach permissions to a role that the actor does not hold (**subset rule** on `roles.assign_permissions`)
- Remove last Tenant Owner / disable last Owner / delete Owner role
- Self-disable when actor is the last Owner
- Use role/user IDs from another tenant (→ `404`)
- Grant `platform_tenants.*` into tenant roles

### Grant authority (chosen simpler-safe MVP rule)

1. **`users.assign_roles`:** required to change user↔role membership. Actor may assign any **active** role in the tenant **except** `tenant_owner`, which requires the actor to already have `tenant_owner`.
2. **`roles.assign_permissions`:** required to change role↔permission. Actor may only attach permissions that are a **subset of the actor’s own effective permissions**. (Tenant Owners are seeded with the full Sprint 006 catalog, so they can manage the matrix for implemented modules.)
3. Creating/updating custom roles requires `roles.create` / `roles.update` plus the same subset rule when setting initial permissions.

This avoids a full “roles I am allowed to manage” meta-catalog while blocking self-escalation.

---

## 9. User management lifecycle (final)

User remains an **auth account** (Sprint 005 fields). Sprint 006 manages: **name, email, status, roles**. No employee fields.

| Operation | Rule |
|---|---|
| List / view | Tenant-scoped; platform users excluded |
| Create | Requires `users.create`; email globally unique (existing Auth decision) |
| Update | Name/email; email uniqueness; cannot change `tenant_id` |
| Disable / enable | `users.disable`; last-owner protection; prefer over delete |
| Assign roles | `PUT .../roles` with `users.assign_roles` |
| Delete | **Not exposed** in MVP API/UI — use disable |

### Password / invitation strategy (final)

**Primary:** On create, do **not** require admin-chosen password. System creates the user (`active`) and sends a **set-password invitation** by reusing the Authentication password-reset broker (frontend `/reset-password` URL). Admin never sees a password. Delivery uses `TenantMailConfigurationResolver`: complete tenant SMTP first, else deliverable server mailer; From identity from tenant `mail_from_*` else `config('mail.from.*')`. Transport is isolated per send (never global `Config::set` of tenant SMTP).

**Fallback (no deliverable mail / `log|array` server without tenant SMTP / send failure):** Create payload may include `temporary_password` (+ confirmation) meeting Auth password policy; stored hashed; **never** written to audit/logs; response may include a one-time `password_provisioned: true` flag **without** echoing the password. When an invite was requested but not delivered, the API also returns `invite_sent: false` with `invite_code` (`INVITE_MAILER_UNAVAILABLE` | `INVITE_SEND_FAILED`) so the UI can force the manual password path. User may still use forgot-password.

No self-registration.

### Self-service profile

Out of Sprint 006 except what Auth already provides. Admin updates cover identity; TBD later for self-service.

---

## 10. User deletion (final)

- Normal hard `DELETE /users/{user}` is **omitted**.
- `users.delete` remains reserved in the long-term catalog but is **not seeded/enforced in Sprint 006** (avoid dead permissions). Reintroduce via Change Request when PDPL/retention flows exist.
- Disable preserves audit and future FKs.

---

## 11. Authorization implementation strategy (final)

- Laravel **Policies** per resource (`UserPolicy`, `RolePolicy`); Gate abilities map to `module.action` where useful.
- **Never** `if ($user->role === 'admin')`.
- Conceptual helpers on User (tenant context assumed): `hasPermission`, `hasAnyPermission`, `hasRole` / `hasAnyRole`.
- Controllers authorize via `$this->authorize` / Policy before Actions.
- Middleware may gate coarse route groups; action-level Policy remains mandatory.
- Deny by default.

### Effective permission cache

- Key via `TenantCache`: e.g. `rbac.user.{userId}.permissions`
- Value: sorted unique permission name list (and optionally role codes)
- Invalidate on: user_roles change, role_permissions change, role `is_active` change, user disable (clear), permission catalog sync affecting attached roles
- Platform users: separate `platform:` cache namespace for platform permission sets (future platform role table if needed — Sprint 006 may grant platform perms via a simple `platform_user_permissions` **only if required**; recommended MVP: seed platform Super Admin through a dedicated platform role table **or** single bootstrap flag — see [DATA_MODEL.md](DATA_MODEL.md) TBD for platform assignment storage). For Sprint 006 tenant focus: platform permission **assignment storage** may remain minimal bootstrap; tenant RBAC is mandatory.

**Platform assignment MVP:** Document that platform Super Admin bootstrap is an idempotent seeder assigning `platform_tenants.*` through a **platform-only** `platform_roles` / `platform_user_roles` **or** a simple `platform_permissions` pivot without tenant_id — **not** mixed into tenant `roles`. Exact platform role tables: see DATA_MODEL § Platform authorization storage.

---

## 12. `/auth/me` extension (final)

After RBAC ships, `AuthUserResource` **adds** (does not remove existing fields):

```json
{
  "roles": [{ "id": 1, "code": "tenant_owner", "name": "مالك المنشأة" }],
  "permissions": ["users.view", "users.create", "roles.view"]
}
```

- Permissions: sorted ascending unique strings.
- Roles: only **active** role assignments; inactive roles omitted.
- Platform user: `roles: []`, `permissions: [...]` from platform layer (or empty until platform roles provisioned).
- Frontend invalidates `['auth','me']` after any self-affecting RBAC mutation.
- Still never returns passwords, tokens, pivots, or fabricated perms.

Compatible with Authentication: additive change; Auth module docs updated at implementation time.

---

## 13. Audit events (final)

| Event | When |
|---|---|
| `USER_CREATED` | User created |
| `USER_UPDATED` | Identity fields changed |
| `USER_ENABLED` / `USER_DISABLED` | Status transitions |
| `USER_ROLES_CHANGED` | Role set replaced; before/after role ids/codes |
| `ROLE_CREATED` / `ROLE_UPDATED` | Role metadata |
| `ROLE_ACTIVATED` / `ROLE_DEACTIVATED` | Activity flag |
| `ROLE_DELETED` | Safe hard delete |
| `ROLE_PERMISSIONS_CHANGED` | Permission set replaced; before/after names |
| `PERMISSION_CATALOG_SYNCED` | Synchronizer run (platform context; summary counts) |

Include: `tenant_id` (nullable for platform catalog sync), actor, correlation ID, before/after. **Never** passwords, hashes, reset tokens, session/CSRF ids.

---

## 14. Conflicts with existing foundation (resolved)

| Topic | Resolution |
|---|---|
| Prompt suggested `platform.tenants.*` | **Rejected** — keep existing **`platform_tenants.*`** from Tenancy module |
| `/auth/me` previously omitted permissions | **Additive extension** in Sprint 006 — Auth foundation unchanged until RBAC implements Resource update |
| Master catalog listed future module perms | **Seeding** only implemented modules; full catalog remains documentation target in PERMISSION_MODEL |
| `users.delete` in old catalog | **Not seeded / not exposed** in Sprint 006; disable replaces delete |

---

## Remaining genuine TBDs (non-blocking)

- Self-service profile editing scope (post-006).
- Whether unused-user hard delete is ever offered to Tenant Owner under PDPL.
- Platform role storage table names if more than one platform persona is needed beyond Super Admin bootstrap.
- Custody/notification permission names (unchanged from PERMISSION_MODEL).
