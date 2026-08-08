# Multi-Tenancy — Implementation Specification

> **Status:** Approved — core implemented (Sprint 004): contexts, resolver, middleware, scoping stack, validation rules, queue/cache/storage isolation. Pending: correlation ID middleware (Audit module), tenant-settings and platform tenant-management endpoints (RBAC module), notification/export isolation (their owning modules). See `docs/09-modules/00-tenancy/README.md` for the per-phase status.
> **Last updated:** 2026-08-06

## Purpose

This is the binding specification of the tenancy layer (`app/Core/Tenancy`). A senior backend engineer must be able to implement the whole layer from this document plus [docs/09-modules/00-tenancy/](../09-modules/00-tenancy/) without asking further questions. Strategy rationale lives in [ADR-0003](../10-decisions/ADR-0003-MULTI-TENANCY.md).

## Terminology

- **Tenant** (مستأجر) is the technical term; **Organization / Campaign Company** (منظمة / شركة حملة) is the business-facing term.
- **Platform user**: a user with `tenant_id = NULL` (Platform Super Admin, Platform Support).
- **Tenant user**: a user with a non-null `tenant_id`.
- **`tenant_id`**: immutable BIGINT primary-key reference — the **only** key used in relationships and scoping.
- **`tenant_code`**: immutable, globally unique, human-readable slug (first tenant: `rafee`) — an operational label, **never** an authorization mechanism.

## Decided Strategy (final)

- **Single application, single database, shared schema.**
- **`tenant_id` on all tenant-owned tables**; platform tables omit it only with justification in [DATABASE_PRINCIPLES.md](../03-database/DATABASE_PRINCIPLES.md).
- **One tenant per user.** Multi-organization membership is future scope.
- **Tenant context is derived exclusively from the authenticated user.** `tenant_id` never comes from any request payload, header, query string, or route parameter. Knowing a `tenant_code` grants nothing.
- **Cross-tenant access returns `404`**, never `403` — a foreign record must be indistinguishable from a nonexistent one.
- **Platform Super Admin has no implicit access to tenant business data.**
- **Everything is auditable**; every request carries a **correlation ID** (see [AUDIT_TRAIL.md](../06-security/AUDIT_TRAIL.md)).

---

## 1. Tenant Identity: `tenant_id` and `tenant_code`

| | `tenant_id` | `tenant_code` |
|---|---|---|
| Type | `BIGINT UNSIGNED` PK | `VARCHAR(63)`, globally unique, indexed |
| Mutability | Immutable | Immutable after creation |
| Used for | All foreign keys, scoping, cache prefixes, storage paths | Logs, exports, human-readable operational references, future subdomain mapping |
| Authorization role | None by itself (context comes from auth) | **None — ever.** Knowing a code must never grant access |

**`tenant_code` rules (final):** required; stored lowercase; allowed characters lowercase English letters, digits, hyphen; no spaces, no Arabic characters; validation pattern `/^[a-z0-9]+(?:-[a-z0-9]+)*$/`; max length 63 (DNS-label size, chosen so the code stays valid for future subdomain mapping); never auto-changed when the display name changes; not the primary key. **First tenant's code: `rafee`.** The platform is **not** hardcoded to `rafee` — every future tenant receives its own globally unique code.

**Expected initial provisioning values (documented only — the record is not created now):**

```text
name: رفيع
tenant_code: rafee
status: pending or active according to provisioning stage
locale: ar
timezone: Asia/Riyadh
```

## 2. Tenant Lifecycle (final)

| Status | Meaning | Tenant users can operate? |
|---|---|---|
| `pending` | Record exists; onboarding/activation incomplete | **No** — `403 TENANT_PENDING` |
| `active` | Normal operation | Yes |
| `suspended` | Temporarily blocked | **No** — `403 TENANT_SUSPENDED`; live sessions rejected on the next protected request |
| `archived` | Terminal; relationship ended; data preserved for audit and contractual obligations | **No** — `403 TENANT_ARCHIVED` |

**Allowed transitions:** `pending → active`, `pending → archived`, `active → suspended`, `active → archived`, `suspended → active`, `suspended → archived`.
**Forbidden:** `archived → active` — reversal requires a future, explicitly approved and audited exceptional recovery policy (TBD in [00-tenancy/BUSINESS_RULES.md](../09-modules/00-tenancy/BUSINESS_RULES.md)).

**Every transition records:** actor, timestamp, previous status, new status, reason, request correlation ID, and whether it ran in platform or tenant context.

