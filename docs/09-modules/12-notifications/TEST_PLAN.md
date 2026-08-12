# Notifications — Test Plan

> **Status:** Implemented (Sprint 016)
> **Last updated:** 2026-08-12
> Tooling: **Pest** (backend) · **Vitest** (frontend)
> Executable coverage: `tests/Feature/Notifications/NotificationTest.php` + frontend Vitest under `modules/notifications/`.

Failing security / cross-tenant / IDOR tests block merge at implementation.

---

## Backend (Pest)

### DATABASE

- [ ] `notifications` columns/FKs/indexes/unique `(tenant_id, dedupe_key)`
- [ ] No SoftDeletes; mass-assignment hardened

### TENANCY

- [ ] Tenant A notification never listed/shown/read by Tenant B
- [ ] Cross-tenant id → 404
- [ ] Job/scheduler restores and clears TenantContext

### RECIPIENT OWNERSHIP

- [ ] User B cannot show/read User A’s notification (same tenant) → 404
- [ ] read-all only affects actor’s rows

### LIST / SHOW / FILTERS

- [ ] Default newest first; pagination
- [ ] `unread_only`, `type`, `severity`, date range

### READ ONE / READ ALL

- [ ] Sets `read_at`; idempotent re-read
- [ ] Unread count decrements

### UNREAD COUNT

- [ ] Matches unread rows; indexed path; no full collection load

### EVENT GENERATION

- [ ] Dispatcher called from Actions; no controller inserts
- [ ] Business action succeeds when notification insert fails (caught)

### TASKS

- [ ] `TASK_ASSIGNED` / `REASSIGNED` → assignee User only if linked
- [ ] Employee without User → no row
- [ ] `TASK_DUE_SOON` / `TASK_OVERDUE` daily dedupe

### MEETINGS

- [ ] Scheduled/rescheduled/cancelled → attendee Users
- [ ] `MEETING_STARTING_SOON` window + dedupe

### DECISIONS

- [ ] `DECISION_SUBMITTED` → users with `decisions.approve` (exclude actor)
- [ ] Approve/return/close/cancel recipient sets

### CONTRACTS

- [ ] Expiring soon / expired → `created_by` (+ linked employee User)
- [ ] Respects `contracts.expiring_soon_days`

### CUSTODY

- [ ] Assigned → holder User; returned → `assigned_by`
- [ ] Overdue / expected-return-soon dedupe

### INVENTORY

- [ ] `STOCK_BELOW_MINIMUM` → warehouse responsible User only; skip if none

### DEDUPE

- [ ] Second scheduler run same bucket does not insert duplicate
- [ ] Distinct lifecycle occurrences insert separately

### DISABLED USERS

- [ ] Disabled recipient skipped

### USER WITHOUT EMPLOYEE

- [ ] Still receives `created_by` / approver notifications

### SECURITY

- [ ] No create endpoint
- [ ] Immutable fields reject mutation
- [ ] Plain text stored; XSS-sensitive characters not executed in API JSON
- [ ] No `action_url` column

### PERFORMANCE

- [ ] Unread count query uses tenant+recipient+read_at index
- [ ] List pagination; no N+1 entity loads

### QUEUE / SCHEDULER

- [ ] Commands iterate tenants with `runAsTenant`
- [ ] Suspended → release; archived → cancel behavior for notification jobs

---

## Frontend (Vitest)

- [ ] Bell renders; badge hidden at 0
- [ ] Unread badge shows count
- [ ] Panel list loading/empty/error
- [ ] Mark read mutation invalidates count
- [ ] Mark all read
- [ ] Deep-link route map for entity types
- [ ] Deleted/inaccessible target does not crash (router/404 handling)
- [ ] Mobile sheet behavior flags / classes
- [ ] No permission catalog gate blocking authenticated users
- [ ] Polling/query keys stable

No snapshot-only tests.
