# Audit Trail — Permissions

> **Status:** Approved
> **Last updated:** 2026-08-06

| Permission | Purpose |
|---|---|
| `audit_logs.view` | View audit logs (tenant-scoped) and per-entity audit history |
| `audit_logs.export` | Export audit logs (current tenant only) |

## Rules

- No permission allows editing or deleting audit records.
- The dashboard's "recent audit activity" widget requires `audit_logs.view`.
- Recording is automatic and permission-free (system-level) — permissions gate viewing/export only.
