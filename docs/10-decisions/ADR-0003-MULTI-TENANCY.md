# ADR-0003: Multi-Tenancy from Day One — Single Database, Shared Schema

> **Status:** Accepted (mechanism finalized and refined 2026-08-06 — tenant identity, four-status lifecycle, explicit contexts)
> **Last updated:** 2026-08-06

## Context

ERP Hajj serves multiple independent Hajj campaign companies (tenants) on one SaaS-ready platform. Retrofitting tenancy is costly and error-prone, and the project requires that cross-tenant access be impossible. The team is two developers who need a strategy that is operationally simple and testable.

## Decision

The platform is **multi-tenant from day one**, using:

- **Single application, single database, shared schema.**
- **`tenant_id` column on all tenant-owned tables**; platform tables omit it only with per-table justification ([DATABASE_PRINCIPLES.md](../03-database/DATABASE_PRINCIPLES.md)).
- **Automatic tenant scoping** enforced centrally in `Core/Tenancy` (global scope + trait) — never left to per-query discipline.
- **Tenant context resolved exclusively from the authenticated user**; `tenant_id` is never trusted from payloads, headers, query strings, or route parameters.
- Each tenant user belongs to one tenant in the MVP; multi-organization membership is future scope.

### Mechanism decisions (finalized with the tenancy specification)

| Decision | Choice | Rejected alternative and why |
|---|---|---|
| Tenant identity | Immutable `tenant_id` (BIGINT PK) for all relationships/scoping **plus** immutable, globally unique `tenant_code` slug (first tenant: `rafee`) for logs, exports, and future subdomains — never an authorization input | Code-as-key or code-based authorization — human-readable identifiers leak into URLs/logs and must never carry security weight. |
| Platform vs. tenant users | One `users` table with **nullable `tenant_id`** (`NULL` = platform user) | Separate `platform_users` table — doubles every auth feature (guards, resets, audit) forever for two developers. |
| Context holder | `TenantContext` scoped singleton (`set/require/clear/runAsTenant`) + narrowly scoped **`PlatformContext`** for explicit platform operations | Static/global state (unsafe under Octane/workers); a generic public bypass like `runWithoutTenancy()` — **removed**: an unrestricted escape hatch invites misuse and defeats fail-closed scoping. |
| Missing context on scoped query | **Fail closed: throw** | Returning empty rows hides bugs; returning all rows is a catastrophic leak. |
| Cross-tenant lookup | **`404`, never `403`** | `403` confirms foreign data exists — an enumeration oracle. |
| Lifecycle | Four statuses (`pending`/`active`/`suspended`/`archived`); `archived → active` forbidden; **full lockout** on non-active (login + live sessions rejected; business jobs held) | Read-only suspension — permanent read/write endpoint classification cost for an unconfirmed business need. |
| Tenant deletion | **Never hard-deleted**; `archived` (+`archived_at`) terminal status; all `tenant_id` FKs `ON DELETE RESTRICT`; no Laravel soft delete on the registry | Cascade/hard delete — one statement could destroy a company's data; soft delete — hides the registry row, making the archived tenant *less* auditable. |
| Primary keys | `BIGINT UNSIGNED AUTO_INCREMENT` | ULID/UUID — larger indexes and operational cost buy nothing, since isolation never depends on id secrecy. |
| Model mechanism | `TenantOwned` contract + `UsesTenantScope` trait (force-set, immutable `tenant_id`, fail-closed) | A `BelongsTo*`-style name — confusable with a plain Eloquent relationship. |
| Propagation | Server-generated tenant metadata in job payloads + job middleware with `finally` cleanup; shared `tenant:{id}:` cache namespace helper; `tenants/{id}/...` private storage paths; mandatory correlation ID | Ad-hoc per-feature handling — exactly the per-query discipline this ADR forbids. |
| Platform access to tenant data | Exceptional, read-only, behind `platform_tenants.access_data`, via `runAsTenant()` inside `PlatformContext`, always audited into the target tenant's trail | Implicit Super Admin access — violates the project's governance requirement outright. |

Schema-per-tenant and database-per-tenant approaches remain rejected for the MVP.

## Consequences

### Positive

- One migration path and one operational surface — simplest for a two-developer team.
- Isolation is centrally enforced, fail-closed, and cheap to test per module (shared two-tenant fixtures).
- SaaS-ready: tenant provisioning is a data operation, not an infrastructure operation.

### Negative / Accepted Trade-offs

- Isolation depends on application-layer enforcement — hence fail-closed scoping, the forbidden-bypass rule, and the mandatory cross-tenant attack test suite ([00-tenancy/TEST_PLAN.md](../09-modules/00-tenancy/TEST_PLAN.md)).
- Raw SQL is outside the scope's protection — mitigated by review checklist, not by tooling.
- Very large tenants share database resources; partitioning would need a future ADR.
- Unique constraints and indexes must consistently include `tenant_id`; a global unique on business identifiers is both a bug and an information leak.
- A nullable `tenant_id` on `users` concentrates risk in the null check — contained by making exactly two components (Resolver, platform middleware) responsible for it.

## Notes

- Full implementation specification: [MULTI_TENANCY.md](../02-architecture/MULTI_TENANCY.md) and [docs/09-modules/00-tenancy/](../09-modules/00-tenancy/).
