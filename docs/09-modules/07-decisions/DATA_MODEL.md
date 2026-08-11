# Decisions — Data Model

> **Status:** Implemented (Sprint 011)
> **Last updated:** 2026-08-10

## Tables (future migration order)

1. `decision_number_sequences`
2. `decisions`
3. `decision_status_transitions`

No category/priority catalog tables.

---

## 1. `decision_number_sequences`

| Column | Type | Notes |
|---|---|---|
| `tenant_id` | FK → tenants | PK |
| `next_number` | unsigned int | Next value to allocate (starts at 1) |
| `updated_at` | timestamp | |

**Allocation:** `SELECT … FOR UPDATE` on the tenant row inside the create transaction; format `DEC-` + `str_pad(next, 6, '0')`; then increment. Mirror Contracts/Meetings.

---

## 2. `decisions`

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | bigint PK | no | |
| `tenant_id` | FK → tenants | no | TenantOwned |
| `decision_number` | string(20) | no | Immutable `DEC-######` |
| `title` | string(255) | no | |
| `body` | text | no | |
| `notes` | text | yes | |
| `status` | string(32) | no | enum below |
| `source_recommendation_id` | FK → meeting_recommendations | yes | Unique when set; immutable after create |
| `organization_unit_id` | FK → organization_units | yes | |
| `issued_by_employee_id` | FK → employees | yes | Issuer |
| `responsible_employee_id` | FK → employees | yes | Oversight — not Task assignee |
| `effective_date` | date | yes | |
| `due_date` | date | yes | Informational until Tasks |
| `created_by` | FK → users | no | |
| `created_at` / `updated_at` | timestamps | no | |

### Status enum

`draft` | `pending_approval` | `approved` | `closed` | `cancelled`

### Explicitly absent columns

- `source_meeting_id` (derive via recommendation)
- `decision_type` / `category_id` / `priority`
- `approved_by_*` as denormalized columns (actor lives in transitions)
- SoftDeletes
- Task / document / KPI fields

### Constraints

| Name / intent | Definition |
|---|---|
| `decisions_tenant_number_unique` | UNIQUE (`tenant_id`, `decision_number`) |
| `decisions_source_recommendation_unique` | UNIQUE (`source_recommendation_id`) — MySQL allows multiple NULLs |
| FK `source_recommendation_id` | `meeting_recommendations` ON DELETE RESTRICT |
| FK org / employees | ON DELETE RESTRICT (delete protection) |
| Check (app-level) | `due_date >= effective_date` when both set |

### Indexes (tenant-leading)

| Index | Columns | Purpose |
|---|---|---|
| PK | `id` | |
| unique | (`tenant_id`, `decision_number`) | Numbering |
| unique | (`source_recommendation_id`) | One Decision per recommendation |
| | (`tenant_id`, `status`) | List filter |
| | (`tenant_id`, `organization_unit_id`) | Filter |
| | (`tenant_id`, `responsible_employee_id`) | Filter |
| | (`tenant_id`, `effective_date`) | Range filter |
| | (`tenant_id`, `due_date`) | Range filter |
| | (`tenant_id`, `created_at`) | Default sort |

---

## 3. `decision_status_transitions`

Append-only history (same pattern as Contracts / Meetings).

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | bigint PK | no | |
| `tenant_id` | FK → tenants | no | |
| `decision_id` | FK → decisions | no | ON DELETE CASCADE only if Decision hard-deleted as untouched draft |
| `from_status` | string(32) | yes | null on initial optional row — **MVP: only write on real transitions** |
| `to_status` | string(32) | no | |
| `performed_by` | FK → users | yes | null only if system — MVP always authenticated user |
| `comment` | text | yes | **Required** on return-draft |
| `correlation_id` | string(64) | no | |
| `created_at` | timestamp | no | No `updated_at` |

### Indexes

| Index | Columns |
|---|---|
| | (`tenant_id`, `decision_id`, `created_at`) |
| | (`decision_id`, `created_at`) |

No separate approvals table.

---

## 4. Relationships

```
Tenant 1──* Decision
User (created_by) 1──* Decision
MeetingRecommendation 0..1──0..1 Decision   (via decisions.source_recommendation_id)
OrganizationUnit 0..1──* Decision
Employee (issuer) 0..1──* Decision
Employee (responsible) 0..1──* Decision
Decision 1──* DecisionStatusTransition

# Future (Sprint 012+)
Decision 0..1──* Task   (tasks.decision_id)
```

Derived (not stored): `Decision → source_recommendation → meeting`.

---

## 5. Model contracts

- `Decision` implements `TenantOwned` + `UsesTenantScope`.
- `DecisionStatusTransition` tenant-owned.
- Mass-assignment: never fill `tenant_id`, `decision_number`, `status`, `source_recommendation_id` via generic update after create (conversion sets recommendation only at create).
- API Resources only — no raw models to clients.

---

## 6. Migration notes

- Create sequence table before `decisions`.
- `meeting_recommendations` must already exist (Sprint 010).
- Do not alter `meeting_recommendations` to add `decision_id`.
