# Decisions — Data Model (conceptual)

> **Status:** Conceptual — no migrations exist
> **Last updated:** 2026-08-06

All tables carry `tenant_id`; decision number unique per tenant.

## Decision

| Field | Notes |
|---|---|
| Decision number | Unique per tenant (generation TBD) |
| Subject | |
| Description | |
| Originating meeting | **Optional** reference |
| Responsible department | Organizational unit |
| Responsible person | User/employee |
| Decision date | |
| Target execution date | |
| Priority | Values TBD |
| Status | New / Approved / In Progress / Completed / Blocked / Cancelled |
| Attachments | Via documents module |
| Related tasks | One-to-many from tasks module |
| Created by | User |
| Approved by | Where required (approval details TBD) |
| Timestamps | Standard |

## TBD

- Link to originating recommendation (vs. meeting only): TBD with meetings data model.
- "Required task" marking on task relations: TBD.
