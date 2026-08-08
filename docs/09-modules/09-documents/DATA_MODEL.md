# Documents — Data Model (conceptual)

> **Status:** Conceptual — no migrations exist
> **Last updated:** 2026-08-06

All tables carry `tenant_id`.

## Document

| Field | Notes |
|---|---|
| Original name | As uploaded |
| Stored name | Generated, never guessable |
| File type | MIME |
| File size | Bytes |
| Storage disk | Private disk |
| Storage path | Tenant-isolated, never public |
| Category | Reference |
| Uploaded by | User |
| Tenant | `tenant_id` |
| Linked entity | Polymorphic: Employee / Contract / Meeting / Decision / Task / Warehouse / Asset / Custody |
| Description | Optional |
| Confidentiality level | Levels TBD |
| Created date | Timestamp |
| Archive/soft delete | Flag + timestamp |

## Document Category (per tenant)

Name, description, active flag.

## TBD

- Polymorphic conventions (single link vs. many links per document): TBD — leaning single link per document record.
