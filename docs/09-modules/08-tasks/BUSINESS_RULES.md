# Tasks — Business Rules

> **Status:** Implemented (Sprint 012)
> **Last updated:** 2026-08-10

Binding product rules for the Tasks module. Implementation must not invent fields, statuses, multi-assignee pivots, or Documents/notification delivery.

## 1. Identity and numbering

1. Every Task belongs to exactly one tenant (`tenant_id` from `TenantContext` only — never from the client payload).
2. `task_number` is **server-generated**, **immutable**, and **tenant-unique**.
3. Format: `TSK-` + six zero-padded digits (`TSK-000001`, `TSK-000002`, …).
4. Allocation uses a per-tenant sequence row with `SELECT … FOR UPDATE` (same pattern as Decisions/Meetings/Contracts). **No `MAX+1`.**
5. Clients never supply or PATCH `task_number` or `tenant_id`.

## 2. Content (MVP fields)

| Field | Required | Notes |
|---|---|---|
| `title` | Yes | Short subject |
| `description` | No | Longer instructions |
| `notes` | No | Internal notes |
| `status` | System | Lifecycle only — never free PATCH |
| `priority` | Yes (default) | `low` \| `medium` \| `high` — default `medium` |
| `decision_id` | No | Immutable after create when set |
| `organization_unit_id` | No | Same tenant; active on new assignment |
| `assigned_to_employee_id` | No at create | Required to leave `draft` via assign / create-with-assignee |
| `progress_percent` | System | Integer 0–100; default `0` |
| `start_date` | No | `DATE` |
| `due_date` | No | `DATE` — Task deadline (not Decision due_date) |
| `completed_at` | System | `datetime` set on complete |
| `completion_notes` | On complete | Measurement / result text — **required** to complete |
| `created_by` | System | User |

**Omitted in MVP:** subtasks, comments/chat, attachments, recurring/seasonal types, Blocked status, multi-assignee pivot, SLA fields, timesheets, task owner/supervisor separate from assignee, Documents FKs.

## 3. Decision relationship

1. Tasks **own** optional FK `decision_id` → `decisions.id`.
2. Do **not** add `task_ids` / JSON / counters on `decisions` (ADR-0008 / ADR-0009).
3. Cardinality: one Decision → **many** Tasks; each Task links to at most one Decision.
4. Standalone Tasks allowed (`decision_id` null).
5. When `decision_id` is set at create:
   - Same tenant.
   - Decision `status` must be **`approved`** (not draft / pending_approval / cancelled / closed).
   - Historical link retained if Decision later closes.
6. Creating Tasks for a **closed** Decision is rejected (`TASK_DECISION_INVALID`).
7. Do **not** auto-create Tasks on Decision approve/close.

## 4. Create from Decision

1. Authoritative write path: **`POST /api/v1/tasks`** with optional `decision_id`.
2. No nested write API under Decisions is required.
3. Prefill (minimal):
   - Set `decision_id` when created from Decision UX.
   - Optionally suggest `organization_unit_id` from Decision (user may clear/change).
   - **Do not** silently copy Decision `responsible_employee_id` as assignee.
   - **Do not** auto-copy Decision title as Task title (user enters title).
4. Decision details CTA **إنشاء مهمة** (`tasks.create`) opens Task create drawer pre-linked — does not auto-submit.

## 5. Assignee (single Employee)

1. MVP assignee is **exactly one** `assigned_to_employee_id` (nullable until assigned).
2. **Group / multi-assignee is deferred** — prior stub claimed MVP group support but semantics were never locked; managers create multiple Tasks instead. Restoring multi-assignee requires Change Request.
3. Assignee points to **Employee** (business identity). User is never the assignee FK.
4. Same tenant; **active** Employee required on new assignment / reassignment; inactive historical reference retained.
5. Self-assignment allowed if actor has `tasks.assign` (or create-with-assignee) and Employee is valid.
6. Unassigned Tasks exist only in `draft`.

## 6. Assignment history

Append-only `task_assignment_history` for every assignee change (including initial assign and clear-to-null only if supported — MVP: clear not allowed after assign except via cancel terminal without clearing history).

