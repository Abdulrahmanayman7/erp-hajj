# Meetings — Permissions

> **Status:** Implemented (Sprint 010) — seeded
> **Last updated:** 2026-08-10

## Seeded with Sprint 010 implementation

| Permission | Purpose |
|---|---|
| `meetings.view` | List/show meetings; read attendees, agenda, minutes, recommendations, timeline |
| `meetings.create` | Create draft meetings |
| `meetings.update` | Update editable fields; schedule/reschedule/start/complete; manage agenda items; hard-delete eligible drafts |
| `meetings.cancel` | Cancel meeting (`draft` \| `scheduled` \| `in_progress`) |
| `meetings.manage_attendees` | Add/remove attendees; update attendance status |
| `meetings.manage_minutes` | Update minutes body; CRUD recommendations |

## Not seeded / not invented

| Name | Reason |
|---|---|
| `meetings.delete` | Folded into `meetings.update` for draft-only delete |
| `meetings.start` / `meetings.complete` / `meetings.schedule` | Folded into `meetings.update` |
| `meetings.manage_recommendations` | Folded into `meetings.manage_minutes` |
| `meetings.manage_agenda` | Folded into `meetings.update` |

## Default role template guidance

| System role | Suggested grants |
|---|---|
| Tenant Owner | All `meetings.*` (via `allNames()`) |
| General Manager | All `meetings.*` |
| Department Manager | `view`, `create`, `update`, `manage_attendees`, `manage_minutes` |
| Supervisor | **none** (default) |
| Employee / read_only | none |
| Auditor | `meetings.view` |

**TBD at seed PR:** Whether DeptMgr also receives `meetings.cancel`; recommendation above omits cancel for DeptMgr (Owner/GM only) — product may grant cancel to DeptMgr.

## Policy authority

- `MeetingPolicy` — capability checks only; never role-name checks.
- Nested attendee / agenda / recommendation mutations authorize against the parent `Meeting` + relevant permission (separate policies optional; not required).
- No row-level organization scoping in Sprint 010.
- Frontend `can()` is UX only; backend authoritative.
