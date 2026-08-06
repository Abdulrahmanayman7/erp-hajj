# Tenancy — Data Model (conceptual)

> **Status:** Conceptual — no migrations exist
> **Last updated:** 2026-08-06

## Tenant (platform table — no tenant_id)

| Field | Notes |
|---|---|
| Name | Organization / campaign company name |
| Status | Active / Suspended (full status list TBD) |
| Created at / updated at | Standard timestamps |

Additional fields (contact info, identifiers, subscription data): **TBD** — commercial model not confirmed.

## Tenant Settings

| Field | Notes |
|---|---|
| Tenant | Owning tenant |
| Key / value | Settings required by MVP modules (keys TBD) |

## Cross-cutting

- Every tenant-owned table in other modules carries `tenant_id` (indexed; part of unique constraints where needed).
- See [DATABASE_PRINCIPLES.md](../../03-database/DATABASE_PRINCIPLES.md).
