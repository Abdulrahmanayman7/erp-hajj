# Assets and Custodies — Acceptance Criteria

> **Status:** Implemented (Sprint 015)
> **Last updated:** 2026-08-11

Sprint 015 is **done** only when all items below are true and critical security tests pass.

## Functional

- [x] Assets CRUD with `AST-######`; categories tenant-scoped
- [x] Custodies assign/return with `CUS-######`; ≤1 active custody per asset
- [x] Status machine matches BUSINESS_RULES (no generic status PATCH)
- [x] Current assignee derived from active custody / `current_custody_id` cache
- [x] Optional warehouse = home/storage only (no inventory mutation)
- [x] Documents morph `asset` (+ `custody` if UI ships)
- [x] My custodies self-view per ADR-0012
- [x] Arabic RTL UI: list, details, assign/return, my custodies

## Security / tenancy

- [x] Cross-tenant access → 404
- [x] Policies capability-based; no role-name checks
- [x] Concurrent double-assign impossible under lock
- [x] Returned custody immutable
- [x] Hard delete blocked when history/docs exist

## Quality

- [x] Pest matrix in TEST_PLAN green (incl. tenancy, custody race, RBAC, audit) — `AssetTest` 20/20
- [x] Vitest matrix green; `tsc` / build green — assets 15/15
- [x] Permissions synced; role templates updated without wiping customs (`PermissionCatalog`)
- [x] Module docs marked Implemented; ADR-0012 implementation note; CHANGELOG/ROADMAP updated

## Explicit non-goals (must remain false)

- [x] No Procurement / inventory auto-post / depreciation / maintenance work-orders
- [x] No custody correction void API
- [x] No `inventory_item_id` on assets
