# Accommodation Sprint 1 — Phase 2 MariaDB Integration & Security Verification

> **Date:** 2026-10-19
> **Scope:** Phase 2 services, permissions, APIs, policies, and audit. No frontend. No confirmation, occupancy, check-in, checkout, relocation, or cancellation listeners.
> **ADR-0019 status:** unchanged — **PROPOSED / NOT ACCEPTED**
> **Verdict:** **PASS**

Verification used only the disposable database `erp_hajj_testing`. The development database `erp_hajj` was never migrated, rolled back, or dropped. Its table count stayed **85** before and after every command. SQLite results from Phase 2 implementation were not treated as MariaDB proof.

## Server and database

| Item | Value |
|---|---|
| Server | MariaDB 10.4.27 (`10.4.27-MariaDB`) |
| SQL mode | `NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION` |
| `explicit_defaults_for_timestamp` | `0` |
| Host | `127.0.0.1:3306`, user `root` |
| Test database | `erp_hajj_testing` (`SELECT DATABASE()` confirmed before tests) |
| Development database | `erp_hajj` (85 tables before and after) |
| Test config | `backend/phpunit.mysql.xml` (`DB_CONNECTION=mysql`, `DB_DATABASE=erp_hajj_testing`, both `force="true"`) |
| App timezone | `UTC` (`backend/config/app.php`) |

`erp_hajj_testing` held 95 tables after Pest `RefreshDatabase` (foundation + season + transport + ten accommodation tables).

## Commands executed

1. Read-only preflight (`storage/app/acc_phase2_preflight.php`): `VERSION()`, SQL mode, schema presence, `SELECT DATABASE()` against `erp_hajj_testing` only. Aborts unless the name is `erp_hajj_testing`.
2. `php vendor/bin/pest -c phpunit.mysql.xml tests/Feature/Accommodation --compact`
3. Route middleware inspection (`storage/app/acc_phase2_route_check.php`) — no DDL.
4. `php vendor/bin/pest -c phpunit.mysql.xml tests/Feature/SeasonPilgrims/SeasonPilgrimsSchemaTest.php tests/Feature/SeasonPilgrims/SeasonTest.php tests/Feature/Transportation/TransportSchemaTest.php tests/Unit/Authorization/PermissionCatalogLocalizationTest.php --compact`
5. Schema postflight (`storage/app/acc_phase2_mariadb_checks.php`): assignment interval column types, `acc_asg_draft_ck`, table counts for `erp_hajj` and `erp_hajj_testing`.
6. `php vendor/bin/pest -c phpunit.mysql.xml tests/Feature/Authorization/BootstrapTenantOwnerTest.php tests/Feature/Tenancy/TenantScopeTest.php --compact`

No hand-run `migrate:fresh`, `migrate:rollback`, or `DROP DATABASE`. Pest `RefreshDatabase` rebuilds only `erp_hajj_testing`.

Temporary verification scripts under `backend/storage/app/acc_phase2_*.php` were removed after this report was written.

## Feature and regression test results

| Suite | Result |
|---|---|
| `tests/Feature/Accommodation` (schema + Sprint 1 API) | **17 passed** (191 assertions) |
| SeasonPilgrims schema + `SeasonTest` + Transport schema + permission localization | **45 passed** (935 assertions) |
| `BootstrapTenantOwnerTest` + `TenantScopeTest` | **23 passed** (61 assertions) |
| **Total this verification** | **85 passed** |

Accommodation Sprint 1 API coverage exercised on MariaDB includes:

