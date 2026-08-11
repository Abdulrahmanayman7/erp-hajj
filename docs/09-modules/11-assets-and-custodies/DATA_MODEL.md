# Assets and Custodies — Data Model (conceptual)

> **Status:** Conceptual — no migrations exist
> **Last updated:** 2026-08-06

All tables carry `tenant_id`; asset code unique per tenant.

## Asset

| Field | Notes |
|---|---|
| Asset code | Unique per tenant (generation TBD) |
| Name | |
| Category | e.g. phone, radio, computer, printer, vehicle, generator, furniture |
| Serial number | |
| Purchase value | Informational |
| Acquisition date | |
| Current location | |
| Current status | Available / Assigned / Maintenance / Damaged / Retired / Lost |
| Warehouse | Optional storage reference |
| Responsible person | Employee/user |
| Notes | |

## Custody (immutable history)

| Field | Notes |
|---|---|
| Asset | Reference |
| Assigned employee | Receiver |
| Assignment date | |
| Expected return date | When applicable |
| Actual return date | Set on return |
| Assigned by | User |
| Returned to | User |
| Condition at assignment | |
| Condition at return | |
| Status | Active / Returned (list TBD) |
| Notes | |

## Constraints

- At most **one active custody per asset** (enforced in application; DB backing constraint approach TBD).
- Custody rows are never updated after return except via controlled correction (TBD).
