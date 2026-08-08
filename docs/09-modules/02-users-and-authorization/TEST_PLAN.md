# Users and Authorization — Test Plan

> **Status:** Sprint 006 — specification complete; implementation pending  
> **Last updated:** 2026-08-08  
> **Stack:** Pest (backend) · Vitest (frontend)

Follow [TESTING_STRATEGY.md](../../07-testing/TESTING_STRATEGY.md) and tenancy matrix helpers from [00-tenancy/TEST_PLAN.md](../00-tenancy/TEST_PLAN.md).

---

## Backend (Pest)

### Users

| # | Scenario | Expect |
|---|---|---|
| U1 | List same-tenant users | 200; platform users excluded |
| U2 | Cross-tenant list / show / update / disable | 404 |
| U3 | Search name/email | filtered |
| U4 | Filter status + role_id | filtered |
| U5 | Default sort name ASC | order |
| U6 | Create success + invite path | 201; audit USER_CREATED |
| U7 | Duplicate global email | 422 USER_EMAIL_TAKEN |
| U8 | Validation failures | 422 |
| U9 | Unauthenticated | 401 |
| U10 | Authenticated without `users.create` | 403 |
| U11 | Update name/email | 200; USER_UPDATED |
| U12 | Disable / enable | audit events |
| U13 | Self-disable | 422 USER_SELF_DISABLE_FORBIDDEN |
| U14 | Disable last Owner | 422 USER_LAST_OWNER_PROTECTED |
| U15 | Assign roles (replace) | USER_ROLES_CHANGED |
| U16 | Assign cross-tenant role id | 404/422; no pivot |
| U17 | Assign inactive role (new) | 422 ROLE_INACTIVE |
| U18 | Disabled user cannot login | existing Auth behavior |
| U19 | No DELETE user route | 404/405 |

### Roles

| # | Scenario | Expect |
|---|---|---|
| R1 | List tenant roles | 200 + counts |
| R2 | Create custom role | 201 |
| R3 | Duplicate name same tenant | 422 ROLE_NAME_TAKEN |
| R4 | Same name different tenants | both OK |
| R5 | System role delete | 422 ROLE_SYSTEM_PROTECTED |
| R6 | Update name; code immutable | |
| R7 | Activate / deactivate | audits; Owner role cannot deactivate |
| R8 | Assign permissions subset OK | ROLE_PERMISSIONS_CHANGED |
| R9 | Assign permission outside actor set | PERMISSION_ASSIGNMENT_FORBIDDEN |
| R10 | Assign `platform_tenants.*` | forbidden |
| R11 | Cross-tenant role access | 404 |
| R12 | Duplicate pivot prevented | DB unique / 422 |
| R13 | Effective permissions = union of active roles | `/me` + Gate |
| R14 | Permission cache invalidation on assign | stale not served |
| R15 | Last-owner role strip | USER_LAST_OWNER_PROTECTED / ROLE_LAST_OWNER_PROTECTED |
| R16 | Delete unused custom role | 200; ROLE_DELETED |
| R17 | Delete role in use | ROLE_IN_USE |

### Policies / Gates

One test per Sprint 006 permission capability (allow + deny).

### Database

| # | Scenario |
|---|---|
| D1 | Unique (`tenant_id`,`name`) on roles |
| D2 | Unique (`tenant_id`,`user_id`,`role_id`) on user_roles |
| D3 | Unique role_permissions |
| D4 | FK RESTRICT behaviors |
| D5 | Tenant-leading indexes exist (migration assertion or schema test) |

### Security

| # | Scenario |
|---|---|
| S1 | IDOR on user/role ids |
| S2 | Privilege escalation via role_ids payload |
| S3 | Self-escalation via permissions PUT on own role |
| S4 | Cross-tenant references in pivots |
| S5 | Mass-assignment `tenant_id` ignored |

---

## Frontend (Vitest)

### Users

- List render, loading, error, empty
- Search/filter controls
- Create validation + success
- Duplicate email error display
- Disable confirmation
- Role assignment UI
- Unauthorized actions hidden (`can()`)

### Roles

- List + badges
- Create
- System-role protection (delete disabled)
- Edit
- Permission matrix grouping + select + save
- Error handling on save

### Authorization UX

- Sidebar link hidden without permission
- Route with meta.permission → 403 page
- Action button hidden
- After role change, current-user query invalidates / permissions refresh
- `can` / `canAny` unit tests

---

## Mandatory cross-tenant suite

Tenant A actor must fail all of: list/view/update/disable B’s users; assign B’s roles; mutate B’s roles; attach B role to A user; enumerate B membership. Assert **404** where applicable.
