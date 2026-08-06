# Dashboard — Data Model (conceptual)

> **Status:** Conceptual — no migrations exist
> **Last updated:** 2026-08-06

The dashboard **owns no business tables** — it aggregates read-only indicators from other modules:

| Widget | Source module |
|---|---|
| Active users | Users |
| Employees / Supervisors | Employees |
| Active contracts / nearing expiry | Contracts |
| Open / overdue tasks | Tasks |
| Upcoming meetings | Meetings |
| Recent decisions | Decisions |
| Low-stock alerts | Inventory |
| Assigned assets | Assets/Custodies |
| Recent audit activity | Audit trail |

## Notes

- Any caching/materialization of aggregates uses tenant-scoped cache keys and is never a source of truth.
- Per-user dashboard preferences (layout customization): not specified — do not build without approval.
