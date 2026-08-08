# Dashboard — Permissions

> **Status:** Approved
> **Last updated:** 2026-08-06

| Permission | Purpose |
|---|---|
| `dashboard.view` | Access the administrative dashboard |

## Rules

- `dashboard.view` gates the page; **each widget additionally requires the underlying module's view permission** (`contracts.view`, `tasks.view`, `audit_logs.view`, ...).
- Widgets the viewer is not permitted to see are omitted entirely (no empty placeholders leaking their existence: TBD confirm UX).
