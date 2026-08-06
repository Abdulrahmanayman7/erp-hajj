# Database Principles

> **Status:** Approved principles; no migrations exist yet
> **Last updated:** 2026-08-06

## Purpose

Define the rules every table and migration must follow. **No migrations are created at this stage** — these principles govern future work. Conceptual entities per module are documented in each module's `DATA_MODEL.md` under [docs/09-modules/](../09-modules/).

## Engine

- MySQL is the system database.
- Redis is used only when required (cache, queues) and never as the source of truth.

## Tenancy (decided — see [ADR-0003](../10-decisions/ADR-0003-MULTI-TENANCY.md))

- Single database, shared schema. Every **tenant-owned table carries `tenant_id`**, automatically scoped at the application layer.
- Central platform tables (tenants registry, platform settings) may omit `tenant_id` where appropriate — each omission must be justified in module docs.
- **Unique constraints include `tenant_id` where needed** (e.g. employee number unique per tenant, contract number per tenant).
- Indexes on tenant-owned tables should lead with or include `tenant_id` for scoped query performance.

## Integrity

- Foreign keys with explicit constraints; no orphaned tenant data.
- Database constraints back up application rules where practical (e.g. preventing double-active custody: enforcement approach TBD at design time).
- Statuses and enumerations that carry business meaning are documented in the owning module's `DATA_MODEL.md`.

## Auditability and Deletion

- Standard timestamps (`created_at`, `updated_at`) on all tables.
- **Prefer soft deletion where appropriate**, especially for approved/executed contracts, completed meetings, and audited business records.
- Deleting a business record never deletes its audit history.
- Inventory quantities are derived from transaction rows — no directly edited balance column as source of truth.
- Custody history rows are immutable; corrections happen through controlled, permissioned operations.

## Change Discipline

- Schema changes only through migrations, reviewed via pull request.
- Every migration PR states its tenant-scoping impact in the PR template's "Database changes" section.
- Migrations must be repeatable in CI (fresh migrate + seed).

## TBD

- Primary key strategy (auto-increment vs. ULID/UUID): TBD before first migration.
- Table naming conventions document: TBD at scaffolding time.
- Polymorphic relation conventions for documents/audit: TBD at design time.
