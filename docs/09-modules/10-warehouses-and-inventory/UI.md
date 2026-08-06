# Warehouses and Inventory — UI (planned)

> **Status:** Planned — no pages exist
> **Last updated:** 2026-08-06

## Pages

- **Warehouses list** — Data Table; create/edit forms with responsible-person selector.
- **Inventory items list** — Data Table with search (code, name, barcode), category filter, low-stock indicator.
- **Item details** — item data, per-warehouse balances, transaction history (Timeline/Data Table).
- **New transaction form** — type selector drives visible fields (source/destination warehouses, quantity, reason); permission-gated per type.

## Rules

- Balances are read-only everywhere — no editable stock fields in any UI.
- Adjustment requires a reason field (mandatory).
- Low stock (below minimum level) flagged visually and feeds the dashboard widget.

## TBD

- Barcode/QR scanning support in the web UI: not specified — do not build without approval.
