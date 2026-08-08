# Users and Authorization — API

> **Status:** Contract approved for Sprint 006 — implementation pending
> **Base path:** `/api/v1`
> **Auth:** Sanctum SPA session + CSRF; tenant middleware stack as Authentication/Tenancy
> **Last updated:** 2026-08-08

All responses use the standard envelope ([API_STANDARDS.md](../../04-api/API_STANDARDS.md)). Cross-tenant IDs → **404** `RESOURCE_NOT_FOUND` (not 403).

---

## Authorization matrix (required permission)

| Method | Path | Permission |
|---|---|---|
| GET | `/users` | `users.view` |
| POST | `/users` | `users.create` |
| GET | `/users/{user}` | `users.view` |
| PATCH | `/users/{user}` | `users.update` |
| POST | `/users/{user}/disable` | `users.disable` |
| POST | `/users/{user}/enable` | `users.disable` |
| PUT | `/users/{user}/roles` | `users.assign_roles` |
| GET | `/roles` | `roles.view` |
| POST | `/roles` | `roles.create` |
| GET | `/roles/{role}` | `roles.view` |
| PATCH | `/roles/{role}` | `roles.update` |
| POST | `/roles/{role}/deactivate` | `roles.update` |
| POST | `/roles/{role}/activate` | `roles.update` |
| PUT | `/roles/{role}/permissions` | `roles.assign_permissions` |
| DELETE | `/roles/{role}` | `roles.delete` |
| GET | `/permissions` | `permissions.view` |

Unauthenticated → `401`. Authenticated without permission → `403` `AUTHORIZATION_DENIED`.

---

## Current user (Auth extension)

### `GET /auth/me` (existing; extend after RBAC)

Additive fields on `data.user` (or sibling `data` keys — prefer **sibling** for clarity):

```json
{
  "user": { "...existing fields..." },
  "roles": [
    { "id": 1, "code": "tenant_owner", "name": "مالك المستأجر" }
  ],
  "permissions": [
    "dashboard.view",
    "users.view",
    "users.create"
  ]
}
```

- `permissions`: sorted ascending string array; effective union of **active** roles only.
- No pivot IDs, no platform grants for tenant users.
- Platform users: tenant `roles`/`permissions` empty or omitted; platform grants via separate future fields — do not mix.

---

## Users

### `GET /users`

Query:

| Param | Notes |
|---|---|
| `search` | name / email (partial, case-insensitive) |
| `status` | `active` \| `disabled` |
| `role_id` | filter by assigned role |
| `sort` | `name` \| `email` \| `created_at` \| `status` (default **`name`**) |
| `direction` | `asc` \| `desc` (default **`asc`**) |
| `page`, `per_page` | pagination; max `per_page` per API standards |

**Default sort:** `name ASC` — stable operator scanning of Arabic names in admin lists.

Response: paginated `UserResource` with nested `roles: [{id, code, name}]`. Never includes platform users (`tenant_id` null). Eager-load roles (no N+1).

### `POST /users`

Body:

| Field | Rules |
|---|---|
| `name` | required, string |
| `email` | required, email, **globally unique** |
| `role_ids` | optional array of tenant role IDs (same tenant); validated against assign authority |
| `send_invite` | boolean, default `true` |

Behavior: create `active` user; if `send_invite` and mail works → password reset invitation; else temporary password path (logged as security-sensitive create — **never** return password in API body in production responses; implementation may return one-time `temporary_password` only when invite skipped and actor has create permission — document as high-risk; prefer invite-only when mail ready).

### `GET /users/{user}`

Same tenant only. Includes roles.

### `PATCH /users/{user}`

Body (partial): `name`, `email` (unique). **Not** status, roles, password.

### `POST /users/{user}/disable` / `enable`

Empty body or optional `{ "reason": "..." }` for audit.

Guards: last Owner; self-disable forbidden (see BUSINESS_RULES).

### `PUT /users/{user}/roles`

Body: `{ "role_ids": [1, 2, 3] }` — **full replace**. Empty array allowed only if not stripping last Owner path.

Duplicate IDs ignored. All roles must be same-tenant and active (or allow inactive only if already assigned — prefer **only active roles** for new assignments).

