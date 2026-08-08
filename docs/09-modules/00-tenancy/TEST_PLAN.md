# Tenancy — Test Plan (complete Pest matrix)

> **Status:** Approved — implementation-ready; tests do not exist yet
> **Last updated:** 2026-08-06

Fixtures for every feature test: **two active tenants** (A, B) with parallel users and data, plus tenant P (`pending`), tenant S (`suspended`), tenant Z (`archived`), and one platform user — all factory-created. Shared helpers: `actingAsTenantUser(?Tenant $t = null)`, `actingAsPlatformUser()`, `withTenant(Tenant $t)`. The real first tenant (**رفيع** / `rafee`) is never seeded by tests.

## 1. TenantContext (unit)

| # | Case | Type | Expected |
|---|---|---|---|
| C1 | `set()` then `get()`/`id()`/`has()` | Positive | Returns the set tenant. |
| C2 | `require()` with context | Positive | Returns tenant. |
| C3 | `require()` without context | Negative | `MissingTenantContextException`. |
| C4 | `set()` twice with a different tenant | Negative | `ConflictingTenantContextException`. |
| C5 | `set()` twice with the same tenant | Edge | No exception (idempotent). |
| C6 | `clear()` empties context | Positive | `has()` false afterwards. |
| C7 | `runAsTenant()` returns closure result and restores previous context | Positive | Outer context intact after. |
| C8 | `runAsTenant()` restores context when the closure **throws** | Edge | Exception propagates; previous state restored. |
| C9 | Nested `runAsTenant()` (A → B → back) | Edge | Each level restores correctly. |

## 2. PlatformContext (unit + feature)

| # | Case | Type | Expected |
|---|---|---|---|
| PC1 | `PlatformContext::run()` from a platform entry point | Positive | Closure executes; platform state cleared after. |
| PC2 | `PlatformContext::run()` while a TenantContext is set (tenant request) | Security | `UnauthorizedPlatformContextException`. |
| PC3 | Tenant-owned model queried inside PlatformContext without `runAsTenant` | Security | `MissingTenantContextException` — platform context grants no data access. |
| PC4 | Platform operation on one tenant via `runAsTenant` inside PlatformContext | Positive | Succeeds; audit record with target tenant id. |
| PC5 | All-tenant iteration calls `runAsTenant` per tenant | Positive | Each tenant sees only its own data; contexts don't leak across iterations. |

## 3. Resolver and middleware (feature)

| # | Case | Type | Expected |
|---|---|---|---|
| R1 | Tenant user authenticated request | Positive | Context = user's tenant; cleared after response (finally). |
| R2 | Platform user authenticated request | Positive | No context set. |
| R3 | Guest on public route (`/api/v1/health`) | Positive | No context; `200`. |
| R4 | User of suspended tenant with valid session | Security | `403 TENANT_SUSPENDED`; immediate lockout. |
| R5 | User of archived tenant | Security | `403 TENANT_ARCHIVED`. |
| R6 | User of **pending** tenant | Security | `403 TENANT_PENDING`. |
| R7 | Login attempt: suspended / pending / archived tenant | Security | `403` with matching code; no session created; audited. |
| R8 | Platform user calls a tenant-owned endpoint | Negative | `403 TENANT_CONTEXT_MISSING`. |
| R9 | Tenant user calls a `/platform/*` endpoint | Negative | `403`. |
| R10 | User with dangling `tenant_id` (integrity break) | Edge | `403 TENANT_CONTEXT_INVALID` + alert log. |
| R11 | Tenant reactivated → previously blocked user retries | Edge | Succeeds; `suspended_at` cleared. |
| R12 | Context cleared even when the controller throws | Edge | Next request on same worker starts clean. |

## 4. Global scope + trait (feature)

