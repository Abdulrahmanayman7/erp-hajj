# Authentication — API (contract)

> **Status:** Implemented (Sprint 005) — endpoints live under `/api/v1/auth`
> **Last updated:** 2026-08-08

All responses use the standardized envelope in [API_STANDARDS.md](../../04-api/API_STANDARDS.md). Authentication for the SPA is **session cookie + CSRF**, not Bearer tokens.

## Endpoints

| Method | Path | Auth | Purpose |
|---|---|---|---|
| `GET` | `/sanctum/csrf-cookie` | Public | Laravel Sanctum CSRF cookie bootstrap (**not** under `/api/v1/auth`) |
| `POST` | `/api/v1/auth/login` | Guest | Establish session |
| `POST` | `/api/v1/auth/logout` | Authenticated | Invalidate current session |
| `GET` | `/api/v1/auth/me` | Authenticated | Current-user profile for the SPA |
| `POST` | `/api/v1/auth/forgot-password` | Guest | Request reset email |
| `POST` | `/api/v1/auth/reset-password` | Guest | Complete password reset |

### Future (not Sprint 005)

| Method | Path | Notes |
|---|---|---|
| `POST` | `/api/v1/auth/logout-all` | Invalidate all sessions — **future**; not required for MVP |

---

## `GET /sanctum/csrf-cookie`

- Framework infrastructure endpoint.
- SPA must call this **before** the first state-changing auth request (login, logout, forgot, reset) when no CSRF cookie is present.
- Sets `XSRF-TOKEN` (readable by JS) and session cookie; subsequent requests send `X-XSRF-TOKEN`.

---

## `POST /api/v1/auth/login`

### Request

```json
{
  "email": "user@example.com",
  "password": "********",
  "remember": false
}
```

| Field | Rules |
|---|---|
| `email` | required, email format, normalized (trim + lowercase) before lookup |
| `password` | required, string |
| `remember` | optional, boolean (default `false`) |

### Success — `200`

```json
{
  "success": true,
  "message": "تم تسجيل الدخول بنجاح",
  "data": {
    "id": 1,
    "name": "…",
    "email": "user@example.com",
    "status": "active",
    "is_platform_user": false,
    "tenant": {
      "id": 1,
      "tenant_code": "rafee",
      "name": "رفيع",
      "status": "active",
      "locale": "ar",
      "timezone": "Asia/Riyadh"
    }
  }
}
```

Platform user: `is_platform_user: true`, `tenant: null`.

**Do not include:** password, remember_token, reset tokens, internal flags.  
**RBAC (Sprint 006):** After Users & Authorization implementation, `/auth/me` **additively** includes `roles` and sorted `permissions` — see [02-users-and-authorization/API.md](../02-users-and-authorization/API.md). Until then, omit fabricated permissions.

### Errors

| Condition | HTTP | `code` |
|---|---|---|
| Validation | 422 | (field errors; optional `VALIDATION_FAILED` if project adopts a global code) |
| Unknown email / wrong password | 401 | `AUTH_INVALID_CREDENTIALS` |
| Account disabled (after valid password) | 403 | `AUTH_ACCOUNT_DISABLED` |
| Rate limited | 429 | `AUTH_TOO_MANY_ATTEMPTS` |
| Tenant pending | 403 | `TENANT_PENDING` |
| Tenant suspended | 403 | `TENANT_SUSPENDED` |
| Tenant archived | 403 | `TENANT_ARCHIVED` |
| Broken tenant FK | 403 | `TENANT_CONTEXT_INVALID` |
| CSRF / session mismatch | 419 | (Laravel; frontend treats as refresh CSRF + retry once) |

Behavior order: see [BUSINESS_RULES.md](BUSINESS_RULES.md) §4. **No session before gates pass.**

---

## `GET /api/v1/auth/me`

- Middleware: `auth:sanctum` (+ `EnsureUserIsActive`; tenant users also hit Resolve + EnsureActive on tenant-owned groups — `/auth/me` itself must work for **both** platform and active tenant users).
- Recommended middleware for `/auth/me`: `auth:sanctum`, `EnsureUserIsActive`. Tenant summary is loaded from the user's `tenant_id` when present; if tenant is non-active, return the same lifecycle `403` codes (session must not continue as “operational”).
- TenantContext-aware: when the user is a tenant user with an active tenant, context may be set by `ResolveTenantContext` for consistency.

### Success — `200`

Same `data` shape as login success (`AuthUserResource`).

### Errors

