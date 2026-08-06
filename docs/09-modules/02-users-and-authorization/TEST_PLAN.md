# Users and Authorization — Test Plan

> **Status:** Draft
> **Last updated:** 2026-08-06

- **Cross-tenant (mandatory):** tenant A admin cannot view/update/disable tenant B users or roles (`404`).
- Feature: users CRUD + disable/enable — success, `422`, `401`, `403` per permission matrix.
- Feature: roles CRUD + permission assignment; permission catalog listing.
- Policy: every `users.*` and `roles.*` permission enforced; Tenant Owner without permission denied.
- Self-escalation: user with `roles.assign_permissions` cannot grant themselves unauthorized elevation (per decided rule).
- Audit: create/update/delete/disable and permission changes produce records; no password values in audit.
- Validation: unique login identifier per tenant; required fields.
- Frontend: permission matrix component; Permission Guard visibility tests.
- E2E: create user → assign role → login as user → verify allowed/denied actions.
