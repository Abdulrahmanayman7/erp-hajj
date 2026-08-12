# Dashboard — Permissions

> **Status:** Specification complete — implementation pending (Sprint 017)
> **Last updated:** 2026-08-12

## Catalog

| Permission | Purpose | Already in catalog? |
|---|---|---|
| `dashboard.view` | Access Dashboard page + `GET /api/v1/dashboard` | **Yes** (seeded; keep) |

### Do **not** add

```text
dashboard.contracts
dashboard.tasks
dashboard.inventory
dashboard.manage
```

Section visibility uses **existing** module permissions.

## Rules

1. `dashboard.view` is the **page/API gate**.
2. Each section additionally requires its module view permission (see [BUSINESS_RULES.md](BUSINESS_RULES.md) §2.2).
3. Notifications unread count uses **recipient ownership**, not a `notifications.*` permission (ADR-0013).
4. Policies/Gates only — **never** role-name checks.
5. Frontend `PermissionGuard` is UX-only; API must omit unauthorized sections.
6. Role templates already include `dashboard.view` for Owner, GM, Dept Manager, Supervisor, Employee, Auditor, Read-only — **no template redesign required** for Sprint 017. Module sections still depend on each template’s other grants.

## Policy sketch (implementation)

```text
DashboardPolicy::view(User $actor): bool
  → actor active + tenant_id set + can('dashboard.view')
```

No model instance Policy needed (no Dashboard Eloquent entity).

## Landing without `dashboard.view`

Authenticated Users lacking `dashboard.view` cannot use `/app` Dashboard. Route guard → **403** page. (All default templates currently grant it; custom roles may omit it.)