Suspension is a **full lockout** (login rejected; live sessions rejected next request; queued business jobs held — §8). Why not read-only: read-only requires classifying every endpoint as read/write and testing that classification forever, for an unconfirmed business need; introducing it later is a Change Request.

---

## 3. Execution Contexts: TenantContext and PlatformContext

There is **no generic public bypass**. The previously specified `runWithoutTenancy()` concept is **removed** and replaced by two explicit contexts.

### 3.1 TenantContext (`App\Core\Tenancy\TenantContext`)

The single in-memory holder of "which tenant is this unit of work executing for". Business modules consume **only** this — they never resolve tenants themselves.

**Storage:** container **scoped singleton** — fresh per request and per queued job (Octane- and worker-safe). Never static, never in session/cache, never persisted.

**Public API:**

| Method | Behavior |
|---|---|
| `set(Tenant $tenant): void` | Sets context. Throws `ConflictingTenantContextException` if a **different** tenant is already set (idempotent for the same tenant). |
| `get(): ?Tenant` / `id(): ?int` / `has(): bool` | Read access. |
| `require(): Tenant` | Returns tenant or throws `MissingTenantContextException`. |
| `clear(): void` | Empties context (middleware/job middleware `finally`). |
| `runAsTenant(Tenant $tenant, Closure $fn): mixed` | Runs the closure in the given tenant's context and **restores the previous state — including on exception**. The only sanctioned context switch; used by scheduled per-tenant work and audited platform operations on one target tenant. |

**Lifecycle:** empty at request start → set by `ResolveTenantContext` middleware after authentication → **cleared in the middleware's `finally` block**. Jobs: set by job middleware before `handle()`, cleared in `finally`. Console: empty by default; tenant-owned work only inside `runAsTenant`. Tests/factories: set context explicitly via helpers (`actingAsTenantUser()`, `withTenant()`).

### 3.2 PlatformContext (`App\Core\Tenancy\PlatformContext`)

Represents an **explicitly platform-level operation** (tenant registry management, platform monitoring, per-tenant iteration drivers).

- Entered only through an internal, deliberate entry point (`PlatformContext::run(Closure $fn)`), available **only** to: the platform route middleware group (`/api/v1/platform/*` after platform-user verification), platform console commands, and platform jobs. It is **unavailable to normal tenant requests** — entering it while a TenantContext is set, or from a non-platform execution path, throws `UnauthorizedPlatformContextException`.
- **Never grants access to tenant business data.** It only marks the unit of work as platform-level (e.g. operating on the `tenants` registry, which is a platform table needing no tenant scope anyway).
- A platform operation that must touch **one tenant's data** (exceptional access, provisioning) still calls `runAsTenant()` for that single target tenant inside the platform operation — permission-checked (`platform_tenants.access_data`) and audited into the target tenant's trail.
- **Cross-tenant work never uses an unrestricted bypass:** it iterates tenants explicitly (chunked — §14) and calls `runAsTenant()` separately per tenant. One global context for an all-tenant command is forbidden.
- Every entry into PlatformContext that touches tenant data is audited.

### 3.3 Exceptions (final)

| Exception | Thrown when | HTTP mapping |
|---|---|---|
| `MissingTenantContextException` | A tenant-owned model is queried, or `require()` is called, with no TenantContext and no `runAsTenant` wrapper | Programming error → `500` with generic envelope (never leaks internals). At the middleware boundary (platform user calling a tenant route) the middleware responds `403 TENANT_CONTEXT_MISSING` *before* any query runs. |
| `InvalidTenantContextException` | The authenticated user's `tenant_id` references a missing/invalid tenant row | `403 TENANT_CONTEXT_INVALID` + alert-level log (referential integrity should make this near-impossible) |
| `TenantNotActiveException` | A request/job proceeds for a tenant whose status is not `active` | `403` with `TENANT_PENDING` / `TENANT_SUSPENDED` / `TENANT_ARCHIVED` |
| `ConflictingTenantContextException` | `set()` with a different tenant while one is set; or entering `runAsTenant` in a way that would silently replace an active request context | Programming error → `500` |
| `UnauthorizedPlatformContextException` | `PlatformContext::run()` invoked from a tenant request or other non-platform path | `403` when request-driven; otherwise a programming error surfaced in logs/CI |

---

## 4. Tenant Resolver

### Interface and initial implementation

`TenantResolver` (interface, `resolve(Request): ?Tenant`) with the single MVP implementation **`AuthenticatedUserTenantResolver`**:

```text
authenticated user → read user.tenant_id → load tenant fresh (PK lookup)
→ validate tenant status → initialize TenantContext
```

