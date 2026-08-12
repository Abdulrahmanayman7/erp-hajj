# Audit Trail — Permissions

> **Status:** Implemented (Sprint 018)
> **Last updated:** 2026-08-12

## Catalog

| Permission | Seeded in Sprint 018? | Purpose |
|---|---|---|
| `audit_logs.view` | **Yes** | List + show audit logs (tenant-scoped); per-entity history panel |
| `audit_logs.export` | **No** (reserved) | Future export of current-tenant audit data only — format TBD |

Exact names match [PERMISSION_MODEL.md](../../06-security/PERMISSION_MODEL.md). Do **not** introduce `audit.view` / `audit.delete` / `audit.edit`.

## Rules

1. No permission allows editing or deleting audit records.
2. Recording is system-level and **permission-free** (emitters do not check `audit_logs.*`).
3. Policies are **capability-based** — no role-name checks.
4. `audit_logs.view` means **tenant-wide** visibility of that tenant’s `audit_logs` rows. **No** organization-unit row filtering in MVP.
5. Holding `audit_logs.view` does **not** grant unrelated mutation permissions.
6. Auditor template gains `audit_logs.view` but remains read-oriented for business modules.
7. Frontend `can('audit_logs.view')` is UX only; backend Policy is authoritative.
8. Dashboard does **not** require a recent-audit widget in Sprint 018.

## Default template grants (when seeding)

| Role `code` | `audit_logs.view` |
|---|---|
| `tenant_owner` | Yes (all seeded permissions) |
| `general_manager` | Yes |
| `auditor` | Yes |
| `department_manager` | No |
| `supervisor` | No |
| `employee` | No |
| `read_only` | No |

`audit_logs.export` remains in the master catalog documentation but is **not seeded** until export is implemented.

## Policy

```text
AuditLogPolicy
  viewAny / view  → audit_logs.view
  create/update/delete → always deny (or omit abilities)
```

Gate optional alias only if project pattern needs it; prefer Policy on model binding.

## Module display name (catalog)

- Module key: `audit_logs`
- Arabic module label: `سجل التدقيق`
