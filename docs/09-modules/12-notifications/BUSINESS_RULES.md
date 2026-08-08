# Notifications — Business Rules

> **Status:** Approved (principles); catalog TBD
> **Last updated:** 2026-08-06

- **Notifications must not cross tenants** — recipients always belong to the notification's tenant.
- Only notifications **required by MVP modules** are built; no speculative notification types.
- Contract expiry notifications are required (thresholds and recipients TBD).
- Notification generation for module events happens via events/jobs (queued) carrying tenant context.
- Notification content must not leak data the recipient is not permitted to see (permission-aware content: TBD detail).

## TBD

- Full catalog of required notifications per module: TBD — collected as modules are designed.
- Channels (in-app only vs. also email): TBD.
- Read/unread and retention behavior: TBD.
- User notification preferences: TBD (likely out of MVP — confirm).
