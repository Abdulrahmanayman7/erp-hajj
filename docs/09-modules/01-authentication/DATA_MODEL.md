# Authentication — Data Model

> **Status:** Approved for Sprint 005 — migrations **not** created in this specification task
> **Last updated:** 2026-08-08

Authentication builds on the hybrid **User** model (Tenant Foundation already added `users.tenant_id`) and Laravel’s existing auth tables from the default migration.

## Existing tables (already present)

### `users` (current columns relevant to auth)

| Column | Notes |
|---|---|
| `id` | BIGINT PK |
| `tenant_id` | NULLABLE FK → `tenants.id` (NULL = platform user) — Sprint 004 |
| `name` | Display name |
| `email` | **Globally unique** login identifier (normalized lowercase at write/login) |
| `email_verified_at` | Present in schema; **unused in Sprint 005** (verification out of scope) |
| `password` | Hashed; never exposed in Resources/audit |
| `remember_token` | Framework remember-me; never exposed |
| `created_at` / `updated_at` | Standard |

### `password_reset_tokens`

Laravel default: `email` PK, `token` (hashed at rest by framework), `created_at`. Used for forgot/reset. Expiry via `config/auth.php` passwords expire (**60 minutes** default).

### `sessions`

Laravel session driver table (when `SESSION_DRIVER=database`): supports server-side session invalidation. Recommended for production so password-reset can revoke other sessions practically. Local may use `file`/`array` in testing — document driver expectations in implementation PR.

### Sanctum

SPA cookie mode does **not** require personal access tokens for the web app. `personal_access_tokens` table may exist from scaffolding; unused by Sprint 005 login.

---

## Required migration for Sprint 005 (document only — do not create now)

### Add `users.status`

| Column | Type | Default | Notes |
|---|---|---|---|
| `status` | `VARCHAR(20)` (or string enum) | `active` | Values: `active`, `disabled`. Indexed for admin filters later |

**PHP enum (conceptual):** `App\Core\Auth\UserStatus` or `App\Models\Enums\UserStatus` — `Active`, `Disabled`.

**Rules:**

- Not mass-assignable from public auth endpoints.
- Cast on `User` model.
- Helper: `isActive(): bool` / `isDisabled(): bool`.
- Do **not** add employee lifecycle fields, roles, permissions, or soft deletes in this migration.
- Do **not** redesign `users`.

Suggested migration name (implementation time):

`YYYY_MM_DD_HHMMSS_add_status_to_users_table.php`

---

## Email normalization

- Store and look up emails in **lowercase**.
- Login and forgot-password normalize before query.
- Unique index remains global on `email` (existing decision).

## Relationships used by auth

| Relation | Usage |
|---|---|
| `User::tenant()` | Load tenant summary for `/auth/me` and login gates |
| `Tenant::users()` | Not required for auth endpoints |

No new auth-specific tables beyond `users.status`.

## What is intentionally not added

| Item | Reason |
|---|---|
| Failed-login lockout table | Rate limiter is sufficient for MVP |
| MFA columns | Out of scope |
| Password-history table | Out of scope |
| Per-device session UI tables | Out of scope (`logout-all` future) |
| Email verification workflow columns usage | Column may remain unused |

## Alignment with Users module

[02-users-and-authorization/DATA_MODEL.md](../02-users-and-authorization/DATA_MODEL.md) is aligned: login identifier = email; status = `active`/`disabled`. Authentication owns Sprint 005 auth-facing status rules; RBAC remains with the Users & Authorization module.
