# Meetings — Test Plan

> **Status:** Draft
> **Last updated:** 2026-08-06

- **Cross-tenant (mandatory):** tenant A cannot access tenant B meetings (`404`).
- Feature: CRUD; status transitions (draft→scheduled→in progress→completed; cancel paths); invalid transitions rejected.
- Permissions: matrix for all six `meetings.*` permissions (`403` cases).
- Privileged delete: completed meeting delete blocked normally; allowed+audited with privileged permission.
- Audit: completion and cancellation audited.
- Sub-resources: attendees add/remove; minutes/recommendations with `meetings.manage_minutes`.
- Frontend: status-driven action visibility; RTL datetime picker.
- E2E: create meeting → complete → create decision from it (with decisions module).
