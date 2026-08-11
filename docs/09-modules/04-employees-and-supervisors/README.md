# Module: Employees and Supervisors (الموظفون والمشرفون)

> **Status:** Documented — not implemented
> **Last updated:** 2026-08-06

## Purpose

Manage the tenant's staff. **Employee is the primary staff entity; a Supervisor is an employee with supervisory classification and permissions** — one identity record, never duplicated.

## Scope

- Employees CRUD with organizational assignment (primary unit, position, direct manager).
- Sensitive-data protection (`employees.view_sensitive_data`).
- Supervisor classification with supervisor-specific data (experience, seasons, training, evaluation).
- Attachments via the documents module.

## Out of scope

- Backup Supervisor behavior: future vision, **TBD unless explicitly approved for the MVP**.
- Payroll/HR administration beyond the listed fields (future scope).
- Multiple simultaneous assignments (future scope).

## References

- [03-organization-structure/](../03-organization-structure/) · [09-documents/](../09-documents/) · [05-contracts/](../05-contracts/)
