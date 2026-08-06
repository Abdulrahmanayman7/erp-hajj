# Employees and Supervisors — UI (planned)

> **Status:** Planned — no pages exist
> **Last updated:** 2026-08-06

## Pages

- **Employees list** — Data Table with search and filters (unit, position, supervisor flag, status); sensitive columns never shown in lists.
- **Employee profile** — details tabs: basic info, organizational assignment, supervisor data (if classified), attachments, custodies (read-only from custodies module), audit history panel.
- **Create/edit form** — with User Selector (direct manager) and Department Selector components.

## Rules

- Sensitive fields render only for holders of `employees.view_sensitive_data` (backend enforces; UI mirrors).
- Supervisor section appears only for supervisor-classified employees.

## TBD

- Whether employee self-view (my profile) is part of this module or auth module: TBD.
