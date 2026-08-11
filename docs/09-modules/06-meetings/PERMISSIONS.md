# Meetings — Permissions

> **Status:** Approved
> **Last updated:** 2026-08-06

| Permission | Purpose |
|---|---|
| `meetings.view` | List/view meetings |
| `meetings.create` | Create meetings |
| `meetings.update` | Update meeting data |
| `meetings.cancel` | Cancel meetings (audited) |
| `meetings.manage_attendees` | Manage the attendee list |
| `meetings.manage_minutes` | Manage minutes and recommendations |

## Rules

- Deleting a completed meeting requires privileged permission (name TBD — possibly reusing a restricted delete permission) and is always audited.