| Condition | HTTP | `code` |
|---|---|---|
| No session | 401 | `AUTH_UNAUTHENTICATED` |
| Disabled account | 403 | `AUTH_ACCOUNT_DISABLED` |
| Tenant lifecycle | 403 | `TENANT_*` codes as above |

---

## `POST /api/v1/auth/logout`

- Authenticated only (`auth:sanctum`).
- Invalidate current session; regenerate CSRF where appropriate.
- TenantContext cleared by middleware `finally` as usual.
- Audit `LOGOUT`.
- **Idempotent UX:** if already logged out, prefer `401` from auth middleware **or** a safe `200` no-op if the route is reached with a stale cookie — pick one in implementation and test it; recommended: require auth → `401` when already gone (frontend clears state either way).

### Success — `200`

```json
{
  "success": true,
  "message": "تم تسجيل الخروج بنجاح",
  "data": null
}
```

---

## `POST /api/v1/auth/forgot-password`

### Request

```json
{
  "email": "user@example.com"
}
```

### Success — `200` (always, after validation + rate limit)

Same outward message whether or not the email exists:

```json
{
  "success": true,
  "message": "إذا كان البريد مسجلاً لدينا، ستصلك تعليمات إعادة تعيين كلمة المرور.",
  "data": null,
  "code": "AUTH_PASSWORD_RESET_SENT"
}
```

Note: if the global success envelope does not include `code`, put the stable code in `meta.code` **or** keep only the message and document `AUTH_PASSWORD_RESET_SENT` as the logical outcome for tests — prefer adding optional `code` on success only if the envelope already allows it; otherwise tests assert identical message/body for existing vs unknown email.

**Recommended:** keep success envelope without inventing new top-level fields; assert byte-identical responses for existing vs unknown emails in Pest.

### Errors

| Condition | HTTP | `code` |
|---|---|---|
| Validation | 422 | field errors |
| Rate limited | 429 | `AUTH_TOO_MANY_ATTEMPTS` |

---

## `POST /api/v1/auth/reset-password`

### Request

```json
{
  "token": "...",
  "email": "user@example.com",
  "password": "********",
  "password_confirmation": "********"
}
```

| Field | Rules |
|---|---|
| `token` | required |
| `email` | required, email, normalized |
| `password` | required, password policy (§ BUSINESS_RULES) |
| `password_confirmation` | required, same as password |

### Success — `200`

```json
{
  "success": true,
  "message": "تم تحديث كلمة المرور بنجاح. يمكنك تسجيل الدخول الآن.",
  "data": null
}
```

### Errors

| Condition | HTTP | `code` |
|---|---|---|
| Validation / policy | 422 | field errors |
| Invalid token | 422 | `AUTH_PASSWORD_RESET_INVALID` |
| Expired token | 422 | `AUTH_PASSWORD_RESET_EXPIRED` |
| Rate limited | 429 | `AUTH_TOO_MANY_ATTEMPTS` |

---

## Error-code catalog (auth)

| Code | HTTP | Meaning |
|---|---|---|
| `AUTH_INVALID_CREDENTIALS` | 401 | Unknown email or wrong password (identical) |
| `AUTH_ACCOUNT_DISABLED` | 403 | Account disabled |
| `AUTH_TOO_MANY_ATTEMPTS` | 429 | Rate limited |
| `AUTH_UNAUTHENTICATED` | 401 | Missing/invalid session on protected auth routes |
| `AUTH_PASSWORD_RESET_INVALID` | 422 | Token invalid / mismatch |
| `AUTH_PASSWORD_RESET_EXPIRED` | 422 | Token expired |
| `TENANT_PENDING` | 403 | Reused from Tenancy |
| `TENANT_SUSPENDED` | 403 | Reused from Tenancy |
| `TENANT_ARCHIVED` | 403 | Reused from Tenancy |
| `TENANT_CONTEXT_INVALID` | 403 | Reused from Tenancy |
| `TENANT_CONTEXT_MISSING` | 403 | Reused — platform user on tenant-owned route |

Logical outcome (not necessarily a response `code` field): password-reset request accepted → treat as `AUTH_PASSWORD_RESET_SENT` in docs/tests.

## CSRF and credentials

- Browser client: `credentials: 'include'`.
- Do not send `Authorization: Bearer` for the SPA.
- Do not store session or CSRF tokens in `localStorage`.

## Permissions on this module

Login, logout, me, forgot, reset are **not** gated by `module.action` permissions. See [PERMISSIONS.md](PERMISSIONS.md).
