# Audit Trail — Acceptance Criteria

> **Status:** Draft
> **Last updated:** 2026-08-06

- [ ] All minimum audited events (per [AUDIT_TRAIL.md](../../06-security/AUDIT_TRAIL.md)) produce records with the full field set.
- [ ] Records include tenant, user, action, entity, timestamp, IP, user agent, route/source, old/new values.
- [ ] Passwords, tokens, and secrets never appear in audit values.
- [ ] Audit records are immutable — no API or UI path can edit or delete them.
- [ ] Viewing requires `audit_logs.view` and is tenant-scoped; export requires `audit_logs.export` and contains current-tenant data only.
- [ ] Deleting a business record preserves its audit history.
- [ ] Per-entity audit history renders in module detail pages.
- [ ] Cross-tenant access to audit data returns `404`/empty.
