# ADR-0010: Document private storage, entity links, and immutability

- **Status:** Accepted
- **Date:** 2026-08-10
- **Sprint:** 013 (Documents & Archiving)
- **Deciders:** ERP Hajj product / engineering (documentation lock)

## Implementation note (2026-08-10)

Shipped as vertical slice: migrations (`document_number_sequences`, `document_categories`, `documents`), `Document`/`DocumentCategory` models, `DocumentNumberGenerator` (`FOR UPDATE`), private `TenantStorage::DOCUMENTS` paths with UUID filenames, finfo MIME allow-list + 20 MiB, SHA-256 checksum, streamed download, archive/restore/hard-delete, morph aliases + host delete guards, `documents.*` permissions, Pest `DocumentTest`, Vue module + host **المستندات** sections. No versioning, no multi-link pivot, no public URLs.

## Context

Security baseline and multi-tenancy require private, tenant-isolated files with authorized downloads. Prior modules (Contracts, Meetings, Decisions, Tasks) deferred attachments and reserved placeholders. Early Documents stubs mentioned polymorphic links to eight entity types, confidentiality levels, soft delete, and optional versioning — without locking cardinality, storage keys, numbering, or hard-delete rules.

`TenantStorage` already defines `tenants/{tenant_id}/documents/...`. DATABASE_PRINCIPLES recommends morph aliases (never FQCN) when polymorphic documents land.

## Decision

### 1. Private storage ownership

- Documents module owns file metadata and binary lifecycle.
- Binaries live on a **private** filesystem disk only.
- Paths generated exclusively via `TenantStorage::DOCUMENTS` + opaque UUID filenames.
- Original filename is metadata only; never a path segment.
- Downloads are **streamed** through Policy-gated API endpoints. Public URLs and Policy-bypassing signed URLs are out of MVP.

### 2. Tenant isolation

- `Document` / `DocumentCategory` are `TenantOwned` + `UsesTenantScope`.
- Cross-tenant access to metadata or bytes returns **404**.
- Storage path always embeds numeric `tenant_id` from `TenantContext`.

### 3. Numbering

- Human `document_number` = `DOC-######` via per-tenant sequence + `FOR UPDATE` (parity with CTR/MTG/DEC/TSK).

### 4. Entity-link strategy

- **Single optional polymorphic link** on `documents` (`linkable_type` alias + `linkable_id`).
- Cardinality: Document 0..1 entity; Entity 0..N Documents; standalone allowed.
- MVP UI links: Contract, Meeting, Decision, Task, Employee, Organization Unit.
- Morph aliases reserved for Warehouse, Inventory Item (Sprint 014), Asset, Custody (register + UI with owning-module implementation).
- **No** many-to-many `document_links` pivot in MVP.
- Host hard-delete blocked while Documents remain linked.

### 5. Versioning / replacement

- **Immutable files (Option A).** Replacement = new Document upload.
- No version table / version numbering.

### 6. Lifecycle / delete

- Status `active` | `archived` with explicit archive/restore actions.
- Hard delete is a separate capability (`documents.delete`) removing DB row + blob.
- Archive preferred for operational removal; SoftDeletes trait not used.
- Confidentiality levels deferred; all Documents treated as private.

### 7. Permissions

- Catalog: `documents.view|upload|download|update|archive|delete|manage_categories`.
- `documents.delete` is hard delete; archive is not folded into delete.
- No assignee self-service bypass for Documents.

### 8. Validation boundary (malware)

- Enforce MIME/extension allow-list + 20 MiB size + private storage + authz.
- Malware scanner product integration is **deferred** (readiness only).

## Consequences

- Host modules replace “المرفقات — قريباً” with Documents widgets at implementation.
- Reserved `contracts.attach_documents` is not seeded; use Documents permissions.
- Multi-link sharing and version trees require Change Request + new ADR amendment.
- Implementation must never expose `storage_path` in API Resources.

## Alternatives rejected

| Alternative | Why rejected |
|---|---|
| Public disk / direct URLs | Violates SECURITY_BASELINE |
| Many-to-many links in MVP | Overbuilds without approved multi-attach use cases |
| Full version tree | Explicitly TBD historically; complexity unjustified |
| SoftDeletes as only “archive” | Conflicts with explicit active/archived ops + hard delete need |
| Fold archive into `documents.delete` | Ambiguous; separates retention vs purge |

## References

- [09-documents/](../09-modules/09-documents/)
- [MULTI_TENANCY.md](../02-architecture/MULTI_TENANCY.md) §11
- [SECURITY_BASELINE.md](../06-security/SECURITY_BASELINE.md)
- [DATABASE_PRINCIPLES.md](../03-database/DATABASE_PRINCIPLES.md)
- Implemented `App\Core\Tenancy\TenantStorage`
