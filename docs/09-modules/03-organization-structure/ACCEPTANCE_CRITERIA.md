# Organizational Structure — Acceptance Criteria

> **Status:** Approved for Sprint 007 specification
> **Last updated:** 2026-08-09

## Hierarchy & entities

- [ ] Units stored in single `organization_units` table with adjacency-list `parent_id`.
- [ ] Types `department` | `section` | `unit` supported as fixed enum.
- [ ] Root depth = 0; nested units creatable; MVP application max depth 8 enforced via a single centralized constant/config (not a DB limit).
- [ ] Create/move exceeding max depth → `ORGANIZATION_UNIT_DEPTH_EXCEEDED`.
- [ ] Circular parent references impossible (API rejects).
- [ ] Cross-tenant parent/manager ids cannot create or mutate foreign data.

## Lifecycle & codes

- [ ] Status `active` / `inactive` with activate/deactivate endpoints; prefer deactivate to retire units.
- [ ] Code required, tenant-unique, immutable after create.
- [ ] Hard delete only when delete policy fully satisfied (no children; no employees/business/history refs when those exist; `organization_units.delete`; tenant isolation).
- [ ] Delete blocked when unit has children (`ORGANIZATION_UNIT_HAS_CHILDREN`).
- [ ] Deactivate does not cascade-deactivate children.
- [ ] No soft deletes.

## Manager

- [ ] Optional single `manager_user_id` = **unit responsible manager** (not employee line/HR supervisor).
- [ ] Must be same-tenant active user on assign; clearing allowed; disabled existing manager does not auto-remove.
- [ ] Unit manager assignment does not grant RBAC permissions.

## Deferred correctly

- [ ] No positions API/UI/tables in this sprint.
- [ ] No user↔unit membership; User model and `/auth/me` unchanged for org fields.

## Security & tenancy

- [ ] All queries tenant-scoped via `UsesTenantScope`; fail closed without context.
- [ ] Cross-tenant access → `404`.
- [ ] `tenant_id` not mass-assignable from requests.
- [ ] Policies enforce `organization_units.*` regardless of UI.

## API & audit

- [ ] Endpoints match [API.md](API.md); tree via `GET …?view=tree`.
- [ ] Audit events emitted per [BUSINESS_RULES.md](BUSINESS_RULES.md).

## UI

- [ ] `/app/organization` RTL page: tree + details, search, filters, drawer create/edit.
- [ ] Confirmations for deactivate / move / manager replace / delete via `AppConfirmDialog`.
- [ ] Sidebar **الهيكل التنظيمي** gated by `organization_units.view`.

## Tests & docs

- [ ] Pest + Vitest matrices in [TEST_PLAN.md](TEST_PLAN.md) executed on implementation PR.
- [ ] Module docs remain accurate; status flips to Implemented only after DoD.
