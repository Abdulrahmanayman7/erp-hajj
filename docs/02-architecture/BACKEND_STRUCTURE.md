# Backend Structure

> **Status:** Approved — backend scaffolded; Tenant Foundation core implemented (Sprint 004); Authentication specified for Sprint 005 — **not implemented**
> **Last updated:** 2026-08-08

## Purpose

Define the structure of the Laravel 12 backend so both developers organize code identically. The skeleton is scaffolded; module folders are created as modules are implemented.

## Stack

PHP 8.2+, Laravel 12, Sanctum, MySQL, Redis when required, Laravel Queue, REST API under `/api/v1`, Policies and Gates, Form Requests, API Resources, Events and Jobs only when justified.

## Conceptual Structure

```text
app/
├── Core/
│   ├── Tenancy/        # tenant resolution, automatic scoping, tenant context for jobs/cache (Sprint 004)
│   ├── Auth/           # Sanctum SPA session auth, login/logout/me/password-reset Actions (Sprint 005)
│   ├── Audit/          # audit recording used by all modules (minimal recorder may ship with auth)
│   ├── Shared/         # base classes, standardized API response envelope
│   └── Support/        # helpers, cross-cutting utilities
├── Modules/
│   ├── Organizations/          # tenant management (platform level)
│   ├── Users/
│   ├── Authorization/          # roles, permissions
│   ├── OrganizationStructure/
│   ├── Employees/              # includes supervisor classification
│   ├── Contracts/
│   ├── Meetings/
│   ├── Decisions/
│   ├── Tasks/
│   ├── Documents/
│   ├── Warehouses/
│   ├── Inventory/
│   ├── Assets/
│   ├── Custodies/
│   ├── Notifications/
│   └── Dashboard/
```

## Module Contents

A module contains **only what it needs** — do not force every folder into every module:

Models, Actions, Services, Policies, Requests (Form Requests), Resources (API Resources), Controllers, Events, Listeners, Jobs, Queries, DTOs where justified, Tests, Routes.

## Controller Rules

Controllers must:

- Receive validated input (Form Request).
- Call an Action or application service.
- Return an API Resource or the standardized response envelope.

Controllers must not:

- Contain workflows.
- Perform large queries.
- Handle storage directly.
- Perform manual permission logic.
- Build complex response arrays.

## Other Rules

- Repository pattern **only where it provides real value** — no blanket repositories.
- Every tenant-owned model uses `Core/Tenancy` automatic scoping.
- Every critical operation records audit via `Core/Audit`.
- Modules must not bypass permissions or audit logging, and must not reach into other modules' internals (communicate via services/events).

## Decisions Made at Scaffolding

- **Routes:** single `routes/api.php` with a `Route::prefix('v1')->name('api.v1.')` group for now; per-module route files will be introduced when the first business modules are implemented.
- **PSR-4:** everything lives under the default `App\` namespace — `App\Core\...` and `App\Modules\...` (e.g. `App\Core\Shared\ApiResponse`, `App\Core\Health\HealthController`). No extra service-provider wiring until a module needs it.
- **Testing:** Pest replaces PHPUnit-style classes (`tests/Pest.php` binds `Tests\TestCase` to `tests/Feature`).
- **Sanctum:** SPA cookie authentication — `statefulApi()` middleware enabled in `bootstrap/app.php`; `FRONTEND_URL` + `SANCTUM_STATEFUL_DOMAINS` configured; CORS allows only the SPA origin with credentials.

### Auth module placement (Sprint 005)

- HTTP surface: `/api/v1/auth/*` (see [01-authentication/API.md](../09-modules/01-authentication/API.md)).
- Prefer `App\Core\Auth\` for session/login Actions, middleware (`EnsureUserIsActive`), Resources, and Form Requests — authentication is platform plumbing, not a tenant business module.
- Reuse `Core\Tenancy` for tenant gates; do not duplicate tenant resolution inside login beyond the documented login-time checks.
