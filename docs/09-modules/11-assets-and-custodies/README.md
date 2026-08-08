# Module: Assets and Custodies (الأصول والعُهد)

> **Status:** Documented — not implemented
> **Last updated:** 2026-08-06

## Purpose

Track individually identified resources (assets) and their controlled handover to employees or supervisors (custodies). **Assets and Custodies are separate entities**; assets are not inventory items.

Lifecycle: Asset → Available → Assigned as Custody → In Use → Returned → Available / Maintenance / Retired.

## Scope

- Assets registry: devices, radios, computers, printers, vehicles, generators, furniture, etc.
- Asset statuses: Available, Assigned, Maintenance, Damaged, Retired, Lost.
- Custody assignment and return with immutable history.
- Attachments via the documents module.

## Out of scope

- Depreciation/financial accounting of assets (future scope) — purchase value is informational.
- Maintenance management workflows beyond the status: TBD/not specified.

## References

- [CORE_WORKFLOWS.md](../../01-business/CORE_WORKFLOWS.md) (Workflow 3) · [10-warehouses-and-inventory/](../10-warehouses-and-inventory/)
