# System Settings — Data Model

> **Status:** Implemented (Sprint 020 + mail sender columns)
> **Last updated:** 2026-09-16

## Storage strategy (ADR-0016)

**Hybrid:**

1. **Product fields** → `tenants` columns (including mail sender identity).
2. **`tenant_settings` table** → reserved for future **catalog-registered** key/value overrides; **not** used to duplicate Tenant columns.

## `tenants` columns used by Settings

| Column | Settings role |
|---|---|
| `name` | Mutable — general.name |
| `timezone` | Mutable — regional.timezone |
| `locale` | Read-only via Settings — regional.locale |
| `contact_name` / `contact_email` / `contact_phone` | Mutable nullable — general.* |
| `mail_from_address` / `mail_from_name` | Mutable nullable — technical.*; empty → server `mail.from` at send time |
| `tenant_code` | Excluded |
| `status`, `suspended_at`, `archived_at` | Excluded (platform lifecycle) |
| `notes` | Excluded (platform operator) |

Migration `2026_09_16_120000_add_mail_from_fields_to_tenants_table` adds the mail sender columns. SMTP credentials remain env-only.

## `tenant_settings` (existing)

| Column | Notes |
|---|---|
| `id` | PK |
| `tenant_id` | TenantOwned; UNIQUE with `key` |
| `key` | VARCHAR(100); namespaced when used |
| `value` | JSON |
| timestamps | |

- Model already implements `TenantOwned` + `UsesTenantScope`.
- Settings Actions **do not** write KV rows for these product fields.

## Migration strategy

| Change | Required? |
|---|---|
| New table | **No** |
| Alter `tenants` | **Yes** — `mail_from_address`, `mail_from_name` (nullable string 255) |
| Backfill | **No** — null means use server defaults |
| Destructive migration | **Forbidden** |


## Indexes

Existing: `tenants` PK/uniques; `tenant_settings` UNIQUE (`tenant_id`, `key`). Sufficient for singleton reads.
