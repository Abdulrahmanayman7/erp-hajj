# Risks & Complaints — Data Model (Design Only)

> **Status:** PART 0 FINAL — **no migrations in this task**
> All tables are **tenant-owned** (`TenantOwned` + `UsesTenantScope`).
> Binding: Q5, D01, D02, D05, D06, D08, D11.

## 1. Design principles

1. Separate aggregates for risks and complaints (Q2).
2. `tenant_id` + `season_id` on every primary business row (Q5).
3. Soft deletes only under D05; sequences never rewind (D06).
4. Optional FKs never cascade-delete external operational entities.
5. Concurrency: optimistic status checks + `lockForUpdate` on transitions (D12).

## 2. Enumerations (proposed DB/string enums)

### RiskStatus (D01)

`identified` | `under_assessment` | `approved` | `treating` | `closed`

### RiskClosureOutcome (D01)

`mitigated` | `accepted` | `transferred` | `cancelled`

### RiskSeverity (D02)

`low` | `medium` | `high` | `critical`

### TreatmentStatus (D11 — engineering-fixed in Part 1)

Proposed: `planned` | `in_progress` | `done` | `cancelled`

### ComplaintStatus (Q4)

`new` | `under_review` | `in_progress` | `resolved` | `closed`

## 3. Tables

### 3.1 `operational_risk_number_sequences` (D06)

| Column | Type | Notes |
|---|---|---|
| tenant_id | FK | part of PK |
| season_id | FK | part of PK |
| year | smallint | Gregorian year of issue |
| next_value | unsigned int | starts at 1 |

**PK:** `(tenant_id, season_id, year)`
Increment inside create transaction under row lock.

### 3.2 `operational_complaint_number_sequences` (D06)

Same shape as risk sequences.

### 3.3 `operational_risks`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | bigint PK | no | |
| tenant_id | FK tenants | no | indexed leading |
| season_id | FK seasons | no | |
| risk_number | string(32) | no | `RSK-YYYY-######` |
| title | string(200) | no | |
| description | text | yes | |
| status | string/enum | no | RiskStatus |
| likelihood | tinyint | yes | 1–5; required before approve |
| impact | tinyint | yes | 1–5 |
| score | smallint | yes | L×I; stored for query |
| severity_level | string/enum | yes | derived D02 |
| closure_outcome | string/enum | yes | required when closed |
| close_reason | text | yes | |
| created_by | FK users | no | SoD reference (P02) |
| approved_by | FK users | yes | |
| approved_at | timestamp | yes | |
| owner_employee_id | FK employees | yes | treatment/follow owner |
| season_pilgrim_id | FK | yes | optional Q5 |
| group_id | FK season_pilgrim_groups | yes | optional |
| facility_id | FK accommodation_facilities | yes | optional; season scope in app |
| transport_season_vehicle_id | FK | yes | **preferred** vehicle link (season-aligned) |
| project_id | nullable bigint | yes | optional D08; **no DB FK** to `season_projects` (S06 unmerged) |
| created_at / updated_at | timestamps | no | |
| deleted_at | timestamp | yes | D05 |

**Constraints / indexes**

- `UNIQUE (tenant_id, season_id, risk_number)`
- `INDEX (tenant_id, season_id, status)`
- `INDEX (tenant_id, severity_level)`
- `INDEX (tenant_id, owner_employee_id)`
- Check (app-level): likelihood/impact null or between 1 and 5

### 3.4 `operational_risk_treatments` (D11)

| Column | Type | Null | Notes |
|---|---|---|---|
| id | bigint PK | no | |
| tenant_id | FK | no | must match risk |
| risk_id | FK operational_risks | no | |
| title | string(200) | no | |
| plan_body | text | no | actions |
| owner_employee_id | FK employees | no | active same tenant |
| due_at | timestamp | yes | optional at Part 0 |
| status | string/enum | no | TreatmentStatus |
| created_by | FK users | no | |
| created_at / updated_at | timestamps | no | |

**Cardinality (Part 0 default):** one **current** treatment per risk (unique `risk_id`) unless Owner later approves history table. Replacement via PUT replaces fields in place with audit.

### 3.5 `operational_complaints`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | bigint PK | no | |
| tenant_id | FK | no | |
| season_id | FK | no | |
| complaint_number | string(32) | no | `CMP-YYYY-######` |
| title | string(200) | no | |
| body | text | no | |
| category_code | string(64) | no | tenant catalog — seed list engineering |
| status | string/enum | no | ComplaintStatus |
| created_by | FK users | no | |
| assignee_employee_id | FK employees | yes | set on assign |
| escalation_level | unsigned int | no | default 0 |
| escalated_to_employee_id | FK employees | yes | |
| escalate_reason | text | yes | last escalate |
| escalated_at | timestamp | yes | |
| resolution_text | text | yes | required to resolve |
| resolved_at / resolved_by | timestamp / FK users | yes | |
| closed_at / closed_by | timestamp / FK users | yes | |
| close_reason | text | yes | required to close |
| reopened_at / reopened_by | timestamp / FK users | yes | |
| reopen_reason | text | yes | required to reopen |
| season_pilgrim_id / group_id / facility_id / transport_season_vehicle_id | FK | yes | optional; same season/tenant validation as risks |
| project_id | nullable bigint | yes | optional D08; **no DB FK**; app validates tenant/season when S06 table exists |
| created_at / updated_at / deleted_at | | | D05 |

**Constraints / indexes**

- `UNIQUE (tenant_id, season_id, complaint_number)`
- `INDEX (tenant_id, season_id, status)`
- `INDEX (tenant_id, assignee_employee_id)`
- `INDEX (tenant_id, category_code)`

### 3.6 `operational_complaint_notes`

| Column | Type | Notes |
|---|---|---|
| id | PK | |
| tenant_id | FK | |
| complaint_id | FK | |
| body | text | |
| created_by | FK users | |
| created_at | timestamp | append-only; no update/delete in MVP |

## 4. Relationships (logical)

```
Tenant 1──* OperationalRisk 1──1 OperationalRiskTreatment (current)
Tenant 1──* OperationalComplaint 1──* OperationalComplaintNote
Season 1──* OperationalRisk / OperationalComplaint
User (created_by) 1──* Risk/Complaint
Employee (assignee/owner) 1──* Risk/Complaint
Optional: SeasonPilgrim, Group, Facility, TransportSeasonVehicle, SeasonProject
```

## 5. Deletion / archive policy

| Case | Policy |
|---|---|
| Risk `identified` | Soft-delete allowed (D05) |
| Complaint `new` | Soft-delete allowed (D05) |
| After assessment/assignment | Soft-delete forbidden |
| Hard delete | Not in S07 MVP |
| External entity deleted | Keep FK nullability / restrict; do not cascade erase risk/complaint history |

## 6. S06 / project_id (D08)

- `project_id` is **nullable** on risks and complaints.
- **No database foreign key** currently exists to `season_projects` (S06 SeasonProjects is not yet merged into develop).
- When a value is provided and the `season_projects` table exists, application validation must enforce same-tenant and same-season membership.
- When SeasonProjects is unavailable, creating/updating with `project_id` returns a stable domain error; omitting `project_id` never blocks create.
- Future S06 integration must preserve tenant/season validation and must **not** introduce a new S07 migration solely to add the FK as a hard dependency on unmerged S06.
