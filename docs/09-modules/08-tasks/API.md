# Tasks — API (planned)

> **Status:** Planned contract — no endpoints exist yet
> **Last updated:** 2026-08-06

All endpoints follow [API_STANDARDS.md](../../04-api/API_STANDARDS.md).

```text
GET    /api/v1/tasks                        # tasks.view; filters: status, priority, assignee, department, due window, related entity
POST   /api/v1/tasks                        # tasks.create (optional decision_id / related entity)
GET    /api/v1/tasks/{task}                 # tasks.view (comments, evidence, progress)
PATCH  /api/v1/tasks/{task}                 # tasks.update
DELETE /api/v1/tasks/{task}                 # tasks.delete
POST   /api/v1/tasks/{task}/assign          # tasks.assign (audited)
POST   /api/v1/tasks/{task}/status          # tasks.change_status (allowed transitions enforced)
POST   /api/v1/tasks/{task}/complete        # tasks.complete (audited; completion evidence per rules)
```

## Behavior

- Overdue is computed by the backend (due date + status) and returned as part of the resource — it is never accepted from clients.
- Assignees must belong to the current tenant; violations return standardized errors.

## TBD

- Comments sub-resource paths: TBD.
- "My tasks" convenience filter/endpoint: TBD.
