# Meetings — Data Model

> **Status:** Implemented (Sprint 010) — migrations applied
> **Last updated:** 2026-08-10

All tenant-owned tables carry `tenant_id`. Models use `TenantOwned` + `UsesTenantScope`. No SoftDeletes.

## Migration order (implementation)

1. `meeting_number_sequences`
2. `meetings`
3. `meeting_status_transitions`
4. `meeting_attendees`
5. `meeting_agenda_items`
6. `meeting_recommendations`

## `meeting_number_sequences`

| Column | Type | Notes |
|---|---|---|
| `tenant_id` | BIGINT UNSIGNED **PK** | FK → `tenants` RESTRICT |
| `next_value` | BIGINT UNSIGNED | Default `1` |
| timestamps | | |

Concurrency: `SELECT … FOR UPDATE` then increment (mirror Employees/Contracts). Format: `MTG-` + zero-pad 6 → `MTG-000001`.

## `meetings`

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | NO | AI | PK |
| `tenant_id` | BIGINT UNSIGNED | NO | — | FK → `tenants` RESTRICT |
| `meeting_number` | VARCHAR(32) | NO | — | Server-generated; immutable |
| `title` | VARCHAR(200) | NO | — | |
| `description` | TEXT | YES | `NULL` | Free-text overview (not a substitute for agenda items) |
| `status` | VARCHAR(20) | NO | `draft` | See BUSINESS_RULES |
| `scheduled_at` | DATETIME | YES | `NULL` | Required once scheduled; UTC storage |
| `started_at` | DATETIME | YES | `NULL` | Set on start |
| `ended_at` | DATETIME | YES | `NULL` | Set on complete |
| `location_type` | VARCHAR(20) | NO | `physical` | `physical` \| `remote` \| `hybrid` |
| `location_text` | VARCHAR(255) | YES | `NULL` | |
| `meeting_link` | VARCHAR(500) | YES | `NULL` | Informational URL/text |
| `organization_unit_id` | BIGINT UNSIGNED | YES | `NULL` | FK → `organization_units` RESTRICT |
| `chairperson_employee_id` | BIGINT UNSIGNED | YES | `NULL` | FK → `employees` RESTRICT |
| `secretary_employee_id` | BIGINT UNSIGNED | YES | `NULL` | FK → `employees` RESTRICT |
| `minutes_body` | TEXT | YES | `NULL` | Formal minutes (محضر) |
| `notes` | TEXT | YES | `NULL` | Internal notes |
| `created_by` | BIGINT UNSIGNED | NO | — | FK → `users` RESTRICT |
| `created_at` / `updated_at` | TIMESTAMP | YES | — | |

### Constraints / indexes

| Kind | Columns |
|---|---|
| Unique | `(tenant_id, meeting_number)` |
| Index | `(tenant_id, status)` |
| Index | `(tenant_id, scheduled_at)` |
| Index | `(tenant_id, organization_unit_id)` |
| Index | `(tenant_id, chairperson_employee_id)` |
| Index | `(tenant_id, title)` |

### Model relations (implementation names)

`tenant()`, `organizationUnit()`, `chairperson()`, `secretary()`, `createdBy()`, `statusTransitions()`, `attendees()`, `agendaItems()`, `recommendations()`.

### Excluded columns

meeting_type, recurrence, video provider ids, external_guest blobs, decision_id, soft deletes, biometric fields.

## `meeting_status_transitions` (append-only)

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | NO | PK |
| `tenant_id` | BIGINT UNSIGNED | NO | FK RESTRICT |
| `meeting_id` | BIGINT UNSIGNED | NO | FK → `meetings` RESTRICT |
| `from_status` | VARCHAR(20) | YES | `NULL` only for create → `draft` |
| `to_status` | VARCHAR(20) | NO | |
| `actor_user_id` | BIGINT UNSIGNED | YES | Nullable if future system jobs |
| `comment` | TEXT | YES | Required for cancel; used on reschedule |
| `correlation_id` | VARCHAR(64) | YES | Request correlation when available |
| `created_at` | TIMESTAMP | NO | **No** `updated_at` |

Index: `(tenant_id, meeting_id, created_at)`. No API updates/deletes.

## `meeting_attendees`

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | NO | PK |
| `tenant_id` | BIGINT UNSIGNED | NO | FK RESTRICT |
| `meeting_id` | BIGINT UNSIGNED | NO | FK → `meetings` CASCADE on draft delete only via app; DB RESTRICT preferred + app cascade |
| `employee_id` | BIGINT UNSIGNED | NO | FK → `employees` RESTRICT |
| `attendance_status` | VARCHAR(20) | NO | Default `invited` |
| `created_at` / `updated_at` | TIMESTAMP | YES | |

Unique: `(meeting_id, employee_id)`. Index: `(tenant_id, meeting_id)`, `(tenant_id, employee_id)`.

**FK delete note:** Prefer `RESTRICT` on `meeting_id` and let draft-delete Action remove children first (same pattern as Contracts transitions).

## `meeting_agenda_items`

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | NO | PK |
| `tenant_id` | BIGINT UNSIGNED | NO | FK RESTRICT |
| `meeting_id` | BIGINT UNSIGNED | NO | FK → `meetings` RESTRICT |
| `title` | VARCHAR(200) | NO | |
| `description` | TEXT | YES | `NULL` |
| `sort_order` | INT UNSIGNED | NO | Default `0` |
| timestamps | | | |

Index: `(tenant_id, meeting_id, sort_order)`.

## `meeting_recommendations`

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | NO | PK |
| `tenant_id` | BIGINT UNSIGNED | NO | FK RESTRICT |
| `meeting_id` | BIGINT UNSIGNED | NO | FK → `meetings` RESTRICT |
| `agenda_item_id` | BIGINT UNSIGNED | YES | FK → `meeting_agenda_items` SET NULL |
| `title` | VARCHAR(200) | NO | |
| `description` | TEXT | YES | `NULL` |
| `status` | VARCHAR(20) | NO | Default `draft` (`draft` \| `final`) |
| `owner_employee_id` | BIGINT UNSIGNED | YES | FK → `employees` RESTRICT |
| `sort_order` | INT UNSIGNED | NO | Default `0` |
| `created_by` | BIGINT UNSIGNED | NO | FK → `users` RESTRICT |
| timestamps | | | |

Indexes: `(tenant_id, meeting_id)`, `(tenant_id, status)`, `(tenant_id, owner_employee_id)`.

**No `decision_id`.** Decisions own nullable unique `source_recommendation_id` (ADR-0008 / Sprint 011).

## Config (`config/meetings.php`)

| Key | Default |
|---|---|
| `number_prefix` | `MTG-` |
| `number_pad` | `6` |

## Future Documents extension (not Sprint 010)

Polymorphic `documentables` or `documents.meeting_id` — extension point only.
