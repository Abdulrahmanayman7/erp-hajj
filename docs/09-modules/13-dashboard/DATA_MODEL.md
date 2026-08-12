# Dashboard — Data Model

> **Status:** Specification complete — implementation pending (Sprint 017)
> **Last updated:** 2026-08-12

## Ownership

The Dashboard module **owns no business tables** and **adds no migrations** in Sprint 017.

All indicators are computed read-only from existing tenant-owned tables.

## Source map

| Dashboard concept | Tables / resolvers |
|---|---|
| Tasks KPIs / lists | `tasks` (+ open/overdue semantics) |
| Contracts KPIs / lists | `contracts` + `config('contracts.expiring_soon_days')` |
| Meetings KPIs / lists | `meetings` (`scheduled_at` UTC) |
| Decisions KPIs | `decisions`; open-task gate via `tasks.decision_id` |
| Inventory KPIs | `inventory_balances` + `inventory_items.minimum_stock` + `StockStateResolver` |
| Assets KPIs | `assets.status` |
| Custody KPIs / lists | `asset_custodies` (active + `expected_return_at`) |
| Unread notifications | `notifications` (`recipient_user_id`, `read_at IS NULL`) |

## Caching / materialization

- **MVP:** no `dashboard_*` tables, no Redis Dashboard cache keys.
- Conceptual future (out of Sprint 017): any cache **must** use `tenant:{id}:…` namespace and a permission fingerprint — see ADR-0014. Not approved for implementation now.

## Preferences

Per-user Dashboard layout customization: **out of scope** (requires Change Request).

## Migrations

| Decision | Value |
|---|---|
| Migrations added by Sprint 017 | **None** |
| Schema changes to source modules | **None** |
