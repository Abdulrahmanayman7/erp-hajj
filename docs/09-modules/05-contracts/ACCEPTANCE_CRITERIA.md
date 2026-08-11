# Contracts — Acceptance Criteria

> **Status:** Draft
> **Last updated:** 2026-08-06

- [ ] Contract CRUD works, tenant-scoped; contract numbers unique per tenant.
- [ ] Lifecycle transitions only via action endpoints; invalid transitions rejected with standardized errors.
- [ ] Each transition records actor, timestamp, optional comment — visible in status history.
- [ ] Every transition requires its dedicated permission and produces an audit record.
- [ ] Attachments persist across all lifecycle stages.
- [ ] Deleting an approved/executed contract is blocked for normal permissions (soft-delete policy applied).
- [ ] Expiry notifications generated per decided thresholds (TBD blocking).
- [ ] Categories are configurable per tenant; initial examples seeded.
- [ ] Cross-tenant access returns `404`.
