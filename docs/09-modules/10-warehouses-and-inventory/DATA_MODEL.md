# Warehouses and Inventory — Data Model

> **Status:** Specified (Sprint 014) — **not implemented** (no migrations in this sprint)
> **Last updated:** 2026-08-11

## Tables (future migration order)

1. `warehouse_number_sequences`
2. `warehouses`
3. `inventory_categories`
4. `inventory_item_number_sequences`
5. `inventory_items`
6. `inventory_movement_number_sequences`
7. `inventory_balances`
8. `inventory_movements`

No SoftDeletes on movements. No unit-conversion tables. No transfer workflow header table (pair via `transfer_group_id`).

---

## 1. `warehouse_number_sequences`

| Column | Type | Notes |
|---|---|---|
| `tenant_id` | FK → tenants | PK |
| `next_number` | unsigned bigint | Next value (starts 1) |
| timestamps | | |

Allocation: `SELECT … FOR UPDATE`; format `WH-` + pad 6.

---

## 2. `warehouses`

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | bigint PK | no | |
| `tenant_id` | FK → tenants | no | TenantOwned |
| `warehouse_number` | string(20) | no | Immutable `WH-######` |
| `name` | string(255) | no | |
| `description` | text | yes | |
| `location` | string(500) | yes | Free-text address/location |
| `organization_unit_id` | FK → organization_units | yes | nullOnDelete |
| `responsible_employee_id` | FK → employees | yes | nullOnDelete |
| `is_active` | boolean | no | default true |
| `notes` | text | yes | |
| `created_by` | FK → users | no | RESTRICT |
| timestamps | | no | |

Constraints: UNIQUE (`tenant_id`, `warehouse_number`). Indexes: (`tenant_id`, `is_active`), (`tenant_id`, `organization_unit_id`), (`tenant_id`, `responsible_employee_id`).

Contracts: `Warehouse` implements `TenantOwned` + `UsesTenantScope`.

---

## 3. `inventory_categories`

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | bigint PK | no | |
| `tenant_id` | FK → tenants | no | |
| `name` | string(120) | no | |
| `description` | text | yes | |
| `is_active` | boolean | no | default true |
| timestamps | | no | |

UNIQUE (`tenant_id`, `name`). INDEX (`tenant_id`, `is_active`). ON DELETE from items: **RESTRICT**.

---

## 4. `inventory_item_number_sequences`

Same pattern as warehouse sequences; format `ITM-######`.

---

## 5. `inventory_items`

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | bigint PK | no | |
| `tenant_id` | FK → tenants | no | |
| `item_number` | string(20) | no | Immutable `ITM-######` |
| `name` | string(255) | no | |
| `description` | text | yes | |
| `category_id` | FK → inventory_categories | yes | RESTRICT |
| `unit` | string(32) | no | Allow-list value |
| `barcode` | string(64) | yes | UNIQUE (`tenant_id`, `barcode`) where not null |
| `minimum_stock` | decimal(18,3) | no | default 0 |
| `is_active` | boolean | no | default true |
| `notes` | text | yes | |
| `created_by` | FK → users | no | RESTRICT |
| timestamps | | no | |

UNIQUE (`tenant_id`, `item_number`). Indexes: (`tenant_id`, `is_active`), (`tenant_id`, `category_id`), (`tenant_id`, `name`).

---

## 6. `inventory_movement_number_sequences`

Format `MOV-######`.

---

## 7. `inventory_balances`

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | bigint PK | no | |
| `tenant_id` | FK → tenants | no | |
| `warehouse_id` | FK → warehouses | no | RESTRICT |
| `inventory_item_id` | FK → inventory_items | no | RESTRICT |
| `on_hand` | decimal(18,3) | no | default 0; **never client-filled** |
| timestamps | | no | |

UNIQUE (`tenant_id`, `warehouse_id`, `inventory_item_id`).
INDEX (`tenant_id`, `warehouse_id`), (`tenant_id`, `inventory_item_id`), (`tenant_id`, `on_hand`).

Created lazily on first movement (or explicitly when ensuring lock target). Zero rows may remain.

---

## 8. `inventory_movements`

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | bigint PK | no | |
| `tenant_id` | FK → tenants | no | |
| `movement_number` | string(20) | no | Immutable `MOV-######` |
| `type` | string(32) | no | See BUSINESS_RULES |
| `warehouse_id` | FK → warehouses | no | RESTRICT |
| `inventory_item_id` | FK → inventory_items | no | RESTRICT |
| `quantity` | decimal(18,3) | no | Always > 0 |
| `direction` | string(8) | no | `in` \| `out` (redundant with type except adjustment clarity) |
| `balance_before` | decimal(18,3) | no | Snapshot |
| `balance_after` | decimal(18,3) | no | Snapshot |
| `transfer_group_id` | uuid/char(36) | yes | Set for transfer pair |
| `reason` | string(1000) | no | Required |
| `reference` | string(255) | yes | External/manual ref |
| `performed_by` | FK → users | no | RESTRICT |
| `occurred_at` | timestamp | no | Server default now; not client-backdated in MVP |
| `correlation_id` | string(64) | yes | From request correlation |
| timestamps | | no | |

UNIQUE (`tenant_id`, `movement_number`).
Indexes: (`tenant_id`, `warehouse_id`, `occurred_at`), (`tenant_id`, `inventory_item_id`, `occurred_at`), (`tenant_id`, `type`), (`tenant_id`, `transfer_group_id`), (`tenant_id`, `performed_by`).

**No UPDATE/DELETE API.** DB users with app credentials must not soft-edit quantities.

### Explicitly absent

- `purchase_order_id`, supplier FKs
- unit conversion tables
- lot/serial tables
- transfer request/status workflow tables
- SoftDeletes

### Model contracts

- All tables TenantOwned + UsesTenantScope (except sequences keyed by tenant_id PK).
- Mass-assignment: never fill `tenant_id`, numbers, `on_hand`, `balance_before`/`after` from generic client PATCH.

### Morph map (Documents — at inventory implementation)

```
warehouse → Warehouse
inventory_item → InventoryItem
```

(Already reserved conceptually: `warehouse`, `asset`, `custody` in ADR-0010; add `inventory_item`.)

## Config (future)

`config/inventory.php`:

- number prefixes / pads
- `units` allow-list
- `quantity_decimals` = 3
- `negative_stock_allowed` = false (MVP constant; config switch deferred)

## Migration notes

- Do **not** run in Sprint 014 specification.
- Depends on: tenants, users, organization_units, employees.
- No DB FK to Documents (morph application-level).
