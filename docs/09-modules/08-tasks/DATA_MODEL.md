# Tasks — Data Model (conceptual)

> **Status:** Conceptual — no migrations exist
> **Last updated:** 2026-08-06

All tables carry `tenant_id`.

## Task

| Field | Notes |
|---|---|
| Title | |
| Description | |
| Type | Individual / Group / Seasonal / Recurring (recurring automation TBD) |
| Related entity | Polymorphic (notably Decision); design TBD |
| Creator | User |
| Assignee(s) | One (individual) or many (group — semantics TBD) |
| Department | Organizational unit |
| Priority | High / Medium / Low |
| Start date / Due date | ISO 8601 |
| Status | New / In Progress / Blocked / Overdue (derived) / Completed / Cancelled |
| Progress percentage | 0–100 |
| Attachments | Via documents module |
| Comments | Related records (author, body, timestamp) |
| Completion evidence | Text/files (requirements TBD) |
| Timestamps | Standard |

## Notes

- Overdue is derived at read time (or scheduled evaluation — design TBD); never stored as a user-set status.
