# Organizational Structure — Test Plan

> **Status:** Draft
> **Last updated:** 2026-08-06

- **Cross-tenant (mandatory):** tenant A cannot read or modify tenant B's units/positions (`404`).
- Feature: units CRUD; deep nesting (5+ levels); moving subtrees.
- Guard: circular reference attempts rejected; delete-with-children/employees restricted.
- Policy: `departments.*` matrix incl. `403` cases.
- Audit: create/update/delete records with old/new values.
- Validation: required fields; name uniqueness per decided constraint.
- Frontend: tree rendering in RTL; Department Selector component.
- E2E: build a 3-level structure and assign an employee (with employees module).
