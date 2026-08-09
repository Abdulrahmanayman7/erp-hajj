# Meetings — Test Plan

> **Status:** Implemented (Sprint 010) — covered by Pest/Vitest
> **Last updated:** 2026-08-10

Tooling: **Pest** (backend), **Vitest** (frontend). Failing security tests block merge.

## Pest — Database

- FKs: tenant, org unit, chair/secretary employees, created_by, attendees, agenda, recommendations, transitions
- Unique `(tenant_id, meeting_number)`
- Unique `(meeting_id, employee_id)` attendees
- Indexes present as documented
- Sequence table PK `tenant_id`

## Pest — Numbering

- First number `MTG-000001`
- Increments without gaps under sequential creates
- Per-tenant independent sequences
- Immutable; payload ignored/rejected
- Concurrent allocation safe (`FOR UPDATE`)

## Pest — Create / update

- Valid draft create + audit `MEETING_CREATED` + transition `null→draft`
- Inactive/foreign org → `MEETING_ORGANIZATION_INVALID` / 422
- Inactive/foreign chair or secretary → `MEETING_EMPLOYEE_INVALID`
- Draft/scheduled/in_progress PATCH allowed for editable fields
- Completed/cancelled PATCH → `MEETING_NOT_EDITABLE`
- Status cannot be patched
- `tenant_id` / `meeting_number` / `started_at` / `ended_at` not client-writable

## Pest — Lifecycle

- All allowed transitions succeed; timestamps set as documented
- All invalid transitions → `MEETING_INVALID_STATUS_TRANSITION`
- Schedule requires `scheduled_at`
- Cancel requires comment
- Complete without minutes → `MEETING_MINUTES_REQUIRED` or `MEETING_COMPLETION_REQUIREMENTS_NOT_MET`
- Complete auto-finalizes draft recommendations
- Reschedule only from scheduled; history/audit recorded
- Transition history append-only; actor + correlation_id on request-scoped actions

## Pest — Attendees

- Add active employee; duplicate blocked
- Foreign/inactive employee rejected on new assign
- Attendance status updates
- Mutations blocked after complete/cancel
- Cross-tenant attendee impossible

## Pest — Agenda

- CRUD while open; locked after complete/cancel
- Foreign agenda_item on recommendation rejected

## Pest — Minutes

- PUT updates body + audit
- Permission gated
- Locked after complete/cancel

## Pest — Recommendations

- CRUD; owner validation; agenda_item same-meeting
- Immutable after complete/cancel
- Tenant isolation
- No decision side effects

## Pest — Delete

- Untouched draft deletable
- After schedule/start/cancel/complete → `MEETING_DELETE_FORBIDDEN`
- Children removed only with eligible draft delete

## Pest — Tenancy threat model

Tenant A cannot: list/show/update/transition/delete B’s meeting; assign B employee/org; mutate B attendees/agenda/minutes/recommendations; inject `tenant_id`.

## Pest — RBAC

Each `meetings.*` permission gates the documented endpoints (401 unauthenticated, 403 unauthorized).

## Pest — Audit

Every event in [API.md](API.md) is asserted (Event fake / sink).

## Pest — List

Pagination, search, status, org, chairperson, date range, upcoming, past, default sort, eager-load no N+1 where practical.

## Vitest

- Page loading / empty / error
- List + filters + pagination
- Create/edit drawer validation
- Details sections
- Lifecycle buttons + confirms + API errors
- Permission-aware visibility
- Attendees / minutes / recommendations UX
- Status badges + upcoming chip
- Sidebar visibility
- Responsive basics where practical

Do not rely only on snapshots.
