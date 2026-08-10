# Decisions — Test Plan

> **Status:** Implemented (Sprint 011) — Pest/Vitest coverage added
> **Last updated:** 2026-08-10  
> Tooling: **Pest** (backend) · **Vitest** (frontend)

Failing security / cross-tenant tests block merge.

---

## Backend (Pest)

### DATABASE

- [ ] FKs: tenant, recommendation, org, issuer, responsible, created_by, transitions
- [ ] UNIQUE (`tenant_id`, `decision_number`)
- [ ] UNIQUE (`source_recommendation_id`) allows multiple NULL standalone
- [ ] Tenant-leading indexes exist as specified
- [ ] No SoftDeletes column

### NUMBERING

- [ ] First Decision → `DEC-000001`
- [ ] Increments within tenant
- [ ] Tenant B sequence independent of Tenant A
- [ ] `decision_number` immutable on PATCH
- [ ] Concurrent creates do not collide (sequence `FOR UPDATE`)

### CREATE — standalone

- [ ] Success with minimal title/body
- [ ] Optional org/employees/dates
- [ ] `tenant_id` injection ignored
- [ ] Foreign org → `DECISION_ORGANIZATION_INVALID`
- [ ] Foreign/inactive employee on assign → `DECISION_EMPLOYEE_INVALID`
- [ ] `due_date < effective_date` → `DECISION_INVALID_DATE_RANGE`
- [ ] 401 / 403 without auth / permission

### CREATE — from recommendation

- [ ] Final recommendation + completed meeting → draft Decision + prefills
- [ ] Non-final recommendation → `DECISION_RECOMMENDATION_INVALID`
- [ ] Meeting not completed → `DECISION_RECOMMENDATION_INVALID`
- [ ] Foreign recommendation → 404 or invalid (no leakage)
- [ ] Duplicate conversion → `DECISION_ALREADY_CREATED_FROM_RECOMMENDATION`
- [ ] Does not mutate recommendation row into a Decision
- [ ] Audit `DECISION_CREATED` with recommendation metadata

### UPDATE

- [ ] Draft content editable
- [ ] Non-draft → `DECISION_IMMUTABLE`
- [ ] Cannot PATCH `status`, `decision_number`, `source_recommendation_id`, `tenant_id`
- [ ] Return-to-draft restores editability

### LIFECYCLE

- [ ] Every valid transition succeeds + history row + actor + correlation_id
- [ ] Every invalid transition → `DECISION_INVALID_STATUS_TRANSITION`
- [ ] return-draft without comment → 422
- [ ] return-draft with comment → draft
- [ ] approve unauthorized → 403
- [ ] close only from approved
- [ ] cancel only from draft|pending_approval
- [ ] No reopen from closed/cancelled

### DELETE

- [ ] Untouched draft (no transition rows) deletable
- [ ] After submit/cancel history → delete rejected
- [ ] Advanced statuses → delete rejected

### TENANCY

- [ ] Tenant A cannot list/show/update/submit/approve/return/cancel/close/delete Tenant B Decisions
- [ ] Cannot convert Tenant B recommendation
- [ ] Cannot assign Tenant B org/employees
- [ ] Cross-tenant → 404

### RBAC

- [ ] Each `decisions.*` permission gates its mapped actions
- [ ] Policy never checks role names

### AUDIT

- [ ] `DECISION_CREATED` / `UPDATED` / `SUBMITTED` / `RETURNED_TO_DRAFT` / `APPROVED` / `CANCELLED` / `CLOSED` / `DELETED`
- [ ] No secrets in audit values

### LIST

- [ ] Filters: search, status, org, responsible, source_recommendation, meeting_id (if implemented), date ranges
- [ ] Pagination
- [ ] Default sort newest first
- [ ] No N+1 on list compact relations

---

## Frontend (Vitest)

- [ ] List loading / empty / error states
- [ ] List renders columns and filters
- [ ] Create Drawer standalone submit
- [ ] Create from recommendation flow (mutation + navigate)
- [ ] Duplicate-conversion API error surfaced
- [ ] Edit draft only; non-draft readonly
- [ ] Lifecycle action buttons by status + permission
- [ ] Approval gated by `decisions.approve`
- [ ] Details page sections (overview, source, timeline)
- [ ] Source recommendation + meeting link display
- [ ] Sidebar visibility with `decisions.view`
- [ ] Responsive / RTL smoke assertions where shared patterns allow

---

## Explicit non-goals for this suite

- Task creation / assignment tests
- Notification delivery
- Document upload
- Multi-stage approval
- Activation scheduler
