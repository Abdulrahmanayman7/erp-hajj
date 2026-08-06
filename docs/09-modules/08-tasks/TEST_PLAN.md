# Tasks — Test Plan

> **Status:** Draft
> **Last updated:** 2026-08-06

- **Cross-tenant (mandatory):** tenant A cannot view tenant B tasks (`404`); **assigning a tenant B user/employee is rejected** (explicit test per `fix/34`-style regression).
- Feature: CRUD; individual and group assignment; validation.
- Status machine: allowed transitions; Overdue derivation (due date passed + not completed/cancelled); Overdue never settable by clients.
- Permissions: matrix for all seven `tasks.*` permissions; own-task policy once decided.
- Audit: assignment and completion records.
- Decision linkage: completing tasks updates decision closability (with decisions module).
- Frontend: overdue flagging; group assignee selection; progress input.
- E2E: create → assign → progress updates → complete with evidence.