| Event | Record |
|---|---|
| First assign | `from_employee_id` null → `to_employee_id` set |
| Reassign | previous → new |
| Actor | `performed_by` User |
| Comment | optional |

Generic AuditTrail alone is insufficient for operational assignment timelines — explicit history is required (mirror status transitions pattern).

## 7. Organization unit

1. `organization_unit_id` nullable; same tenant; active on new assignment; inactive historical OK.
2. Referenced units are delete-protected.
3. No row-level org authorization in MVP — capability (+ limited assignee self-service) only.

## 8. Dates and overdue

1. `start_date` / `due_date` are nullable `DATE` (not datetime).
2. When both set: `due_date >= start_date` (`TASK_INVALID_DATE_RANGE`).
3. `completed_at` is server-generated **datetime** on complete.
4. Tenant timezone applies to “today” / overdue comparisons; storage remains calendar dates for due/start.
5. **Overdue (derived, never a status):**  
   `due_date < today(tenant tz)` **AND** status ∈ {`draft`,`assigned`,`in_progress`}.  
   Terminal `completed` / `cancelled` are never overdue.
6. Due-soon / overdue **detection** remains derived on Tasks; **notification delivery jobs** owned by Sprint 016 (ADR-0013).

## 9. Priority

| Value | Arabic UI |
|---|---|
| `low` | منخفضة |
| `medium` | متوسطة (default) |
| `high` | عالية |

(Stub “High/Medium/Low” mapped to this enum — no `urgent`.)

## 10. Lifecycle (locked)

| Status | Meaning |
|---|---|
| `draft` | Unassigned; content editable |
| `assigned` | Has assignee; not started |
| `in_progress` | Execution started |
| `completed` | Done (terminal) |
| `cancelled` | Abandoned (terminal) |

**Removed vs early stub:** `Blocked` (omitted), `Overdue` as status (derived), `New` renamed into `draft`/`assigned`.

### Allowed transitions

| From | To | Action | Permission |
|---|---|---|---|
| `draft` | `assigned` | assign (set assignee) | `tasks.assign` |
| `draft` | `cancelled` | cancel | `tasks.change_status` |
| `assigned` | `in_progress` | start | `tasks.change_status` *or* assignee self-service |
| `assigned` | `assigned` | reassign | `tasks.assign` |
| `assigned` | `cancelled` | cancel | `tasks.change_status` |
| `in_progress` | `completed` | complete | `tasks.complete` *or* assignee self-service |
| `in_progress` | `assigned` | reassign (optional keep in_progress?) — **lock: reassign allowed while `assigned` or `in_progress`; status stays `in_progress` if already started** | `tasks.assign` |
| `in_progress` | `cancelled` | cancel | `tasks.change_status` |

**Create with assignee:** initial status **`assigned`** (assignment history row written); not `draft`.

**Not allowed:** free PATCH of `status`; reopen from completed/cancelled; assignee-less `assigned`/`in_progress`.

### Cancel

- Allowed from `draft` \| `assigned` \| `in_progress`.
- **Comment/reason required.**
- Terminal; assignee retained historically.
- Cancelled Tasks **do not** block Decision close.

### Completion (measurement)

1. Only from `in_progress`.
2. `completion_notes` **required** (Workflow 1 Measurement — textual result).
3. Server sets `completed_at = now()`, `progress_percent = 100`.
4. No Documents/attachments required.
5. Completing one Task never auto-closes its Decision.

### Progress

1. `progress_percent` integer 0–100.
2. Updatable via `PUT /tasks/{task}/progress` while `assigned` or `in_progress` (`tasks.change_status` or assignee self-service for own task).
3. Values outside 0–100 rejected; complete forces 100.

## 11. Editing rules

1. Content editable in **`draft` and `assigned`**: `title`, `description`, `notes`, `organization_unit_id`, `priority`, `start_date`, `due_date`.
2. After `in_progress`: content immutable except progress endpoint and completion payload.
3. Never PATCH: `task_number`, `tenant_id`, `status`, `decision_id`, `created_by`, `completed_at`, `assigned_to_employee_id` (assignee only via assign endpoint).
4. Terminal states: fully immutable.

