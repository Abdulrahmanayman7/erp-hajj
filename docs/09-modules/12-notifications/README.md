# Module: Notifications (الإشعارات)

> **Status:** Documented — not implemented
> **Last updated:** 2026-08-06

## Purpose

Deliver the notifications **required by MVP modules** — nothing more. Notifications are strictly tenant-isolated.

## Scope (only notifications required by MVP modules)

- **Contract expiry notifications** (explicitly required by the contracts module).
- **Low-stock alerts** (implied by the dashboard widget and inventory minimum levels).
- Other module-required notifications (e.g. task assignment): **TBD — must be confirmed per module before building**.

## Out of scope

- Notification center beyond MVP needs, marketing/broadcast messaging, SMS/push channels (future scope; push arrives with mobile apps).

## References

- [05-contracts/](../05-contracts/) · [10-warehouses-and-inventory/](../10-warehouses-and-inventory/) · [MULTI_TENANCY.md](../../02-architecture/MULTI_TENANCY.md)
