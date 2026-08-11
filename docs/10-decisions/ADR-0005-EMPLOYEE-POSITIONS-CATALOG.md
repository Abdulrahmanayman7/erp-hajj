# ADR-0005: Tenant-owned positions catalog for employees

> **Status:** Accepted  
> **Date:** 2026-08-09  
> **Sprint:** 008 — Employees + Supervisors (implemented)

## Context

Employees need a job title / المسمى الوظيفي. Sprint 007 deferred positions to the Employees track. Options:

1. **Free-text `job_title` string** on `employees` only.
2. **Tenant-owned `positions` catalog** with optional `employees.position_id`.

MVP must avoid full HR grading while supporting filter/report reuse.

## Decision

Use a minimal tenant-owned **`positions`** table (`name`, optional `code`, `is_active`) and optional `employees.position_id`.

Do **not** implement grades, bands, career ladders, or compensation links.

## Consequences

### Positive

- Stable titles for filters and future reporting
- Aligns with glossary / Sprint 007 deferral ownership
- Matches permission naming `positions.*`

### Negative / trade-offs

- Extra CRUD surface vs a single string field
- Must handle position-in-use on delete (RESTRICT / deactivate)

### Rejected

- Free-text only: uncontrolled duplicates, weak filtering
- Full HR position architecture: out of MVP scope

## References

- [04-employees-and-supervisors/](../09-modules/04-employees-and-supervisors/)
- [ADR-0004](ADR-0004-ORGANIZATION-UNITS-HIERARCHY.md)
