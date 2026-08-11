# ADR-0012: Asset custody and current ownership model

- **Status:** Accepted (implemented Sprint 015)
- **Date:** 2026-08-11
- **Sprint:** 015 (Assets & Custodies)
- **Deciders:** ERP Hajj product / engineering (documentation lock)

## Implementation note (2026-08-11)

Shipped as vertical slice: migrations (`asset_number_sequences`, `asset_categories`, `assets`, `asset_status_transitions`, `asset_custody_number_sequences`, `asset_custodies`, `assets.current_custody_id` FK), assign/return under Asset `FOR UPDATE`, Policies, `assets.*` catalog via `PermissionCatalog`, Documents morph aliases `asset` / `custody`, Pest `AssetTest` (20/20 — tenancy, single active custody, return consistency, self-view, RBAC, no inventory side effects, document morph + delete guard), Vue `assets` module. No Procurement, maintenance work-orders, depreciation, `inventory_item_id`, inventory auto-post, or custody void/correction.

## Context

Workflow 3 requires individually tracked Assets with controlled custody handovers and immutable history. Early stubs listed six asset statuses including **Assigned**, overlapping Workflow 3’s “Assigned as Custody / In Use / Returned” narrative. They also left open: asset numbering, one-active-custody DB strategy, whether `current_employee_id` is SoR, warehouse meaning vs Inventory, Damaged/Lost flows, own-custody access, and Documents morph registration.

ADR-0011 already forbids Assets/Custodies from becoming inventory quantity SoR.

## Decision

### 1. Sprint 015 delivers Assets **and** Custodies together

One module (`11-assets-and-custodies`), permissions remain under `assets.*` (`assign` / `return`), no separate `custodies.*` catalog in MVP.

### 2. Asset status vs custody

Persisted Asset statuses: `available`, `in_use`, `maintenance`, `damaged`, `retired`, `lost`.

- Drop stub status **Assigned** — active **Custody** implies Asset `in_use`.
- “Returned” is custody completion, not an Asset status; return chooses `next_status`.

### 3. Custody is append-only first-class history

- Table `asset_custodies` with `CUS-######`.
- Status `active` \| `returned`.
- After return, row is immutable (no void in MVP).
- `asset_status_transitions` records lifecycle with optional `custody_id` link.

### 4. Current assignee / ownership SoR

- **SoR for holder:** the single `active` custody row (Employee FK).
- Optional cache `assets.current_custody_id` for list performance — never client-writable.
- **No** `current_employee_id` column as SoR.

### 5. Single active custody + concurrency

- Transaction + `SELECT … FOR UPDATE` on Asset.
- Re-validate status and no other active custody after lock.
- MySQL has no partial unique index — application enforcement is binding; cache helps reads.

### 6. Warehouse / org / inventory boundaries

- `warehouse_id` = optional **home/storage** warehouse metadata (may remain while `in_use`).
- `organization_unit_id` = owning unit metadata.
- No `inventory_item_id`; no auto inventory movements in Sprint 015.

### 7. Holder self-view

Mirror ADR-0009 spirit: User↔Employee link + `assets.view` allows viewing own custodies / those assets; cannot mutate without `assets.assign`/`return`/etc.

### 8. Terminal statuses

`retired` and `lost` do not reopen in MVP. Prefer them over hard delete once history exists.

## Consequences

- Implementation must ship sequences, transition table, custody immutability, and double-assign tests.
- UI must not expose generic status editors.
- Future Procurement/Maintenance integrate without rewriting custody SoR.
- Future inventory “capitalize stock → asset” requires explicit Inventory domain actions + Change Request for `inventory_item_id`.

## Alternatives rejected

| Alternative | Why rejected |
|---|---|
| Assets-only sprint without custody | Breaks Workflow 3 and module stubs |
| `current_employee_id` only | Loses immutable handover history |
| Keep status `assigned` plus `in_use` | Duplicate/ambiguous with custody |
| Partial unique DB index only | Not portable on MySQL MVP |
| Auto inventory post on assign | Violates ADR-0011 ownership; out of 015 |
| Separate `custodies.*` permissions | Duplicates approved `assets.assign`/`return` |

## References

- [11-assets-and-custodies/](../09-modules/11-assets-and-custodies/)
- [CORE_WORKFLOWS.md](../01-business/CORE_WORKFLOWS.md) Workflow 3
- [ADR-0011](ADR-0011-INVENTORY-LEDGER-BALANCE-AND-TRANSFER.md)
- [ADR-0009](ADR-0009-TASK-ASSIGNEE-AND-DECISION-GATE.md)
- [ADR-0010](ADR-0010-DOCUMENT-STORAGE-AND-ENTITY-LINKS.md)
