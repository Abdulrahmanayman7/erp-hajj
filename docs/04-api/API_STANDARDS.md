# API Standards

> **Status:** Approved
> **Last updated:** 2026-08-09

Domain error codes for Organization Structure (`ORGANIZATION_UNIT_*`) and Employees (`EMPLOYEE_*`, `POSITION_*`) are listed in their module `API.md` files.

## Purpose

Define the standards for the REST API consumed by the web frontend and, in future phases, mobile applications. Planned endpoints per module are documented in each module's `API.md` under [docs/09-modules/](../09-modules/).

## Decisions

- **REST** over HTTPS, JSON only, base path **`/api/v1`**.
- Authentication via **Laravel Sanctum**.
- Responses via **API Resources** and the standardized envelope below.
- The API is the single interface for all clients; no privileged web-only paths.

## Resource Naming

Plural REST resources with standard verbs:

```text
GET    /api/v1/users            # list (pagination, search, filters, sorting)
POST   /api/v1/users            # create
GET    /api/v1/users/{user}     # show
PATCH  /api/v1/users/{user}     # update
DELETE /api/v1/users/{user}     # delete
```

### Action endpoints for controlled transitions

Workflow transitions use explicit action endpoints (policy-gated, audited):

```text
POST /api/v1/contracts/{contract}/review
POST /api/v1/contracts/{contract}/approve
POST /api/v1/contracts/{contract}/sign
POST /api/v1/contracts/{contract}/close
POST /api/v1/decisions/{decision}/approve
POST /api/v1/tasks/{task}/complete
```

## Response Envelope (decided)

### Success

```json
{
  "success": true,
  "message": "Human-readable message",
  "data": {},
  "meta": {}
}
```

### Error

```json
{
  "success": false,
  "message": "Human-readable error message",
  "errors": {},
  "code": "MACHINE_READABLE_CODE"
}
```

- `errors` holds validation errors keyed by field for `422` responses.
- `meta` holds pagination and collection metadata.
- Machine-readable `code` values grow with modules. Auth + tenancy catalogs: [01-authentication/API.md](../09-modules/01-authentication/API.md), [00-tenancy/API.md](../09-modules/00-tenancy/API.md).

## Conventions

- **Pagination** on all list endpoints (defaults/max page size: TBD); **search, filters, and sorting** supported via query parameters.
- **ISO 8601** dates everywhere.
- **Correct HTTP status codes**: `200/201` success, `401` unauthenticated, `403` unauthorized (`AUTHORIZATION_DENIED` for missing `module.action`), `404` not found, `422` validation / business rule codes (e.g. `USER_LAST_OWNER_PROTECTED`). A resource belonging to another tenant returns **`404`, never `403`**. Module-stable codes for RBAC: [02-users-and-authorization/API.md](../09-modules/02-users-and-authorization/API.md).
- **Idempotency for sensitive actions where needed** (e.g. workflow transitions): mechanism TBD.
- Arabic text must round-trip correctly (UTF-8 everywhere).

## Rules

- No **authorization-gated** business endpoint ships without a Policy/Gate check and Form Request validation. Guest authentication endpoints (login, forgot/reset password) are identity endpoints without `module.action` policies — they still use Form Requests and rate limiting ([01-authentication/PERMISSIONS.md](../09-modules/01-authentication/PERMISSIONS.md)).
- No breaking change inside `v1`; breaking changes require a new version.
- Errors must be secure: no stack traces or internal details in production responses.

## Decisions

- **Sanctum mode for the SPA: cookie session** (HttpOnly cookie + CSRF protection via `sanctum/csrf-cookie`). `statefulApi()` middleware is enabled; CORS allows only the SPA origin with credentials.
- The standardized envelope is implemented by `App\Core\Shared\ApiResponse`; the first live endpoint is `GET /api/v1/health`.

## Auth rate limiting (decided — Sprint 005)

- Login and password-reset endpoints: **5 requests / minute**, keyed primarily by **normalized email + IP** (see [01-authentication/API.md](../09-modules/01-authentication/API.md)).
- Exceeding the limit returns `429` with `AUTH_TOO_MANY_ATTEMPTS`.

## TBD

- OpenAPI documentation tooling: TBD.
- Global list pagination defaults/max: TBD.
- Idempotency mechanism for workflow transitions: TBD.
