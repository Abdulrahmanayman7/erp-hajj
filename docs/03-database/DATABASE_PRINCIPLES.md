# Database Principles

> **Status:** Approved — includes the binding tenancy database strategy; business migrations do not exist yet (Sprint 007 org units specified)
> **Last updated:** 2026-08-09

## Purpose

Define the rules every table and migration must follow. Conceptual entities per module are documented in each module's `DATA_MODEL.md` under [docs/09-modules/](../09-modules/).

## Engine

- MySQL is the system database.
- Redis is used only when required (cache, queues) and never as the source of truth.

## Primary Keys (decided)

- **`BIGINT UNSIGNED AUTO_INCREMENT`** on all tables (decided at scaffolding together with the stack).
- **Why not ULID/UUID:** smaller indexes, faster joins, native Laravel defaults, and zero operational surprises for a two-developer team. The known trade-off — sequential ids are guessable — is **not** mitigated by random ids in this design anyway: isolation rests on automatic scoping and `404` behavior, never on id secrecy (see [SECURITY_BASELINE.md](../06-security/SECURITY_BASELINE.md) on enumeration). If ids must ever become non-guessable in URLs (e.g. public share links, future scope), that feature adds its own random token column; primary keys do not change.

## Tenancy Strategy (decided — see [ADR-0003](../10-decisions/ADR-0003-MULTI-TENANCY.md))

Single database, shared schema. Every table is classified as **platform** or **tenant-owned** in its module's `DATA_MODEL.md`. The classification rule:

> A table is **tenant-owned** if its rows are business data belonging to one campaign company. It is a **platform** table only if its rows describe the platform itself or framework infrastructure.

### Platform tables (no `tenant_id`) — each omission justified

| Table | Why platform |
|---|---|
| `tenants` | The registry of tenants itself — cannot be owned by a tenant. |
| `users` | **Hybrid**: carries a **nullable** `tenant_id`. `NULL` = platform user (Super Admin, Support; access controlled separately via platform permissions); NOT NULL = tenant user belonging to exactly one tenant in the MVP. One table keeps one auth pipeline (see [MULTI_TENANCY.md](../02-architecture/MULTI_TENANCY.md)). `users.email` stays globally unique — current decision unless future business requirements change it. |
| `personal_access_tokens` | Belongs to a user (polymorphic); tenancy is derived through the user. |
| `sessions`, `password_reset_tokens` | Auth infrastructure keyed by user/email; tenancy derived through the user. |
| `jobs`, `job_batches`, `failed_jobs` | Queue infrastructure. Tenant context travels **inside the job payload** as trusted server-generated metadata (see MULTI_TENANCY §9), not as a column — the queue tables store serialized work, not business data. Failed-job payloads preserve tenant identification. Access is platform-level only. |
| `cache`, `cache_locks` | Key-value infrastructure; isolation is by key prefix `tenant:{id}:` through the shared namespace helper (MULTI_TENANCY §10). |
| `migrations` | Framework bookkeeping. |
| `audit_logs` | **Hybrid**: carries a **nullable** `tenant_id` — tenant actions record their tenant; platform events may record `NULL`; **platform access affecting a tenant writes the target tenant's id**. Append-only. Kept as one table so platform actions against a tenant appear in that tenant's audit view (see [AUDIT_TRAIL.md](../06-security/AUDIT_TRAIL.md)). |
| `permissions` | **Platform catalog** (Sprint 006): code-defined capabilities (`module.action`); no `tenant_id` so vocabulary cannot drift per tenant. Justified in [02-users-and-authorization/DATA_MODEL.md](../09-modules/02-users-and-authorization/DATA_MODEL.md). |

Any new platform table must be justified the same way in its module's `DATA_MODEL.md` and in the PR.

### Tenant-owned tables

