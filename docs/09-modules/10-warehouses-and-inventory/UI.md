# Warehouses and Inventory — UI

> **Status:** Implemented (Sprint 014)
> **Last updated:** 2026-08-11
> Shell: existing AppSidebar / RTL layout — **do not redesign**.

## Module placement

| Item | Value |
|---|---|
| Frontend paths | `frontend/src/modules/warehouses/` and/or `frontend/src/modules/inventory/` (may share package; **lock: one module folder `inventory` owning warehouses+stock screens**, or split if structure prefers — recommendation: **`inventory` module** with warehouses pages nested, sidebar two entries) |
| Routes | `/app/warehouses`, `/app/warehouses/:id`, `/app/inventory`, `/app/inventory/items`, `/app/inventory/items/:id`, `/app/inventory/movements` |
| Sidebar | **المستودعات** (`warehouses.view`) then **المخزون** (`inventory.view`) — after الوثائق |
| Theme | Light, Arabic RTL, deep green + gold |

**Recommendation lock:** implement as `frontend/src/modules/inventory/` containing warehouses + stock UX to avoid duplicated clients; expose both sidebar entries.

## Screens

### Warehouses list — المستودعات

- Header + CTA إنشاء مستودع (`warehouses.create`) → Drawer.
- Filters: search, active/inactive, org unit, responsible employee.
- Desktop table / mobile cards: number, name, location, org unit, responsible, status, actions.
- Row → details.

### Warehouse details

Sections:

1. **نظرة عامة** — number, name, location, org, responsible, status, notes
2. **المخزون الحالي** — balances for this warehouse
3. **أحدث الحركات** — recent movements
4. **المستندات** — Documents widget (`warehouse` morph) when Documents permission allows

KPIs: distinct items count, low-stock count, out-of-stock count.
**Do not** show a single “total units” summing incompatible UOMs.

### Inventory stock overview — المخزون

Operational balances table:

| Column | Content |
|---|---|
| الصنف | item number + name |
| المستودع | warehouse |
| التصنيف | category |
| الوحدة | unit |
| الرصيد | on_hand |
| الحد الأدنى | minimum_stock |
| الحالة | derived badge: طبيعي / منخفض / نافد |
| آخر حركة | optional |

Filters: warehouse, category, stock_state, search.
CTAs (permission-gated): استلام، صرف، إرجاع، تحويل، تسوية.

### Items — الأصناف

- List + create/edit drawer (`inventory.manage_items`).
- Fields: name, category, unit, barcode, minimum_stock, notes, active.
- Details: item meta + per-warehouse balances + movements.

### Categories manager

Drawer/manager pattern (like document/contract categories) behind `inventory.manage_items`.

### Movements — سجل الحركات

Append-only history. Columns: number, type, item, warehouse, qty (+/−), before, after, reason, reference, user, datetime.
Transfers: show linked pair / shared `transfer_group_id`.
**No edit/delete actions.**

## Stock action UX

Use Drawers/Dialogs — **not** inline editable balance cells.

| Action | Arabic CTA | Notes |
|---|---|---|
| Receipt / opening | استلام مخزون | toggle/type opening vs receipt |
| Issue | صرف مخزون | |
| Return | إرجاع للمخزن | |
| Transfer | تحويل بين المخازن | source ≠ dest selectors |
| Adjustment | تسوية مخزون | direction + qty + **mandatory reason**; danger styling |

Client validates quantity > 0; server authoritative. Show insufficient-stock errors clearly.

## Responsive

| Breakpoint | Behavior |
|---|---|
| Desktop | Tables + filter toolbar + drawers |
| Mobile | Cards; full-width drawers; stacked actions; large touch targets |

RTL throughout. No dark mode.

## Query invalidation

Invalidate balances, movements, item/warehouse detail after every stock action. Avoid full-app invalidation.

## Explicit non-goals

- Barcode camera scanner
- Printable barcode labels
- Multi-step transfer wizard
- Editable ledger rows
