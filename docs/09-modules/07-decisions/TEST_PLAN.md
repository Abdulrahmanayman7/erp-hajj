# Decisions — Test Plan

> **Status:** Draft
> **Last updated:** 2026-08-06

- **Cross-tenant (mandatory):** tenant A cannot access or approve tenant B decisions (`404`); tasks cannot link across tenants.
- Feature: CRUD with/without meeting reference; validation (`422`).
- Workflow: approve/close transitions; close blocked with incomplete required tasks; close succeeds after all complete; single task completion does not auto-close.
- Permissions: matrix for all six `decisions.*` permissions.
- Audit: approval and closure records with actor/timestamps.
- Frontend: close button disabled state; related tasks rendering.
- E2E: meeting → decision → two tasks → complete both → close decision.
