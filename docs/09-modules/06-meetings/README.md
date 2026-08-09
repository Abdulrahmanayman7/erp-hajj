# Module: Meetings (الاجتماعات)

> **Status:** Implemented (Sprint 010)
> **Last updated:** 2026-08-10

## Purpose

Manage each tenant’s **formal administrative meetings** — agendas, attendees, minutes (محضر), and recommendations (توصيات) — as the **entry point** of Workflow 1 in [CORE_WORKFLOWS.md](../../01-business/CORE_WORKFLOWS.md):

**Meeting → Recommendation → Decision → Task(s) → Responsible → Execution → Measurement → Closure**

Sprint 010 specifies **Meetings only**. Decisions and Tasks remain separate future modules. Recommendations produced here are **not** Decisions.

## Domain separation (non-negotiable)

| Concept | Meaning | Owned by |
|---|---|---|
| **Meeting** | Scheduled governance / work session | This module |
| **Meeting attendee** | Participant (tenant employee) | This module |
| **Agenda item** | Ordered discussion topic | This module |
| **Minutes** | Formal meeting record (محضر) | This module (`minutes_body`) |
| **Recommendation** | Meeting outcome that *may* become a Decision later | This module |
| **Decision** | Formal governance record | Decisions (Sprint 011+) |
| **Task** | Assignable execution work | Tasks (Sprint 012+) |
| **Employee** | Personnel record (chair / secretary / attendee / recommendation owner) | Employees |
| **User** | Authenticated account (`created_by`) | Users & Authorization |
| **OrganizationUnit** | Optional responsible unit | Organization Structure |
| **Document** | File attachments | Documents (later) |
| **Notification** | Delivery of invites / reminders | Notifications (later) |

Do **not** conflate Recommendation with Decision. Do **not** convert recommendations into tasks in Sprint 010. Do **not** invent Decision/Task rows from Meetings.

## Sprint 010 scope

| In scope | Out of scope |
|---|---|
| `meetings` + server-generated `MTG-######` | Decisions module / create-decision actions |
| Controlled lifecycle action endpoints | Tasks / automatic task creation |
| Append-only `meeting_status_transitions` | Calendar sync (Google/Outlook) |
| Attendees (tenant employees only) | External guests as first-class party master |
| Structured `meeting_agenda_items` | Video conferencing APIs (Zoom/Teams) |
| Minutes as `minutes_body` text on meeting | Rich-text editor packages |
| First-class `meeting_recommendations` | Recommendation → Decision conversion UI |
| Optional org unit / chair / secretary (Employee) | Email/SMS invitation delivery |
| Policies + `meetings.*` permissions (seed at implementation) | Documents upload/download |
| Arabic RTL list + details UX | SoftDeletes; free PATCH of `status` |
| Audit events + Pest/Vitest matrices | Dashboard KPI widgets |

## Module placement

| Layer | Path |
|---|---|
| Backend | `backend/app/Modules/Meetings/` |
| Frontend | `frontend/src/modules/meetings/` |
| Config | `backend/config/meetings.php` (number prefix/pad) |

## Personas

| Persona | Typical use |
|---|---|
| Tenant Owner / General Manager | Full meeting lifecycle; minutes; recommendations |
| Department Manager | Create/schedule meetings; manage attendees/minutes for unit work |
| Secretary / minutes owner | Record minutes and recommendations (`manage_minutes`) when granted |
| Auditor | View meetings + history (read) |
| Supervisor / Employee | Default **none** (or view-only if product later chooses) |

Exact default role grants: [PERMISSIONS.md](PERMISSIONS.md).

## Architectural decisions

| Topic | Decision | Doc |
|---|---|---|
| Numbering | `MTG-000001…`, tenant sequence + `FOR UPDATE`, immutable | [DATA_MODEL.md](DATA_MODEL.md) |
| Meeting type | **Omitted** — no taxonomy/catalog in MVP | [BUSINESS_RULES.md](BUSINESS_RULES.md) |
| Lifecycle | `draft` → `scheduled` → `in_progress` → `completed`; cancel from early states; action endpoints only | [BUSINESS_RULES.md](BUSINESS_RULES.md) |
| Transition history | `meeting_status_transitions` append-only (+ correlation ID) | [DATA_MODEL.md](DATA_MODEL.md) |
| Agenda | Structured `meeting_agenda_items` (ordered) | [ADR-0007](../../10-decisions/ADR-0007-MEETING-AGENDA-AND-RECOMMENDATIONS.md) |
| Minutes | Single `minutes_body` TEXT on `meetings` (textarea UX) | [ADR-0007](../../10-decisions/ADR-0007-MEETING-AGENDA-AND-RECOMMENDATIONS.md) |
| Recommendations | First-class table; **not** Decisions; no `decision_id` in Sprint 010 | [ADR-0007](../../10-decisions/ADR-0007-MEETING-AGENDA-AND-RECOMMENDATIONS.md) |
| Attendees | Tenant **employees only**; external names deferred | [BUSINESS_RULES.md](BUSINESS_RULES.md) |
| Attachments | Deferred to Documents; UI placeholder only | [UI.md](UI.md) |
| Notifications | Hooks named only; no delivery | [BUSINESS_RULES.md](BUSINESS_RULES.md) |
| Delete | Hard delete **draft-only** (never left draft) | [BUSINESS_RULES.md](BUSINESS_RULES.md) |

## References

- [CORE_WORKFLOWS.md](../../01-business/CORE_WORKFLOWS.md) Workflow 1 · [BUSINESS_RULES.md](../../01-business/BUSINESS_RULES.md)
- [07-decisions/](../07-decisions/) · [08-tasks/](../08-tasks/) · [04-employees-and-supervisors/](../04-employees-and-supervisors/) · [03-organization-structure/](../03-organization-structure/)
- [09-documents/](../09-documents/) · [12-notifications/](../12-notifications/)
- [MODULE_TEMPLATE.md](../../02-architecture/MODULE_TEMPLATE.md)
- [ADR-0007](../../10-decisions/ADR-0007-MEETING-AGENDA-AND-RECOMMENDATIONS.md)
