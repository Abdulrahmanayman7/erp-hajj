# Employees and Supervisors — Business Rules

> **Status:** Implemented (Sprint 008)
> **Last updated:** 2026-08-09

## 1. Identity model

- **Employee** is the primary personnel entity.
- There is **one** employee record per person in the tenant MVP — never a separate “Supervisor” table for identity.
- **RBAC role** `supervisor` / `employee` ≠ personnel classification fields.
- Operational reporting uses **`employees.supervisor_id`** only.

## 2. Employee ↔ User

| Rule | Decision |
|---|---|
| Must every employee have a User? | **No** |
| May User exist without Employee? | **Yes** (current platform) |
| Link cardinality | Optional; **at most one** Employee per User (`user_id` unique per tenant / globally unique among non-null) |
| Same tenant | Required |
| Platform users | **Forbidden** as linked users |
| Assign / change link | `employees.update` (via dedicated user-link endpoint) |
| Disabled User | Link **retained**; UI shows disabled; login still blocked by Auth |
| Unlink | Allowed (`user_id = null`); does not delete User or Employee |
| Delete User | Prefer disable User (Sprint 006). If User hard-removed later: FK **SET NULL** |
| Creating credentials from Employees UI | **Out of scope** — select existing tenant Users only |

## 3. Organization unit

| Rule | Decision |
|---|---|
| Field | `organization_unit_id` → `organization_units.id` |
| Cardinality | **Exactly one** primary unit (no multi-membership in MVP) |
| Required? | **Yes** on create and while record exists (no draft status in MVP) |
| Unit status | Prefer **active** units on assign; assigning an **inactive** unit is **rejected** (`EMPLOYEE_ORGANIZATION_INVALID` / parent inactive style) |
| Cross-tenant | Impossible under TenantExists |
| Org unit delete | ON DELETE **RESTRICT** → org module returns `ORGANIZATION_UNIT_HAS_EMPLOYEES` |
| vs unit manager | Unrelated to `organization_units.manager_user_id` |

## 4. Direct supervisor (`supervisor_id`)

| Rule | Decision |
|---|---|
| Field | Nullable FK → `employees.id` (same tenant) |
| Meaning | Direct operational / reporting supervisor |
| Same organization unit required? | **No** — any active same-tenant employee (MVP) |
| Must be active? | **Yes** on assign/replace |
| Self | Forbidden |
| Cycles | Forbidden (A→A, A→B→A, longer chains) |
| Inactive supervisor later | Link **kept**; UI warns; new assignments cannot pick inactive supervisors |
| Subordinates on deactivate | **Not** auto-reassigned; remain pointing at inactive supervisor until updated |
| Permission | Assign/clear via `employees.assign_supervisor` (dedicated endpoint) |

Future (not Sprint 008): restrict supervisor to same unit / ancestor unit — Change Request.

## 5. Supervisor cycle prevention

Server-side only (frontend exclusion is UX):

1. Reject if `supervisor_id === employee.id`.
2. Walk ancestor chain of proposed supervisor (follow `supervisor_id`) with a bounded guard (e.g. max depth 64).
3. If current employee appears in that chain → `EMPLOYEE_SUPERVISOR_CYCLE`.
4. Perform checks inside a DB transaction with row locks where practical.

## 6. Employee numbering

| Rule | Decision |
|---|---|
| Format | `EMP-` + zero-padded sequence, e.g. `EMP-000001` |
| Scope | **Tenant-unique** (`UNIQUE (tenant_id, employee_number)`) |
| Generation | **Server-side** on create; client cannot choose |
| Immutable | Yes after create |
| Concurrency | Transaction + tenant-scoped counter/max+1 with unique constraint retry |
| Authorization | Never an auth input |

Config centralization at implementation: e.g. `config/employees.php` prefix/pad length — no magic strings scattered.

## 7. Status lifecycle

Statuses: **`active`** | **`inactive`**.

| Transition | Permission | Notes |
|---|---|---|
| create | `employees.create` | Default **`active`** |
| update profile fields | `employees.update` | Not status; not employee_number |
| activate | `employees.deactivate` *or* dedicated activate under same permission family — **use `employees.update` for activate and `employees.deactivate` for deactivate** (see PERMISSIONS) |
| deactivate | `employees.deactivate` | Preferred retirement |

### Inactive behavior

- Inactive employee **cannot** be newly assigned as supervisor.
- Existing supervisor links to an inactive employee **remain**.
- User link may remain.
- Historical references preserved; prefer inactive over delete.

### Activate permission note

Activate uses `employees.update` (mirror roles/org pattern for activate). Deactivate uses explicit `employees.deactivate`.

## 8. Positions / job titles (decided)

**Tenant-owned `positions` catalog** (not free-text-only on employee).

Rationale: reuse in filters/reporting; Employees module already deferred ownership from Sprint 007; avoids uncontrolled string drift.

MVP position fields: `name`, optional `code`, `is_active`, timestamps. No grades, bands, career paths.

Employee has optional `position_id` (nullable).

## 9. Deletion policy

- **No** normal `DELETE /employees` endpoint in Sprint 008.
- Prefer **deactivate**.
- Hard delete is **not** offered in MVP API (future platform purge only, if ever).
- No Laravel SoftDeletes.

## 10. Contact fields

- `phone`, `email` on Employee are **optional business contacts** (may differ from User email).
- **Not** treated as PDPL “sensitive” catalog fields in Sprint 008 (no national ID in MVP).
- Therefore **`employees.view_sensitive_data` is not seeded** in Sprint 008 (reserved in master catalog for future sensitive attributes).

## 11. Audit events

| Event | When |
|---|---|
| `EMPLOYEE_CREATED` | Create |
| `EMPLOYEE_UPDATED` | Profile update |
| `EMPLOYEE_ACTIVATED` | Activate |
| `EMPLOYEE_DEACTIVATED` | Deactivate |
| `EMPLOYEE_SUPERVISOR_CHANGED` | Supervisor set/cleared/changed |
| `EMPLOYEE_ORGANIZATION_CHANGED` | Primary unit changed |
| `EMPLOYEE_USER_LINKED` | User linked |
| `EMPLOYEE_USER_UNLINKED` | User unlinked |
| `POSITION_CREATED` / `UPDATED` / `ACTIVATED` / `DEACTIVATED` / `DELETED` | Position lifecycle |

No passwords/secrets in audit values.

## 12. Notifications

None in Sprint 008.

## 13. Performance assumptions

- Typical tenant: hundreds to low thousands of employees.
- List always paginated; eager-load unit, position, supervisor (minimal), linked user (minimal).
- No recursive query per row for supervisor chains on list (show immediate supervisor only).

## TBD (non-blocking)

- Whether inactive employees appear in operational pickers for future modules (tasks/custodies): decide per consumer.
- Stricter supervisor-must-be-in-same-unit rules: future CR.
- Re-introducing sensitive fields (national ID) + `employees.view_sensitive_data`: future CR.
