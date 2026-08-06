# Engineering Principles

> **Status:** Approved — enforceable daily reference for both developers and Cursor
> **Last updated:** 2026-08-06

## Purpose

The binding day-to-day engineering rules of ERP Hajj. Detailed specifications live in the referenced documents; this file is the checklist-level contract. Violations are review blockers ([REVIEW_CHECKLIST.md](REVIEW_CHECKLIST.md)); completion is defined by [DEFINITION_OF_DONE.md](../00-project/DEFINITION_OF_DONE.md).

## 1. Scope control

- The `docs/` tree is **authoritative**. If code and docs disagree, raise it — never silently pick one.
- No feature expansion without a **Change Request** issue and approval ([MVP_SCOPE.md](../00-project/MVP_SCOPE.md) is fixed).
- **No silent assumptions.** Unresolved business decisions are marked **TBD** (with why, recommendation, impact) and implementation stops where the TBD blocks it.

## 2. Backend architecture

- **Thin controllers**: validated input (Form Request) → authorization (Policy/Gate) → Action → API Resource/envelope. Business workflows never live in controllers.
- **One Action per application use case** where justified; Actions are cohesive, not ceremonial.
- **Form Requests** own validation; **Policies/Gates** own authorization; **API Resources** own output shape.
- **Transactions** protect multi-step state changes.
- **Services** hold reusable domain behavior — never dumping grounds. Prefer cohesive Actions and focused domain services (no “fat services” by design).
- **Repositories** only when they add meaningful abstraction — no blanket repositories.
- **Eloquent models** stay models — not unbounded business-service classes.
- **DTOs** only where they improve boundaries and type clarity.
- **Events/Jobs** only for justified asynchronous or decoupled behavior; queued work carries tenant context ([MULTI_TENANCY.md](MULTI_TENANCY.md) §9).

## 3. Multi-tenancy

- Tenant resolution is **centralized** (`TenantResolver` → `TenantContext`); business modules consume **TenantContext only** and never resolve tenants directly.
- No `tenant_id` from client input; `tenant_code` is never an authorization input.
- No unscoped tenant-owned query; scoping fails closed.
- **No public tenancy bypass** — only `runAsTenant()` and the narrowly scoped `PlatformContext`.
- Cross-tenant tests are mandatory for every tenant-owned module.

## 4. Frontend architecture

- **Feature-first modules** (`src/modules/<feature>/`); shared components stay generic in `src/shared/`; module-specific components stay in their module.
- Presentation components never call HTTP directly — data access lives in the module `api/` layer, consumed via **TanStack Query** (which owns server state). **Pinia** owns only appropriate client state.
- The backend remains the source of truth for authorization and business rules; frontend permission checks are UX only.
- **Arabic and RTL are mandatory from the start** (right sidebar, logical CSS properties — [DESIGN_GUIDELINES.md](../05-ui-ux/DESIGN_GUIDELINES.md)).
- **Accessibility is part of the Definition of Done** (focus management, labels, contrast, keyboard paths).

## 5. Database

- **Migrations are append-only** once shared environments exist — no editing merged migrations.
- Foreign keys and unique constraints enforce invariants; indexes match actual query paths.
- Tenant-owned uniqueness includes `tenant_id` unless explicitly global ([DATABASE_PRINCIPLES.md](../03-database/DATABASE_PRINCIPLES.md)).
- No irreversible destructive migration without a reviewed rollback and backup plan.

## 6. API

- Versioned under `/api/v1`; standardized envelope; **stable machine-readable error codes**; correct HTTP status codes ([API_STANDARDS.md](../04-api/API_STANDARDS.md)).
- Pagination, filtering, search, and sorting are consistent across list endpoints.
- Workflow transitions use explicit action endpoints — never generic status PATCHes.
- Never leak authorization-sensitive existence across tenants (`404` rule).

## 7. Security

- **Deny by default.** Validate every input; authorize every protected operation.
- Private files require protected downloads; signed URLs never bypass policy.
- Secrets never enter Git; sensitive values never enter audit logs.
- High-risk changes require security tests; security-relevant changes require the other developer's review.
- Full baseline: [SECURITY_BASELINE.md](../06-security/SECURITY_BASELINE.md).

## 8. Testing

- Critical business rules require tests; workflow transitions require positive **and** negative tests.
- Every tenant-owned module requires **cross-tenant attack tests**.
- Tests must be deterministic. **Failing or flaky security tests block merging.**
- Full strategy: [TESTING_STRATEGY.md](../07-testing/TESTING_STRATEGY.md).

## 9. Documentation

- Code and documentation stay synchronized **in the same PR**.
- Never mark planned behavior as implemented.
- Architectural changes get an ADR where appropriate (`docs/10-decisions/`).
- Every feature PR updates module docs and CHANGELOG where relevant. Module structure follows [MODULE_TEMPLATE.md](MODULE_TEMPLATE.md).

## 10. Performance

- Prevent N+1 (eager loading; lazy loading disabled in dev/test where practical).
- Paginate large results; background jobs for justified expensive work; chunk background processing.
- Avoid unnecessary dependencies and abstractions; **measure before major optimization**.
- **Security and data isolation may never be weakened for speed** — tenancy performance rules in [MULTI_TENANCY.md](MULTI_TENANCY.md) §14.