- Carry `tenant_id BIGINT UNSIGNED NOT NULL`, FK → `tenants.id` `ON DELETE RESTRICT`; **`tenant_id` is immutable after record creation**.
- Model implements the `TenantOwned` contract and uses the `UsesTenantScope` trait — no exceptions.
- First tenant-owned table: `tenant_settings` (see [00-tenancy/DATA_MODEL.md](../09-modules/00-tenancy/DATA_MODEL.md)).
- **Implemented tenant-owned tables:** `roles`, `user_roles`, `role_permissions` (Sprint 006); `organization_units` (Sprint 007 — see [03-organization-structure/DATA_MODEL.md](../09-modules/03-organization-structure/DATA_MODEL.md); ADR-0004).
- **Planned tenant-owned tables** (each specified in its module's `DATA_MODEL.md` when designed): `positions`, `employees` (Sprint 008 — implemented; ADR-0005), `contract_categories`, `contracts`, `contract_status_transitions`, `contract_number_sequences` (Sprint 009 — implemented; ADR-0006), `meetings`, `meeting_*` (Sprint 010 — implemented; ADR-0007), `decision_number_sequences`, `decisions`, `decision_status_transitions` (Sprint 011 — implemented; ADR-0008), `task_number_sequences`, `tasks`, `task_status_transitions`, `task_assignment_history` (Sprint 012 — implemented; ADR-0009), `document_number_sequences`, `document_categories`, `documents` (Sprint 013 — specified; ADR-0010), `warehouses`, `inventory_items`, `inventory_transactions`, `assets`, `custodies`, `notifications`, `tenant_settings`.
- **Platform catalog (Sprint 006):** `permissions` — global, no `tenant_id`; justified because capabilities are code-defined and must not drift per tenant.

### Indexes on tenant-owned tables

- **Every secondary index leads with `tenant_id`**: `(tenant_id, status)`, `(tenant_id, created_at)`, etc. Because every query is scoped, an index not leading with `tenant_id` forces MySQL to scan across tenants and then filter — the composite order matches the guaranteed predicate.
- The `tenant_id` FK itself provides the plain `tenant_id` index.

### Unique constraints

- Business uniqueness is **per tenant**: `UNIQUE (tenant_id, employee_number)`, `UNIQUE (tenant_id, contract_number)`, `UNIQUE (tenant_id, key)` for settings, etc. A global unique on business identifiers would let tenant A's data block tenant B's inserts — both a correctness bug and an information leak (the failed insert reveals the value exists somewhere).
- Global uniqueness is reserved for genuinely global identifiers: `users.email` (login identifier across the platform — one login namespace), `tenants.name`, and `tenants.tenant_code` (immutable operational slug; first tenant: `rafee` — see [00-tenancy/DATA_MODEL.md](../09-modules/00-tenancy/DATA_MODEL.md)).

### Foreign keys and deletion rules

- All FKs explicit. `tenant_id` FKs are **`ON DELETE RESTRICT`** — never `CASCADE`. A cascading tenant delete could silently destroy an entire company's data in one statement; restriction makes hard tenant deletion physically impossible while any business row exists. Tenants are archived, not deleted (see [00-tenancy/BUSINESS_RULES.md](../09-modules/00-tenancy/BUSINESS_RULES.md)).
- Business-to-business FKs within a tenant choose `RESTRICT` or `CASCADE` per relationship in the owning module's `DATA_MODEL.md`; audit history is never cascaded away.

### Migration order (binding for the tenancy phase)

1. `tenants` (registry must exist before anything references it).
2. `users` — add nullable `tenant_id` FK to the existing table.
3. `tenant_settings` (first tenant-owned table; proves the whole scoping stack).
4. Business module tables, each in its own module's migrations, always after `tenants`.

## Integrity

- Foreign keys with explicit constraints; no orphaned tenant data.
- Database constraints back up application rules where practical (e.g. preventing double-active custody: enforcement approach decided at that module's design time).
- Statuses and enumerations that carry business meaning are documented in the owning module's `DATA_MODEL.md`.

## Auditability and Deletion

- Standard timestamps (`created_at`, `updated_at`) on all tables.
- **Prefer soft deletion where appropriate**, especially for completed meetings and audited business records where a `deleted_at` model is chosen. Soft-deleted rows keep their `tenant_id` and remain tenant-scoped. **Contracts (Sprint 009):** prefer explicit terminal statuses (`cancelled` / `closed` / `renewed` / `expired`) plus draft-only hard delete — not SoftDeletes — see [05-contracts/BUSINESS_RULES.md](../09-modules/05-contracts/BUSINESS_RULES.md).
- Deleting a business record never deletes its audit history.
- Inventory quantities are derived from transaction rows — no directly edited balance column as source of truth.
- Custody history rows are immutable; corrections happen through controlled, permissioned operations.

## Change Discipline

- Schema changes only through migrations, reviewed via pull request.
- Every migration PR states its tenant-scoping impact (platform vs. tenant-owned, index/unique shape) in the PR template's "Database changes" section.
- Migrations must be repeatable in CI (fresh migrate + seed).

## TBD

- **Table naming conventions document**: unresolved because no business tables exist yet. **Recommended:** Laravel defaults (plural snake_case) codified when the first business module is designed. **Impact:** documentation only.
- **Polymorphic relation conventions for documents/audit**: Documents Sprint 013 locks Laravel morph **string aliases** on `documents.linkable_type` (never FQCN) — see [ADR-0010](../10-decisions/ADR-0010-DOCUMENT-STORAGE-AND-ENTITY-LINKS.md). Audit polymorphic storage remains TBD until the Audit module design.
