# Organizational Structure — Business Rules

> **Status:** Approved for Sprint 007 (specification)
> **Last updated:** 2026-08-09

## 1. Domain separation

| Concept | Meaning | Owned by |
|---|---|---|
| **Tenant** | Customer / Hajj company boundary | Tenancy |
| **Organization unit** | Internal department / section / unit | This module |
| **RBAC role** | Authorization template (`general_manager`, …) | Users & Authorization |
| **Employee** | Staff identity + primary unit + position + direct manager | Employees (later) |
| **User** | Login account | Users & Authorization |
| **Unit manager** | User responsible for managing an organization unit | This module (`manager_user_id`) |
| **Employee direct manager** | Employee-level reporting / line supervisor | Employees (later) — **not** this field |

Organization unit responsibility ≠ RBAC permission. Tenant Owner does **not** bypass Policies; they manage units only when their roles include `organization_units.*`.

## 2. Entity strategy (decided)

- **Single entity:** `organization_units` — generic hierarchical node.
- **Not** separate tables for departments / sections / teams.
- **Type** is a fixed enum label for UX/reporting: `department` | `section` | `unit`.
- Type is **not** tenant-configurable in MVP (no custom type catalog).
- Type does **not** enforce parent/child type rules in MVP (e.g. section under section is allowed) — hierarchy is structural; type is descriptive. Soft UX guidance may recommend Department → Section → Unit but the API must not hard-block other combinations unless a Change Request adds rules later.

## 3. Hierarchy and maximum depth

| Rule | Decision |
|---|---|
| Model | Adjacency list: nullable `parent_id` → same table |
| Root units | `parent_id = NULL` |
| Root depth | **Depth 0** (roots). A direct child of a root has depth **1**, and so on |
| Maximum depth | **MVP application-level safety constraint: depth ≤ 8** (i.e. deepest allowed node depth is 8). This is **not** a database architectural limitation — the schema does not encode depth |
| Centralization | Implementation must use a **single named constant / config value** (e.g. `OrganizationUnit::MAX_DEPTH` or config key) — **not** scattered magic `8`s. Do not ship a runtime admin UI for it in Sprint 007 |
| Exceeding depth | Create or move that would produce a node (or moved subtree root) with depth > max → `422` `ORGANIZATION_UNIT_DEPTH_EXCEEDED` |
| Circular references | Forbidden — validated in application before write (walk ancestors; reject if target is self or descendant) |
| Cross-tenant parent | Impossible — parent resolved under `TenantContext` / `TenantExists`; foreign id → validation failure (same as missing) |
| Moving | Allowed via dedicated move action; moves the subtree with the node; must re-validate depth for the entire moved subtree |
| Inactive parent | Creating/moving a child under an **inactive** parent is **rejected** (`ORGANIZATION_UNIT_PARENT_INACTIVE`). Existing children of a deactivated parent remain until moved; UI warns |
| Inactive unit | Remains in tree (filterable); cannot be selected as parent for new/moved units; unit manager may remain assigned for history |

## 4. User membership (decided)

- Sprint 007 does **not** assign Users to units.
- An employee’s **one primary organizational unit** is owned by the **Employees** module (later).
- No `organization_unit_user` pivot in Sprint 007.
- No `organization_units.assign_users` permission or endpoints in Sprint 007.
- `/auth/me` does **not** gain organization fields in Sprint 007 (User has no unit membership yet).

## 5. Unit manager (decided)

`organization_units.manager_user_id` means: **the user responsible for managing the organizational unit**.

It does **not** mean:

- employee direct supervisor
- HR reporting manager
- employee line manager

Future Employees / Supervisors models may introduce separate **employee-level** reporting relationships. Those concepts **must remain distinct** from unit manager.

| Rule | Decision |
|---|---|
| Field | Optional `manager_user_id` → `users.id` (same tenant) |
| Cardinality | **At most one** unit manager per unit (MVP) |
| Must manager belong to unit? | **No** (no membership model yet) |
| Manager must be active user? | **Yes** on assign/replace; disabled user cannot be newly assigned |
| Existing manager disabled later | Assignment **kept**; UI shows warning; does not auto-clear |
| Clear manager | Allowed (`manager_user_id = null`) via update |
| Tenant Owner | Can administer all units via `organization_units.*` **without** being listed as unit manager |
| RBAC | Assigning someone as unit manager does **not** grant permissions |

Self-escalation: assigning yourself as unit manager does **not** grant RBAC permissions. Unit manager is organizational accountability metadata only.

## 6. Job titles / positions (decided)

- **Deferred** from Sprint 007.
- Positions / المسميات الوظيفية ship with (or immediately before) the **Employees** module.
- No `positions` table, API, UI, or `positions.*` permissions in Sprint 007.

