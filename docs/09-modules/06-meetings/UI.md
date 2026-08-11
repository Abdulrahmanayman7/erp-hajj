# Meetings — UI (planned)

> **Status:** Planned — no pages exist
> **Last updated:** 2026-08-06

## Pages

- **Meetings list** — Data Table with filters (status, date range); upcoming meetings surfaced.
- **Meeting details** — info, attendees, agenda, minutes, recommendations, attachments, audit history panel; status-driven actions (schedule/start/complete/cancel) with Confirmation Dialog.
- **Create/edit form** — title, datetime picker, location/method, organizer, attendees (User Selector).

## Rules

- Completed meetings show a "create decision" affordance (navigates to decisions module with meeting reference) for holders of `decisions.create`.
- Cancel and complete actions are permission-gated and confirmed.

## TBD

- Minutes editor experience (structured list vs. rich text): follows the data model decision.
