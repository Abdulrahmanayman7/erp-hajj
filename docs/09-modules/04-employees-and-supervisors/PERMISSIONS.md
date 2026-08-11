# Employees and Supervisors — Permissions

> **Status:** Approved
> **Last updated:** 2026-08-06

| Permission | Purpose |
|---|---|
| `employees.view` | List/view employees (non-sensitive fields) |
| `employees.create` | Create employees |
| `employees.update` | Update employees |
| `employees.delete` | Delete employees (audited; restriction rules TBD) |
| `employees.view_sensitive_data` | View sensitive fields (national ID, ...) |

## Rules

- API Resources must omit sensitive fields unless the viewer holds `employees.view_sensitive_data`.
- Supervisor classification management uses `employees.update` (dedicated permission: TBD if needed).
