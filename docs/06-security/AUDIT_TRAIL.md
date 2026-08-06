# Audit Trail

> **Status:** Approved requirement; storage design TBD
> **Last updated:** 2026-08-06

## Purpose

Define what must be audited and the guarantees the audit trail provides. The audit trail is both an MVP module ([docs/09-modules/14-audit-trail/](../09-modules/14-audit-trail/)) and a cross-cutting requirement — **no module may bypass audit logging**.

## Audit Record Fields

| Field | Notes |
|---|---|
| Tenant | `tenant_id` — always present for tenant actions |
| User | Acting user |
| Action | Machine-readable action name |
| Entity type | Affected entity class/type |
| Entity ID | Affected record |
| Timestamp | When |
| IP address | Request origin |
| Device / user agent | Client metadata |
| Route or source | Endpoint, console command, or job source |
| Old values | Before state for updates |
| New values | After state |
| Reason or comment | Where applicable (e.g. adjustments, transitions) |
| Correlation ID | Where useful to group related records |

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
- Login success; security-relevant login failure.
- Platform Super Admin access to tenant data (always).

Each module lists its audit events in its `BUSINESS_RULES.md` / `ACCEPTANCE_CRITERIA.md`.

## Guarantees

- **Passwords, tokens, and secrets must never be stored in audit values** (masked field list per module: TBD).
- **Audit logs must not be editable by normal users** — immutable through the application; viewing requires `audit_logs.view`.
- Audit records include `tenant_id` and are tenant-scoped for viewing.
- Deleting a business record never deletes its audit history.

## TBD

- Storage design (single table vs. per-domain, package vs. hand-rolled): TBD.
- Retention period: TBD.
- Export format for `audit_logs.export`: TBD.
- Masked/sensitive field list per module: TBD.
