# Contracts — Business Rules

> **Status:** Approved
> **Last updated:** 2026-08-06

- Lifecycle: **Draft → Review → Approval → Signature → Execution → Closure or Renewal**. Transitions are controlled — no free status editing.
- Each transition records **actor, timestamp, and optional comments**, and is **audited**.
- Unauthorized users must not review, approve, sign, execute, close, or renew contracts (dedicated permission per transition).
- Attachments must be preserved across the lifecycle.
- Contract expiry notifications must be supported (thresholds TBD).
- **Deleting an approved or executed contract is restricted**; prefer soft deletion where appropriate.
- Contract categories are configurable per tenant (initial examples seeded).

## TBD

- Contract number generation scheme: TBD.
- Rejection/return-to-previous-stage transitions: TBD.
- Renewal mechanics (new record vs. extension): TBD.
- Expiry notification thresholds and recipients: TBD.
- Which roles hold which transition permissions by default: TBD.
