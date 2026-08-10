# Tasks — API

> **Status:** Implemented (Sprint 012)
> **Last updated:** 2026-08-10  
> Base: `/api/v1` · Auth: Sanctum SPA · Envelope: [API_STANDARDS.md](../../04-api/API_STANDARDS.md)

All routes: authenticated + tenant-active. Authorization via `TaskPolicy`. Cross-tenant → **404**.

---

## Endpoints

| Method | Path | Permission / access | Notes |
|---|---|---|---|
| GET | `/tasks` | `tasks.view` | Paginated list |
| POST | `/tasks` | `tasks.create` | Standalone or with `decision_id` |
| GET | `/tasks/{task}` | `tasks.view` **or** assignee self-service | Details + histories |
| PATCH | `/tasks/{task}` | `tasks.update` | Content in draft\|assigned |
| DELETE | `/tasks/{task}` | `tasks.delete` | Untouched draft only |
| PUT | `/tasks/{task}/assignee` | `tasks.assign` | Assign / reassign |
| POST | `/tasks/{task}/start` | `tasks.change_status` **or** self-service | → `in_progress` |
| PUT | `/tasks/{task}/progress` | `tasks.change_status` **or** self-service | 0–100 |
| POST | `/tasks/{task}/complete` | `tasks.complete` **or** self-service | → `completed` |
| POST | `/tasks/{task}/cancel` | `tasks.change_status` | → `cancelled` (comment required) |

**No** generic `PATCH …/status`.  
**No** nested `/decisions/{id}/tasks` write API (list Decision’s tasks via `GET /tasks?decision_id=`).

---

## Create — `POST /tasks`

```json
{
  "title": "تجهيز قوائم الحجاج",
  "description": "…",
  "notes": null,
  "priority": "medium",
  "decision_id": 12,
  "organization_unit_id": 5,
  "assigned_to_employee_id": 8,
  "start_date": "2026-08-12",
  "due_date": "2026-08-20"
}
```

**Rules:**

- Omit `decision_id` → standalone.
- When set: Decision must be `approved`, same tenant.
- If `assigned_to_employee_id` present → status **`assigned`** + assignment history + status transition `null→assigned`.
- Else → status **`draft`**.
- Assign `task_number` via sequence; `progress_percent = 0`.
- Audit: `TASK_CREATED` (+ `TASK_ASSIGNED` when assignee set at create).

---

## Update — `PATCH /tasks/{task}`

Allowed in `draft` \| `assigned` only. Fields: title, description, notes, organization_unit_id, priority, start_date, due_date.

Locked → `TASK_IMMUTABLE`.

---

## Assign — `PUT /tasks/{task}/assignee`

```json
{ "assigned_to_employee_id": 8, "comment": "اختياري" }
```

- From `draft`: requires non-null employee → `assigned`.
- From `assigned` \| `in_progress`: reassign to another active same-tenant Employee (status unchanged).
- Terminal → `TASK_IMMUTABLE`.
- Null assignee after first assign: **not allowed** in MVP.
- History + `TASK_ASSIGNED` / `TASK_REASSIGNED`.

---

## Start — `POST /tasks/{task}/start`

- `assigned` → `in_progress`.
- Optional `{ "comment": "…" }`.
- Audit `TASK_STARTED`.

---

## Progress — `PUT /tasks/{task}/progress`

```json
{ "progress_percent": 40 }
```

- Allowed in `assigned` \| `in_progress`.
- Audit `TASK_PROGRESS_UPDATED`.

---

## Complete — `POST /tasks/{task}/complete`

```json
{ "completion_notes": "تم التنفيذ وفق الخطة" }
```

- `completion_notes` **required**.
- `in_progress` → `completed`; `completed_at` set; progress 100.
- Missing notes → `TASK_COMPLETION_REQUIREMENTS_NOT_MET`.
- Audit `TASK_COMPLETED`.

---

## Cancel — `POST /tasks/{task}/cancel`

```json
{ "comment": "لم يعد مطلوباً" }
```

- Comment **required**.
- From draft \| assigned \| in_progress.
- Audit `TASK_CANCELLED`.

---

## List — `GET /tasks`

| Param | Notes |
|---|---|
| `search` | `task_number`, `title` |
| `status` | string \| array |
| `decision_id` | |
| `organization_unit_id` | |
| `assigned_to_employee_id` | |
| `assigned_to_me` | bool — filter to User’s linked Employee |
| `priority` | |
| `due_date_from` / `due_date_to` | |
| `overdue` | bool — derived filter |
| `page` / `per_page` | |

### Sorting

Default operational order:

1. Open overdue first (derived)
2. Then `due_date` ASC (nulls last)
3. Then `id` DESC

Alternate: `sort=created_at` desc (newest first) when requested.

---

## Details — `GET /tasks/{task}`

Includes: all Task fields; `is_overdue` boolean; compact Decision `{id,decision_number,title,status}`; compact org/assignee/created_by; `status_transitions`; `assignment_history`.

Do **not** embed full Decision body or Documents.

---

## Decision close interaction

Decisions `POST /decisions/{id}/close` (existing) must, once Tasks ships, reject when open linked Tasks exist → `DECISION_CLOSE_NOT_ALLOWED`.

---

## Error codes

| Code | When |
|---|---|
| `TASK_NOT_FOUND` | Missing / cross-tenant (prefer generic 404) |
| `TASK_NUMBER_TAKEN` | Defensive |
| `TASK_INVALID_STATUS_TRANSITION` | Illegal action |
| `TASK_DECISION_INVALID` | Missing / wrong status / wrong tenant Decision |
| `TASK_EMPLOYEE_INVALID` | Assignee foreign or inactive on assign |
| `TASK_ORGANIZATION_INVALID` | Org foreign or inactive on assign |
| `TASK_INVALID_DATE_RANGE` | due < start |
| `TASK_IMMUTABLE` | Edit/delete/assign on locked Task |
| `TASK_ASSIGNEE_REQUIRED` | Assign without employee / start without assignee |
| `TASK_COMPLETION_REQUIREMENTS_NOT_MET` | Missing completion_notes |
| `DECISION_CLOSE_NOT_ALLOWED` | Decision close blocked by open Tasks |

---

## Audit events

| Event | When |
|---|---|
| `TASK_CREATED` | Create |
| `TASK_UPDATED` | Content PATCH |
| `TASK_ASSIGNED` | First assign (incl. create-with-assignee) |
| `TASK_REASSIGNED` | Reassign |
| `TASK_STARTED` | start |
| `TASK_PROGRESS_UPDATED` | progress |
| `TASK_COMPLETED` | complete |
| `TASK_CANCELLED` | cancel |
| `TASK_DELETED` | hard delete draft |

---

## Resource shape (illustrative)

```json
{
  "id": 1,
  "task_number": "TSK-000001",
  "title": "…",
  "description": null,
  "notes": null,
  "status": "assigned",
  "priority": "medium",
  "progress_percent": 0,
  "decision_id": 12,
  "organization_unit_id": 5,
  "assigned_to_employee_id": 8,
  "start_date": null,
  "due_date": "2026-08-20",
  "completed_at": null,
  "completion_notes": null,
  "is_overdue": false,
  "decision": { "id": 12, "decision_number": "DEC-000003", "title": "…", "status": "approved" },
  "organization_unit": { "id": 5, "name": "…" },
  "assigned_to_employee": { "id": 8, "employee_number": "EMP-000008", "full_name": "…" },
  "created_by": { "id": 3, "name": "…" },
  "status_transitions": [],
  "assignment_history": [],
  "created_at": "…",
  "updated_at": "…"
}
```
