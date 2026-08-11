# Warehouses and Inventory — Test Plan

> **Status:** Draft
> **Last updated:** 2026-08-06

- **Cross-tenant (mandatory):** tenant A cannot see tenant B warehouses/items/transactions (`404`); transfer to a tenant B warehouse rejected.
- Balance math: sequence of addition/issue/return/transfer/adjustment yields correct per-warehouse balances.
- Negative stock: issue exceeding balance rejected; boundary (exact balance) succeeds.
- Immutability: no update/delete on transactions through the API.
- Permissions: each transaction type requires its own permission; adjustment without reason rejected.
- Audit: every transaction creation audited with actor/reason.
- Low stock: crossing minimum level flags the item (dashboard integration).
- Frontend: transaction form field switching per type; read-only balances.
- E2E: full inventory cycle — add stock → transfer → issue → verify balances.
