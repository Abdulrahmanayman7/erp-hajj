# Definition of Done

> **Status:** Approved
> **Last updated:** 2026-08-06

A feature is **not Done** unless every item below holds. The reviewer verifies against [REVIEW_CHECKLIST.md](../02-architecture/REVIEW_CHECKLIST.md); the engineering rules are in [ENGINEERING_PRINCIPLES.md](../02-architecture/ENGINEERING_PRINCIPLES.md).

## Before implementation

- [ ] An Issue exists and is approved.
- [ ] Scope and acceptance criteria are clear (module docs per [MODULE_TEMPLATE.md](../02-architecture/MODULE_TEMPLATE.md)).
- [ ] Business rules are approved; blocking TBDs resolved or the work is re-scoped.

## Implementation quality

- [ ] Code follows the architecture (thin controllers, Actions, Form Requests, Policies, API Resources) and [CODING_STANDARDS.md](../02-architecture/CODING_STANDARDS.md).
- [ ] **Tenant isolation is verified** — scoped models, no client-supplied tenancy, cross-tenant behavior returns `404`.
- [ ] **Authorization is verified** — every protected operation passes a Policy/Gate.
- [ ] Validation is implemented via Form Requests (tenant-scoped `exists`/`unique` where applicable).
- [ ] Audit events are implemented where required, with correlation ID.

## Verification

- [ ] Tests pass; new behavior is covered (positive + negative).
- [ ] **Cross-tenant tests pass where applicable** — security tests are never skipped or flaky.
- [ ] Pint, type-check, tests, and build pass locally and in CI (CI is green).
- [ ] No secret, debug statement, dead code, or commented-out code exists in the change.

## Documentation and review

- [ ] Documentation is updated in the same PR (module docs, API docs, CHANGELOG; ADR if architecture changed).
- [ ] Screenshots are attached for UI changes (RTL visible).
- [ ] Migration and rollback impact are reviewed (tenant-scoping impact stated in the PR).
- [ ] Accessibility and Arabic/RTL are checked.
- [ ] No unrelated feature is bundled into the PR.
- [ ] PR is reviewed and approved by the **other** developer (no self-merge).
