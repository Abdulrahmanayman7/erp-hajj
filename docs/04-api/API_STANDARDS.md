# API Standards

> **Status:** Approved
> **Last updated:** 2026-08-06

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
- Machine-readable `code` values catalog: TBD (grows with implementation).

## Conventions

- **Pagination** on all list endpoints (defaults/max page size: TBD); **search, filters, and sorting** supported via query parameters.
- **ISO 8601** dates everywhere.
- **Correct HTTP status codes**: `200/201` success, `401` unauthenticated, `403` unauthorized, `404` not found, `422` validation. A resource belonging to another tenant returns **`404`, never `403`**.
- **Idempotency for sensitive actions where needed** (e.g. workflow transitions): mechanism TBD.
- Arabic text must round-trip correctly (UTF-8 everywhere).

## Rules

- No endpoint ships without a Policy check and Form Request validation.
- No breaking change inside `v1`; breaking changes require a new version.
- Errors must be secure: no stack traces or internal details in production responses.

## Decisions

- **Sanctum mode for the SPA: cookie session** (HttpOnly cookie + CSRF protection via `sanctum/csrf-cookie`). `statefulApi()` middleware is enabled; CORS allows only the SPA origin with credentials.
- The standardized envelope is implemented by `App\Core\Shared\ApiResponse`; the first live endpoint is `GET /api/v1/health`.

## TBD

- Rate limiting values (authentication endpoints must be rate-limited — see [SECURITY_BASELINE.md](../06-security/SECURITY_BASELINE.md)): TBD.
- OpenAPI documentation tooling: TBD.
