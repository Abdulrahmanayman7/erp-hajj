# Assets and Custodies — Business Rules

> **Status:** Implemented (Sprint 015)
> **Last updated:** 2026-08-11
> ADR: [ADR-0012](../../10-decisions/ADR-0012-ASSET-CUSTODY-AND-OWNERSHIP.md)

## 1. Domain identity (binding)

1. An **Asset** is an individually tracked physical or registered resource (device, furniture, vehicle registry entry, etc.).
2. A **Custody** is the controlled handover of an Asset to an **Employee** (not a User FK as recipient).
3. Assets ≠ Inventory items; Custodies ≠ Inventory stock returns; Custodies ≠ Asset identity.
4. Inventory owns quantity SoR ([ADR-0011](../../10-decisions/ADR-0011-INVENTORY-LEDGER-BALANCE-AND-TRANSFER.md)). Assets/Custodies **must never** write `inventory_balances` directly.
5. Sprint 015 does **not** auto-post inventory movements and does **not** store `inventory_item_id` on Asset.

## 2. Asset registry

1. Tenant-owned; numbered `AST-######` (server-generated, immutable, sequence + `FOR UPDATE`).
2. Fields (final MVP): see [DATA_MODEL.md](DATA_MODEL.md).
3. Optional `serial_number` — unique per tenant when present (multiple NULLs allowed).
4. Optional `barcode` — searchable metadata string only; hardware scanning deferred. Unique per tenant when present.
5. Optional `purchase_value` — informational DECIMAL only (no currency FX / depreciation).
6. Optional `acquisition_date` — metadata date.
7. Optional `warehouse_id` — **home/storage warehouse** (same tenant). Not a stock balance. May remain set while `in_use` (home warehouse). Cleared only when explicitly updated.
8. Optional `organization_unit_id` — owning/responsible unit (not physical location).
9. Optional category via `asset_categories`.
10. **No** `current_employee_id` as source of truth — current assignee is derived from the **active custody** (optional cache `current_custody_id` only).

## 3. Asset statuses (persisted)

| Status | Meaning |
|---|---|
| `available` | Can receive a new custody assignment |
| `in_use` | Has an **active** custody (Workflow stages “Assigned as Custody” / “In Use”) |
| `maintenance` | Out of assignable service; no new custody |
| `damaged` | Damaged; no new custody until restored to `available` or retired/lost |
| `retired` | Terminal for operational use; history retained |
| `lost` | Declared lost; no new custody; history retained |

### Resolved conflict with early stubs

- Stub enum used **Assigned** as an asset status. **Dropped.** Assignment is represented by an active **Custody** row; Asset status becomes `in_use`.
- Workflow 3 narrative “Returned” is a **custody completion**, not an asset status. On return, Asset moves to a chosen next status (`available` / `maintenance` / `damaged` / `retired`).
- **Damaged** and **Lost** remain first-class statuses (approved stub vocabulary), entered via explicit actions / return outcome — not silent PATCH.

### Transition matrix (MVP)

| From | To | How |
|---|---|---|
| `available` | `in_use` | **Assign custody** |
| `in_use` | `available` \| `maintenance` \| `damaged` \| `retired` | **Return custody** (`next_status`) |
| `available` | `maintenance` | Send to maintenance |
| `maintenance` | `available` | Restore from maintenance |
| `available` \| `maintenance` \| `damaged` | `retired` | Retire (reason required) |
| `available` \| `maintenance` \| `damaged` \| `in_use`* | `lost` | Declare lost (reason required; *if `in_use`, close custody in same txn then set `lost`) |
| `damaged` | `available` | Restore after repair |
| `retired` \| `lost` | — | **No reopen** in MVP |

No generic `PATCH status`. Only documented action endpoints.

## 4. Categories

1. Tenant-owned `asset_categories`: unique `name` per tenant; `is_active`; optional description.
2. New category assignment requires active category; historical inactive category may remain linked.
3. Hard delete only if unreferenced; prefer deactivate.
4. Category list: `assets.view`. Category write: `assets.update` (no separate `asset_categories.*` verbs).

## 5. Custody (append-only)

1. First-class tenant-owned `asset_custodies`, numbered `CUS-######`.
2. Status: `active` \| `returned` only.
3. **At most one `active` custody per asset** (enforced under asset `FOR UPDATE` lock; see ADR-0012).
4. Assign requires: Asset `available`; Employee same tenant and **active**; optional expected return date ≥ assignment date when both set.
5. Receiver is always **Employee** (never User as FK). Assigned-by / returned-by are Users (actors).
6. Return requires: active custody; sets `returned_at`, `returned_by`, return condition/notes; Asset `next_status` required from allowed set.
7. Custody rows are **immutable after return**. No PATCH/DELETE API. Corrections deferred (no void endpoint in MVP).
8. Condition values (custody snapshots + optional asset `condition`): `good` \| `fair` \| `damaged` \| `unknown`.

## 6. Condition vs status

1. **Status** = operational lifecycle (assignability / terminal).
2. **Condition** = physical state snapshot (especially at assign/return).
3. Returning with condition `damaged` does **not** auto-force Asset status `damaged` — caller chooses `next_status`. UX should suggest `damaged`/`maintenance` when condition is damaged.

## 7. Concurrency (custody)

1. Assign and return run in a DB transaction.
2. Lock Asset row `SELECT … FOR UPDATE`.
3. Re-check Asset status + absence/presence of active custody after lock.
4. Insert/update custody + update Asset status (+ optional `current_custody_id`) + append status transition + audit; commit.
5. Concurrent second assign → `ASSET_ALREADY_ASSIGNED` or `ASSET_CUSTODY_CONFLICT`.

## 8. Delete / retirement

1. Prefer lifecycle (`retired` / `lost`) over hard delete.
2. Hard delete Asset only if: **no** custodies, **no** status transition history beyond optional create snapshot, **no** Documents linked, and status never requires active custody.
3. Never cascade-delete custody or transition history.
4. Categories: hard delete only when unused.

## 9. Documents

1. Morph aliases at implementation: `asset`, `custody`.
2. Host hard-delete blocked when Documents remain (`DOCUMENT_ENTITY_IN_USE`).

## 10. Notifications

Hooks only (e.g. future overdue expected return). No delivery in Sprint 015.

## 11. Audit

See [API.md](API.md). Include asset/custody numbers, employee, statuses before/after, reason, correlation ID. Never secrets.

## Deferred former TBDs

| Item | Lock |
|---|---|
| Asset code generation | `AST-######` |
| Assigned vs in_use | `in_use` + active custody |
| Custody correction permission | Deferred |
| Receiver acknowledgment | Deferred |
| Damaged/Lost liability workflow | Status actions only; liability TBD later |
| Inventory auto-post | Deferred |
| `inventory_item_id` | Deferred |
| Own-custody access | Requires `assets.view` + Employee↔User link (ADR-0012) |
