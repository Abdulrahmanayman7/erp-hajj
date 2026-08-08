# Employees and Supervisors — Test Plan

> **Status:** Draft
> **Last updated:** 2026-08-06

- **Cross-tenant (mandatory):** tenant A cannot access tenant B employees (`404`); employee numbers can repeat across tenants but not within one.
- Feature: CRUD; validation (`422`); permission matrix (`403`).
- Sensitive data: response excludes national ID without `employees.view_sensitive_data`; includes it with the permission.
- Supervisor: classifying an employee adds supervisor data; no second identity record is created.
- Relations: unit/position/manager assignment; manager must belong to same tenant.
- Audit: created/updated records; masked sensitive values per audit rules.
- Frontend: sensitive field visibility; supervisor section conditional rendering.
- E2E: create employee → classify as supervisor → verify profile.
