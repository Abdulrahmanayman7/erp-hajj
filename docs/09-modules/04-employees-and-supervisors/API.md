# Employees and Supervisors — API (planned)

> **Status:** Planned contract — no endpoints exist yet
> **Last updated:** 2026-08-06

All endpoints follow [API_STANDARDS.md](../../04-api/API_STANDARDS.md).

```text
GET    /api/v1/employees                 # employees.view; filters: unit, position, supervisor flag, status
POST   /api/v1/employees                 # employees.create
GET    /api/v1/employees/{employee}      # employees.view (+ sensitive fields only with employees.view_sensitive_data)
PATCH  /api/v1/employees/{employee}      # employees.update
DELETE /api/v1/employees/{employee}      # employees.delete
```

## Behavior

- The employee resource conditionally includes sensitive fields based on the viewer's permissions.
- Supervisor data (experience, seasons, training, evaluation) is part of the employee resource for classified employees.

## TBD

- Supervisor classification endpoint (action endpoint vs. field on update): TBD.
- Attachment upload flow (via documents module endpoints): cross-module contract TBD.
