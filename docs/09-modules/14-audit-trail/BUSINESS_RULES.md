# Audit Trail — Business Rules

> **Status:** Approved
> **Last updated:** 2026-08-06

- Every audit record captures: tenant, user, action, entity type, entity ID, timestamp, IP address, device/user agent, route or source, old values, new values, reason/comment where applicable, correlation ID where useful.
- Audited events (minimum) are listed in [AUDIT_TRAIL.md](../../06-security/AUDIT_TRAIL.md) — covering users, roles/permissions, employees, contract transitions, meetings, decisions, tasks, documents, inventory, custody, tenant settings, and logins.
- **Passwords, tokens, and secrets must never be stored in audit values.**
- **Audit logs must not be editable by normal users** — no update/delete through the application.
- Audit records include `tenant_id`; viewing is tenant-scoped.
- Deleting a business record never deletes its audit history.
- Exports contain only current-tenant data and require `audit_logs.export`.
- Platform Super Admin access to tenant data is itself always audited.

## TBD

- Retention period: TBD.
- Masked field list per module: TBD.
- Export format (CSV/Excel): TBD.
- Storage design (single table vs. partitioning): TBD at design.
