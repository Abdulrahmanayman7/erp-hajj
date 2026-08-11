# Employees and Supervisors — Acceptance Criteria

> **Status:** Implemented (Sprint 008) — acceptance verified via Pest/Vitest
> **Last updated:** 2026-08-09

## Entity & relationships

- [ ] `employees` and `positions` tenant-owned with `UsesTenantScope`.
- [ ] Employee has required primary `organization_unit_id`; optional `position_id`, `supervisor_id`, `user_id`.
- [ ] Employee number server-generated, tenant-unique, immutable.
- [ ] User link optional, unique, same-tenant, platform users rejected.
- [ ] Unit manager semantics remain separate from `supervisor_id`.

## Supervisor

- [ ] Self and cycles rejected (`EMPLOYEE_SUPERVISOR_CYCLE`).
- [ ] Inactive employees not selectable as new supervisors.
- [ ] Deactivate does not auto-clear subordinates’ `supervisor_id`.

## Lifecycle

- [ ] Status `active`/`inactive`; activate/deactivate endpoints; no DELETE employee API.
- [ ] Prefer deactivate; positions hard-delete only when unused.

## Security & tenancy

- [ ] Cross-tenant access → 404; `tenant_id` not from payload.
- [ ] Policies enforce `employees.*` / `positions.*`.
- [ ] No row-level org scoping invented.

## API & audit

- [ ] Endpoints match [API.md](API.md).
- [ ] Audit events emitted per [BUSINESS_RULES.md](BUSINESS_RULES.md).

## UI

- [ ] `/app/employees` RTL list + drawer + supervisor/user actions.
- [ ] Positions management reachable per [UI.md](UI.md).
- [ ] Sidebar **الموظفون** gated by `employees.view`.

## Tests & docs

- [ ] Pest + Vitest matrices executed on implementation PR.
- [ ] Status flips to Implemented only after DoD / security tests green.