---

## Roles

### `GET /roles`

Query: `search` (name/code), `is_active`, `is_system`, `sort` (`name` default ASC), pagination.

Include aggregates: `users_count`, `permissions_count`.

### `POST /roles`

Body: `name`, `code` (optional auto-slug from name if omitted), `description`.

Creates `is_system=false`, `is_active=true`.

### `GET /roles/{role}`

Includes `permissions: string[]` (names) and counts.

### `PATCH /roles/{role}`

`name`, `description` only. **Not** `code`, `is_system`.

System roles: name/description editable; code immutable.

### `POST /roles/{role}/deactivate` / `activate`

Cannot deactivate last Owner role type while it would leave zero Owners (prefer block deactivating `tenant_owner` system role entirely, or block only when it would orphan Owners — **final:** system `tenant_owner` **cannot be deactivated**; other system roles may deactivate with warning).

Custom roles: deactivate allowed; assignments remain; effective perms lose inactive role.

### `PUT /roles/{role}/permissions`

Body: `{ "permission_ids": [...] }` or `{ "permissions": ["users.view", ...] }` — **full replace**.

Subset rule + reject `platform_tenants.*`.

### `DELETE /roles/{role}`

Only if: not system, zero `user_roles`, safe. Else `ROLE_IN_USE` / `ROLE_SYSTEM_PROTECTED`.

---

## Permissions

### `GET /permissions`

Query: optional `module`, `search` on name/display_name.

Response: flat list or grouped by `module` (prefer **grouped** for matrix):

```json
{
  "modules": [
    {
      "module": "users",
      "display_name": "المستخدمون",
      "permissions": [
        { "id": 1, "name": "users.view", "display_name": "عرض" }
      ]
    }
  ]
}
```

Read-only. No POST/PATCH/DELETE in tenant API.

---

## Resources (conceptual)

**UserResource:** `id`, `name`, `email`, `status`, `roles[]`, `created_at`, `updated_at` — never password hash, never tokens.

**RoleResource:** `id`, `name`, `code`, `description`, `is_system`, `is_active`, `users_count`, `permissions_count`, optional `permissions[]` on show.

**PermissionResource:** `id`, `name`, `display_name`, `module`, `description`.

---

## Error codes

| Code | HTTP | When |
|---|---|---|
| `AUTHORIZATION_DENIED` | 403 | Missing permission |
| `USER_NOT_FOUND` | 404 | Missing / cross-tenant user |
| `USER_EMAIL_TAKEN` | 422 | Duplicate email |
| `USER_DISABLED` | 403 | Target already disabled where relevant |
| `USER_LAST_OWNER_PROTECTED` | 422 | Would remove last Owner |
| `USER_SELF_DISABLE_FORBIDDEN` | 422 | Self disable |
| `USER_SELF_ROLE_ESCALATION_FORBIDDEN` | 422 | Self role grant beyond authority |
| `ROLE_NOT_FOUND` | 404 | Missing / cross-tenant role |
| `ROLE_NAME_TAKEN` | 422 | Duplicate name in tenant |
| `ROLE_CODE_TAKEN` | 422 | Duplicate code in tenant |
| `ROLE_SYSTEM_PROTECTED` | 422 | Illegal system mutation |
| `ROLE_IN_USE` | 422 | Delete blocked |
| `ROLE_INACTIVE` | 422 | Assign inactive role |
| `ROLE_LAST_OWNER_PROTECTED` | 422 | Owner role lifecycle block |
| `PERMISSION_NOT_FOUND` | 422 | Unknown permission id/name |
| `PERMISSION_ASSIGNMENT_FORBIDDEN` | 422 | Subset rule / platform perm / not grantable |
| `VALIDATION_ERROR` | 422 | Field errors |

Cross-tenant: prefer unified `RESOURCE_NOT_FOUND` / entity `*_NOT_FOUND` with **404** — never leak tenancy.

---

## Pagination & sorting

Follow [API_STANDARDS.md](../../04-api/API_STANDARDS.md). Include `meta` + `links` as established by Auth/list patterns in the codebase.
