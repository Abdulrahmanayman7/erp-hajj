# Audit Trail — API (planned)

> **Status:** Planned contract — no endpoints exist yet
> **Last updated:** 2026-08-06

All endpoints follow [API_STANDARDS.md](../../04-api/API_STANDARDS.md). Read-only — there are no create/update/delete endpoints by design.

```text
GET /api/v1/audit-logs               # audit_logs.view; filters: user, action, entity type, entity id, date range
GET /api/v1/audit-logs/{auditLog}    # audit_logs.view
GET /api/v1/audit-logs/export        # audit_logs.export (format TBD; current tenant only; may be queued)
```

## Behavior

- Per-entity audit history (Audit History Panel) uses the same listing endpoint filtered by entity type + ID.
- Old/new values returned with masked sensitive fields (mask list TBD).
- Large exports may run as queued jobs with tenant context (delivery mechanism TBD).
