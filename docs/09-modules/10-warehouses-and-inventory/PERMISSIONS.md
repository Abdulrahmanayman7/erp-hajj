# Warehouses and Inventory — Permissions

> **Status:** Approved
> **Last updated:** 2026-08-06

## Warehouses

| Permission | Purpose |
|---|---|
| `warehouses.view` | List/view warehouses |
| `warehouses.create` | Create warehouses |
| `warehouses.update` | Update warehouses |
| `warehouses.delete` | Delete warehouses (restriction with stock: TBD) |

## Inventory

| Permission | Purpose |
|---|---|
| `inventory.view` | View items, balances, transactions |
| `inventory.add` | Purchase/Addition transactions |
| `inventory.issue` | Issue transactions |
| `inventory.transfer` | Transfer transactions |
| `inventory.return` | Return transactions |
| `inventory.adjust` | Adjustment transactions (reason required; audited) |

## TBD

- Item master management permission (create/update items — separate permission vs. covered by `inventory.add`): TBD.
