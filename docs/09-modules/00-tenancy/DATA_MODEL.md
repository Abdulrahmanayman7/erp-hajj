# Tenancy — Data Model

> **Status:** Approved — implementation-ready; migrations do not exist yet
> **Last updated:** 2026-08-06

Primary keys everywhere: `BIGINT UNSIGNED AUTO_INCREMENT` (see [DATABASE_PRINCIPLES.md](../../03-database/DATABASE_PRINCIPLES.md)).

## `tenants` (platform table — no `tenant_id`: it *is* the tenant registry)

### Required columns (final)

| Column | Type | Constraints | Why |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, auto-increment | Standard PK; the **only** key used in relationships, scoping, cache prefixes, and storage paths (`tenants/{id}/...`) because it is immutable and cheap. |
| `tenant_code` | VARCHAR(63) | **UNIQUE**, NOT NULL, indexed, **immutable after creation**, lowercase | Human-readable operational identifier for logs, exports, operational references, and future subdomain mapping. 63 chars = DNS-label limit, keeping every code subdomain-compatible. Pattern `/^[a-z0-9]+(?:-[a-z0-9]+)*$/` (lowercase letters, digits, hyphen; no spaces; no Arabic). **Never an authorization input.** Not the PK. **First tenant: `rafee`.** |
| `name` | VARCHAR(255) | **UNIQUE**, NOT NULL | Display name of the campaign company (Arabic; first tenant: **رفيع**). May change freely **without** changing `tenant_code`. Unique platform-wide to prevent operator confusion in the platform console (tenants are created manually by the Super Admin in the MVP). |
| `status` | VARCHAR(20) | NOT NULL, default `pending` | Lifecycle state (`pending`/`active`/`suspended`/`archived`); stored as string, represented by a PHP enum (`TenantStatus`) at implementation — readable in the DB, avoids MySQL `ENUM` migration pain. |
| `locale` | VARCHAR(10) | NOT NULL, default `'ar'` | Tenant default locale (Arabic-first platform). |
| `timezone` | VARCHAR(64) | NOT NULL, default `'Asia/Riyadh'` | Tenant default timezone for business timestamps display. |
| `suspended_at` | TIMESTAMP | NULL | Set when suspended, **cleared when reactivated** — support diagnostics and audit correlation. |
| `archived_at` | TIMESTAMP | NULL | Set when archived (terminal). |
| `created_at` / `updated_at` | TIMESTAMP | NOT NULL | Standard. |

### Optional columns (MVP-safe, already justified)

| Column | Type | Constraints | Why |
|---|---|---|---|
| `contact_name` / `contact_email` / `contact_phone` | VARCHAR(255)/(255)/(50) | NULL | Operational contact for the platform operator; nullable because onboarding data collection is not finalized (commercial model TBD) — no migration needed when it is. |
| `notes` | TEXT | NULL | Free-form operator notes; the authoritative suspension/archival reason lives in the audit record. |

### Status lifecycle (final)

| Value | Meaning | Users can log in? |
|---|---|---|
| `pending` | Record exists; onboarding/activation incomplete | No (`403 TENANT_PENDING`) |
| `active` | Normal operation | Yes |
| `suspended` | Temporarily blocked; sessions rejected on next protected request | No (`403 TENANT_SUSPENDED`) |
| `archived` | Terminal; data preserved for audit and contractual obligations | No (`403 TENANT_ARCHIVED`) |

**Allowed transitions:** `pending → active`, `pending → archived`, `active → suspended`, `active → archived`, `suspended → active`, `suspended → archived`. **Forbidden:** `archived → active` (requires a future, explicitly approved and audited recovery policy). Every transition is audited with actor, timestamp, old/new status, reason, correlation ID, and context type (platform/tenant).

### Expected initial provisioning record (documented only — NOT created now)

