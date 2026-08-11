# Warehouses and Inventory — Permissions

> **Status:** Specified (Sprint 014) — **not implemented** (named for catalog; seed at implementation)
> **Last updated:** 2026-08-11

Capabilities use `module.action`. Policies never check role names. Frontend gates are UX-only.

## Catalog (final MVP)

### Warehouses

| Permission | Meaning |
|---|---|
| `warehouses.view` | List/view warehouses |
| `warehouses.create` | Create warehouses |
| `warehouses.update` | Update metadata; activate/deactivate |
| `warehouses.delete` | Hard-delete unused warehouses |

### Inventory

| Permission | Meaning |
|---|---|
| `inventory.view` | View items, categories (read), balances, movements |
| `inventory.manage_items` | Item + category CRUD / activate / deactivate / unused delete |
| `inventory.add` | Opening + receipt (manual addition) |
| `inventory.issue` | Issue stock |
| `inventory.return` | Return-to-stock |
| `inventory.transfer` | Warehouse-to-warehouse transfer |
| `inventory.adjust` | Stock adjustment (sensitive) |

**Supersedes stub TBD:** item master uses `inventory.manage_items` (not folded into `inventory.add`).
**Retained from master catalog:** `inventory.add|issue|transfer|return|adjust` verbs unchanged.
**Not seeded:** purchase-order permissions, costing permissions, negative-stock override permission.

### Action → permission map

| API / UX | Permission |
|---|---|
| Warehouse list/show | `warehouses.view` |
| Warehouse create | `warehouses.create` |
| Warehouse patch / activate / deactivate | `warehouses.update` |
| Warehouse delete | `warehouses.delete` |
| Item/category write | `inventory.manage_items` |
| Balances / movements / item read | `inventory.view` |
| Receipts / opening | `inventory.add` |
| Issues | `inventory.issue` |
| Returns | `inventory.return` |
| Transfers | `inventory.transfer` |
| Adjustments | `inventory.adjust` |

## Policies

- `WarehousePolicy` — capability + same tenant.
- `InventoryItemPolicy` / category policy — capability + same tenant.
- Stock actions authorized on ability names matching permissions (or a dedicated `InventoryStockPolicy` gating action classes).
- No org-unit row scoping in Sprint 014.
- Responsible employee does **not** imply stock permissions.

## Default system role template grants

| Role template | warehouses.* | inventory.view | manage_items | add | issue | return | transfer | adjust |
|---|---|---|---|---|---|---|---|---|
| Tenant Owner | all | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| General Manager | all | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Department Manager | view/create/update (no delete) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | — |
| Supervisor | view | ✓ | — | — | — | — | — | — |
| Employee (base) | — | — | — | — | — | — | — | — |
| Auditor | view | ✓ | — | — | — | — | — | — |

Owner via full catalog. Do not wipe custom roles when seeding templates.
