# Dashboard — Business Rules

> **Status:** Approved
> **Last updated:** 2026-08-06

- **Dashboard data must be tenant-scoped** — indicators computed strictly from the current tenant's data.
- **Dashboard data must be permission-aware** — a widget appears only if the viewer holds the underlying module's view permission (e.g. contracts widgets require `contracts.view`; recent audit activity requires `audit_logs.view`).
- Widgets read from MVP modules only; no future-scope indicators.
- The dashboard is read-only — no mutations from widgets (links navigate to modules).

## TBD

- Final widget list and layout for the first release: TBD (the "possible widgets" list is the candidate pool).
- Refresh behavior (on load vs. periodic): TBD.
- Thresholds (e.g. "nearing expiry" window): TBD — shared with contracts/notifications decisions.
- Per-role default dashboard variations: TBD.