| # | Case | Type | Expected |
|---|---|---|---|
| S1 | Tenant A lists tenant-owned records | Positive | Only A's rows. |
| S2 | Create as tenant A user | Positive | Row saved with A's `tenant_id`. |
| S3 | Create with foreign `tenant_id` in payload | Security | Value ignored/overwritten; row belongs to A. |
| S4 | Mutating `tenant_id` at the model layer | Security | Throws (immutability). |
| S5 | Query tenant-owned model with **no context, no `runAsTenant`** | Security | `MissingTenantContextException` — never returns rows. |
| S6 | `find()`/`first()` on B's id as A | Security | `null` / `ModelNotFoundException`. |
| S7 | Update/delete B's row via Eloquent as A | Security | 0 rows affected. |
| S8 | Soft-deleted rows: A cannot see B's trash (`withTrashed`) | Security | Only A's trashed rows. |
| S9 | Restore attempt on B's trashed row as A | Security | Not found; B's row stays trashed. |
| S10 | Scope qualifies `tenant_id` with table name in joins | Edge | Correct SQL, no ambiguous-column error. |
| S11 | Related creation (child of A's parent) gets A's `tenant_id` | Positive | Parent and child tenant match. |
| S12 | Factory under explicit test context | Positive | Rows carry the fixture tenant. |

## 5. Route model binding (feature)

| # | Case | Type | Expected |
|---|---|---|---|
| B1 | Valid same-tenant binding | Positive | `200`, correct envelope. |
| B2 | Cross-tenant binding (B's id as A) | Security | **`404`** (not `403`). |
| B3 | Nonexistent id | Negative | `404` — response shape identical to B2 (no oracle). |
| B4 | PATCH/DELETE on B's record id as A | Security | `404`; B's row unchanged. |
| B5 | Nested binding with foreign parent (`/parents/{B}/children/{x}`) | Security | `404`. |
| B6 | Binding with missing tenant context | Security | Fail closed (no query returns rows). |
| B7 | Archived/soft-deleted record binding | Edge | `404` by default; still tenant-scoped where trash access is explicit. |
| B8 | Platform request binding a tenant-owned model without `runAsTenant` | Security | Rejected before resolution (`403 TENANT_CONTEXT_MISSING`). |

## 6. Validation (feature)

| # | Case | Type | Expected |
|---|---|---|---|
| V1 | `TenantUnique`: same value in A and B | Positive | Both succeed. |
| V2 | `TenantUnique`: duplicate within A | Negative | `422` on the field. |
| V3 | `TenantUnique` with `ignore()` on update within A | Positive | Updating own record keeps its value; ignore stays tenant-scoped. |
| V4 | `TenantExists`: reference to B's record id (e.g. foreign `department_id`) | Security | `422` — reference smuggling blocked. |
| V5 | `tenant_id` present in payload | Security | Ignored; not in validated data; row scoped to A. |
| V6 | `tenant_code` create validation: pattern, lowercase, max 63, global uniqueness | Negative | Invalid codes (spaces, Arabic, uppercase, duplicate) → `422`. |
| V7 | `tenant_code` immutability via PATCH | Security | Attempt to change → `422`. |

## 7. Queues and scheduling (feature)

| # | Case | Type | Expected |
|---|---|---|---|
| Q1 | Job dispatched in A's context executes with A's context | Positive | Job sees only A's data. |
| Q2 | Job retry re-establishes the same tenant | Positive | Deterministic context from server-generated payload metadata. |
| Q3 | Tenant-requiring job dispatched with no context | Negative | Fails at dispatch. |
| Q4 | Business job for suspended/pending tenant | Edge | **Released** (held), not executed, not failed. |
| Q5 | Business job for archived tenant | Edge | **Cancelled** (logged + audited). |
| Q6 | Maintenance-classified job for suspended tenant | Edge | Executes. |
| Q7 | Context cleared in `finally` after each job (worker reuse) | Security | Next job on same worker starts clean, even after job exception. |
| Q8 | Failed job payload retains tenant identification | Positive | Re-run restores context. |
| Q9 | Scheduled all-tenant loop chunks tenants; one failure doesn't abort others | Edge | Remaining tenants processed; failure logged with correlation ID. |

## 8. Cache and storage (feature)

| # | Case | Type | Expected |
|---|---|---|---|
| K1 | Same logical key cached by A and B via the namespace helper | Security | Different values; no bleed-through. |
| K2 | Whole-tenant invalidation (version bump) | Positive | A's entries invalidated; B's intact. |
| K3 | Platform cache uses `platform:` namespace | Positive | Disjoint from tenant namespaces. |
| F1 | Upload as A stored under `tenants/{A_id}/...` | Positive | Path from context, not payload; metadata includes `tenant_id`. |
| F2 | Download of B's file record as A | Security | `404`. |
| F3 | Unauthenticated direct file URL access | Security | Denied (no public paths). |
| F4 | Signed URL does not bypass policy | Security | Signed but unauthorized request denied. |
| F5 | Temp/export files expire and are cleaned per tenant | Edge | Cleanup removes only expired files in the right namespace. |

## 9. Platform endpoints and lifecycle (feature)

| # | Case | Type | Expected |
|---|---|---|---|
| P1 | Super Admin lists/creates tenants with `platform_tenants.*` | Positive | `200`/`201`; audit record written. |
| P2 | Create with `tenant_code` `rafee`-style valid slug | Positive | Stored lowercase; globally unique. |
| P3 | Allowed transitions (pending→active, active→suspended, suspended→active, →archived) | Positive | Status + `suspended_at`/`archived_at` updated; audit with actor/old/new/reason/correlation ID/context type. |
| P4 | Forbidden transition `archived → active` (and any invalid pair) | Negative | `422 INVALID_TENANT_TRANSITION`. |
| P5 | Platform user without permission calls platform endpoints | Negative | `403`. |
| P6 | Tenant user calls platform endpoints | Security | `403`. |
| P7 | Super Admin reads tenant data **without** `platform_tenants.access_data` | Security | Denied. |
| P8 | Super Admin reads tenant data **with** permission | Positive | Allowed via `runAsTenant`; audit record carries the **target tenant's id** + correlation ID. |
| P9 | Duplicate tenant `name` or `tenant_code` on create | Negative | `422`. |

## 10. Tenant settings (feature)

| # | Case | Type | Expected |
|---|---|---|---|
| T1 | View with `tenant_settings.view` | Positive | Own tenant's settings only. |
| T2 | Update with `tenant_settings.update` | Positive | `200` + audit record with old/new values. |
| T3 | View/update without permission | Negative | `403`. |
| T4 | A's settings never visible to B | Security | Covered by S-series; asserted here explicitly. |

## 11. Correlation ID (feature)

| # | Case | Type | Expected |
|---|---|---|---|
| X1 | Request without incoming correlation ID | Positive | Secure ID generated; echoed in response header. |
| X2 | Request with valid incoming correlation ID | Positive | Accepted and propagated. |
| X3 | Request with malformed/oversized incoming ID | Security | Rejected; new secure ID generated. |
| X4 | Correlation ID present in audit records and job payloads of the request | Positive | Same ID end-to-end. |

## 12. Performance guards

| # | Case | Type | Expected |
|---|---|---|---|
| PF1 | Scoped list query uses the tenant-leading index | Performance | `EXPLAIN`/query assertion shows no cross-tenant scan. |
| PF2 | Resolver adds exactly one tenant query per request | Performance | Query-count assertion. |
| PF3 | Lazy-loading violations surface in tests | Performance | Strict mode enabled in the test environment. |

## Exit criteria

All cases green in CI. The **cross-tenant attack suite** — PC2–PC3, R4–R10, S3–S9, B2–B8, V4–V7, Q7, K1, F2–F4, P6–P8 — may never be skipped or marked flaky; a failing security test blocks merge ([DEFINITION_OF_DONE.md](../../00-project/DEFINITION_OF_DONE.md)).
