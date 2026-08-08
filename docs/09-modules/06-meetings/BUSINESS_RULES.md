# Meetings — Business Rules

> **Status:** Approved
> **Last updated:** 2026-08-06

- Statuses: **Draft, Scheduled, In Progress, Completed, Cancelled** (suggested set; confirm at implementation).
- A **completed meeting may create decisions** (one or more).
- **Completed meetings must not be deleted without privileged permission and audit logging.**
- Meeting completion is an audited event.
- Attendees, agenda items, minutes, and recommendations are managed with dedicated permissions (`meetings.manage_attendees`, `meetings.manage_minutes`).
- Recommendations may become decisions; a recommendation is not automatically a decision.

## TBD

- Whether attendees must be tenant users/employees or can include external names: TBD.
- Minutes format (structured items vs. rich text): TBD.
- Which statuses allow editing which fields: TBD.
- Cancellation rules (who, when, effect on linked recommendations): partially covered by `meetings.cancel`; details TBD.