- The tenant row is loaded **fresh per request** (not cached across requests) so suspension takes effect immediately; cost is one indexed PK lookup.
- `user.tenant_id = NULL` → platform user → no TenantContext.
- No user (public route, e.g. `/health`, `/login`) → no resolution.

### Hard rules

- `tenant_id` is never accepted from request payload or any public header.
- `tenant_code` is never trusted for authorization.
- **Future scope, each behind the same interface** (new implementation + ADR, zero business-module changes): subdomain resolution, custom domain resolution, API-token tenant resolution, SSO tenant resolution.

---

## 5. Middleware Separation (two distinct responsibilities)

### 5.1 `ResolveTenantContext`

- Requires an authenticated user where applicable (runs after `auth:sanctum`).
- Determines tenant user vs. platform user; delegates resolution to `TenantResolver`; initializes `TenantContext`.
- Rejects invalid or missing tenant relationships (`403 TENANT_CONTEXT_INVALID`).
- **Clears TenantContext in a `finally` block** — no context survives the request, even on exception.
- Does **not** check tenant status — that is the next middleware's job (single responsibility; platform routes reuse this middleware without the status gate).

### 5.2 `EnsureTenantIsActive`

- Requires an initialized TenantContext; a tenant-owned route reached without one (platform user or misconfiguration) → `403 TENANT_CONTEXT_MISSING`.
- Checks `status === active`; rejects otherwise with the stable code (`TENANT_PENDING` / `TENANT_SUSPENDED` / `TENANT_ARCHIVED`).
- **Never performs tenant resolution itself.**

### Stable machine-readable error codes (final)

| Code | HTTP | Meaning |
|---|---|---|
| `TENANT_CONTEXT_MISSING` | 403 | Tenant-owned route reached without a tenant context (e.g. platform user) |
| `TENANT_CONTEXT_INVALID` | 403 | User's tenant relationship is broken |
| `TENANT_PENDING` | 403 | Tenant not yet activated |
| `TENANT_SUSPENDED` | 403 | Tenant temporarily blocked |
| `TENANT_ARCHIVED` | 403 | Tenant terminally closed |

**`403` vs. `404` rule:** tenant **lifecycle blocking** returns `403` with the stable code — the caller's own tenant state is not a secret from them. **Cross-tenant resource lookups** return `404` — foreign data existence is always a secret (enumeration risk).

---

## 6. Tenant-Owned Model Mechanism

**Contract:** `TenantOwned` (marker interface — declares the model as tenant-owned).
**Trait:** `UsesTenantScope` (implements the mechanics). The name deliberately avoids `BelongsTo*` so it cannot be confused with a mere Eloquent relationship.

### The trait must

| Concern | Behavior |
|---|---|
| Scope | Register `TenantScope` automatically in `booted()`. |
| Creation | Force-set `tenant_id` from `TenantContext::require()` on `creating` — **any client-supplied value is overwritten**. No context → `MissingTenantContextException`. |
| Mutation | Prevent `tenant_id` mutation after creation — throw if dirty on `updating`. Records never move between tenants (a move would detach audit history and relationships). |
| Fail-closed | Any query with no TenantContext (and no `runAsTenant`) throws — never silently queries all tenants, never returns empty as a disguise. |
| Trash/archive | Archived or soft-deleted records remain tenant-scoped (`withTrashed` still filtered). |
| Relationship | Provide a `tenant(): BelongsTo` method where useful. |
| Relations | Prevent cross-tenant relation attachment: related tenant-owned models carry their own `tenant_id`, force-set from the same context — parent/child tenant equality is guaranteed by construction, and scoped `exists` validation (§7 below) blocks foreign ids at the boundary. |
| Tests/factories | Factories create rows under an explicit TenantContext (test helpers) — no factory-level tenant guessing. |

### The trait must not

- Resolve a tenant from the request. — Read `tenant_id` from form input. — Silently query all tenants. — Contain platform bypass logic (bypass lives only in the two explicit contexts, §3).

## 7. Route Model Binding

- Binding for tenant-owned models occurs **under TenantContext**; `TenantScope` applies before the model is returned — a foreign tenant's id yields `404` automatically, with zero per-endpoint code. Never `403` for cross-tenant lookup (enumeration oracle).
- Nested resources use scoped bindings (`->scopeBindings()`) so parent and child are verified to belong to the same tenant.
- **No unscoped `find` operations inside controllers** — controllers receive already-scoped models from binding or scoped queries in Actions.
- Never override `resolveRouteBinding()` to bypass the scope; custom binding keys still resolve through the scoped query.

