# Warehouses and Inventory — Business Rules

> **Status:** Implemented (Sprint 014)
> **Last updated:** 2026-08-11
> ADR: [ADR-0011](../../10-decisions/ADR-0011-INVENTORY-LEDGER-BALANCE-AND-TRANSFER.md)

## 1. Source of truth (binding)

1. **Append-only `inventory_movements` are the authoritative stock history.**
2. **`inventory_balances.on_hand` is a materialized cache** for operational reads and concurrency control.
3. Clients **never** PATCH balance or PATCH movement quantity.
4. Every stock change is a domain action that, **in one DB transaction**:
   - locks the relevant balance row(s) (`SELECT … FOR UPDATE`);
   - validates quantity rules;
   - inserts immutable movement row(s);
   - updates cached balance(s);
   - writes audit event(s).
5. If ledger and balance diverge (ops anomaly), ledger wins; rebuild is an operational procedure (not MVP UI).

This satisfies [DATABASE_PRINCIPLES.md](../../03-database/DATABASE_PRINCIPLES.md):
“Inventory quantities are derived from transaction rows — no directly edited balance column as source of truth.”
**Clarification (ADR-0011):** “derived” means **ledger-authored**; the balance column is **not** a client-editable source of truth.

## 2. Warehouse

1. Tenant-owned; numbered `WH-######` (server-generated, immutable, sequence + `FOR UPDATE`).
2. Fields (final): `warehouse_number`, `name`, `description` nullable, `location` nullable text, optional `organization_unit_id`, optional `responsible_employee_id`, `is_active`, `notes` nullable, `created_by`, timestamps.
3. **No separate free-form “code”** besides `warehouse_number` (avoids dual identifiers).
4. New stock mutations require warehouse **active**. Historical movements remain valid after deactivate.
5. Hard delete only when **no** movements, **no** balance rows, and no Documents linked. Prefer deactivate.
6. `organization_unit_id` / `responsible_employee_id` are **metadata** — they do **not** grant RBAC access.
7. Assigning org unit / employee: same tenant; target must be active at assign time; historical inactive references remain readable.

## 3. Inventory item master

1. Tenant-owned item identity shared across warehouses (one item, many warehouse balances).
2. Numbered `ITM-######` (immutable, sequence + `FOR UPDATE`).
3. Fields: `item_number`, `name`, `description` nullable, optional `category_id`, `unit` (fixed base UOM), optional `barcode` (unique per tenant when set), `minimum_stock` (DECIMAL ≥ 0), `is_active`, `notes` nullable, `created_by`, timestamps.
4. **One fixed base unit per item** — no conversions in MVP.
5. Allow-list units (config): `piece`, `box`, `pack`, `set`, `kg`, `g`, `liter`, `ml`, `meter`, `cm`. Custom units deferred.
6. Quantity type: **`DECIMAL(18, 3)`** — never floating binary; app validates `quantity > 0` with max 3 decimal places.
7. Deactivate preferred when movements/balances exist; hard delete only if unused (no movements, no balances).
8. Inactive item: cannot be used in **new** stock mutations; history retained.
9. Barcode/QR **scanning hardware** deferred; `barcode` is optional searchable metadata only. No QR image generation.

## 4. Categories

1. Tenant-owned `inventory_categories`: `name` unique per tenant, `description` nullable, `is_active`.
2. Pattern mirrors contract/document categories: deactivate preferred; hard delete only if unreferenced.
3. New item assignment requires active category; historical inactive category may remain linked.
4. Managed under `inventory.manage_items` (no separate `inventory_categories.*` verbs).

## 5. Movement types

| Type | Effect on warehouse balance | Permission |
|---|---|---|
| `opening` | + inbound | `inventory.add` |
| `receipt` | + inbound (manual addition; **not** a PO) | `inventory.add` |
| `issue` | − outbound | `inventory.issue` |
| `return` | + inbound (return-to-stock) | `inventory.return` |
| `transfer_out` | − at source | `inventory.transfer` |
| `transfer_in` | + at destination | `inventory.transfer` |
| `adjustment` | + or − via signed delta | `inventory.adjust` |

1. Quantity on the movement row is always **positive** `DECIMAL(18,3)`.
2. For `adjustment`, payload includes `direction` = `in` \| `out` plus positive `quantity`.
3. Movements are **immutable** after insert. No void endpoint. Corrections = compensating movements.
4. Each movement stores optional **balance_before** / **balance_after** snapshots for the affected warehouse+item (audit/UX; not independently authoritative).

