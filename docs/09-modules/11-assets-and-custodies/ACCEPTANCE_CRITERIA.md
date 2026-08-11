# Assets and Custodies — Acceptance Criteria

> **Status:** Specified (Sprint 015) — **not implemented**
> **Last updated:** 2026-08-11

Sprint 015 is **done** only when all items below are true and critical security tests pass.

## Functional

- [ ] Assets CRUD with `AST-######`; categories tenant-scoped
- [ ] Custodies assign/return with `CUS-######`; ≤1 active custody per asset
- [ ] Status machine matches BUSINESS_RULES (no generic status PATCH)
- [ ] Current assignee derived from active custody / `current_custody_id` cache
- [ ] Optional warehouse = home/storage only (no inventory mutation)
- [ ] Documents morph `asset` (+ `custody` if UI ships)
- [ ] My custodies self-view per ADR-0012
- [ ] Arabic RTL UI: list, details, assign/return, my custodies

## Security / tenancy

- [ ] Cross-tenant access → 404
- [ ] Policies capability-based; no role-name checks
- [ ] Concurrent double-assign impossible under lock
- [ ] Returned custody immutable
- [ ] Hard delete blocked when history/docs exist

## Quality

- [ ] Pest matrix in TEST_PLAN green (incl. tenancy, custody race, RBAC, audit)
- [ ] Vitest matrix green; `tsc` / build green
- [ ] Permissions synced; role templates updated without wiping customs
- [ ] Module docs marked Implemented; ADR-0012 implementation note; CHANGELOG/ROADMAP updated

## Explicit non-goals (must remain false)

- [ ] No Procurement / inventory auto-post / depreciation / maintenance work-orders
- [ ] No custody correction void API
- [ ] No `inventory_item_id` on assets
