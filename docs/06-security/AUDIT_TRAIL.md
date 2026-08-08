# Audit Trail

> **Status:** Approved requirement (correlation ID design finalized; auth event names aligned with Sprint 005); storage design TBD
> **Last updated:** 2026-08-08

## Purpose

Define what must be audited and the guarantees the audit trail provides. The audit trail is both an MVP module ([docs/09-modules/14-audit-trail/](../09-modules/14-audit-trail/)) and a cross-cutting requirement — **no module may bypass audit logging**.

## Audit Record Fields

| Field | Notes |
|---|---|
| Tenant | `tenant_id` — **nullable**: always present for tenant actions; platform events may be `NULL`; platform access affecting a tenant **must** record the target tenant's id |
| User | Acting user ID |
| Context type | **Platform or tenant** — which execution context produced the record |
| Action | Machine-readable action name |
| Entity type | Affected entity class/type |
| Entity ID | Affected record |
| Timestamp | When |
| IP address | Request origin |
| Device / user agent | Client metadata |
| Route or source | Endpoint, console command, or job source |
| Old values | Before state for updates |
| New values | After state |
| Reason or comment | **Required** for privileged/exceptional access and lifecycle transitions (e.g. tenant suspension); where applicable elsewhere |
| Correlation ID | **Mandatory** — see the design below |

## Correlation ID (decided design)

Every incoming request receives a **correlation ID** assigned by early middleware:

- An **incoming** correlation ID (header) is accepted **only** if it passes strict validation (bounded length, safe character set — exact pattern fixed at implementation); otherwise a new **secure random ID** is generated. Never trust arbitrary client strings into logs (log-injection vector).
- The ID is **returned in the response headers** so clients and support can reference it.
- It is included in: application logs, audit records, queued job payloads (and therefore retries and failed jobs), exports, and exceptional platform-access records — one ID traces a request end-to-end across process boundaries.
- It is **not a secret**, must **not** contain tenant or user personal information, and is **never** an authorization input.

## Audited Events (minimum)

- User created, updated, deleted, disabled.
- Role or permission changed.
- Employee created or updated.
- Contract reviewed, approved, signed, executed, closed, renewed.
- Meeting completed.
- Decision approved or closed.
- Task assigned or completed.
- Sensitive document downloaded; document uploaded or deleted.
- Inventory transaction created.
- Asset assigned or returned (custody events).
- Tenant settings changed.
- Tenant lifecycle transitions (created/activated/suspended/reactivated/archived) with actor, old/new status, reason, correlation ID, context type.
- Login success; security-relevant login failure (including rejections due to tenant status or disabled account).
- Logout; password-reset requested; password-reset completed (Authentication module — see [01-authentication/BUSINESS_RULES.md](../09-modules/01-authentication/BUSINESS_RULES.md) for event names: `LOGIN_SUCCESS`, `LOGIN_FAILED`, `LOGOUT`, `PASSWORD_RESET_REQUESTED`, `PASSWORD_RESET_COMPLETED`, `ACCOUNT_DISABLED_ACCESS_ATTEMPT`, `TENANT_BLOCKED_ACCESS_ATTEMPT`).
- Platform Super Admin access to tenant data (always) — recorded with the **target** tenant's id.
- Unauthorized attempts to enter `PlatformContext`.

Each module lists its audit events in its `BUSINESS_RULES.md` / `ACCEPTANCE_CRITERIA.md`.

## Guarantees

- **Passwords, tokens, and secrets must never be stored in audit values** (masked field list per module: TBD).
- **Audit records are append-only** — never updated or deleted through the application; viewing requires `audit_logs.view`.
- Audit records include `tenant_id` (per the nullable rules above) and are tenant-scoped for viewing.
- Deleting a business record never deletes its audit history.

## TBD

- Storage design (single table vs. per-domain, package vs. hand-rolled): TBD.
- Retention period: TBD.
- Export format for `audit_logs.export`: TBD.
- Masked/sensitive field list per module: TBD.
