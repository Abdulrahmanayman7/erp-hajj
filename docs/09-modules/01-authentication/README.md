# Module: Authentication (المصادقة)

> **Status:** Specification complete — implementation pending. **No authentication behavior is implemented.**
> **Last updated:** 2026-08-08
> **Sprint:** 005

## Purpose

Authenticate users of the ERP Hajj SPA via **Laravel Sanctum session (cookie) authentication**, enforce account and tenant lifecycle gates before a session is established, expose a current-user profile for the frontend shell, and support email-based password reset. Authentication events are audited. This sprint is the first vertical slice: backend auth API + frontend auth UI + route guards + session/CSRF handling.

## Scope (Sprint 005)

| Area | In scope |
|---|---|
| Backend | `POST/GET` auth endpoints under `/api/v1/auth`, Sanctum CSRF cookie flow, session login/logout, `/auth/me`, forgot/reset password |
| Frontend | Login, forgot-password, reset-password pages; guest/auth route guards; authenticated app shell (no dashboard widgets); TanStack Query current-user state |
| Security | Rate limiting, generic credential errors, account status, tenant lifecycle integration, password policy, audit events |
| Tenancy | Reuse Tenant Foundation middleware and stable codes; no redesign |

## Explicitly out of scope (Sprint 005)

- **RBAC** (roles, permissions, Permission Guard data on `/auth/me`) — next dependency after auth
- **Email verification** — not in Sprint 005
- **MFA / 2FA** — future security enhancement only (Change Request)
- **SSO / social login / username / phone login** — future
- **Bearer / Personal Access Token login for the web app** — future mobile/API clients
- **Self-registration / signup** — never in MVP without Change Request
- **`POST /api/v1/auth/logout-all`** — future (not required for MVP)
- **Real dashboard KPIs / business widgets** — Dashboard module
- **Full Audit Trail UI / retention** — Auth writes audit events via `Core/Audit`; viewing/export is the Audit module

## Specification map

| Concern | Document |
|---|---|
| Business rules, login order, account status, tenant gates, session, password policy | [BUSINESS_RULES.md](BUSINESS_RULES.md) |
| Endpoint contracts, envelopes, error codes | [API.md](API.md) |
| User status column, password-reset tokens, sessions | [DATA_MODEL.md](DATA_MODEL.md) |
| Auth is identity, not authorization | [PERMISSIONS.md](PERMISSIONS.md) |
| Login / forgot / reset pages, shell, guards, CSRF | [UI.md](UI.md) |
| Definition of Done for Sprint 005 | [ACCEPTANCE_CRITERIA.md](ACCEPTANCE_CRITERIA.md) |
| Pest + Vitest matrices | [TEST_PLAN.md](TEST_PLAN.md) |
| Sanctum SPA + CSRF baseline | [SECURITY_BASELINE.md](../../06-security/SECURITY_BASELINE.md) |
| Tenant lifecycle codes | [00-tenancy/API.md](../00-tenancy/API.md) · [MULTI_TENANCY.md](../../02-architecture/MULTI_TENANCY.md) |

## Implementation order (recommended within Sprint 005)

1. **Data:** `users.status` migration (`active`/`disabled`) + model cast/helpers.
2. **Backend auth API:** Form Requests → Login/Logout/Me/Forgot/Reset Actions → Resources → routes under `/api/v1/auth` + rate limiters.
3. **Core wiring:** ensure session config (lifetime, remember), Sanctum stateful domains, CSRF; thin `Core/Audit` recorder if not yet present; correlation ID on audited records.
4. **Frontend:** `modules/auth` (api, pages, mutations, queries, validation, routes) + router guards + CSRF bootstrap in shared HTTP client.
5. **Shell:** authenticated layout entry after login (welcome / system status only — no fake KPIs).
6. **Tests:** Pest matrix then Vitest matrix; `pint` / type-check / CI green.

## Dependencies

| Depends on | Status |
|---|---|
| Tenant Foundation (contexts, middleware, status codes) | Implemented (Sprint 004) |
| Sanctum SPA scaffolding (`statefulApi`, CORS, `credentials: include`) | Scaffolded |
| Mail transport for reset emails | Environment (`MAIL_*`); local may use `log`/`array` |
| `Core/Audit` append path | Minimal recorder may ship with this sprint if Audit module not yet implemented |
| Correlation ID middleware | Specified in [AUDIT_TRAIL.md](../../06-security/AUDIT_TRAIL.md); introduce with auth auditing if absent |

## References

- [SECURITY_BASELINE.md](../../06-security/SECURITY_BASELINE.md) · [AUDIT_TRAIL.md](../../06-security/AUDIT_TRAIL.md) · [API_STANDARDS.md](../../04-api/API_STANDARDS.md) · [MULTI_TENANCY.md](../../02-architecture/MULTI_TENANCY.md) · [DESIGN_GUIDELINES.md](../../05-ui-ux/DESIGN_GUIDELINES.md) · [FRONTEND_STRUCTURE.md](../../02-architecture/FRONTEND_STRUCTURE.md) · [DEFINITION_OF_DONE.md](../../00-project/DEFINITION_OF_DONE.md)
