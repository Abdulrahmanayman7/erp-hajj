# ADR-0011: Inventory ledger, balance cache, and transfer consistency

- **Status:** Accepted (implemented Sprint 014)
- **Date:** 2026-08-11
- **Sprint:** 014 (Warehouses & Inventory)
- **Deciders:** ERP Hajj product / engineering (documentation lock)

## Implementation note (2026-08-11)

Shipped as vertical slice: migrations (`warehouse_number_sequences`, `warehouses`, `inventory_categories`, `inventory_item_number_sequences`, `inventory_items`, `inventory_movement_number_sequences`, `inventory_balances`, `inventory_movements`), `InventoryBalanceLocker` (`FOR UPDATE` + race-safe ensure row; transfer locks by `warehouse_id` ASC), stock Actions, Policies, `warehouses.*` / `inventory.*` catalog, Documents morph `warehouse` / `inventory_item`, Pest `InventoryTest` (I01–I30), Vue `inventory` module. No Procurement, multi-UOM conversions, multi-step transfer workflow, or negative-stock configuration UI.

## Context

Workflow 4 and [DATABASE_PRINCIPLES.md](../03-database/DATABASE_PRINCIPLES.md) require inventory quantities to come from transactions — never from a client-editable balance. Early module stubs left open: materialization strategy, negative-stock configuration, void vs compensate, transfer workflow depth, item-manage permission, and unit conversions.

Concurrent stock mutations (two issues, transfers) are race-prone without explicit locking. Assets/Custodies and future Procurement must not invent parallel stock stores.

## Decision

### 1. Ledger + cached balance (Option C)

- **Authoritative history:** append-only `inventory_movements`.
- **Operational quantity:** `inventory_balances.on_hand` maintained **only** inside the same DB transaction as the movement insert.
- Clients cannot PATCH balances or movements.
- Compensating movements only (no void/edit of history).

### 2. No negative stock (MVP)

- After `FOR UPDATE`, reject any mutation that would yield `on_hand < 0` with `INVENTORY_INSUFFICIENT_STOCK`.
- Negative-stock configuration remains **deferred** (Change Request).

### 3. Deterministic locking

- Mutate under row locks on `inventory_balances`.
- Transfers lock both warehouses’ balance rows ordered by **`warehouse_id` ASC** (same item) to prevent AB-BA deadlocks.
- Frontend balances are advisory.

### 4. Atomic immediate transfers

- MVP transfer is one transaction producing `transfer_out` + `transfer_in` sharing `transfer_group_id`.
- No request → dispatch → receive workflow in MVP.

### 5. Fixed base unit per item

- One allow-listed UOM per item; quantities `DECIMAL(18,3)`.
- Unit conversions deferred.

### 6. Manual receipt before Procurement

- `inventory.add` covers opening/receipt.
- Procurement (out of MVP) must integrate by calling inventory domain actions — never writing balances directly.

### 7. Boundaries

| Domain | Owns | Must not |
|---|---|---|
| Inventory (014) | Warehouses, items, balances, movements | Assets identity, custody history, PO |
| Assets/Custodies (later) | Individual assets + custody ledger | Become the stock quantity system of record |
| Documents (013) | Files; morph `warehouse` / `inventory_item` | Store quantities |
| Procurement (future) | Suppliers / POs | Bypass inventory ledger |

### 8. Permissions

- Keep `warehouses.*` and `inventory.add|issue|transfer|return|adjust`.
- Add `inventory.manage_items` for item/category master data (resolves stub TBD).

## Consequences

- Implementation must ship sequences, balances uniqueness, movement immutability, and concurrency tests.
- Dashboard low-stock widgets and notification delivery remain later consumers of derived stock_state.
- Stub wording “balances computed live vs cached” is **resolved** as cached-with-ledger.

## Alternatives rejected

| Alternative | Why rejected |
|---|---|
| Balance-only mutable table | Violates auditability / DATABASE_PRINCIPLES |
| Live SUM(ledger) on every list row | Poor performance under growth |
| Multi-step transfer workflow | Over-build for MVP |
| Allow negative stock now | Conflicts with approved Workflow 4 default |
| Unit conversion engine | Unnecessary complexity |

## References

- [10-warehouses-and-inventory/](../09-modules/10-warehouses-and-inventory/)
- [CORE_WORKFLOWS.md](../01-business/CORE_WORKFLOWS.md) Workflow 4
- [DATABASE_PRINCIPLES.md](../03-database/DATABASE_PRINCIPLES.md)
- [ADR-0010](ADR-0010-DOCUMENT-STORAGE-AND-ENTITY-LINKS.md)
- [OUT_OF_SCOPE.md](../00-project/OUT_OF_SCOPE.md) (Procurement)
