# System Settings — Data Model

> **Status:** Implemented (Sprint 020) — **no new migration** (Tenant columns only)
> **Last updated:** 2026-08-13

## Storage strategy (ADR-0016)

**Hybrid:**

1. **MVP product fields** → existing `tenants` columns.
2. **`tenant_settings` table** → reserved for future **catalog-registered** key/value overrides; **not** used to duplicate Tenant columns; MVP may ship with an empty product KV key set.

## `tenants` columns used by Settings

| Column | Settings role |
|---|---|
| `name` | Mutable — general.name |
| `timezone` | Mutable — regional.timezone |
| `locale` | Read-only via Settings — regional.locale |
| `contact_name` / `contact_email` / `contact_phone` | Mutable nullable — general.* |
| `tenant_code` | Excluded |
| `status`, `suspended_at`, `archived_at` | Excluded (platform lifecycle) |
| `notes` | Excluded (platform operator) |

Schema already exists (Sprint 004). **No migration** for these fields.

## `tenant_settings` (existing)

| Column | Notes |
|---|---|
| `id` | PK |
| `tenant_id` | TenantOwned; UNIQUE with `key` |
| `key` | VARCHAR(100); namespaced when used |
| `value` | JSON |
| timestamps | |

- Model already implements `TenantOwned` + `UsesTenantScope`.
- MVP Settings Actions **should not** write arbitrary keys.
- Future keys require catalog entry + tests + docs before use.

## Migration strategy

| Change | Required? |
|---|---|
| New table | **No** |
| Alter `tenants` | **No** for MVP catalog |
| Backfill | **No** — existing rows already have `locale`/`timezone` defaults |
| Destructive migration | **Forbidden** |

If a future CR adds tenant-editable thresholds, prefer **new catalog keys in `tenant_settings`** (or explicit columns via CR) — not silent config forks.

## Indexes

Existing: `tenants` PK/uniques; `tenant_settings` UNIQUE (`tenant_id`, `key`). Sufficient for singleton reads.
