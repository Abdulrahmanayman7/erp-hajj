# Meetings — API

> **Status:** Specified (Sprint 010) — **no endpoints yet**
> **Last updated:** 2026-08-09

Base: `/api/v1`. Auth: Sanctum SPA + active user + active tenant. Envelope: [API_STANDARDS.md](../../04-api/API_STANDARDS.md).

Cross-tenant lookups → **404**. Authorization failures → **403** `AUTHORIZATION_DENIED`.

## Resources (response shapes)

### `MeetingResource` (list/show)

`id`, `meeting_number`, `title`, `description`, `status`, `scheduled_at`, `started_at`, `ended_at`, `location_type`, `location_text`, `meeting_link`, `minutes_body` (show; list may omit or truncate), `notes`, `attendee_count` (list), `organization_unit` `{id,name,code}` nullable, `chairperson` `{id,employee_number,full_name}` nullable, `secretary` `{id,employee_number,full_name}` nullable, `created_by` `{id,name}`, `created_at`, `updated_at`, derived `is_upcoming` (bool).

**Show only:** `attendees[]`, `agenda_items[]`, `recommendations[]`, `transitions[]`.

### Nested summaries

| Nested | Fields |
|---|---|
| Attendee | `id`, `employee` `{id,employee_number,full_name}`, `attendance_status` |
| Agenda item | `id`, `title`, `description`, `sort_order` |
| Recommendation | `id`, `title`, `description`, `status`, `sort_order`, `agenda_item_id`, `owner` `{id,employee_number,full_name}` nullable, `created_by` `{id,name}` |
| Transition | `id`, `from_status`, `to_status`, `comment`, `actor` `{id,name}` nullable, `correlation_id`, `created_at` |

## Meetings CRUD

| Method | Path | Permission | Notes |
|---|---|---|---|
| `GET` | `/meetings` | `meetings.view` | Filters below |
| `POST` | `/meetings` | `meetings.create` | Creates `draft` + `null→draft` transition |
| `GET` | `/meetings/{meeting}` | `meetings.view` | Full details |
| `PATCH` | `/meetings/{meeting}` | `meetings.update` | Status-gated editable fields |
| `DELETE` | `/meetings/{meeting}` | `meetings.update` | Draft-only never-left-draft |

### Create / PATCH body (accepted)

- `title` (required)
- `description` (nullable)
- `location_type` (`physical`\|`remote`\|`hybrid`, default `physical`)
- `location_text` (nullable)
- `meeting_link` (nullable)
- `organization_unit_id` (nullable)
- `chairperson_employee_id` (nullable)
- `secretary_employee_id` (nullable)
- `notes` (nullable)
- `scheduled_at` — optional on draft create/PATCH as planned time metadata

**Never accept:** `tenant_id`, `meeting_number`, `status`, `started_at`, `ended_at`, `created_by`, `minutes_body` (use minutes endpoint).

### PATCH field rules by status

| Status | PATCH allowed? |
|---|---|
| `draft` | Yes (all editable fields) |
| `scheduled` / `in_progress` | Yes for non-lifecycle fields; **not** `status` |
| `completed` / `cancelled` | No → `MEETING_NOT_EDITABLE` |

## Lifecycle actions

| Method | Path | Permission | From → To | Body |
|---|---|---|---|---|
| `POST` | `/meetings/{meeting}/schedule` | `meetings.update` | `draft` → `scheduled` | `{ "scheduled_at": "…" }` required |
| `POST` | `/meetings/{meeting}/reschedule` | `meetings.update` | `scheduled` → `scheduled` | `{ "scheduled_at": "…", "comment": "…" }` — `scheduled_at` required; comment optional |
| `POST` | `/meetings/{meeting}/start` | `meetings.update` | `scheduled` → `in_progress` | optional `{ "comment": "…" }` |
| `POST` | `/meetings/{meeting}/complete` | `meetings.update` | `in_progress` → `completed` | optional comment; enforces minutes |
| `POST` | `/meetings/{meeting}/cancel` | `meetings.cancel` | `draft`\|`scheduled`\|`in_progress` → `cancelled` | `{ "comment": "…" }` **required** |

No `PATCH status`. Repeating a past edge → `422 MEETING_INVALID_STATUS_TRANSITION`.

## Minutes

| Method | Path | Permission |
|---|---|---|
| `PUT` | `/meetings/{meeting}/minutes` | `meetings.manage_minutes` |

Body: `{ "minutes_body": "…" }`. Forbidden when `completed` \| `cancelled` → `MEETING_NOT_EDITABLE`.

## Attendees

