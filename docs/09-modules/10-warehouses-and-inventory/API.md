# Warehouses and Inventory — API

> **Status:** Specified (Sprint 014) — **not implemented**
> **Last updated:** 2026-08-11
> Base: `/api/v1` · Auth: Sanctum SPA · Envelope: [API_STANDARDS.md](../../04-api/API_STANDARDS.md)

All routes: authenticated + tenant-active. Authorization via Policies. Cross-tenant → **404**.
**No** `PATCH` on balances or movements.

---

## Warehouses

| Method | Path | Permission | Notes |
|---|---|---|---|
| GET | `/warehouses` | `warehouses.view` | Paginated |
| POST | `/warehouses` | `warehouses.create` | |
| GET | `/warehouses/{warehouse}` | `warehouses.view` | |
| PATCH | `/warehouses/{warehouse}` | `warehouses.update` | Metadata; not number |
| POST | `/warehouses/{warehouse}/activate` | `warehouses.update` | |
| POST | `/warehouses/{warehouse}/deactivate` | `warehouses.update` | |
| DELETE | `/warehouses/{warehouse}` | `warehouses.delete` | Unused only |

---

## Categories

| Method | Path | Permission |
|---|---|---|
| GET | `/inventory-categories` | `inventory.view` or `inventory.manage_items` |
| POST | `/inventory-categories` | `inventory.manage_items` |
| PATCH | `/inventory-categories/{category}` | `inventory.manage_items` |
| DELETE | `/inventory-categories/{category}` | `inventory.manage_items` (unreferenced) |

---

## Inventory items

| Method | Path | Permission |
|---|---|---|
| GET | `/inventory-items` | `inventory.view` |
| POST | `/inventory-items` | `inventory.manage_items` |
| GET | `/inventory-items/{item}` | `inventory.view` |
| PATCH | `/inventory-items/{item}` | `inventory.manage_items` |
| POST | `/inventory-items/{item}/activate` | `inventory.manage_items` |
| POST | `/inventory-items/{item}/deactivate` | `inventory.manage_items` |
| DELETE | `/inventory-items/{item}` | `inventory.manage_items` (unused only) |
| GET | `/inventory-items/{item}/balances` | `inventory.view` | Per-warehouse balances |

---

## Balances (stock overview)

| Method | Path | Permission |
|---|---|---|
| GET | `/inventory/balances` | `inventory.view` | Paginated operational stock |

Filters: `warehouse_id`, `inventory_item_id`, `category_id`, `stock_state` (`normal`\|`low`\|`out_of_stock`), `search`, pagination.

---

## Movements (ledger)

| Method | Path | Permission |
|---|---|---|
| GET | `/inventory/movements` | `inventory.view` | Paginated; immutable rows |
| GET | `/inventory/movements/{movement}` | `inventory.view` | |

Filters: `warehouse_id`, `inventory_item_id`, `type`, `performed_by`, `transfer_group_id`, `occurred_from`/`occurred_to`, `search` (movement_number/reason/reference).

**No** PATCH/DELETE.

---

## Stock actions (explicit)

| Method | Path | Permission |
|---|---|---|
| POST | `/inventory/receipts` | `inventory.add` | body: item, warehouse, quantity, reason, optional reference; optional `as_opening` bool → type `opening` |
| POST | `/inventory/issues` | `inventory.issue` | |
| POST | `/inventory/returns` | `inventory.return` | |
| POST | `/inventory/transfers` | `inventory.transfer` | source_warehouse_id, destination_warehouse_id, item, quantity, reason |
| POST | `/inventory/adjustments` | `inventory.adjust` | direction `in`\|`out`, quantity, reason |

Server sets: movement_number(s), balances, snapshots, performed_by, occurred_at, correlation_id, transfer_group_id.

---

## List filters / sorting

### Warehouses

`search` (number/name/location), `is_active`, `organization_unit_id`, `responsible_employee_id`. Default sort: `name` ASC.

### Items

`search` (number/name/barcode), `category_id`, `is_active`, `unit`, `low_stock` (any warehouse). Default: `name` ASC.

### Balances

Default: warehouse name, item name. Alternate sort: `on_hand`.

### Movements

Default: `occurred_at` DESC, `id` DESC.

---

## Error codes

| Code | When |
|---|---|
| `WAREHOUSE_NOT_FOUND` | Prefer generic 404 envelope |
| `WAREHOUSE_INACTIVE` | Mutation against inactive warehouse |
| `WAREHOUSE_IN_USE` | Delete blocked |
| `INVENTORY_ITEM_NOT_FOUND` | Prefer 404 |
| `INVENTORY_ITEM_INACTIVE` | Mutation against inactive item |
| `INVENTORY_ITEM_IN_USE` | Delete blocked |
| `INVENTORY_CATEGORY_IN_USE` | Category delete blocked |
| `INVENTORY_CATEGORY_INVALID` | Inactive/foreign category assign |
| `INVENTORY_INVALID_QUANTITY` | ≤0, too many decimals, invalid unit |
| `INVENTORY_INSUFFICIENT_STOCK` | Would go negative |
| `INVENTORY_TRANSFER_SAME_WAREHOUSE` | Source = destination |
| `INVENTORY_ADJUSTMENT_REASON_REQUIRED` | Empty reason |
| `INVENTORY_REASON_REQUIRED` | Empty reason on other mutations |
| `INVENTORY_IMMUTABLE` | Attempt to alter movement/balance fields |

Cross-tenant references → **404** (no leakage).

---

## Audit events

| Event | When |
|---|---|
| `WAREHOUSE_CREATED` / `_UPDATED` / `_ACTIVATED` / `_DEACTIVATED` / `_DELETED` | Warehouse lifecycle |
| `INVENTORY_CATEGORY_CREATED` / `_UPDATED` / `_DELETED` | Categories |
| `INVENTORY_ITEM_CREATED` / `_UPDATED` / `_ACTIVATED` / `_DEACTIVATED` / `_DELETED` | Items |
| `STOCK_RECEIVED` | opening/receipt |
| `STOCK_ISSUED` | issue |
| `STOCK_RETURNED` | return |
| `STOCK_TRANSFERRED` | atomic transfer (one event for pair) |
| `STOCK_ADJUSTED` | adjustment |

Payload concepts: ids/numbers, warehouse/item summaries, quantity, direction, balance_before/after, reason, transfer_group_id, actor, tenant, correlation ID. No secrets.

---

## Resource notes

- Balance resources include derived `stock_state`.
- Movement resources never expose editable quantity controls.
- Do not return internal lock tokens.
