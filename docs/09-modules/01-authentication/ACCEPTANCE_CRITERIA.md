# Authentication — Acceptance Criteria

> **Status:** Approved Definition of Done for Sprint 005 (specification)
> **Last updated:** 2026-08-08

A feature is not Done until it also satisfies [DEFINITION_OF_DONE.md](../../00-project/DEFINITION_OF_DONE.md).

## Backend

- [ ] `users.status` migration applied (`active` / `disabled`); User model casts and helpers exist.
- [ ] `POST /api/v1/auth/login` follows the documented gate order; **no session before gates pass**.
- [ ] Valid active tenant user can log in and receives `AuthUserResource` + session cookie.
- [ ] Valid active platform user can log in; `tenant` is null; no tenant business data access implied.
- [ ] Invalid credentials (unknown email and wrong password) return identical `401 AUTH_INVALID_CREDENTIALS`.
- [ ] Email lookup is case-normalized.
- [ ] Disabled user cannot log in (`403 AUTH_ACCOUNT_DISABLED`); existing sessions rejected on next protected request.
- [ ] Pending / suspended / archived tenant users cannot establish a session; stable tenancy codes returned.
- [ ] Login rate limit: 5/min per normalized email + IP; success clears limiter; `429 AUTH_TOO_MANY_ATTEMPTS`.
- [ ] Session regenerated on login; invalidated on logout.
- [ ] Remember-me accepted and does not bypass status gates.
- [ ] `GET /api/v1/auth/me` returns auth-safe profile only (no password, tokens, fabricated permissions).
- [ ] `POST /api/v1/auth/logout` invalidates the current session.
- [ ] Forgot-password: outward-identical success for existing and unknown emails; rate limited; mail queued/sent with frontend reset URL.
- [ ] Reset-password: policy enforced; token one-time + expiry; other sessions revoked where practical; audit recorded.
- [ ] Audit events emitted per [BUSINESS_RULES.md](BUSINESS_RULES.md); **no secrets** in audit/log payloads.
- [ ] Correlation ID present on audited auth records (middleware introduced if missing).
- [ ] Pest matrix in [TEST_PLAN.md](TEST_PLAN.md) green; Pint green.

## Frontend

- [ ] Guest pages: `/login`, `/forgot-password`, `/reset-password` — Arabic RTL, light theme, accessible.
- [ ] CSRF bootstrap before mutating auth calls; `credentials: 'include'`; no localStorage tokens.
- [ ] Current user owned by TanStack Query; no duplicated Pinia current-user store without documented reason.
- [ ] Route guards: guest ↔ authenticated; loading `/auth/me` before decide; intended redirect safe; no loops.
- [ ] Tenant/account block codes clear session query and show blocked UX without retry storms.
- [ ] Authenticated shell: RTL right sidebar + header; logout; temporary home **without** fake KPIs.
- [ ] Vitest matrix in [TEST_PLAN.md](TEST_PLAN.md) green; `vue-tsc` / type-check green.

## Explicitly not required for Sprint 005 Done

- RBAC / permissions on `/auth/me`
- Email verification
- MFA
- `logout-all`
- Dashboard widgets
- Full Audit Trail viewing UI
