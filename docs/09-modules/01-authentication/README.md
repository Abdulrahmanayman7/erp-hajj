# Module: Authentication (المصادقة)

> **Status:** Implemented (Sprint 005). Backend API + SPA UI + guards + Pest/Vitest green. `/auth/me` includes additive `roles` + `permissions` from Sprint 006 RBAC.
> **Last updated:** 2026-08-08
> **Sprint:** 005

## Purpose

Authenticate users of the ERP Hajj SPA via **Laravel Sanctum session (cookie) authentication**, enforce account and tenant lifecycle gates before a session is established, expose a current-user profile for the frontend shell, and support email-based password reset. Authentication security events are emitted for the future Audit module.

## Implemented in Sprint 005

| Area | Status |
|---|---|
| `users.status` (`active`/`disabled`) | Done |
| `POST /api/v1/auth/login\|logout`, `GET /me`, forgot/reset | Done |
| Rate limiting, remember-me, session regenerate/invalidate | Done |
| Tenant lifecycle login + `/me` gates | Done |
| Auth security events (log sink until Audit module) | Done |
| Correlation ID middleware | Done |
| Vue login / forgot / reset / `/app` shell + guards | Done |
| Pest + Vitest matrices | Done |

## Out of scope (unchanged)

- RBAC / permissions on `/auth/me`
- Email verification, MFA, SSO, registration
- Bearer/PAT login for the web app
- `logout-all`
- Dashboard KPIs

## Specification map

| Concern | Document |
|---|---|
| Business rules | [BUSINESS_RULES.md](BUSINESS_RULES.md) |
| Endpoint contracts | [API.md](API.md) |
| Data model | [DATA_MODEL.md](DATA_MODEL.md) |
| Permissions (identity only) | [PERMISSIONS.md](PERMISSIONS.md) |
| UI / guards / CSRF | [UI.md](UI.md) |
| Acceptance | [ACCEPTANCE_CRITERIA.md](ACCEPTANCE_CRITERIA.md) |
| Tests | [TEST_PLAN.md](TEST_PLAN.md) |

## Code locations

- Backend: `backend/app/Core/Auth/`
- Frontend: `frontend/src/modules/auth/`
- Guards: `frontend/src/app/guards/authGuard.ts`
- Routes: `/login`, `/forgot-password`, `/reset-password`, `/access-blocked`, `/app`
