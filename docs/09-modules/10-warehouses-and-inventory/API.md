# Warehouses and Inventory — API (planned)

> **Status:** Planned contract — no endpoints exist yet
> **Last updated:** 2026-08-06

All endpoints follow [API_STANDARDS.md](../../04-api/API_STANDARDS.md).

## Warehouses

```text
GET/POST           /api/v1/warehouses               # warehouses.view / warehouses.create
GET/PATCH/DELETE   /api/v1/warehouses/{warehouse}   # warehouses.view / update / delete
```

## Inventory items

```text
GET/POST           /api/v1/inventory-items                  # inventory.view / (item management permission TBD)
GET/PATCH          /api/v1/inventory-items/{item}
GET                /api/v1/inventory-items/{item}/balances  # inventory.view — per-warehouse balances (computed)
```

## Inventory transactions (immutable once created)

```text
GET  /api/v1/inventory-transactions            # inventory.view; filters: type, item, warehouse, date, actor
POST /api/v1/inventory-transactions            # permission by type: inventory.add / issue / transfer / return / adjust
```

## Behavior

- Transaction payload includes type, item, quantity, source/destination warehouses (per type), and reason.
- A transaction that would produce negative stock is rejected (standardized error) unless configured otherwise (TBD).
- No update/delete endpoints for transactions — corrections via compensating/adjustment transactions (pending TBD confirmation).

## TBD

- Single transactions endpoint vs. per-type endpoints: proposal above uses single; confirm at implementation.
