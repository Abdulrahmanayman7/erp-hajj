# Warehouses and Inventory — Acceptance Criteria

> **Status:** Specified (Sprint 014) — **not implemented**
> **Last updated:** 2026-08-11

Implementation is **done** only when critical items below pass and docs say **Implemented**.

## Functional

- [ ] Warehouses CRUD + activate/deactivate; `WH-######` unique per tenant
- [ ] Items + categories managed; `ITM-######`; fixed unit allow-list; optional barcode
- [ ] Balances materialized per warehouse+item; never client-editable
- [ ] Movements append-only `MOV-######` with reason; no update/delete API
- [ ] Receipt/opening, issue, return, atomic transfer, adjustment all work with correct balance effects
- [ ] Transfer creates paired out/in with shared `transfer_group_id` in one transaction
- [ ] Negative stock rejected (`INVENTORY_INSUFFICIENT_STOCK`)
- [ ] Low/out stock states derived from on_hand vs minimum_stock
- [ ] Documents sections for warehouse/item when morph aliases enabled
- [ ] Sidebar المستودعات + المخزون; RTL pages per UI.md

## Security / tenancy / concurrency

- [ ] Cross-tenant access → 404 across resources and actions
- [ ] Stock mutations lock balances; transfer lock order deterministic
- [ ] Policies capability-only; responsible_employee grants nothing alone
- [ ] Concurrent issue/transfer tests green (no negative race / no deadlock)

## Quality

- [ ] Pest matrix green (DATABASE/NUMBERING/STOCK/TENANCY/RBAC/AUDIT)
- [ ] Vitest coverage for lists, actions, permissions
- [ ] composer/pint/`php artisan test` and frontend type-check/test/build green at implementation
- [ ] ADR-0011 + module docs marked Implemented only after above

## Explicitly not required for Sprint 014 acceptance

- [ ] Procurement / suppliers / PO
- [ ] Unit conversions
- [ ] Lot/serial tracking
- [ ] Multi-step transfer workflow
- [ ] Negative-stock configuration
- [ ] Barcode hardware scanning
- [ ] Notification delivery / Dashboard widgets
- [ ] Assets/Custodies auto stock posting
