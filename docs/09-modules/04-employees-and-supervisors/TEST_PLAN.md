# Employees and Supervisors — Test Plan

> **Status:** Specified for Sprint 008 implementation
> **Last updated:** 2026-08-09

Tooling: **Pest** (backend), **Vitest** (frontend). Cross-tenant / policy failures block merge.

---

## Backend (Pest)

### A. Create / update / list

| # | Case |
|---|---|
| E01 | Create employee success; number `EMP-000001` pattern |
| E02 | Concurrent creates unique numbers (retry/constraint) |
| E03 | Required organization_unit_id |
| E04 | Inactive organization unit rejected |
| E05 | Foreign org unit / position → validation fail |
| E06 | Update profile; employee_number immutable |
| E07 | List search by name/number |
| E08 | Filters: status, organization_unit_id, supervisor_id, position_id |
| E09 | Default sort employee_number |
| E10 | Pagination meta |

### B. User link

| # | Case |
|---|---|
| E20 | Link active same-tenant user |
| E21 | Platform user rejected |
| E22 | Disabled user rejected on new link |
| E23 | User already linked → `EMPLOYEE_USER_ALREADY_LINKED` |
| E24 | Unlink sets null; User remains |
| E25 | Foreign tenant user id rejected |

### C. Supervisor

| # | Case |
|---|---|
| E30 | Assign active supervisor |
| E31 | Self rejected |
| E32 | Cycle A→B→A rejected |
| E33 | Longer cycle rejected |
| E34 | Inactive supervisor rejected on assign |
| E35 | Clear supervisor |
| E36 | Requires `employees.assign_supervisor` |

### D. Lifecycle

| # | Case |
|---|---|
| E40 | Deactivate / activate + audit |
| E41 | No DELETE route |
| E42 | Inactive not offered as supervisor (assign) |
| E43 | Subordinates retain supervisor_id after supervisor deactivate |

### E. Positions

| # | Case |
|---|---|
| E50 | Position CRUD |
| E51 | Delete with employees → `POSITION_IN_USE` |
| E52 | Tenant isolation on positions |

### F. Tenancy (mandatory)

| # | Case |
|---|---|
| E60 | List excludes other tenant |
| E61 | Show/update/supervisor/user foreign → **404** |
| E62 | `tenant_id` payload ignored |
| E63 | Cross-tenant supervisor/org/user/position refs blocked |

### G. RBAC

| # | Case |
|---|---|
| E70 | 401 unauthenticated |
| E71 | 403 without permission |
| E72 | Permission matrix per endpoint |

### H. Audit

| # | Case |
|---|---|
| E80 | Expected events for create/update/activate/deactivate/supervisor/org/user/position |
| E81 | No secrets in audit context |

### I. Org integration

| # | Case |
|---|---|
| E90 | Deleting org unit with employees blocked (org module / FK) |

---

## Frontend (Vitest)

| # | Case |
|---|---|
| F01 | Loading / empty / error states |
| F02 | Table renders columns |
| F03 | Search & filters update query params |
| F04 | Create drawer validation |
| F05 | Edit drawer; number read-only |
| F06 | Supervisor dialog excludes self |
| F07 | User link selector constraints |
| F08 | Org unit select uses tenant units |
| F09 | Deactivate confirm via AppConfirmDialog |
| F10 | Permission-aware CTAs/actions |
| F11 | API 422 domain errors surfaced |
| F12 | Mobile/responsive smoke where practical |
| F13 | Positions list/drawer basics |

---

## Out until later modules

- Attachments, contracts, custodies, payroll, attendance
- `/auth/me` employee payload (unless explicitly added)
- Playwright E2E