**Mandatory binding test cases** (full matrix in [00-tenancy/TEST_PLAN.md](../09-modules/00-tenancy/TEST_PLAN.md)): valid same-tenant binding · cross-tenant binding (`404`) · missing tenant context (fail closed) · nested cross-tenant binding (`404`) · archived/soft-deleted record behavior · platform request without `runAsTenant` (rejected).

## 8. Validation Standards

Shared rule builders in `Core/Tenancy` (conceptual names; final class names fixed at implementation):

- **`TenantExists(table, column)`** — `exists` constrained to `tenant_id = TenantContext::id()`. Any payload field referencing another tenant-owned record (`department_id`, `warehouse_id`, …) must use it: a record from another tenant behaves like a nonexistent record (`422`), preventing **reference smuggling**.
- **`TenantUnique(table, column)`** — `unique` constrained to the current tenant when uniqueness is tenant-local (employee number, contract number, settings key). `ignore(id)` variants remain tenant-scoped: the ignored row is looked up inside the tenant constraint, never globally.

Rules: validation never accepts `tenant_id` from payload (not declared in any Form Request; silently ignored and force-overwritten by the trait — two independent layers); business modules never hand-write `exists:`/`unique:` strings against tenant-owned tables.

**Conceptual example (not code):** creating a task with `assignee_id` and `unit_id` validates both through `TenantExists` against `employees`/`organizational_units`; a valid-looking id belonging to tenant B fails validation exactly as a random nonexistent id would.

## 9. Queues, Scheduler, Console, and Workers

- **Propagation:** tenant-aware jobs carry `tenant_id` explicitly as **trusted server-generated metadata** captured from TenantContext at dispatch (never from client input). Dispatching a tenant-requiring job with no context fails at dispatch.
- **Execution:** job middleware restores TenantContext before `handle()`, re-validates that the tenant exists, and **clears context in a `finally` block** — worker reuse (queue workers, Octane, future Horizon) can never leak context between jobs.
- **Non-active tenant at execution time — behavior by job classification:**

| Classification | Examples | `pending`/`suspended` | `archived` |
|---|---|---|---|
| Business (default) | Notifications, report generation, workflow side-effects | **Release** with delay (work is held, not lost; drains on reactivation) | **Cancel** (logged + audited) — the relationship is terminally ended |
| Maintenance (explicitly marked) | Temp-file cleanup, cache pruning | Execute (safe, non-business) | Execute |

- **Retries:** context comes from the immutable payload — deterministic regardless of worker.
- **Failed jobs:** payload (with tenant id) preserved in `failed_jobs`; re-running restores context identically. Platform-level access only.
- **Scheduled jobs / console commands:** the scheduler runs with no context. All-tenant work **iterates tenants explicitly** (chunked/streamed, §14) and wraps **each tenant separately** in `runAsTenant()` — one tenant's failure is collected and logged, the loop continues. Setting one global context for an entire all-tenant command is forbidden.

## 10. Cache Isolation

- **One shared namespace helper** in `Core/Tenancy` generates all tenant cache keys. Format: **`tenant:{tenant_id}:`** — optionally `tenant:{tenant_id}:{tenant_code}:` for diagnostics, where the code appears **only after** the authoritative numeric id.
- Business modules **must not invent prefixes manually**; all tenant cache access goes through the helper. Invalidation stays inside the tenant namespace (per-key forget; whole-tenant invalidation via a version-stamp key inside the prefix — O(1) without tag support).
- Cache tags may be used only when the selected driver supports them; **prefixing remains mandatory even with tags** (defense in depth; tag support must never become a correctness dependency).
- Platform cache uses the separate **`platform:`** namespace — never unprefixed keys.

**Conceptual example:** dashboard counters for tenant 1 (`rafee`) live under `tenant:1:dashboard:counters`; the equivalent key for tenant 2 is `tenant:2:dashboard:counters` — same logical key, disjoint namespaces.

## 11. Storage and Export Paths (final layout)

```text
tenants/
  {tenant_id}/
    documents/
    contracts/
    employees/
    assets/
    exports/
    temp/
```

- **`tenant_id` is the stable path key** (immutable). A `tenant_code` segment is not used: the code is immutable too, but the id costs nothing and requires no path-migration strategy; adding a code segment later would require a documented storage migration plan — deliberately avoided.
- User-supplied paths are forbidden; paths are built from TenantContext only. Filenames are generated; originals stored as metadata. **File metadata includes `tenant_id`** (file records are tenant-owned models).
- Private documents never live on a public disk. Downloads require tenant-scoped model resolution **and** policy authorization. **Signed URLs must not bypass tenant authorization policy** — a signed URL is a transport convenience, not an authorization grant; the signed route still resolves the file record under TenantContext and checks the policy.
- Exports are generated inside the requester's context (or `runAsTenant` for scheduled exports) into `tenants/{tenant_id}/exports/`; temporary exports carry an expiration and are removed by the scheduled cleanup (maintenance classification, §9). File deletion is tenant-scoped like every other operation.

