# Meetings — Data Model (conceptual)

> **Status:** Conceptual — no migrations exist
> **Last updated:** 2026-08-06

All tables carry `tenant_id`.

## Meeting

| Field | Notes |
|---|---|
| Title | |
| Description | |
| Date and time | ISO 8601 |
| Location / meeting method | Physical or virtual descriptor |
| Organizer | User/employee reference |
| Status | Draft / Scheduled / In Progress / Completed / Cancelled |
| Attachments | Via documents module |
| Timestamps | Standard |

## Related concepts

| Entity | Notes |
|---|---|
| Attendee | Meeting ↔ participant (user/employee; external attendees TBD) |
| Agenda item | Ordered items per meeting |
| Minutes | محضر — format TBD (structured vs. rich text) |
| Recommendation | توصية — may lead to a decision (referenced by decisions module) |

## TBD

- Recommendation as own table vs. part of minutes: leaning to own table (decisions reference it); confirm at design.
