# Warehouses and Inventory — Data Model (conceptual)

> **Status:** Conceptual — no migrations exist
> **Last updated:** 2026-08-06

All tables carry `tenant_id`; item code and warehouse code unique per tenant.

## Warehouse

| Field | Notes |
|---|---|
| Name | |
| Code | Unique per tenant |
| Location | |
| Responsible person | Employee/user reference |
| Status | Active/inactive |

## Inventory Item

| Field | Notes |
|---|---|
| Item code | Unique per tenant |
| Name | |
| Category | Management TBD |
| Unit | Unit of measure (list TBD) |
| Barcode | Optional |
| QR code | Optional |
| Minimum stock level | Drives low-stock alerts |
| Active status | |

## Inventory Transaction (immutable)

| Field | Notes |
|---|---|
| Type | Addition / Issue / Return / Transfer / Adjustment |
| Item | Reference |
| Quantity | Positive value; sign semantics by type |
| Source warehouse | Per type (e.g. issue, transfer) |
| Destination warehouse | Per type (e.g. addition, transfer, return) |
| Actor | User |
| Time | Timestamp |
| Reason | Required (mandatory for adjustments) |

## Balances

Computed per item per warehouse from transactions (materialization strategy — live query vs. cached summary — is an implementation decision; source of truth is always transactions).
