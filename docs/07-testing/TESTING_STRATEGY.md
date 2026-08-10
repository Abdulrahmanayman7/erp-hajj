# Testing Strategy

> **Status:** Approved (principles binding; Pest/Vitest decided; E2E tooling still TBD)
> **Last updated:** 2026-08-09

## Purpose

Define what must be tested and to what standard. Per-module test plans live in each module's `TEST_PLAN.md` under [docs/09-modules/](../09-modules/).

## Critical Rule

**Every tenant-owned module must have at least one explicit cross-tenant access test** proving tenant A cannot read or mutate tenant B's data.

## Backend Tests

- **Unit tests** — actions/services with non-trivial logic.
- **Feature API tests** — per endpoint: success, validation failure (`422`), unauthenticated (`401`), unauthorized (`403`), cross-tenant (`404`).
- **Policy tests** — permission-gated actions per the permission catalog; RBAC matrices in [02-users-and-authorization/TEST_PLAN.md](../09-modules/02-users-and-authorization/TEST_PLAN.md) (Sprint 006 — implemented). Organization unit matrices in [03-organization-structure/TEST_PLAN.md](../09-modules/03-organization-structure/TEST_PLAN.md) (Sprint 007 — implemented). Employee/supervisor matrices in [04-employees-and-supervisors/TEST_PLAN.md](../09-modules/04-employees-and-supervisors/TEST_PLAN.md) (Sprint 008 — implemented). Contract lifecycle matrices in [05-contracts/TEST_PLAN.md](../09-modules/05-contracts/TEST_PLAN.md) (Sprint 009 — implemented). Meeting matrices in [06-meetings/TEST_PLAN.md](../09-modules/06-meetings/TEST_PLAN.md) (Sprint 010 — implemented). Decision matrices in [07-decisions/TEST_PLAN.md](../09-modules/07-decisions/TEST_PLAN.md) (Sprint 011 — specified).
- **Privilege-escalation / last-owner / self-disable tests** — mandatory for Users & Authorization.
- **Hierarchy / circular-reference tests** — mandatory for Organization Structure and employee supervisor chains when implemented.
- **Workflow transition tests** — valid and invalid paths for core workflows when implemented (Contracts: [05-contracts/TEST_PLAN.md](../09-modules/05-contracts/TEST_PLAN.md); Meetings: [06-meetings/TEST_PLAN.md](../09-modules/06-meetings/TEST_PLAN.md); Decisions: [07-decisions/TEST_PLAN.md](../09-modules/07-decisions/TEST_PLAN.md)).
- **Validation tests** — Form Request rules.
- **Audit tests** — critical operations produce the expected audit records; secrets never appear in audit values.
- **File authorization tests** — private files are not downloadable without permission.
- **Multi-tenant isolation tests** — mandatory per module. The tenancy foundation itself has a complete positive/negative/edge/security matrix in [00-tenancy/TEST_PLAN.md](../09-modules/00-tenancy/TEST_PLAN.md); business modules reuse its shared helpers (`actingAsTenantUser()`, two-tenant fixtures) and add at least one explicit cross-tenant test per endpoint group.

## Frontend Tests

- **Shared component tests** (Data Table, Form Field, Permission Guard, etc.).
- **Form validation tests.**
- **Permission visibility tests** — UI hides/disables unauthorized actions.
- **Page state tests** — loading, empty, error states.
- **Query and mutation tests** — TanStack Query hooks behavior.

## End-to-End Tests

- Authentication.
- Tenant isolation.
- User and role management.
- Contract lifecycle (draft → review → approval → signature → execution → closure/renewal).
- Meeting → Decision → Task workflow.
- Document upload and protected download.
- Inventory transaction.
- Asset custody assignment and return.

## Rules

- **Tests must be deterministic** — no time-of-day, ordering, or external-service dependence; flaky tests are fixed or quarantined with an issue, never ignored.
- **Failing or flaky security tests block merging** (cross-tenant attack suites, policy tests, file-authorization tests) — see [DEFINITION_OF_DONE.md](../00-project/DEFINITION_OF_DONE.md).
- Do not weaken or delete a failing test to make it pass — fix the code or raise the issue.

## Environment Rules (see [ENVIRONMENTS.md](../08-deployment/ENVIRONMENTS.md))

- Isolated test database; repeatable migrations; factories for all entities; separate tenant fixtures.
- No production external dependencies; mock storage and notifications where appropriate.

## Tooling (decided at scaffolding)

- **Backend: Pest** (installed; `tests/Pest.php` binds the Laravel `TestCase` to `tests/Feature`).
- **Frontend: Vitest** (installed; `npm run test`).
- **CI:** `.github/workflows/ci.yml` runs both suites on push/PR to `develop` and `main`.

## Auth testing (Sprint 005)

Full Pest and Vitest matrices live in [01-authentication/TEST_PLAN.md](../09-modules/01-authentication/TEST_PLAN.md). Mandatory themes: generic credential errors (no enumeration), rate limiting, account disabled, tenant lifecycle login blocks, session regeneration/invalidation, password-reset outward equivalence, secrets never in audit payloads, CSRF/session SPA flow, and frontend route-guard behavior without retry storms.

## TBD

- **E2E tooling** (Playwright proposed): still unresolved for full product E2E. **Recommended:** introduce Playwright after Authentication is implemented, covering login → shell → logout. **Impact:** new CI job; no effect on unit/feature suites.
- **Coverage thresholds**: unresolved because a numeric gate before several modules exist would measure scaffolding. **Recommended:** enforce category completeness now; add a line-coverage floor after Authentication + RBAC. **Impact:** CI configuration only.