## 12. Delete policy

1. Hard delete **only** when `status = draft` **and** zero rows in `task_status_transitions` **and** zero rows in `task_assignment_history`.
2. After assign/start/cancel/complete: no hard delete.
3. No SoftDeletes.

## 13. Decision closure gate (Sprint 012 change)

When closing a Decision (`approved → closed`):

1. If any Task with `decision_id = this Decision` has status in `{draft, assigned, in_progress}` → reject with **`DECISION_CLOSE_NOT_ALLOWED`** / `TASK_DECISION_HAS_OPEN_TASKS` (stable Decision API code remains `DECISION_CLOSE_NOT_ALLOWED`).
2. Cancelled and completed Tasks do not block close.
3. Decisions with **zero** linked Tasks may still close (administrative path remains for Decisions never executed via Tasks).
4. Completing the last open Task does **not** auto-close the Decision — an authorized actor must still call close.

Update Decisions module docs/tests at implementation time (spec lock here + ADR-0009).

## 14. Documents

1. No attachments in Sprint 012 (measurement remains `completion_notes`).
2. **Sprint 013 owns** file infrastructure ([09-documents/](../09-documents/), ADR-0010): optional morph alias `task`.
3. At Documents implementation: Task details gain **المستندات**; until then omit upload UI.
4. Completing a Task never requires Documents.

## 15. Notifications (Sprint 016 ownership)

In-app delivery owned by [12-notifications/](../12-notifications/) (ADR-0013).

| Type (MVP delivered) | When | Recipient |
|---|---|---|
| `TASK_ASSIGNED` / `TASK_REASSIGNED` | Assign / reassign | Assignee Employee’s User if linked |
| `TASK_COMPLETED` | Complete | `created_by` if ≠ actor |
| `TASK_DUE_SOON` / `TASK_OVERDUE` | Daily job | Assignee User if linked |

Not delivered in MVP (noise): create/start/progress/cancel hooks. No email/SMS/push. Employee without User → skip assignee notification.

## 16. Self-service and User↔Employee

1. Broad management uses `tasks.*` capabilities (no role-name checks).
2. **Limited assignee self-service** (ADR-0009): if the authenticated User has a linked Employee and that Employee id equals `assigned_to_employee_id`, Policy may allow **view**, **start**, **update progress**, and **complete** on that Task — even without the broad `tasks.change_status` / `tasks.complete` grants — **provided** the User also holds at least `tasks.view` **or** a dedicated minimal grant: lock that self-service requires **`tasks.view`** plus assignee match (Employee persona receives `tasks.view` by default for execution visibility).
3. Self-service does **not** allow: create, delete, assign/reassign, cancel, edit content after start, cross-tenant access.
4. Employee without linked User: Task exists; only managers with capabilities can transition it.
5. Never auto-create Users from Employees.

## 17. Tenancy and security

1. All Task queries use `UsesTenantScope` / `TenantOwned`.
2. Cross-tenant → **404**.
3. Same-tenant validation for Decision, org unit, assignee Employee.
4. `tenant_id` injection ignored / rejected.
5. Self-service never bypasses tenant isolation.

### Threat model (mandatory tests)

Tenant A must not list/view/update/transition/assign Tenant B Tasks; cannot attach B Decision/Employee/org; cannot inject `tenant_id`. Also: status PATCH blocked; immutable number; terminal immutability; unauthorized self-service blocked; User without linked Employee cannot claim assignee rights; Decision close gate cannot be bypassed.

## 18. Performance

1. Paginate lists; tenant-leading indexes.
2. Eager-load compact Decision/org/assignee on list; transitions + assignment history on details only.
3. Overdue filter uses date comparison + status IN open set (indexed).
4. Decision details Tasks section: one nested list/count query — no N+1 per Task.
5. No weakening of tenant isolation for speed.

## 19. Audit

Critical actions emit named audit events (see [API.md](API.md)). Secrets never appear. Correlation ID required on transitions.
