# Warehouses and Inventory — Test Plan

> **Status:** Implemented (Sprint 014)
> **Last updated:** 2026-08-11
> Tooling: **Pest** (backend) · **Vitest** (frontend)

Failing security / cross-tenant / concurrency stock tests block merge.

---

## Backend (Pest)

### DATABASE

- [ ] Sequences / warehouses / categories / items / balances / movements FKs
- [ ] UNIQUE tenant+number; UNIQUE balance (tenant, warehouse, item)
- [ ] Tenant-leading indexes; DECIMAL columns
- [ ] No SoftDeletes on movements

### NUMBERING

- [ ] First WH/ITM/MOV-000001; increments; per-tenant isolation; immutable; client numbers ignored; FOR UPDATE safe

### WAREHOUSE CRUD

- [ ] Create/update/activate/deactivate
- [ ] Delete unused OK; delete with history → `WAREHOUSE_IN_USE`
- [ ] Inactive warehouse rejects new stock mutations
- [ ] Org unit / employee same-tenant validation; foreign → safe 404/422

### ITEM / CATEGORY

- [ ] CRUD; inactive category blocked on new assign
- [ ] Category in-use delete blocked
- [ ] Unit allow-list; barcode unique when set
- [ ] Inactive item rejects new mutations

### BALANCES / LEDGER

- [ ] Receipt creates/updates balance; movement immutable
- [ ] No PATCH balance / PATCH movement endpoints
- [ ] balance_before/after snapshots correct

### RECEIPT / OPENING / ISSUE / RETURN

- [ ] Happy paths; reason required
- [ ] Issue exact balance → zero OK
- [ ] Issue over balance → `INVENTORY_INSUFFICIENT_STOCK`

### TRANSFER

- [ ] Atomic pair with same `transfer_group_id`
- [ ] Same warehouse rejected
- [ ] Insufficient source rejected; dest increases; source decreases
- [ ] Cross-tenant dest impossible (404/422)
- [ ] Failure mid-way leaves no partial pair

### ADJUSTMENT

- [ ] In/out deltas; reason required
- [ ] Outbound insufficient blocked
- [ ] Unauthorized → 403

### CONCURRENCY

- [ ] Two concurrent issues against same balance: one succeeds, other insufficient or serialized correctly
- [ ] Concurrent transfers locking order does not deadlock (ordered warehouse_id)
- [ ] Documented as integration tests (may use parallel HTTP or transaction tests)

### NEGATIVE STOCK

- [ ] Never persists on_hand < 0

### TENANCY

- [ ] Full matrix warehouses/items/balances/movements/actions → cross-tenant 404
- [ ] Transfer cannot target foreign warehouse

### RBAC

- [ ] Each permission gates mapped actions
- [ ] Dept Mgr cannot adjust / cannot warehouse delete (per template)
- [ ] Auditor view-only

### AUDIT

- [ ] Warehouse/item/category events + STOCK_* events with correlation id; no secrets

### FILTERING

- [ ] Balances stock_state; movements date/type; pagination; default sorts

### DOCUMENTS

- [ ] Morph aliases warehouse/inventory_item when wired
- [ ] Host delete blocked while Documents linked

### THREAT CASES

- [ ] Forged IDs, mass-assignment tenant_id, inactive mutation, historical edit attempts, enumeration

---

## Frontend (Vitest)

- [ ] Warehouses list loading/empty/error + permission CTA
- [ ] Item list + category manager gating
- [ ] Inventory filters + low/out badges
- [ ] Receipt/issue/return/transfer/adjustment dialogs validation
- [ ] Insufficient stock error display
- [ ] Movement history has no edit controls
- [ ] Warehouse details sections + Documents widget mount
- [ ] Sidebar visibility warehouses.view / inventory.view
- [ ] Responsive smoke (cards vs table) where practical

## Explicit non-goals

- Procurement PO tests
- Barcode scanner device tests
- Multi-step transfer workflow
- Negative-stock configuration matrix
