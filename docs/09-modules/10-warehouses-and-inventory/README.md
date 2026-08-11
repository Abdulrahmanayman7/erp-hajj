# Module: Warehouses and Inventory (المستودعات والمخزون)

> **Status:** Implemented (Sprint 014)
> **Last updated:** 2026-08-11
> Binding ADR: [ADR-0011](../../10-decisions/ADR-0011-INVENTORY-LEDGER-BALANCE-AND-TRANSFER.md)

## Purpose

Provide the **tenant-isolated inventory foundation** for ERP Hajj: warehouses (storage locations), quantity-tracked inventory items, an append-only stock movement ledger, materialized per-warehouse balances, and controlled stock actions (receipt, issue, return, transfer, adjustment).

This module answers Workflow 4 ([CORE_WORKFLOWS.md](../../01-business/CORE_WORKFLOWS.md)):
**Purchase/Addition → Storage → Transfer/Issue/Return → Updated Balance**.

## Domain separations (binding)

| Concept | This module | Not this module |
|---|---|---|
| **Warehouse** | Physical/logical storage location | OrganizationUnit (structure only) |
| **Inventory item** | Quantity-tracked stock SKU | **Asset** (individually tracked — [11-assets-and-custodies/](../11-assets-and-custodies/)) |
| **Stock movement** | Append-only ledger row affecting quantity | Purchase Order / supplier invoice |
| **Balance** | Cached on-hand qty for `(warehouse, item)` | Editable stock field |

## Sprint 014 scope

- Warehouses CRUD + activate/deactivate; `WH-######` numbering.
- Inventory item master + tenant categories; `ITM-######` numbering; fixed base unit per item.
- Materialized `inventory_balances` + append-only `inventory_movements` (`MOV-######`).
- Stock actions: opening/receipt, issue, return, warehouse transfer (atomic), adjustment (delta + reason).
- **No negative stock** in MVP (configuration deferred).
- Concurrent mutation safety via `SELECT … FOR UPDATE` + deterministic lock order.
- Permissions, Policies, audit events, API, RTL UI contract, Pest/Vitest plans.
- Documents morph aliases `warehouse` / `inventory_item` reserved for implementation (Documents already implemented — ADR-0010).

## Out of scope (explicit)

- Procurement, suppliers, purchase orders, goods-receipt against PO.
- Asset registry, custody assignment/return workflows (may later **emit** inventory movements — they must not mutate balances directly).
- Accounting / costing / valuation / multi-currency stock value.
- Batch/lot, expiry, serial-number stock tracking.
- Unit conversions (multi-UOM).
- Barcode/QR hardware scanning (optional `barcode` metadata string only).
- Multi-step transfer workflow (requested → dispatched → received).
- Negative-stock configuration UI.
- Dashboard KPI widgets / notification delivery (hooks only).
- SoftDeletes on movements.

## Personas

| Persona | Usage |
|---|---|
| Tenant Owner / GM | Full warehouse + stock control |
| Department Manager | Operate stock; limited/no hard delete; adjustments may be restricted |
| Supervisor / Employee | View-only where granted |
| Auditor | View warehouses, items, balances, movements |

## Documents in this folder

| File | Role |
|---|---|
| [BUSINESS_RULES.md](BUSINESS_RULES.md) | Domain locks |
| [DATA_MODEL.md](DATA_MODEL.md) | Tables / constraints |
| [API.md](API.md) | Endpoints / errors / audit |
| [PERMISSIONS.md](PERMISSIONS.md) | Catalog + role grants |
| [UI.md](UI.md) | Vue UX |
| [TEST_PLAN.md](TEST_PLAN.md) | Pest / Vitest |
| [ACCEPTANCE_CRITERIA.md](ACCEPTANCE_CRITERIA.md) | Definition of done |

## References

- [CORE_WORKFLOWS.md](../../01-business/CORE_WORKFLOWS.md) Workflow 4
- [DATABASE_PRINCIPLES.md](../../03-database/DATABASE_PRINCIPLES.md) — quantities from transactions
- [ADR-0010](../../10-decisions/ADR-0010-DOCUMENT-STORAGE-AND-ENTITY-LINKS.md) — Documents morph
- [11-assets-and-custodies/](../11-assets-and-custodies/) — Assets ≠ Inventory
- [OUT_OF_SCOPE.md](../../00-project/OUT_OF_SCOPE.md) — Procurement deferred