```text
name: رفيع
tenant_code: rafee
status: pending or active according to provisioning stage
locale: ar
timezone: Asia/Riyadh
```

### Indexes

- PK `id`; UNIQUE `tenant_code`; UNIQUE `name`; index on `status` (platform console filters; the resolver reads by PK only).

### Deletion strategy — why hard delete and soft delete are both rejected

- **No hard delete:** one statement could destroy a company's entire history; contractual/audit obligations require preservation. Every tenant-owned `tenant_id` FK is `ON DELETE RESTRICT`, making hard deletion physically impossible while business rows exist — defense in depth, not just policy.
- **No Laravel soft delete (`deleted_at`):** soft delete hides the row from the platform console and from platform queries by default, which makes an archived tenant *less* visible and auditable — the opposite of the requirement. `archived` + `archived_at` conveys "terminally closed" while keeping the row fully visible, queryable, and auditable. Archiving is the explicit lifecycle mechanism.
- Physical purge of an archived tenant's data (PDPL/retention) is future scope and requires its own ADR.

### Future extensibility (documented, not built)

Subscription/billing fields, per-tenant branding, and per-tenant domains are **additive nullable columns or satellite tables** keyed by `tenant_id` — the entity requires no rework. The MVP entity is deliberately minimal so an unconfirmed commercial model is not hardened into the schema.

## `users` — tenancy columns (owned by the Users module; tenancy shape fixed here)

| Column | Type | Constraints | Why |
|---|---|---|---|
| `tenant_id` | BIGINT UNSIGNED | **NULL**, FK → `tenants.id` ON DELETE RESTRICT, indexed | `NULL` = platform user (Super Admin, Support); non-null = tenant user (exactly one tenant in the MVP). One table keeps one auth pipeline (rationale: [MULTI_TENANCY.md](../../02-architecture/MULTI_TENANCY.md) §1 of the ADR trade-offs). Platform user access is controlled separately (platform permissions). |

- `users.email` remains **globally unique** (one login namespace) — the current decision unless future business requirements change it.
- The `User` model does **not** use `UsesTenantScope` (it must be readable during authentication, before context exists); the tenancy layer treats `users` as a hybrid platform table.

## `tenant_settings` (tenant-owned — first table to use the full scoping stack)

| Column | Type | Constraints | Why |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK | Standard. |
| `tenant_id` | BIGINT UNSIGNED | NOT NULL, FK → `tenants.id` ON DELETE RESTRICT | Tenant-owned; **immutable after creation** like every `tenant_id`. |
| `key` | VARCHAR(100) | NOT NULL | Namespaced `module.setting_name`. |
| `value` | JSON | NOT NULL | Accommodates scalar and structured settings without schema churn. |
| `created_at` / `updated_at` | TIMESTAMP | NOT NULL | Standard. |

- **Unique:** `UNIQUE (tenant_id, key)`; doubles as the tenant-leading index. Model implements `TenantOwned` + `UsesTenantScope`; no soft deletes (settings are overwritten; history lives in audit records).

## Cross-cutting

- Every tenant-owned table in other modules: `tenant_id BIGINT UNSIGNED NOT NULL`, FK `ON DELETE RESTRICT`, immutable after creation, secondary indexes leading with `tenant_id`, per-tenant unique constraints — see [DATABASE_PRINCIPLES.md](../../03-database/DATABASE_PRINCIPLES.md) (including the full list of planned tenant-owned tables).
- Migration order: `tenants` → `users.tenant_id` → `tenant_settings` → business tables.

## TBD

- **Settings keys required by MVP modules**: unresolved — keys emerge as each module is designed. **Recommended:** each module's `DATA_MODEL.md` declares its keys; central registry class in `Core/Tenancy` aggregates them. **Impact:** validation layer only; no schema change.
- **Subscription/commercial fields**: unresolved — commercial model not confirmed. **Recommended:** defer; add via satellite table when confirmed. **Impact:** additive migration only.
