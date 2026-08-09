# ADR-0006: Tenant-owned contract categories catalog

> **Status:** Accepted  
> **Date:** 2026-08-09  
> **Sprint:** 009 — Contracts (specification)

## Context

Contracts need a classification (موظف، إسكان، إعاشة، نقل، توريد، …). Options:

1. **Free-text** category string on `contracts`.
2. **Global PHP enum** of fixed types.
3. **Tenant-owned `contract_categories` catalog** with FK from `contracts`.

Existing module stubs already called categories “configurable per tenant” with seeded examples. Employees already established the pattern of a small tenant catalog for titles (ADR-0005).

## Decision

Use a minimal tenant-owned **`contract_categories`** table (`name`, optional `code`, `is_active`) and required `contracts.contract_category_id`.

**Lifecycle (MVP):** active / inactive only.

- New contracts select **active** categories only.
- Deactivation does **not** modify existing contracts.
- Hard delete **unused only**; referenced categories must be deactivated (`CONTRACT_CATEGORY_IN_USE`).

Do **not** build a hierarchical taxonomy, category versioning, workflow-per-category engine, or supplier-type subsystem.

Permissions for category management fold into `contracts.view` / `contracts.update` for Sprint 009 (no separate `contract_categories.*` unless a later Change Request splits duties).

## Consequences

### Positive

- Stable filters/reporting; per-tenant Arabic labels
- Aligns with prior catalog pattern (positions)
- Matches documented “configurable categories” intent
- Clear deactivate-vs-delete rule preserves historical FKs

### Negative / trade-offs

- Extra CRUD surface vs free-text
- Operators must deactivate instead of delete when in use

### Rejected

- Free-text only: duplicates, weak filters
- Global enum only: blocks tenant-specific naming without code deploys
- Full contract-type workflow matrix / category versioning: out of MVP scope

## References

- [docs/09-modules/05-contracts/](../09-modules/05-contracts/)
- [ADR-0005](ADR-0005-EMPLOYEE-POSITIONS-CATALOG.md)
- [CORE_WORKFLOWS.md](../01-business/CORE_WORKFLOWS.md) Workflow 2
