# Authentication — Acceptance Criteria

> **Status:** Sprint 005 acceptance — implementation complete (checklist below reflects shipped work)
> **Last updated:** 2026-08-08

A feature is not Done until it also satisfies [DEFINITION_OF_DONE.md](../../00-project/DEFINITION_OF_DONE.md).

## Backend

- [x] `users.status` migration applied (`active` / `disabled`); User model casts and helpers exist.
- [x] `POST /api/v1/auth/login` follows the documented gate order; **no session before gates pass**.
- [x] Valid active tenant user can log in and receives `AuthUserResource` + session cookie.
- [x] Valid active platform user can log in; `tenant` is null; no tenant business data access implied.
- [x] Invalid credentials (unknown email and wrong password) return identical `401 AUTH_INVALID_CREDENTIALS`.
- [x] Email lookup is case-normalized.
- [x] Disabled user cannot log in (`403 AUTH_ACCOUNT_DISABLED`); existing sessions rejected on next protected request.
- [x] Pending / suspended / archived tenant users cannot establish a session; stable tenancy codes returned.
- [x] Login rate limit: 5/min per normalized email + IP; success clears limiter; `429 AUTH_TOO_MANY_ATTEMPTS`.
- [x] Session regenerated on login; invalidated on logout.
- [x] Remember-me accepted and does not bypass status gates.
- [x] `GET /api/v1/auth/me` returns auth-safe profile only (no password, tokens, fabricated permissions).
- [x] `POST /api/v1/auth/logout` invalidates the current session.
- [x] Forgot-password: outward-identical success for existing and unknown emails; rate limited; mail queued/sent with frontend reset URL.
- [x] Reset-password: policy enforced; token one-time + expiry; other sessions revoked where practical; security event recorded.
- [x] Security events emitted per [BUSINESS_RULES.md](BUSINESS_RULES.md); **no secrets** in payloads.
- [x] Correlation ID middleware present on API requests.
- [x] Pest matrix green; Pint green.

## Frontend

- [x] Guest pages: `/login`, `/forgot-password`, `/reset-password` — Arabic RTL, light theme, accessible.
- [x] CSRF bootstrap before mutating auth calls; `credentials: 'include'`; no localStorage tokens.
- [x] Current user owned by TanStack Query; no duplicated Pinia current-user store.
- [x] Route guards: guest ↔ authenticated; loading `/auth/me` before decide; intended redirect safe; no loops.
- [x] Tenant/account block codes clear session query and show blocked UX without retry storms.
- [x] Authenticated shell: RTL right sidebar + header; logout; temporary home **without** fake KPIs.
- [x] Vitest matrix green; `vue-tsc` / type-check / build green.

## Explicitly not required for Sprint 005 Done

- RBAC / permissions on `/auth/me`
- Email verification
- MFA
- `logout-all`
- Dashboard widgets
- Full Audit Trail viewing UI
