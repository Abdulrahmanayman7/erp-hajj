# Decisions — API (planned)

> **Status:** Planned contract — no endpoints exist yet
> **Last updated:** 2026-08-06

All endpoints follow [API_STANDARDS.md](../../04-api/API_STANDARDS.md).

```text
GET    /api/v1/decisions                        # decisions.view; filters: status, priority, department, meeting, date range
POST   /api/v1/decisions                        # decisions.create (optional meeting_id)
GET    /api/v1/decisions/{decision}             # decisions.view (includes related tasks)
PATCH  /api/v1/decisions/{decision}             # decisions.update
DELETE /api/v1/decisions/{decision}             # decisions.delete
POST   /api/v1/decisions/{decision}/approve     # decisions.approve (action endpoint, audited)
POST   /api/v1/decisions/{decision}/close       # decisions.close (audited; blocked unless all required tasks completed)
```

## Behavior

- Closing a decision while required tasks are incomplete returns the standardized error with a machine-readable code.
- Task creation from a decision happens in the tasks module with `decision_id` (related entity).

## TBD

- Additional status transitions (block, cancel) as action endpoints: TBD with status semantics.
