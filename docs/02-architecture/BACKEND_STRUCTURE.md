# Backend Structure

> **Status:** Approved (conceptual — Laravel is not installed yet)
> **Last updated:** 2026-08-06

## Purpose

Define the conceptual structure of the Laravel 12 backend so both developers organize code identically. It becomes binding when the backend is scaffolded.

## Stack

PHP 8.2+, Laravel 12, Sanctum, MySQL, Redis when required, Laravel Queue, REST API under `/api/v1`, Policies and Gates, Form Requests, API Resources, Events and Jobs only when justified.

## Conceptual Structure

```text
app/
├── Core/
│   ├── Tenancy/        # tenant resolution, automatic scoping, tenant context for jobs/cache
│   ├── Auth/           # Sanctum setup, authentication plumbing
│   ├── Audit/          # audit recording used by all modules
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

## TBD

- Route file organization (single `api_v1.php` vs. per-module route files): TBD at scaffolding.
- PSR-4 namespace details and service provider wiring: TBD at scaffolding.
