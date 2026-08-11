# Assets and Custodies — Test Plan

> **Status:** Implemented (Sprint 015) — Pest `AssetTest` 20/20 + Vitest assets 15/15 green
> **Last updated:** 2026-08-11
> Tooling: **Pest** (backend) · **Vitest** (frontend)

Failing security / cross-tenant / double-assign tests block merge.

---

## Backend (Pest)

### DATABASE

- [ ] Sequences / categories / assets / transitions / custodies FKs
- [ ] UNIQUE tenant+asset_number / custody_number / serial / barcode
- [ ] Tenant-leading indexes
- [ ] No SoftDeletes on history

### NUMBERING

- [ ] AST-000001 / CUS-000001; increments; per-tenant isolation; immutable; client numbers ignored; FOR UPDATE safe

### CATEGORY

- [ ] CRUD; inactive blocked on new assign; in-use delete blocked

### ASSET CRUD

- [ ] Create defaults `available`; update metadata; ignore client status/number/tenant_id
- [ ] Serial/barcode uniqueness
- [ ] Warehouse/org same-tenant + active-on-assign rules
- [ ] Delete unused OK; delete with custody/history/docs → in-use / DOCUMENT_ENTITY_IN_USE

### STATUS LIFECYCLE

- [ ] maintenance / restore / retire / declare-lost valid and invalid transitions
- [ ] Transition rows append-only; no PATCH

### CUSTODY ASSIGN

- [ ] Available → in_use; custody active; current_custody_id set; ASSET_ASSIGNED audited
- [ ] Non-available assign → ASSET_NOT_AVAILABLE
- [ ] Inactive employee → ASSET_EMPLOYEE_INVALID

### CUSTODY RETURN

- [ ] Return closes custody; next_status applied; current_custody_id cleared; ASSET_RETURNED audited
- [ ] Return without active → ASSET_NOT_ASSIGNED
- [ ] Invalid next_status → ASSET_INVALID_STATUS_TRANSITION

### DOUBLE-ASSIGN CONCURRENCY

- [ ] Sequential: second assign fails ASSET_ALREADY_ASSIGNED / CONFLICT
- [ ] Document limitation if true parallel PHP workers unavailable; still lock-order tested in transaction

### DECLARE LOST WHILE IN USE

- [ ] Closes custody + sets lost in one transaction

### TENANCY

- [ ] Cross-tenant asset/custody show → 404
- [ ] Foreign employee/warehouse/category in payload → 422/404 safe

### RBAC

- [ ] Full permission matrix for seven `assets.*` verbs
- [ ] Dept Mgr cannot delete/retire
- [ ] Self-view: linked employee can show own custody/asset; cannot assign/return without permission

### AUDIT

- [ ] Create/assign/return/retire/lost events dispatched with correlation metadata

### FILTERS

- [ ] Assets filters including employee_id via active custody
- [ ] Custodies overdue filter
- [ ] `/my-custodies` scoped

### DOCUMENTS

- [ ] Morph asset/custody link OK
- [ ] Delete guard when documents linked

### IMMUTABILITY

- [ ] Returned custody rejects further mutation
- [ ] No DELETE custody route

### INVENTORY BOUNDARY

- [ ] No inventory balance changes on assign/return/create asset

---

## Frontend (Vitest)

- [ ] Assets list filters / empty/loading/error
- [ ] Create/edit validation (name required; serial optional)
- [ ] Details action visibility by status + permission
- [ ] Assign dialog validation (employee required)
- [ ] Return dialog next_status required
- [ ] Retire/lost reason required
- [ ] My custodies query scoping
- [ ] Permission gating CTAs
- [ ] Documents section props (`asset`)
- [ ] Status badge mapping
- [ ] Responsive conditional rendering where practical

No snapshot-only tests.
