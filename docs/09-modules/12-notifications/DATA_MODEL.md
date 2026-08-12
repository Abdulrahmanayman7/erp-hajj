# Notifications — Data Model

> **Status:** Implemented (Sprint 016)
> **Last updated:** 2026-08-11
> ADR: [ADR-0013](../../10-decisions/ADR-0013-IN-APP-NOTIFICATION-OWNERSHIP-AND-DELIVERY.md)

## Migration order (future implementation)

1. `notifications`

No SoftDeletes. No separate dedupe table (key lives on the row). No per-channel tables in MVP.

---

## 1. `notifications`

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | bigint PK | no | |
| `tenant_id` | FK → tenants | no | TenantOwned; RESTRICT |
| `recipient_user_id` | FK → users | no | Same tenant; RESTRICT |
| `type` | string(64) | no | Stable catalog code (e.g. `TASK_ASSIGNED`) |
| `title` | string(255) | no | Plain text Arabic snapshot |
| `body` | string(1000) | yes | Plain text |
| `severity` | string(16) | no | `info` \| `warning` \| `critical` |
| `entity_type` | string(64) | yes | Morph alias (never FQCN) |
| `entity_id` | unsigned bigint | yes | |
| `dedupe_key` | string(191) | yes | Unique with tenant when set |
| `read_at` | timestamp | yes | null = unread |
| `correlation_id` | string(64) | yes | From originating request/job when available |
| `created_at` | timestamp | no | |
| `updated_at` | timestamp | no | Updated when `read_at` changes (Laravel convention) |

### Constraints / indexes

| Kind | Definition |
|---|---|
| UNIQUE | (`tenant_id`, `dedupe_key`) — multiple NULLs allowed for non-deduped rows |
| INDEX | (`tenant_id`, `recipient_user_id`, `read_at`) — unread count + unread filter |
| INDEX | (`tenant_id`, `recipient_user_id`, `created_at`) — inbox newest-first |
| INDEX | (`tenant_id`, `type`) |
| INDEX | (`tenant_id`, `entity_type`, `entity_id`) — optional ops/debug |

### Mass assignment

Never fill from client: `tenant_id`, `recipient_user_id`, `type`, `title`, `body`, `severity`, `entity_*`, `dedupe_key`, `correlation_id`.

Client may only trigger **read** mutations (server sets `read_at`).

### Model contracts

- `Notification` implements `TenantOwned` + `UsesTenantScope`.
- `updating`: allow only `read_at` / `updated_at` dirty; reject other mutations → domain `NOTIFICATION_IMMUTABLE`.
- `deleting`: reject for normal app paths (no user delete).

### Explicitly absent

- `action_url`
- `employee_id` recipient
- `html_body`
- `channel` / `sent_at` (future delivery)
- SoftDeletes
- Preferences tables

## Config (future)

`config/notifications.php`:

| Key | Default | Purpose |
|---|---|---|
| `task_due_soon_days` | 3 | `TASK_DUE_SOON` window |
| `meeting_starting_soon_minutes` | 60 | `MEETING_STARTING_SOON` |
| `custody_expected_return_soon_days` | 3 | custody soon window |
| `sync_fanout_max` | 20 | above → queue job |
| `retention_days` | null / TBD | future prune |
| Reuse | `contracts.expiring_soon_days` | contract soon |

## Notes

- Laravel's `Illuminate\Notifications` database channel is **optional**; preferred MVP is a first-class `Modules/Notifications` model for explicit tenancy, dedupe, and Policies. Do not fight the framework if a thin adapter helps — document choice at implementation.
- Entity morph aliases align with Documents where overlapping (`contract`, `meeting`, `decision`, `task`, `asset`, `custody`, `warehouse`, `inventory_item`).
