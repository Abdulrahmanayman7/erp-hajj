# Contracts — Test Plan

> **Status:** Draft
> **Last updated:** 2026-08-06

- **Cross-tenant (mandatory):** tenant A cannot view or transition tenant B contracts (`404`).
- Workflow: every valid transition succeeds with permission; every invalid transition (wrong order, wrong status) fails; each transition permission tested for `403`.
- History: status history rows created with actor/timestamp/comment; approval history recorded.
- Audit: every transition audited; delete attempts on approved/executed contracts audited and blocked.
- Attachments: preserved after transitions; permission-checked download.
- Notifications: expiry notification generated at threshold (when decided).
- Validation: dates, parties, category required fields.
- Frontend: transition buttons visibility per status+permission; status timeline rendering.
- E2E: full lifecycle draft → review → approve → sign → execute → close.
