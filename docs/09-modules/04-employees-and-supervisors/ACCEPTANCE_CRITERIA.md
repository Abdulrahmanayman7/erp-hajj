# Employees and Supervisors — Acceptance Criteria

> **Status:** Draft
> **Last updated:** 2026-08-06

- [ ] Employees CRUD works, tenant-scoped, with `404` for cross-tenant access.
- [ ] Employee number is unique per tenant.
- [ ] One primary unit, one position, one direct manager enforced.
- [ ] Sensitive fields hidden from API responses without `employees.view_sensitive_data`.
- [ ] Supervisor classification with supervisor data works without duplicating identity records.
- [ ] Employee created/updated events are audited (sensitive values handling per audit masking rules).
- [ ] Attachments link through the documents module.
- [ ] Lists support pagination, search, filters, sorting.
