# Notifications — Data Model (conceptual)

> **Status:** Conceptual — no migrations exist
> **Last updated:** 2026-08-06

All tables carry `tenant_id`.

## Notification

| Field | Notes |
|---|---|
| Tenant | `tenant_id` — never crosses tenants |
| Recipient | User in the same tenant |
| Type | e.g. contract_expiry, low_stock (catalog TBD) |
| Related entity | Polymorphic reference (e.g. contract, inventory item) |
| Payload | Title/body data (localizable — Arabic first) |
| Read state | TBD model |
| Created at | Timestamp |

## Notes

- Laravel's notification system is the likely base (database channel) — confirm at implementation; document the package/approach when chosen.
- Retention/cleanup policy: TBD.
