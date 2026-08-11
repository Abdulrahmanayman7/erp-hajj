# ADR-0013: In-App Notification Ownership and Delivery Model

- **Status:** Accepted (specification — implementation pending Sprint 016)
- **Date:** 2026-08-11
- **Sprint:** 016 (Notifications)
- **Deciders:** ERP Hajj product / engineering (documentation lock)

## Context

MVP modules emit many audited lifecycle events. Early stubs mixed “notification hooks” with audit events and left unresolved: recipient identity (User vs Employee), channels, dedupe for schedulers, permissions for inbox access, and whether Laravel’s notification package is mandatory. MULTI_TENANCY §12 already requires tenant-scoped database notifications and job context isolation.

Without a single ownership model, implementers risk: fanout to all Owners, Employee-targeted rows without login, duplicate overdue spam, HTML/open-redirect payloads, and coupling Controllers to inbox inserts.

## Decision

### 1. MVP channel = in-app persistence only

Email/SMS/WhatsApp/FCM/Web Push are **out of MVP**. Design a `DeliveryAdapter` seam for future channels that consume (or parallel) persisted in-app records — domain modules never call providers directly.

### 2. Recipient = User (not Employee)

`recipient_user_id` is mandatory. Resolve Employee→User via `employees.user_id`. If missing → skip that recipient (never auto-create Users). Disabled Users are skipped.

### 3. Notifications ≠ Audit

Do not drive the inbox from `AuthorizationSecurityEvent` alone. Domain Actions may emit audit **and** call `NotificationDispatcher` independently. Creating/read-marking notifications does not create high-volume audit rows in MVP.

### 4. Dispatcher owns fanout

`Modules/Notifications` owns rules, recipient resolution, plain-text composition, dedupe, and insert. Controllers do not compose rows.

### 5. Sync persist + queued scanners

- Lightweight immediate notifications: persist after successful business commit; **swallow** notification failures (log).
- Large fanout / scheduled scans: queue/scheduler with `runAsTenant()`.
- Future external delivery: always queued.

### 6. Dedupe via `(tenant_id, dedupe_key)` unique

Scheduled types use date (or hour) buckets in tenant timezone. Lifecycle one-shots use stable occurrence keys.

### 7. No `action_url` column

Frontend maps `entity_type` + `entity_id` (Documents-style aliases). Prevents open redirects.

### 8. Recipient-owned access without `notifications.*` catalog verbs

Authenticated tenant Users access **only** their rows. No admin manage in MVP. No role-name checks.

### 9. Read model = `read_at`

No mark-unread; no user delete; retention pruning deferred.

### 10. Targeted catalog only

Approve the type list in [12-notifications/BUSINESS_RULES.md](../09-modules/12-notifications/BUSINESS_RULES.md). Do not notify on every audit event. Default Owner/GM broadcast is forbidden; the sole MVP capability fanout is `DECISION_SUBMITTED` → holders of `decisions.approve`.

## Consequences

- Positive: clear tenancy, IDOR model, scheduler safety, future channel adapters.
- Trade-off: Employees without Users miss in-app alerts (acceptable; managers still operate via modules).
- Trade-off: `DECISION_SUBMITTED` fanout may be wide in large tenants — monitor; CR may introduce explicit approver lists later.
- Implementation must register scheduler commands and wire Action hooks without expanding MVP types silently.

## References

- [MULTI_TENANCY.md](../02-architecture/MULTI_TENANCY.md) §12
- [12-notifications/](../09-modules/12-notifications/)
- ADR-0009 (Task assignee User↔Employee), ADR-0012 (custody holder User↔Employee)
