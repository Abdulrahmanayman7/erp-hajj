# Module: Warehouses and Inventory (المستودعات والمخزون)

> **Status:** Documented — not implemented
> **Last updated:** 2026-08-06

## Purpose

Manage storage locations and quantity-tracked stock. Three separate concepts:

- **Warehouse** (مستودع): a physical or logical storage location.
- **Inventory Item** (صنف مخزون): a stock-tracked item.
- **Inventory Transaction** (حركة مخزون): a movement affecting quantity.

Inventory items are **not** assets — assets are individually tracked in [11-assets-and-custodies/](../11-assets-and-custodies/).

## Scope

- Warehouses CRUD.
- Inventory items with codes, units, barcodes/QR, minimum stock levels.
- Transactions: Purchase/Addition, Issue, Return, Transfer, Adjustment.
- Balances calculated from transactions; low-stock alerts (dashboard/notifications).

## Out of scope

- Automatic supplier purchasing (explicitly out of the MVP).
- Procurement and suppliers (future scope).

## References

- [CORE_WORKFLOWS.md](../../01-business/CORE_WORKFLOWS.md) (Workflow 4)