- five catalog verbs with zero role-template grants, including seeded `tenant_owner`
- unauthenticated `401`
- facility and scope bootstrap without a prior facility-scope row
- facility-scope grant, duplicate live grant (`ACCOMMODATION_SCOPE_EXISTS`), foreign/platform grantee rejection (`ACCOMMODATION_GRANTEE_TENANT`), revoke, re-grant, and post-revoke `403`
- supervisor scope widening `view`/`draft` without granting manage verbs
- structure cycles and cross-facility parents (`ACCOMMODATION_STRUCTURE_CYCLE`, `ACCOMMODATION_INVALID_REFERENCE`)
- referenced facility delete blocked (`ACCOMMODATION_REFERENCED`)
- cross-tenant facility `404`
- Draft/Active allocation writes; Closed write `422` `SEASON_CLOSED`
- overlapping drafts with UTC conversion, `capacity_reserved = false`, unchanged stored capacity, prohibited `booking_status`, invalid interval, and assignment audit events

## Routes and middleware

43 Accommodation routes are registered under `api/v1/accommodation/...`. Every route gathers:

- `auth:sanctum`
- `tenant.active`
- `season.context`

No route URI or name contains confirm, check-in, check-out, relocate, extend, or occupancy. Season and tenant identifiers are taken from middleware context, not request JSON (`tenant_id` / `season_id` are prohibited on Accommodation form requests).

## Permission bootstrap findings

| Check | Result |
|---|---|
| Catalog definitions | Exactly `accommodation.view`, `manage_facilities`, `manage_season_allocation`, `draft`, `manage_scopes` |
| Role templates | Every template, including `tenant_owner`, has **zero** `accommodation.*` grants (`roleTemplates()` filters `accommodation.*` out of the owner “all” list) |
| Provisioned `tenant_owner` role | Feature test asserts `0` attached Accommodation permissions after `ProvisionDefaultTenantRoles` |
| Safe bootstrap | `manage_facilities` creates the first facility without a scope row; `manage_scopes` grants the first facility or supervisor scope; `view`/`draft` remain denied until a live scope exists |
| Owner without Accommodation verbs | Creating a facility as provisioned owner returns `403` `AUTHORIZATION_DENIED` |

This matches Module 18 `PERMISSIONS.md`: no automatic role grants; bootstrap is grant-then-view, not an implicit creator bypass.

## Scope, isolation, drafts, and deletes

| Topic | Finding |
|---|---|
| Tenant isolation | Cross-tenant facility show returns `404`. Models use `UsesTenantScope`. |
| Season isolation | Cross-season assignment show returns `404` via `SeasonOwned` binding in `ResolveSeasonContext`. Closed-season read of another season’s allocation returns `404`. |
| Facility scope | `view`/`draft` require a live facility scope or season supervisor scope. Missing same-tenant scope returns `403` `FORBIDDEN` on draft write and `AUTHORIZATION_DENIED` / empty list on reads per policy. |
| Supervisor scope | Widens visible facilities for `view`/`draft` only; does not authorize `manage_facilities` or `manage_scopes`. |
| Revoked scopes | After revoke, subsequent facility show is `403`. Revoked rows are retained (`revoked_at` set). Retry revoke returns `ACCOMMODATION_SCOPE_REVOKED`. A new live grant after revoke is allowed. |
| Closed season | Allocation create returns `422` `SEASON_CLOSED` (`SeasonGuard::assertWritable`). |
| Non-reserving drafts | Actions force `booking_status = draft`, null operational/family/checkout, `effective_ends_at = original_ends_at`. Overlaps succeed. Stored season-unit `capacity` unchanged. API rejects client `booking_status`. No confirm route or action exists. |
| UTC intervals | Requests with offsets are normalized with `Carbon::parse(...)->utc()`; responses use `...Z`. Invalid end ≤ start returns `ACCOMMODATION_INTERVAL_INVALID`. Columns are `DATETIME`, not `TIMESTAMP`. App timezone is UTC. |
| Referenced deletes | Deleting a facility that still has structures/units returns `422` `ACCOMMODATION_REFERENCED` (FK RESTRICT mapped in `AccommodationPersistence`). |
| Draft CHECK on MariaDB | `acc_asg_draft_ck` is present and requires draft-only invariants including `effective_ends_at = original_ends_at` and `original_ends_at > starts_at`. |