| Method | Path | Permission |
|---|---|---|
| `GET` | `/meetings/{meeting}/attendees` | `meetings.view` |
| `POST` | `/meetings/{meeting}/attendees` | `meetings.manage_attendees` |
| `PATCH` | `/meetings/{meeting}/attendees/{attendee}` | `meetings.manage_attendees` |
| `DELETE` | `/meetings/{meeting}/attendees/{attendee}` | `meetings.manage_attendees` |

### POST body

`{ "employee_id": <int> }` — active same-tenant employee; default attendance `invited`. Duplicate → `MEETING_ATTENDEE_DUPLICATE`.

### PATCH body

`{ "attendance_status": "invited|attended|absent|excused" }`

Mutations forbidden when meeting `completed` \| `cancelled`.

## Agenda items

| Method | Path | Permission |
|---|---|---|
| `GET` | `/meetings/{meeting}/agenda-items` | `meetings.view` |
| `POST` | `/meetings/{meeting}/agenda-items` | `meetings.update` |
| `PATCH` | `/meetings/{meeting}/agenda-items/{item}` | `meetings.update` |
| `DELETE` | `/meetings/{meeting}/agenda-items/{item}` | `meetings.update` |

Body: `title`, `description` nullable, `sort_order` optional. Locked after complete/cancel.

## Recommendations

| Method | Path | Permission |
|---|---|---|
| `GET` | `/meetings/{meeting}/recommendations` | `meetings.view` |
| `POST` | `/meetings/{meeting}/recommendations` | `meetings.manage_minutes` |
| `PATCH` | `/meetings/{meeting}/recommendations/{recommendation}` | `meetings.manage_minutes` |
| `DELETE` | `/meetings/{meeting}/recommendations/{recommendation}` | `meetings.manage_minutes` |

Body: `title`, `description` nullable, `agenda_item_id` nullable (same meeting), `owner_employee_id` nullable, `status` `draft`\|`final` (optional; default `draft`), `sort_order` optional.

Locked after meeting complete/cancel. **No Decision endpoints** in this module.

## List filters

| Query | Behavior |
|---|---|
| `search` | title / meeting_number |
| `status` | exact status |
| `organization_unit_id` | |
| `chairperson_employee_id` | |
| `date_from` / `date_to` | filter on `scheduled_at` (tenant calendar dates) |
| `upcoming=1` | `status=scheduled` AND `scheduled_at >= now(tenant tz)` |
| `past=1` | `status in (completed, cancelled)` OR (`status=scheduled` AND `scheduled_at < now`) |
| `sort` / `direction` | `scheduled_at`, `created_at`, `title`, `meeting_number`, `status` |
| `page` / `per_page` | standard pagination |

**Default sort:** `ORDER BY scheduled_at IS NULL, scheduled_at DESC, id DESC` (drafts without schedule still appear via nulls-first/last policy — prefer nulls last so scheduled meetings surface first).

## Error codes

| Code | HTTP |
|---|---|
| `MEETING_NOT_FOUND` | 404 (prefer generic route 404) |
| `MEETING_NUMBER_TAKEN` | 422 |
| `MEETING_INVALID_STATUS_TRANSITION` | 422 |
| `MEETING_INVALID_SCHEDULE` | 422 |
| `MEETING_COMMENT_REQUIRED` | 422 |
| `MEETING_NOT_EDITABLE` | 422 |
| `MEETING_DELETE_FORBIDDEN` | 422 |
| `MEETING_EMPLOYEE_INVALID` | 422 |
| `MEETING_ORGANIZATION_INVALID` | 422 |
| `MEETING_ATTENDEE_DUPLICATE` | 422 |
| `MEETING_ATTENDEE_INVALID` | 422 |
| `MEETING_MINUTES_REQUIRED` | 422 |
| `MEETING_COMPLETION_REQUIREMENTS_NOT_MET` | 422 |
| `MEETING_RECOMMENDATION_NOT_FOUND` | 404/422 |
| `MEETING_AGENDA_ITEM_INVALID` | 422 |
| `AUTHORIZATION_DENIED` | 403 |

## Audit events

| Event | When |
|---|---|
| `MEETING_CREATED` | Create |
| `MEETING_UPDATED` | PATCH |
| `MEETING_DELETED` | Draft hard delete |
| `MEETING_SCHEDULED` | schedule |
| `MEETING_RESCHEDULED` | reschedule |
| `MEETING_STARTED` | start |
| `MEETING_COMPLETED` | complete |
| `MEETING_CANCELLED` | cancel |
| `MEETING_ATTENDEE_ADDED` | |
| `MEETING_ATTENDEE_REMOVED` | |
| `MEETING_ATTENDANCE_UPDATED` | |
| `MEETING_MINUTES_UPDATED` | |
| `MEETING_AGENDA_ITEM_CREATED` / `_UPDATED` / `_DELETED` | |
| `MEETING_RECOMMENDATION_CREATED` / `_UPDATED` / `_DELETED` | |