## 12. Notification Isolation

- Notifications are tenant-scoped: recipients must belong to the same tenant unless the notification is **explicitly platform-generated** (e.g. suspension notice), which uses a separate explicit platform path — never the business notification flow.
- Database notifications are tenant-owned rows (carry `tenant_id`, use `UsesTenantScope`).
- Queued notifications preserve tenant context exactly like jobs (§9); templates and per-tenant notification settings are loaded under TenantContext.
- **No cross-tenant broadcast channels.** Future realtime channel names must include the tenant namespace (e.g. `tenant.{tenant_id}.…`).

## 13. Audit Interaction and Correlation ID

- Every audit record carries `tenant_id` (nullable): tenant actions record the acting tenant; platform events may have `NULL`; **platform access affecting a tenant writes the target tenant's id** so the intrusion appears in that tenant's own audit view. Audit records are **append-only**.
- Every request/job carries a **correlation ID** included in application logs, audit records, queued job payloads, exports, and exceptional-access records — full design in [AUDIT_TRAIL.md](../06-security/AUDIT_TRAIL.md).
- Audit rows are written inside whatever context exists (never blocked by scoping) and are tenant-scoped for reading (`audit_logs.view`).

## 14. Tenancy Performance Principles

- Every frequent tenant-owned query path has a **tenant-leading index** (`(tenant_id, …)`) — see [DATABASE_PRINCIPLES.md](../03-database/DATABASE_PRINCIPLES.md).
- `TenantScope` **qualifies `tenant_id` with the full table name** — correct in joins, no ambiguous columns.
- Prevent N+1: eager-load declared relations; **disable lazy loading in local development and testing** where practical so violations surface early.
- No `SELECT *` for large or sensitive list queries; API Resources select what they serialize.
- **Pagination on all lists**; cursor pagination only where ordering and UX justify it.
- Background processing uses **chunking**; never load all tenants (or all of a tenant's rows) into one unbounded in-memory collection; scheduled all-tenant work streams or chunks the tenants table.
- Query plans (`EXPLAIN`) reviewed for high-volume tenant tables before merge.
- **Performance optimizations may never bypass tenant isolation.** Raw SQL against tenant-owned tables requires an explicit tenant predicate and mandatory review ([REVIEW_CHECKLIST.md](REVIEW_CHECKLIST.md)).
- No fixed performance SLA numbers are defined at this stage (deliberate — they would be invented, not measured).

---

## Non-negotiable Rules

1. Cross-tenant access must be impossible.
2. Tenant-owned queries are automatically scoped — never hand-written `where` clauses.
3. `tenant_id` is never trusted from request payloads; `tenant_code` is never an authorization input.
4. Route model binding returns `404` for foreign records — never `403`.
5. Unique constraints include `tenant_id` where uniqueness is per-tenant.
6. Background jobs carry and enforce tenant context; workers never leak context.
7. Cache keys include tenant context via the shared helper.
8. Files use tenant-isolated storage paths derived from TenantContext.
9. Audit logs include `tenant_id` and the correlation ID.
10. Notifications never cross tenants.
11. Exports contain only current-tenant data.
12. Every tenant-owned module ships explicit cross-tenant isolation tests.
13. Platform Super Admin access to tenant data is exceptional, permission-controlled, and audited.
14. There is no public tenancy bypass — only `TenantContext.runAsTenant()` and the narrowly scoped `PlatformContext`.

## TBD

- **Tenant provisioning/onboarding flow** (who creates the first Tenant Owner user; `pending → active` trigger; credential delivery): unresolved because the commercial onboarding process is not confirmed. **Recommended:** Super Admin creates tenant (status `pending`) + first Tenant Owner in one platform operation; activation is an explicit audited transition; credentials delivered out-of-band until email infrastructure is decided. **Impact:** one platform endpoint + one Action; no schema impact.
- **Archived-tenant recovery policy** (`archived → active`): forbidden until an exceptional recovery policy is explicitly approved. **Recommended:** keep forbidden; recovery would be a platform-level, twice-confirmed, audited operation defined in its own ADR. **Impact:** none now.
- **Per-tenant domains/subdomains**: future scope behind `TenantResolver` (`tenant_code` is DNS-label-compatible by design). **Impact if adopted:** new resolver implementation + ADR; no business-module changes.
