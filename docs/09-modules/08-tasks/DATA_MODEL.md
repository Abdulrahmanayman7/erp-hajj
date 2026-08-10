# Tasks — Data Model

> **Status:** Specified (Sprint 012) — **not implemented**  
> **Last updated:** 2026-08-10

## Tables (future migration order)

1. `task_number_sequences`
2. `tasks`
3. `task_status_transitions`
4. `task_assignment_history`

No multi-assignee pivot. No comments/attachments tables.

---

## 1. `task_number_sequences`

| Column | Type | Notes |
|---|---|---|
| `tenant_id` | FK → tenants | PK |
| `next_number` | unsigned int | Next value to allocate (starts at 1) |
| `updated_at` | timestamp | |

**Allocation:** `SELECT … FOR UPDATE`; format `TSK-` + pad 6.

---

## 2. `tasks`

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | bigint PK | no | |
| `tenant_id` | FK → tenants | no | TenantOwned |
| `task_number` | string(20) | no | Immutable `TSK-######` |
| `title` | string(255) | no | |
| `description` | text | yes | |
| `notes` | text | yes | |
| `status` | string(32) | no | enum below |
| `priority` | string(16) | no | `low`\|`medium`\|`high` default `medium` |
| `decision_id` | FK → decisions | yes | Immutable after create |
| `organization_unit_id` | FK → organization_units | yes | |
| `assigned_to_employee_id` | FK → employees | yes | Single assignee |
| `progress_percent` | unsigned tinyint | no | 0–100 default 0 |
| `start_date` | date | yes | |
| `due_date` | date | yes | |
| `completed_at` | timestamp | yes | Set on complete |
| `completion_notes` | text | yes | Required on complete |
| `created_by` | FK → users | no | |
| `created_at` / `updated_at` | timestamps | no | |

### Status enum

`draft` | `assigned` | `in_progress` | `completed` | `cancelled`

### Explicitly absent

- Multi-assignee pivots / `task_assignees`
- SoftDeletes
- Polymorphic `related_type` (Decision FK only in MVP)
- Blocked / overdue as stored status
- Document FKs

### Constraints

| Intent | Definition |
|---|---|
| `tasks_tenant_number_unique` | UNIQUE (`tenant_id`, `task_number`) |
| FK `decision_id` | `decisions` ON DELETE RESTRICT |
| FK org / employee | ON DELETE RESTRICT |
| App check | `due_date >= start_date` when both set |
| App check | `progress_percent` between 0 and 100 |
| App check | `assigned`/`in_progress` require non-null assignee |

### Indexes (tenant-leading)

| Columns | Purpose |
|---|---|
| (`tenant_id`, `task_number`) unique | Numbering |
| (`tenant_id`, `status`) | Filter |
| (`tenant_id`, `decision_id`) | Decision Tasks section |
| (`tenant_id`, `assigned_to_employee_id`) | Assignee / my tasks |
| (`tenant_id`, `organization_unit_id`) | Filter |
| (`tenant_id`, `due_date`) | Range / overdue |
| (`tenant_id`, `priority`) | Filter |
| (`tenant_id`, `created_at`) | Sort fallback |

---

## 3. `task_status_transitions`

Append-only (Contracts / Meetings / Decisions pattern).

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | bigint PK | no | |
| `tenant_id` | FK → tenants | no | |
| `task_id` | FK → tasks | no | CASCADE on draft hard-delete |
| `from_status` | string(32) | yes | |
| `to_status` | string(32) | no | |
| `performed_by` | FK → users | yes | |
| `comment` | text | yes | Required on cancel; optional elsewhere |
| `correlation_id` | string(64) | no | |
| `created_at` | timestamp | no | |

Indexes: (`tenant_id`, `task_id`, `created_at`), (`task_id`, `created_at`).

MVP: write rows on **real** transitions only (not on create unless create lands as `assigned` via assign edge — then write draft→assigned or null→assigned; lock: **create-with-assignee writes one transition `null → assigned`** OR treat initial status without transition and only record subsequent — prefer **record transition on every status change including create-with-assignee as `null → assigned`**; create as draft = **no** transition row (delete rule: zero transitions + zero assignment history).

---

## 4. `task_assignment_history`

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | bigint PK | no | |
| `tenant_id` | FK → tenants | no | |
| `task_id` | FK → tasks | no | CASCADE on draft hard-delete |
| `from_employee_id` | FK → employees | yes | |
| `to_employee_id` | FK → employees | yes | |
| `performed_by` | FK → users | no | |
| `comment` | text | yes | |
| `correlation_id` | string(64) | no | |
| `created_at` | timestamp | no | |

Index: (`tenant_id`, `task_id`, `created_at`).

---

## 5. Relationships

```
Tenant 1──* Task
User (created_by) 1──* Task
Decision 0..1──* Task          (tasks.decision_id)
OrganizationUnit 0..1──* Task
Employee (assignee) 0..1──* Task
Task 1──* TaskStatusTransition
Task 1──* TaskAssignmentHistory
```

Derived overdue: computed in API/resource, not stored.

---

## 6. Model contracts

- `Task`, transitions, assignment history: `TenantOwned` + `UsesTenantScope`.
- Mass-assignment: never fill `tenant_id`, `task_number`, `status`, `completed_at` via generic update.
- API Resources only.

---

## 7. Migration notes (do not run in Sprint 012 spec)

- `decisions` must already exist (Sprint 011).
- Do not alter `decisions` to add task arrays.
- Decisions close gate is application logic (+ tests), not a DB trigger.
