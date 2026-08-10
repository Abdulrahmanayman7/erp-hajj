# Tasks — Permissions

> **Status:** Specified (Sprint 012) — **not implemented** (named in catalog; seed at implementation)  
> **Last updated:** 2026-08-10

Capabilities use `module.action` vocabulary. Policies check capabilities and (where approved) assignee self-service rules — never role names. Frontend gates are UX-only; backend is authoritative.

## Catalog (final MVP)

| Permission | Meaning |
|---|---|
| `tasks.view` | List/view Tasks (+ required base for assignee self-service visibility) |
| `tasks.create` | Create standalone or Decision-linked Tasks |
| `tasks.update` | Edit content in draft \| assigned |
| `tasks.assign` | Assign / reassign Employee |
| `tasks.change_status` | Start; cancel; update progress (managers) |
| `tasks.complete` | Complete Tasks (managers) |
| `tasks.delete` | Hard-delete untouched draft |

**Not added:** `tasks.start`, `tasks.cancel`, `tasks.reassign`, `tasks.progress` — folded into existing verbs.

### Action → permission map

| API action | Permission / access |
|---|---|
| GET list | `tasks.view` |
| GET show | `tasks.view` **or** assignee self-service |
| POST create | `tasks.create` |
| PATCH content | `tasks.update` |
| PUT assignee | `tasks.assign` |
| POST start | `tasks.change_status` **or** assignee self-service |
| PUT progress | `tasks.change_status` **or** assignee self-service |
| POST complete | `tasks.complete` **or** assignee self-service |
| POST cancel | `tasks.change_status` (managers only — **no** self-cancel) |
| DELETE | `tasks.delete` |

## Policy: `TaskPolicy`

| Ability | Rule |
|---|---|
| `viewAny` | `tasks.view` |
| `view` | (`tasks.view` **or** assigneeSelf) + same tenant |
| `create` | `tasks.create` |
| `update` | `tasks.update` + status draft\|assigned + same tenant |
| `assign` | `tasks.assign` + non-terminal + same tenant |
| `changeStatus` | (`tasks.change_status` **or** assigneeSelf for start/progress) + valid status |
| `complete` | (`tasks.complete` **or** assigneeSelf) + `in_progress` |
| `delete` | `tasks.delete` + untouched draft |

### Assignee self-service (`assigneeSelf`)

True iff:

1. Authenticated User has `users.employee` / Employee link (`employees.user_id = actor.id` — use existing Employee↔User relation), and  
2. That Employee’s id equals `task.assigned_to_employee_id`, and  
3. Actor holds at least `tasks.view` (so visibility is still capability-gated at tenant level for “see my tasks”), and  
4. Same tenant.

Self-service **cannot** escalate to assign/cancel/delete/create/edit locked content.

See [ADR-0009](../../10-decisions/ADR-0009-TASK-ASSIGNEE-AND-DECISION-GATE.md).

## Default system role template grants

| Role template | view | create | update | assign | change_status | complete | delete |
|---|---|---|---|---|---|---|---|
| Tenant Owner | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| General Manager | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Department Manager | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Supervisor | ✓ | — | — | — | — | — | — |
| Employee (base) | ✓ | — | — | — | — | — | — |
| Auditor | ✓ | — | — | — | — | — | — |

Supervisor/Employee rely on **assignee self-service** for start/progress/complete on their own Tasks. Managers retain broad `change_status` / `complete` / `assign`.

Do not grant broad assign/complete to Employee base.

## Decision details affordance

`إنشاء مهمة` requires **`tasks.create`**. Viewing Decision still requires `decisions.view`. Closing Decision with open Tasks blocked regardless of Tasks permissions (Decision close permission still required).
