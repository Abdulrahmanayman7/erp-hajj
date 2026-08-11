# ADR-0009: Task assignee model, self-service, and Decision close gate

- **Status:** Accepted
- **Date:** 2026-08-10
- **Sprint:** 012 (Tasks & Assignments specification)
- **Deciders:** ERP Hajj product / engineering (documentation lock)

## Context

Workflow 1 requires Decision → Task(s) → Execution → Measurement → Closure. Sprint 011 shipped Decisions with:

- Tasks deferred; Tasks will own nullable `decision_id`
- Administrative Decision close without Task checks
- Reserved `DECISION_CLOSE_NOT_ALLOWED`

Early Tasks stubs claimed individual **and group** assignments, progress %, and priorities, but left group semantics, assignee identity (User vs Employee), own-task policy, and the close gate unresolved. Personas expect Employees/Supervisors to execute assigned work. Prior modules avoided row-level org authorization.

## Decision

### 1. Single Employee assignee

- Task has exactly one optional `assigned_to_employee_id` (Employee).
- **No** multi-assignee pivot in MVP.
- Prior “group assignment” stub is **deferred** (semantics were TBD); managers create multiple Tasks per Decision instead.
- User is never the assignee FK — only audit actor and optional self-service subject via User↔Employee link.

### 2. Standalone Tasks + Decision link

- `decision_id` nullable; standalone allowed.
- New links only to **`approved`** Decisions; closed Decisions reject new Tasks.
- No auto-create on Decision approve/close.

### 3. Limited assignee self-service

- Capability-based Policies remain primary.
- Additionally, if `actor.employee.id === task.assigned_to_employee_id` and actor has `tasks.view`, allow **view / start / progress / complete** on that Task.
- Self-service does **not** allow assign, cancel, delete, create, or content edit after start.
- Unlinked Employees cannot self-serve; managers operate with broad `tasks.*`.

### 4. Progress + measurement

- Keep `progress_percent` 0–100 (committed measurable execution).
- Measurement in MVP = required `completion_notes` on complete (no Documents).

### 5. Decision close gate (Sprint 012)

- `approved → closed` is **blocked** while any linked Task is `draft` \| `assigned` \| `in_progress`.
- Cancelled/completed Tasks do not block; zero Tasks still allow close.
- Completing Tasks never auto-closes the Decision.
- Error: existing `DECISION_CLOSE_NOT_ALLOWED`.

## Consequences

### Positive

- Aligns assignee with Employees module (Meetings/Decisions pattern).
- Gives Employee/Supervisor personas a workable execution path without inventing role-name checks.
- Enforces Workflow 1 closure discipline once Tasks exist.
- Avoids multi-assignee complexity until business locks group semantics.

### Negative / trade-offs

- Defers true “group assignment” vs earlier stub wording — requires Change Request to restore.
- Self-service is a narrow row-level exception — must be tested rigorously so it cannot escalate.
- Decisions previously closable without Tasks become stricter after Sprint 012 implementation — intentional compatibility change documented here.

## Alternatives considered

| Alternative | Why rejected |
|---|---|
| Multi-assignee pivot now | Semantics TBD; overbuilds MVP |
| User as assignee FK | Breaks Employee-centric ops; orphans Employees without Users as first-class assignees |
| No self-service (managers only) | Conflicts with Employee/Supervisor personas |
| Full row-level org scoping | Out of approved tenancy/RBAC pattern |
| Keep administrative close forever | Weakens Workflow 1 measurement→closure once Tasks exist |
| Auto-close Decision when last Task completes | Forbidden by CORE_WORKFLOWS (“must not automatically close”) |

## References

- [08-tasks/](../09-modules/08-tasks/)
- [07-decisions/](../09-modules/07-decisions/)
- [ADR-0008](ADR-0008-DECISION-RECOMMENDATION-AND-APPROVAL.md)
- [CORE_WORKFLOWS.md](../01-business/CORE_WORKFLOWS.md)
- [USERS_AND_PERSONAS.md](../01-business/USERS_AND_PERSONAS.md)

## Implementation note (Sprint 012)

Implemented in application code: `TaskPolicy` assigneeSelf path; Auth `/me` `employee_id`; `CloseDecision` open-Task gate → `DECISION_CLOSE_NOT_ALLOWED`; Decision `tasks()` + `tasks_summary`; Tasks module API/UI. Critical Pest suites (tenancy, self-service, close gate, lifecycle, RBAC) green.
