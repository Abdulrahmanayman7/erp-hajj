# Module: Assets and Custodies (الأصول والعُهد)

> **Status:** Specified (Sprint 015) — **not implemented**
> **Last updated:** 2026-08-11
> Binding ADR: [ADR-0012](../../10-decisions/ADR-0012-ASSET-CUSTODY-AND-OWNERSHIP.md)

## Purpose

Provide tenant-isolated **individually tracked physical assets** and their **immutable custody (عهدة) handover history** for ERP Hajj.

This module answers Workflow 3 ([CORE_WORKFLOWS.md](../../01-business/CORE_WORKFLOWS.md)):
**Asset → Available → Assigned as Custody → In Use → Returned → Available / Maintenance / Retired** (+ Damaged / Lost as controlled statuses).

## Domain separations (binding)

| Concept | This module | Not this module |
|---|---|---|
| **Asset** | Individually tracked physical/registered resource (`AST-######`) | InventoryItem (quantity SKU) |
| **Custody** | Append-only handover of an Asset to an Employee | Inventory `return` (stock return-to-warehouse) |
| **Warehouse FK** | Optional **home/storage** location metadata | Inventory balance / stock quantity |
| **Organization unit** | Optional owning/responsible department | Physical location |
| **Maintenance status** | Lifecycle flag only | Maintenance work-orders / technicians / spare parts |
| **Purchase value** | Optional informational decimal | Finance / depreciation / accounting |

## Sprint 015 scope

- Asset categories (tenant catalog) + Asset registry CRUD + activate-style lifecycle actions.
- Immutable numbering `AST-######` / custody `CUS-######`.
- Asset statuses: `available`, `in_use`, `maintenance`, `damaged`, `retired`, `lost`.
- First-class append-only **custodies** with **at most one active custody per asset**.
- Assign / return concurrency-safe under asset row lock.
- Optional `warehouse_id` (home/storage) and `organization_unit_id` (owner unit).
- Documents morph aliases `asset` + `custody` (register at implementation).
- Permissions `assets.*` (including assign/return/retire); Policies; audit; API; RTL UI; Pest/Vitest plans.
- Assignee **self-view** of own custodies (ADR-0012), capability-gated like Tasks.

## Out of scope (explicit)

- Procurement / suppliers / PO → asset creation workflow.
- Auto stock deduction / inventory movement emission when creating or assigning Assets (may integrate later via Inventory domain actions only).
- `inventory_item_id` link on Asset (deferred).
- Maintenance management (orders, vendors, parts, SLA).
- Finance: depreciation, capitalization journals, disposal accounting.
- Custody correction/void after return (compensating process deferred).
- Receiver digital acknowledgment / printed handover form.
- Fleet operations, GPS, utilization analytics.
- SoftDeletes on custody/transition history.
- Barcode/QR hardware scanning (optional serial/barcode string metadata only if documented on Asset).

## Personas

| Persona | Usage |
|---|---|
| Tenant Owner / GM | Full asset + custody control |
| Department Manager | Register/operate; may lack hard delete / retire (see PERMISSIONS) |
| Supervisor | View + limited operations per grants |
| Employee | View **own** custodies / assigned assets (self-view) |
| Auditor | View assets + custody/status history |

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

- [CORE_WORKFLOWS.md](../../01-business/CORE_WORKFLOWS.md) Workflow 3
- [ADR-0011](../../10-decisions/ADR-0011-INVENTORY-LEDGER-BALANCE-AND-TRANSFER.md) — Inventory ≠ Asset
- [ADR-0010](../../10-decisions/ADR-0010-DOCUMENT-STORAGE-AND-ENTITY-LINKS.md) — Documents morph
- [ADR-0009](../../10-decisions/ADR-0009-TASK-ASSIGNEE-AND-DECISION-GATE.md) — self-service pattern
- [10-warehouses-and-inventory/](../10-warehouses-and-inventory/)
- [04-employees-and-supervisors/](../04-employees-and-supervisors/)
