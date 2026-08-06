# Team and Git Workflow

> **Status:** Approved
> **Last updated:** 2026-08-06

## Purpose

Define the two-developer working model, GitHub branching workflow, commit conventions, and issue/PR quality requirements.

## Two-Developer Working Model

Responsibilities represent **primary ownership, not exclusive access**.

### Developer A — Backend lead

Technical architecture · Laravel backend · Multi-tenancy · Database design · REST API · Authentication · Authorization · Audit trail · Security · Backend tests

### Developer B — Frontend lead

Vue architecture · Design system · RTL layout · Shared components · Pages and forms · Pinia · TanStack Query · Frontend validation · Accessibility · Frontend tests

### Shared

Business rules review · Pull request review · Integration testing · Documentation · GitHub Issues · Acceptance criteria · Performance review · Security review · Release preparation

## Branching Model

Permanent branches:

- **`main`** — stable releases only. **No direct push.**
- **`develop`** — approved integration work. Prefer no direct push.

All work uses short-lived branches named:

```text
feature/<issue-number>-<short-name>
fix/<issue-number>-<short-name>
chore/<issue-number>-<short-name>
docs/<issue-number>-<short-name>
refactor/<issue-number>-<short-name>
test/<issue-number>-<short-name>
```

Examples: `feature/12-tenant-foundation`, `feature/18-users-rbac`, `fix/34-contract-expiry-filter`, `docs/41-update-audit-rules`.

## Feature Workflow

Issue → Branch → Implementation → Local testing → Push → Pull Request into `develop` → Review by the **other** developer → Requested changes → Approval → Merge → Delete branch

- A developer **cannot approve their own Pull Request**.
- Use **squash merge** for normal feature branches unless preserving commits provides real value.

## Release Workflow

`develop` → `release/<version>` → final testing → merge into `main` → create release tag → merge corrections back into `develop`

## Commit Conventions (Conventional Commits)

Allowed prefixes: `feat` `fix` `docs` `style` `refactor` `test` `chore` `perf` `build` `ci` `revert`

Good examples:

```text
feat(auth): add tenant-aware login endpoint
feat(contracts): implement contract approval transition
fix(tasks): prevent cross-tenant assignment
docs(scope): clarify mobile exclusion
test(tenancy): verify cross-tenant access is blocked
refactor(documents): extract upload action
```

Avoid vague messages: `update`, `changes`, `final`, `new code`, `fix issue`, `work`.

## Issue Requirements

Every implementation Issue must include: business purpose, scope, out of scope, user roles, permissions, business rules, API requirements, UI requirements, tenant isolation rules, audit events, acceptance criteria, testing requirements, documentation requirements, dependencies.

**No implementation starts from a vague one-line Issue.** Module docs in [docs/09-modules/](../09-modules/) provide the source content for Issues.

## Pull Request Rules

Every PR must confirm (enforced by the PR template):

Related Issue · Scope matches approved requirements · No extra features · Tenant isolation checked · Permissions checked · Audit events implemented where required · Validation added · Standardized API errors used · Tests added/updated · Documentation updated · Database changes are safe · Screenshots for UI changes · No secrets committed · No debug code remains
