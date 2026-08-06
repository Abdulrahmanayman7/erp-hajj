# Testing Strategy

> **Status:** Approved (principles binding; tooling partially TBD)
> **Last updated:** 2026-08-06

## Purpose

Define what must be tested and to what standard. Per-module test plans live in each module's `TEST_PLAN.md` under [docs/09-modules/](../09-modules/).

## Critical Rule

**Every tenant-owned module must have at least one explicit cross-tenant access test** proving tenant A cannot read or mutate tenant B's data.

## Backend Tests

- **Unit tests** — actions/services with non-trivial logic.
- **Feature API tests** — per endpoint: success, validation failure (`422`), unauthenticated (`401`), unauthorized (`403`), cross-tenant (`404`).
- **Policy tests** — permission-gated actions per the permission catalog.
- **Validation tests** — Form Request rules.
- **Workflow transition tests** — valid and invalid transitions for all four core workflows.
- **Audit tests** — critical operations produce the expected audit records; secrets never appear in audit values.
- **File authorization tests** — private files are not downloadable without permission.
- **Multi-tenant isolation tests** — mandatory per module.

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

## Environment Rules (see [ENVIRONMENTS.md](../08-deployment/ENVIRONMENTS.md))

- Isolated test database; repeatable migrations; factories for all entities; separate tenant fixtures.
- No production external dependencies; mock storage and notifications where appropriate.

## TBD

- Backend framework (Pest vs. PHPUnit): TBD at scaffolding.
- Frontend tooling (Vitest proposed) and E2E tooling (Playwright proposed): TBD.
- CI pipeline in `.github/workflows/`: TBD.
- Coverage thresholds: TBD — the required test categories above are the current gate.
