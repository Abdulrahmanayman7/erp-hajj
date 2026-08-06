# Pull Request Review Checklist

> **Status:** Approved
> **Last updated:** 2026-08-06

Used by the reviewing developer on every PR. Any unchecked blocking item = request changes. Companion documents: [ENGINEERING_PRINCIPLES.md](ENGINEERING_PRINCIPLES.md), [CODING_STANDARDS.md](CODING_STANDARDS.md), [DEFINITION_OF_DONE.md](../00-project/DEFINITION_OF_DONE.md).

## Scope

- [ ] Maps to an approved MVP module and an existing issue; no invented requirements.
- [ ] No unrelated changes bundled in; scope changes went through a Change Request.
- [ ] New TBDs are marked with why/recommendation/impact — not silently resolved.

## Architecture

- [ ] Thin controllers; use cases in Actions; validation in Form Requests; authorization in Policies/Gates; output via API Resources.
- [ ] No new abstraction (repository, DTO, event, service) without a justified need.
- [ ] Module boundaries respected — no reaching into another module's internals.

## Tenancy (blocking — zero tolerance)

- [ ] Every new tenant-owned model implements `TenantOwned` + `UsesTenantScope`; every new tenant-owned table has `tenant_id NOT NULL` FK `ON DELETE RESTRICT`.
- [ ] No `tenant_id` (or `tenant_code`) read from client input anywhere.
- [ ] No unscoped query against tenant-owned tables; **any raw SQL includes an explicit tenant predicate** — flag every raw query.
- [ ] No new tenancy bypass: only `runAsTenant()` / `PlatformContext`; any use of either is justified in the PR description.
- [ ] Cache access via the tenant namespace helper; storage paths via TenantContext; jobs carry tenant metadata.
- [ ] Cross-tenant lookups return `404`; lifecycle blocks return `403` with stable codes.

## Security

- [ ] Deny by default: every new endpoint has a Policy check and Form Request.
- [ ] No secrets, tokens, or credentials in code, config defaults, logs, or audit values.
- [ ] Private files on private disks; downloads authorized; signed URLs don't bypass policy.
- [ ] Audit events added for critical operations (with correlation ID); sensitive values masked.

## Database

- [ ] Migrations append-only; reversible or with a documented rollback/backup plan.
- [ ] Indexes lead with `tenant_id` on tenant-owned tables; uniques are per-tenant unless justified global.
- [ ] PR states tenant-scoping impact in the "Database changes" section.

## API

- [ ] `/api/v1`, standardized envelope, correct status codes, stable error codes documented in module `API.md`.
- [ ] List endpoints paginated; transitions via explicit action endpoints.

## Backend code

- [ ] Types everywhere; strict comparison; enums for closed sets; naming per [CODING_STANDARDS.md](CODING_STANDARDS.md).
- [ ] Transactions around multi-step state changes; no business logic in controllers or models.

## Frontend code

- [ ] No HTTP in presentation components; server state in TanStack Query; module structure respected.
- [ ] Typed props/emits; no `any`; `@/` alias used.
- [ ] Permission-gated UI (UX only) consistent with backend authorization.

## Performance

- [ ] No N+1 (eager loading verified); no `SELECT *` on large/sensitive lists; chunking for background work.
- [ ] New hot queries reviewed against indexes (`EXPLAIN` for high-volume tables).

## Testing

- [ ] Tests for new behavior: success, `422`, `401`, `403`, `404` cross-tenant where applicable.
- [ ] Cross-tenant attack tests present for tenant-owned changes; security tests not skipped/flaky.
- [ ] Tests deterministic; factories + tenant fixtures used.

## Documentation

- [ ] Module docs and CHANGELOG updated in this PR; behavior not marked implemented before it is.
- [ ] ADR added/updated for architectural changes.

## Migrations / Dependencies / Secrets / CI

- [ ] No edits to merged migrations; migration order safe.
- [ ] New dependencies justified in the PR; lockfiles updated consistently.
- [ ] No `.env` or secret files committed.
- [ ] CI green (Pint, Pest, type-check, Vitest, build); no debug statements or dead/commented-out code.

## Accessibility, Arabic and RTL

- [ ] RTL correct (logical properties only; no `ml-*`/`left-*`); sidebar/right layout unaffected.
- [ ] Arabic strings via i18n (no hardcoded UI text); terminology matches [GLOSSARY.md](../00-project/GLOSSARY.md).
- [ ] Labels, focus order, and keyboard access for new UI; screenshots attached for UI changes.
