# Employees and Supervisors — API

> **Status:** Implemented (Sprint 008)
> **Last updated:** 2026-08-09

Envelope: [API_STANDARDS.md](../../04-api/API_STANDARDS.md). Auth + tenant middleware as existing modules.

---

## Employee resource (`EmployeeResource`)

```json
{
  "id": 1,
  "employee_number": "EMP-000001",
  "full_name": "…",
  "phone": null,
  "email": null,
  "status": "active",
  "hire_date": "2026-01-15",
  "notes": null,
  "organization_unit": { "id": 10, "name": "…", "code": "OPS" },
  "position": { "id": 3, "name": "منسق ميداني", "code": "FIELD" },
  "supervisor": {
    "id": 2,
    "employee_number": "EMP-000002",
    "full_name": "…",
    "organization_unit": { "id": 10, "name": "…", "code": "OPS" }
  },
  "user": { "id": 12, "name": "…", "email": "…", "status": "active" },
  "created_at": "…",
  "updated_at": "…"
}
```

- `position`, `supervisor`, `user` may be `null`.
- Do **not** embed roles/permissions or full org trees.
- List may return the same shape with lighter nesting (still eager-loaded ids/names).

---

## Employees endpoints

### List

| | |
|---|---|
| **Route** | `GET /api/v1/employees` |
| **Permission** | `employees.view` |
| **Query** | `search` (name, employee_number, email, phone) · `status=active\|inactive\|all` (default `all`) · `organization_unit_id` · `supervisor_id` · `position_id` · `sort` (default `employee_number`) · `direction` (`asc`\|`desc`) · `page` · `per_page` |

**Default sort:** `employee_number` ascending (stable operational identifier).

Paginated. Eager-load `organizationUnit`, `position`, `supervisor`, `user`.

### Show

`GET /api/v1/employees/{employee}` — `employees.view` — cross-tenant **404**.

### Create

`POST /api/v1/employees` — `employees.create`

**Body:** `full_name` (required), `organization_unit_id` (required), `position_id` (nullable), `phone`, `email`, `hire_date`, `notes`, `supervisor_id` (nullable; requires `employees.assign_supervisor` **or** allow on create if actor has assign_supervisor — **decision:** create may include `supervisor_id` only if actor has `employees.assign_supervisor`; otherwise omit), `user_id` (nullable; requires `employees.update` capability for linking — **decision:** allow `user_id` on create if actor has `employees.update`, else link later via PUT user).

Simpler rule for implementation:

- Create body: `full_name`, `organization_unit_id`, `position_id?`, `phone?`, `email?`, `hire_date?`, `notes?`
- Supervisor: only via `PUT …/supervisor` (`employees.assign_supervisor`)
- User link: only via `PUT …/user` (`employees.update`)

**Never accept:** `tenant_id`, `employee_number`, salary/HR fields.

**Audit:** `EMPLOYEE_CREATED`

### Update

`PATCH /api/v1/employees/{employee}` — `employees.update`

**Body:** `full_name`, `organization_unit_id`, `position_id`, `phone`, `email`, `hire_date`, `notes`  
**Prohibited:** `employee_number`, `status`, `supervisor_id`, `user_id`, `tenant_id`

If `organization_unit_id` changes → also emit `EMPLOYEE_ORGANIZATION_CHANGED`.

**Audit:** `EMPLOYEE_UPDATED`

### Activate

`POST /api/v1/employees/{employee}/activate` — `employees.update`  
**Audit:** `EMPLOYEE_ACTIVATED`

### Deactivate

`POST /api/v1/employees/{employee}/deactivate` — `employees.deactivate`  
**Audit:** `EMPLOYEE_DEACTIVATED`

### Assign supervisor

`PUT /api/v1/employees/{employee}/supervisor` — `employees.assign_supervisor`

**Body:** `{ "supervisor_id": <int|null> }`

Validations: same tenant, active, not self, no cycle.

**Audit:** `EMPLOYEE_SUPERVISOR_CHANGED`

### Link / unlink user

`PUT /api/v1/employees/{employee}/user` — `employees.update`

**Body:** `{ "user_id": <int|null> }`

Validations: same-tenant User, not platform, not already linked to another employee, prefer active User on link (disabled User rejected on new link).

**Audit:** `EMPLOYEE_USER_LINKED` / `EMPLOYEE_USER_UNLINKED`

### Explicitly not in Sprint 008

- `DELETE /employees`
- Attachment endpoints
- Supervisor dossier endpoints

---

## Positions endpoints

### List / show / create / update

```text
GET    /api/v1/positions                 # positions.view
POST   /api/v1/positions                 # positions.create
GET    /api/v1/positions/{position}      # positions.view
PATCH  /api/v1/positions/{position}      # positions.update
POST   /api/v1/positions/{position}/activate    # positions.update
POST   /api/v1/positions/{position}/deactivate  # positions.update
DELETE /api/v1/positions/{position}      # positions.delete (only if unused)
```

List query: `search`, `is_active`, pagination. Default sort: `name`.

---

## Stable error codes

| Code | HTTP | Meaning |
|---|---|---|
| `EMPLOYEE_NOT_FOUND` | 404 | Prefer generic route 404 |
| `EMPLOYEE_NUMBER_TAKEN` | 422 | Unique collision (rare with server gen) |
| `EMPLOYEE_SUPERVISOR_INVALID` | 422 | Missing / wrong tenant / inactive / self |
| `EMPLOYEE_SUPERVISOR_CYCLE` | 422 | Reporting cycle |
| `EMPLOYEE_USER_INVALID` | 422 | Bad / platform / disabled user |
| `EMPLOYEE_USER_ALREADY_LINKED` | 422 | User already linked |
| `EMPLOYEE_ORGANIZATION_INVALID` | 422 | Bad / inactive unit |
| `EMPLOYEE_POSITION_INVALID` | 422 | Bad / inactive position |
| `EMPLOYEE_INACTIVE` | 422 | Operation requires active employee |
| `POSITION_NOT_FOUND` | 404 | |
| `POSITION_IN_USE` | 422 | Delete blocked |
| `POSITION_CODE_TAKEN` / `POSITION_NAME_TAKEN` | 422 | |
| `AUTHORIZATION_DENIED` | 403 | |

Cross-tenant: **404**, no existence leak.
