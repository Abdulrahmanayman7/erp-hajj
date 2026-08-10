# Tasks — Test Plan

> **Status:** Specified (Sprint 012) — **not implemented**  
> **Last updated:** 2026-08-10  
> Tooling: **Pest** (backend) · **Vitest** (frontend)

Failing security / cross-tenant / close-gate / self-service tests block merge.

---

## Backend (Pest)

### DATABASE

- [ ] Sequences / tasks / transitions / assignment_history FKs
- [ ] UNIQUE (`tenant_id`, `task_number`)
- [ ] Tenant-leading indexes
- [ ] No SoftDeletes

### NUMBERING

- [ ] First → `TSK-000001`; increments; per-tenant isolation; immutable; concurrency-safe

### CREATE

- [ ] Standalone draft
- [ ] Create-with-assignee → assigned + histories
- [ ] From approved Decision
- [ ] Reject draft/pending/cancelled/closed Decision
- [ ] Foreign Decision / org / employee
- [ ] `tenant_id` injection ignored
- [ ] Invalid date range

### ASSIGN

- [ ] Draft → assigned
- [ ] Reassign in assigned/in_progress
- [ ] Inactive/foreign employee rejected
- [ ] Terminal blocked
- [ ] History + audit ASSIGNED/REASSIGNED

### UPDATE

- [ ] Draft/assigned content OK
- [ ] In-progress/terminal immutable content
- [ ] Number/status/decision_id not patchable

### LIFECYCLE

- [ ] start / progress / complete / cancel valid + invalid
- [ ] Cancel comment required
- [ ] Complete notes required → `TASK_COMPLETION_REQUIREMENTS_NOT_MET`
- [ ] completed_at + progress 100
- [ ] Transition rows + correlation + actor
- [ ] No reopen

### OVERDUE

- [ ] Derived true/false; terminal excluded; timezone-aware today

### SELF-SERVICE

- [ ] Linked assignee User: view/start/progress/complete own
- [ ] Cannot modify other assignees’ Tasks
- [ ] Cannot cancel/assign/delete via self-service
- [ ] User without Employee link blocked from self-service
- [ ] Still tenant-isolated

### DECISION INTEGRATION

- [ ] Many Tasks per Decision
- [ ] Close Decision with open Tasks → `DECISION_CLOSE_NOT_ALLOWED`
- [ ] Close OK when only completed/cancelled Tasks (or zero Tasks)
- [ ] Complete last Task does not auto-close Decision

### DELETE

- [ ] Untouched draft OK
- [ ] After assign/transition rejected

### TENANCY

- [ ] Full list/show/update/assign/start/complete/cancel/delete isolation (404)

### RBAC

- [ ] Each `tasks.*` permission gates mapped actions
- [ ] Dept Mgr / Employee / Auditor defaults

### AUDIT

- [ ] All critical events; no secrets

### LIST

- [ ] Filters incl. `assigned_to_me`, `overdue`, `decision_id`
- [ ] Pagination; default sort; no N+1

---

## Frontend (Vitest)

- [ ] List loading/empty/error
- [ ] Filters + مهامي + overdue badge
- [ ] Create standalone + from Decision
- [ ] Assign/reassign UX
- [ ] Lifecycle actions + complete dialog
- [ ] Self-service action visibility
- [ ] Details sections
- [ ] Decision linked Tasks + close blocked message
- [ ] Sidebar visibility
- [ ] Responsive/RTL smoke where applicable

---

## Explicit non-goals

- Documents upload tests
- Notification delivery
- Multi-assignee / group pivot
- Recurring tasks
- Dashboard KPI widgets