## Scope and concurrency review

Prefer verification over refactoring. No confirmed Sprint 1 defect was found; no code change was required.

### Revoke versus draft write

- Draft create/update/delete call `AccommodationAccess::lockAndAssert()` inside a DB transaction after `SeasonGuard::assertWritable`.
- Grant/revoke lock the scope row with `lockForUpdate()` before mutating `revoked_at`.
- Feature tests on MariaDB confirm post-revoke denial and re-grant after history retention.

**Residual risk:** `lockAndAssert()` uses `exists()` with `lockForUpdate()`. Laravel compiles this as `SELECT EXISTS (SELECT ... FOR UPDATE)`. InnoDB/MariaDB normally lock examined rows in that subquery, but a multi-process race suite was **not** executed for Accommodation. If a future engine path short-circuits locking under `EXISTS`, a TOCTOU window could open between scope check and draft insert. Mitigations already present: live-grant uniqueness via generated `active_grant`, and revoke’s row lock. A safer follow-up (out of this verification’s fix scope unless proven failing) would be `lockForUpdate()->first()` instead of `exists()`.

### Structure parent updates and concurrent cycles

- Structure create/update lock the owning facility row, then walk parents in-application (`AccommodationHierarchy::assertParent`).
- Structure update also locks the structure being edited.
- Same-facility parent requirement and cycle walk are covered by feature tests (single-process).

**Residual risk:** Concurrent cycle creation is mitigated by facility-level serialization for writes in that facility. No Accommodation multi-process concurrency suite exists (unlike Transportation’s dedicated concurrency folder). A multi-worker structure re-parent race was **not** executed safely in this pass and remains a documented limitation, not a confirmed defect.

## Audit verification

MariaDB feature tests asserted these event types on critical mutations:

- `ACCOMMODATION_FACILITY_CREATED`
- `ACCOMMODATION_FACILITY_DEACTIVATED`
- `ACCOMMODATION_FACILITY_SCOPE_GRANTED` / `ACCOMMODATION_FACILITY_SCOPE_REVOKED`
- `ACCOMMODATION_SUPERVISOR_SCOPE_GRANTED` / `ACCOMMODATION_SUPERVISOR_SCOPE_REVOKED`
- `ACCOMMODATION_POSITION_DELETED`
- `ACCOMMODATION_SEASON_UNIT_CREATED`
- `ACCOMMODATION_ASSIGNMENT_CREATED` / `ACCOMMODATION_ASSIGNMENT_DELETED`

Audit is dispatched through the existing `AuthorizationSecurity` path into the tenant audit trail. Secrets are not included in Accommodation after-values.

## Defects fixed

**None.** No confirmed Sprint 1 defect was found during this MariaDB verification. No application code, Foundation behavior, Transportation behavior, ADR status, or frontend was changed for this pass.

## Remaining risks

1. **No multi-process Accommodation concurrency suite** for revoke-vs-draft or structure re-parent races (see above). Documented limitation; not a failing test.
2. **MySQL 8.0 CI image was not run on this machine.** Local proof is MariaDB 10.4.27 via PHP’s `mysql` driver and `phpunit.mysql.xml`.
3. **Parent-cycle protection remains application-level**, serialized by facility row locks; there is no DB cycle constraint.
4. **Capacity-decrease conflict** remains unresolved and out of Sprint 1 scope.
5. **ADR-0019 remains PROPOSED / NOT ACCEPTED.** This verification does not accept the ADR and does not authorize Phase 3 or frontend work.

## Final verdict

**PASS**

Phase 2 Accommodation APIs, permissions, scopes, season guards, draft non-reservation, UTC intervals, referenced-delete protection, and audit behavior were verified against MariaDB 10.4.27 on `erp_hajj_testing`. Foundation/season/transport regression suites used for this pass passed. Development database `erp_hajj` remained untouched at 85 tables.
