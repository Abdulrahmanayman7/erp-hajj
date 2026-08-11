# Warehouses and Inventory — Business Rules

> **Status:** Approved
> **Last updated:** 2026-08-06

- **Inventory quantities are calculated from transactions** — there is no directly editable balance.
- **Manual stock editing is restricted**: corrections happen only through Adjustment transactions with `inventory.adjust` and a required reason.
- Every transaction records **source, destination, actor, time, and reason**.
- **Negative stock is blocked** unless explicitly configured later (configuration mechanism TBD).
- Transaction types: Purchase/Addition, Issue, Return, Transfer, Adjustment.
- Transfers move quantity between warehouses within the same tenant.
- Every inventory transaction creation is audited.
- Minimum stock level per item drives low-stock alerts.

## TBD

- Negative-stock configuration (per tenant? per item?): TBD.
- Whether transactions can be voided/reversed (vs. compensating transactions): TBD — leaning compensating only.
- Item categories management (fixed vs. tenant-configurable): TBD.
- Units of measure list: TBD.
- Low-stock alert recipients: TBD (with notifications module).