## 7. Lifecycle and deletion

Statuses: **`active`** | **`inactive`**.

| Transition | Permission | Notes |
|---|---|---|
| create | `organization_units.create` | Default status `active` |
| update (name, type, sort_order, manager) | `organization_units.update` | Code immutable |
| move (re-parent) | `organization_units.update` | Separate action + audit |
| activate | `organization_units.update` | `inactive → active` |
| deactivate | `organization_units.update` | `active → inactive`; children **not** auto-deactivated |
| delete | `organization_units.delete` | Hard delete only when the **delete policy** below allows |

### Prefer deactivation

**Deactivation is the normal way to retire a unit.** Hard delete is exceptional.

### Hard-delete policy (MVP)

Hard delete is allowed **only when all** of the following are true:

1. Unit has **no child** organization units.
2. Unit has **no users/employees** or other **future business FK references** (employees, tasks, meetings, warehouses, documents, etc. when those modules exist).
3. Unit has **never participated in auditable business history** where record preservation is required (once such historical references exist, hard delete is rejected — use deactivate).
4. Actor has `organization_units.delete`.
5. Tenant isolation passes (same-tenant unit; cross-tenant → `404`).

Otherwise reject with an appropriate domain code (e.g. `ORGANIZATION_UNIT_HAS_CHILDREN`, `ORGANIZATION_UNIT_HAS_EMPLOYEES`, `ORGANIZATION_UNIT_IN_USE` / history-related code when defined by consuming modules).

**Sprint 007 implementation baseline:** enforce no-children + permission + tenancy. Wire additional reference checks as consumer modules ship (ON DELETE **RESTRICT** on their FKs).

**No soft deletes** unless a separate Change Request justifies them. Prefer `inactive` status for retention.

## 8. Codes

| Rule | Decision |
|---|---|
| Required | Yes |
| Scope | Unique per **tenant** (`tenant_id` + `code`) |
| Format | Same spirit as role codes: uppercase alphanumeric + underscore; max length 50; pattern fixed at implementation |
| Mutation | **Immutable** after create |
| Global uniqueness | Never |

## 9. Names

- Display name required; Arabic-first UI.
- Sibling uniqueness: unique `(tenant_id, parent_id, name)` — two units under the same parent cannot share the same name; roots use `parent_id IS NULL` uniqueness carefully (DB: nullable parent in unique index — MySQL treats NULLs specially; application enforces sibling name uniqueness; prefer generated unique key or enforce in Form Request).
- Names may repeat across different parents.

## 10. Sort order

- Optional integer `sort_order` (default `0`) for sibling ordering in tree UI.
- Not a nested-set left/right encoding.

## 11. Tenant isolation

- `organization_units` is **tenant-owned**: `TenantOwned` + `UsesTenantScope`.
- Never accept `tenant_id` from client input.
- Cross-tenant show/update/delete/move → **`404`** (no existence leak).
- Manager user id validated with **TenantExists** against same-tenant users.

## 12. Audit events

| Event | When |
|---|---|
| `ORGANIZATION_UNIT_CREATED` | Create |
| `ORGANIZATION_UNIT_UPDATED` | Update fields (excl. pure move/status if those fire dedicated events) |
| `ORGANIZATION_UNIT_MOVED` | Re-parent |
| `ORGANIZATION_UNIT_ACTIVATED` | Activate |
| `ORGANIZATION_UNIT_DEACTIVATED` | Deactivate |
| `ORGANIZATION_UNIT_DELETED` | Delete |
| `ORGANIZATION_MANAGER_ASSIGNED` | Unit manager set or changed (include old/new manager ids; no PII beyond ids/names already on user resource if logged) |

Passwords/tokens never in audit. Prefer ids + code + name snapshots; avoid unnecessary PII.

## 13. Notifications

None in Sprint 007.

## 14. Performance assumptions

- Typical tenant: tens to low hundreds of units (plan for ≤ ~500).
- Build tree in memory from one (or few) queries; no recursive N+1; no per-node user list fetch.
- Child counts via aggregation or subtree walk in memory after load.
- Employee counts: **N/A until Employees module** (UI shows `—` or omits).

## TBD (non-blocking for Sprint 007 start)

- Whether later modules add type-enforcement rules (Department may only contain Section, etc.): **TBD** — not required for Sprint 007.
- Exact MySQL unique index strategy for sibling names with `NULL` parent: decide at migration implementation.
- Positions table ownership details when Employees ships: Employees module docs.
- Exact domain error code name for “has auditable history / in use” beyond employees: finalize when first consumer that creates irreversible history ships.
