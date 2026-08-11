# Warehouses and Inventory — Acceptance Criteria

> **Status:** Draft
> **Last updated:** 2026-08-06

- [ ] Warehouses CRUD works, tenant-scoped; codes unique per tenant.
- [ ] Items managed with code, unit, barcode/QR, minimum stock level.
- [ ] All five transaction types work with correct balance effects.
- [ ] Balances always equal the sum of transactions — no direct editing anywhere.
- [ ] A transaction producing negative stock is rejected (until configuration exists).
- [ ] Adjustments require `inventory.adjust` and a reason.
- [ ] Every transaction records source, destination, actor, time, reason — and is audited.
- [ ] Transactions are immutable via the API.
- [ ] Low-stock detection works against minimum levels.
- [ ] Cross-tenant access returns `404`; transfers cannot cross tenants.
