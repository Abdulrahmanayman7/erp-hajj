# Meetings — API (planned)

> **Status:** Planned contract — no endpoints exist yet
> **Last updated:** 2026-08-06

All endpoints follow [API_STANDARDS.md](../../04-api/API_STANDARDS.md).

```text
GET    /api/v1/meetings                       # meetings.view; filters: status, date range, organizer
POST   /api/v1/meetings                       # meetings.create
GET    /api/v1/meetings/{meeting}             # meetings.view (agenda, attendees, minutes, recommendations)
PATCH  /api/v1/meetings/{meeting}             # meetings.update
POST   /api/v1/meetings/{meeting}/cancel      # meetings.cancel (action endpoint, audited)
```

## Sub-resources (paths TBD at implementation)

- Attendees management — `meetings.manage_attendees`.
- Agenda items — `meetings.update`.
- Minutes and recommendations — `meetings.manage_minutes`.
- Status transitions (schedule, start, complete): action endpoints; completion is audited.

## TBD

- Exact sub-resource paths and transition endpoint names: TBD.
- Creating a decision from a recommendation (endpoint here vs. decisions module referencing the meeting): TBD — leaning to decisions module with `meeting_id`.