## 6. Receipt / opening

1. Manual inbound into one warehouse for one item.
2. `opening` preferred for first stock introduction; `receipt` for subsequent manual additions.
3. Both allowed when warehouse+item active (no hard once-only gate).
4. Requires reason/reference text (non-empty).
5. Procurement later must call inventory domain actions — never update `inventory_balances` directly.

## 7. Issue

1. Outbound from one warehouse; sufficient `on_hand` required after lock.
2. Mandatory reason.
3. Future Assets/Custodies may trigger issues; MVP is manual issue only.

## 8. Return

1. Inbound “return to stock” (e.g. unused issued materials). **Not** asset custody return (Assets module).
2. Mandatory reason.
3. No required prior-issue FK in MVP (optional free-text `reference`). Linked `source_movement_id` deferred.

## 9. Transfer (atomic)

1. **Immediate atomic transfer** (no request/dispatch/receive workflow in MVP).
2. Source ≠ destination; both warehouses active; same tenant; item active.
3. Sufficient source stock; quantity > 0.
4. Single transaction:
   - lock both balance rows in **deterministic order** (ascending `warehouse_id`, then `inventory_item_id`);
   - insert `transfer_out` + `transfer_in` sharing `transfer_group_id` (UUID);
   - update both balances;
   - audit `STOCK_TRANSFERRED` once (payload includes both movement ids / group id).
5. Any failure rolls back both legs. No orphan single-leg transfer.

## 10. Adjustment

1. Explicit delta only — **never** “set quantity = X”.
2. Mandatory reason (min length enforced).
3. Outbound adjustment blocked by insufficient stock (same as issue).
4. Sensitive: `inventory.adjust` granted narrowly.

## 11. Negative stock

1. **MVP lock: negative stock is forbidden.**
2. After lock, if resulting `on_hand < 0` → `INVENTORY_INSUFFICIENT_STOCK` (422).
3. Exact zero after outbound is allowed.
4. Per-tenant/per-item negative-stock configuration: **deferred** (Change Request later). No config UI now.

## 12. Concurrency

1. Mutating actions lock `inventory_balances` row(s) with `FOR UPDATE` inside a transaction.
2. Missing balance row: ensure zero row exists, then lock — deterministic.
3. Transfer lock order: sort by `warehouse_id` ASC (both rows same `inventory_item_id`).
4. Frontend stock figures are informational only; server re-validates after lock.

## 13. Low stock

1. Derived, not persisted: `on_hand <= minimum_stock` → `low`; `on_hand = 0` → `out_of_stock`; else `normal`.
2. Dashboard widgets remain deferred; **in-app** `STOCK_BELOW_MINIMUM` delivery is owned by Sprint 016 (ADR-0013) — recipient = warehouse `responsible_employee` User only (skip if none). Detection remains queryable via balances/`stock_state`.

## 14. Documents

1. At implementation, register morph aliases `warehouse` and `inventory_item`.
2. Linking movements as Document hosts: **deferred**.
3. Host hard-delete guards when Documents remain linked (same pattern as Contracts/Tasks).

## 15. Notifications (Sprint 016 ownership)

Type `STOCK_BELOW_MINIMUM` — see [12-notifications/BUSINESS_RULES.md](../12-notifications/BUSINESS_RULES.md). Inventory movements may trigger the dispatcher after commit; daily scanner backs it. No GM broadcast.

## 16. Audit

See [API.md](API.md). Never log secrets; include tenant, actor, correlation ID, warehouse/item numbers, quantities, before/after when available, reason, transfer_group_id.

## Resolved former TBDs

| Former TBD | Lock |
|---|---|
| Negative-stock config | Deferred; MVP always blocks negative |
| Void vs compensate | Compensate only |
| Item categories | Tenant `inventory_categories` table |
| Units list | Fixed allow-list; one base unit per item |
| Item manage permission | `inventory.manage_items` |
| Transaction API shape | Explicit action endpoints |
| Transfer workflow | Atomic immediate |
| Balance materialization | Cached balance + ledger (ADR-0011) |
| Warehouse code vs number | `warehouse_number` only (`WH-######`) |
| Barcode scanning | Deferred; optional string field only |
