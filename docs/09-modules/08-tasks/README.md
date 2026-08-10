# Module: Tasks and Assignments (المهام والتكليفات)

> **Status:** Implemented (Sprint 012)
> **Last updated:** 2026-08-10

## Purpose

Manage each tenant’s **execution work items** as a **separate business entity** (never merged into Decisions) — the execution layer of Workflow 1 in [CORE_WORKFLOWS.md](../../01-business/CORE_WORKFLOWS.md):

**Meeting → Recommendation → Decision → Task(s) → Responsible → Execution → Measurement → Closure**

Sprint 012 specifies **Tasks & Assignments only**. Documents and notification delivery remain future modules. Tasks are future-compatible with Documents, Notifications, Dashboard, and Audit.

## Domain separation (non-negotiable)

| Concept | Meaning | Owned by |
|---|---|---|
| **Decision** | Formal governance decision | Decisions |
| **Task** | Execution / action item | This module |
| **Task assignee** | Employee responsible for **execution** | This module (`assigned_to_employee_id`) |
| **Decision responsible** | Governance follow-up — **not** Task assignee | Decisions |
| **User** | Authenticated actor (`created_by`, transition actor) | Users & Authorization |
| **OrganizationUnit** | Optional owning/responsible unit | Organization Structure |
| **Document** | Attachments / evidence files | Documents (later) |
| **Notification** | Delivery of assignment / due alerts | Notifications (later) |

Do **not** auto-create Tasks when a Decision is approved. Do **not** put `task_ids` or progress on Decision. Do **not** conflate Decision closure with Task completion without the explicit close gate (ADR-0009).

## Sprint 012 scope

| In scope | Out of scope |
|---|---|
| `tasks` + server-generated `TSK-######` | Documents upload / evidence files |
| Optional `decision_id` (Tasks own the FK) | Multi-assignee / group pivot |
| Standalone Tasks (`decision_id` null) | Subtasks, comments/chat, timesheets |
| Single Employee assignee | Recurring / seasonal automation |
| Controlled lifecycle action endpoints | Kanban board / PM suite |
| Append-only status + assignment history | Notification delivery |
| Progress % + completion notes (measurement) | KPI / Dashboard charts |
| Decision details Tasks section + close gate | SoftDeletes; free PATCH of `status` |
| Limited assignee self-service (linked User) | Auto-create Users for Employees |
| Policies + `tasks.*` permissions (seed at implementation) | SLA / scheduler jobs |

## Module placement

| Layer | Path |
|---|---|
| Backend | `backend/app/Modules/Tasks/` |
| Frontend | `frontend/src/modules/tasks/` |
| Config | `backend/config/tasks.php` (number prefix/pad) |

## Personas

| Persona | Typical use |
|---|---|
| Tenant Owner / General Manager | Full lifecycle; assign; complete; delete drafts |
| Department Manager | Create/assign/update/start/cancel; view |
| Supervisor | View; update own assigned tasks (self-service + grants) |
| Employee | View/start/complete **own** assigned tasks when User↔Employee linked |
| Auditor | View tasks + history (read) |

Exact default role grants: [PERMISSIONS.md](PERMISSIONS.md).

## Architectural decisions

| Topic | Decision | Doc |
|---|---|---|
| Numbering | `TSK-000001…`, tenant sequence + `FOR UPDATE`, immutable | [DATA_MODEL.md](DATA_MODEL.md) |
| Decision link | Tasks own nullable `decision_id`; one Decision → many Tasks | [ADR-0009](../../10-decisions/ADR-0009-TASK-ASSIGNEE-AND-DECISION-GATE.md) |
| Standalone | Allowed (`decision_id` null) | [ADR-0009](../../10-decisions/ADR-0009-TASK-ASSIGNEE-AND-DECISION-GATE.md) |
| Assignee | **Single** Employee; User is audit/self-service actor only | [ADR-0009](../../10-decisions/ADR-0009-TASK-ASSIGNEE-AND-DECISION-GATE.md) |
| Group/multi-assign | **Deferred** (semantics were TBD; create multiple Tasks instead) | [BUSINESS_RULES.md](BUSINESS_RULES.md) |
| Lifecycle | `draft` → `assigned` → `in_progress` → `completed`; early cancel | [BUSINESS_RULES.md](BUSINESS_RULES.md) |
| Overdue | **Derived**, not a status | [BUSINESS_RULES.md](BUSINESS_RULES.md) |
| Progress / measurement | `progress_percent` 0–100 + required `completion_notes` on complete | [ADR-0009](../../10-decisions/ADR-0009-TASK-ASSIGNEE-AND-DECISION-GATE.md) |
| Priority | `low` \| `medium` \| `high` (default `medium`) | [BUSINESS_RULES.md](BUSINESS_RULES.md) |
| Self-service | Limited Policy: linked User’s Employee = assignee | [ADR-0009](../../10-decisions/ADR-0009-TASK-ASSIGNEE-AND-DECISION-GATE.md) |
| Decision close gate | Block `approved → closed` while open linked Tasks exist | [ADR-0009](../../10-decisions/ADR-0009-TASK-ASSIGNEE-AND-DECISION-GATE.md) |
| Delete | Hard delete **draft-only** (never left draft) | [BUSINESS_RULES.md](BUSINESS_RULES.md) |

## References

- [CORE_WORKFLOWS.md](../../01-business/CORE_WORKFLOWS.md) Workflow 1 · [BUSINESS_RULES.md](../../01-business/BUSINESS_RULES.md)
- [07-decisions/](../07-decisions/) · [04-employees-and-supervisors/](../04-employees-and-supervisors/) · [09-documents/](../09-documents/)
- [ADR-0008](../../10-decisions/ADR-0008-DECISION-RECOMMENDATION-AND-APPROVAL.md) · [ADR-0009](../../10-decisions/ADR-0009-TASK-ASSIGNEE-AND-DECISION-GATE.md)
- [MODULE_TEMPLATE.md](../../02-architecture/MODULE_TEMPLATE.md)
