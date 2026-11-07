# Season Projects — Data Model & Migration Design (Docs Only)

> **Status:** UPDATED — AWAITING FINAL OWNER APPROVAL
> **No migrations are to be applied in Part 0.**
> Locked: E-Q01, E-Q05, E-Q06, E-Q14

## 1. Tables (new)

### 1.1 `season_project_number_sequences`

| Column | Type | Notes |
|---|---|---|
| tenant_id | FK | PK part |
| season_id | FK | PK part |
| last_value | unsigned int | locked with FOR UPDATE |

### 1.2 `season_projects`

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| tenant_id | FK NOT NULL | TenantOwned |
| season_id | FK NOT NULL | SeasonContext |
| number | string | unique (tenant_id, season_id, number) among non-deleted |
| title | string | |
| description | text nullable | |
| status | enum | draft/active/completed/cancelled |
| owner_employee_id | FK nullable | required at activate |
| budget_amount | decimal(18,2) nullable | envelope; **no PO required to set** |
| currency_code | char(3) | default SAR; mismatch policy E-Q09 deferred |
| progress_percent | decimal(5,2) | rollup cache |
| delay_summary | enum/string nullable | optional rollup |
| activated_at / completed_at / cancelled_at | timestamps nullable | |
| created_by / updated_by | FK users nullable | |
| timestamps | | |
| deleted_at | timestamp nullable | **E-Q14 = B:** soft delete **draft only** |

Indexes: `(tenant_id, season_id, status)`, `(tenant_id, owner_employee_id)`.

**Soft-delete constraints (application + tests):**

- Allow `deleted_at` set only when `status = draft`.
- Reject soft-delete when child tasks with `project_id` exist or budget PO links exist.
- Soft-deleted drafts excluded from default queries.
- **Restore:** unresolved — no `restored_at` workflow approved.

### 1.3 `season_project_phases`

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| tenant_id | FK | |
| season_id | FK | denormalized = parent |
| project_id | FK | cascade with project hard paths; soft-deleted parent not exposed |
| title | string | |
| sort_order | int | |
| starts_on / ends_on | date nullable | |
| status | enum | planned/active/done — semantics E-Q03 deferred |
| timestamps | | |

### 1.4 `season_project_budget_po_links` (E-Q06 = A)

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| tenant_id | FK | |
| season_id | FK | |
| project_id | FK | |
| purchase_order_id | FK | **PO header** |
| commitment_amount | decimal(18,2) nullable | **not approved formula** (E-Q07/08 deferred) |
| actual_amount | decimal(18,2) nullable | deferred |
| currency_code | char(3) | |
| linked_at | timestamp | |
| linked_by | FK users | |
| notes | text nullable | |

**UNIQUE:** (`tenant_id`, `purchase_order_id`) — prevents double counting across projects.
**Do not** add required `season_project_id` on `purchase_orders`.
**No** `purchase_order_line_id` in v1 (line grain rejected by E-Q06 = A).

---

## 2. Tasks table extensions (backward compatible) — E-Q01 / E-Q05

Additive columns on existing `tasks`:

| Column | Type | Default | MVP impact |
|---|---|---|---|
| season_id | FK nullable | NULL | none |
| project_id | FK nullable | NULL | none |
| phase_id | FK nullable | NULL | none |
| delay_state | enum `none\|delayed` | `none` | projection; MVP rows stay `none` until due |
| delay_calculated_at | timestamp nullable | NULL | none |

Constraints:

- App: `project_id` NULL OR (`season_id` IS NOT NULL).
- FK `project_id` → `season_projects`; season alignment verified in app.
- Index `(tenant_id, project_id, status)`, `(tenant_id, delay_state)`.

**No data rewrite** of existing rows. No sticky/manual delay columns.

---

## 3. Purchase orders

- Link table only for project association.
- Unlinked POs remain first-class S05 rows.

---

## 4. Migration order (Part 1)

1. `season_project_number_sequences`
2. `season_projects` (incl. `deleted_at`)
3. `season_project_phases`
4. `season_project_budget_po_links` + unique (`tenant_id`, `purchase_order_id`)
5. Alter `tasks` add nullable columns + indexes

Rollback: drop new tables; drop new task columns (safe while unused).

---

## 5. Consistency rules

| Concern | Mechanism |
|---|---|
| Cross-tenant PO link | App: PO.tenant_id = project.tenant_id; else 404 |
| Season | Project.season_id = SeasonContext; tasks.season_id = project.season_id |
| Double count | UNIQUE (`tenant_id`, `purchase_order_id`) |
| Soft delete | Draft-only; block with children/links |
| Progress | Recompute with task progress writes |
| Delay | Projection sync per E-Q05 |

---

## 6. Still open (schema-adjacent, not approved)

- E-Q07/08 amount population strategy (snapshot vs live) — gate Part 4.
- Soft-delete **restore** columns/API — unresolved.
- E-Q03 phase status meaning — gate Part 3.
