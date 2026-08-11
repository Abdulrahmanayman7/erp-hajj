# Module: Documents and Archiving (الوثائق والأرشفة)

> **Status:** Implemented (Sprint 013)  
> **Last updated:** 2026-08-10  
> Binding ADR: [ADR-0010](../../10-decisions/ADR-0010-DOCUMENT-STORAGE-AND-ENTITY-LINKS.md)

## Purpose

Provide a **tenant-isolated, permission-controlled document repository** for ERP Hajj: upload private files, store metadata, optionally link each Document to one business record, search/filter/categorize, download through authorized APIs only, archive, and hard-delete under controlled rules.

A **Document** is a single uploaded file plus server-managed metadata (and optional category / entity link). It is **not** a knowledge-base article, OCR corpus, or multi-file folder.

## Scope (Sprint 013 MVP)

- Central document center (`/app/documents`) + details.
- Tenant-owned `document_categories` catalog (active/inactive).
- Human `document_number` (`DOC-######`) + private binary storage under `TenantStorage::DOCUMENTS`.
- Optional **single** polymorphic link per Document to one of: Contract, Meeting, Decision, Task, Employee, Organization Unit.
- Reserved morph aliases for later host UI / registration: `warehouse`, `inventory_item` (Sprint 014 inventory implementation), `asset`, `custody`.
- Lifecycle: `active` ↔ `archived`; hard delete with `documents.delete`.
- Streamed authorized download (no public URLs).
- Embedded **المستندات** sections on implemented entity details (Contracts, Meetings, Decisions, Tasks, Employees, Organization Units).
- Permissions, Policies, audit events, Pest/Vitest plans.

## Out of scope

- Multi-entity sharing of one Document (many links) — Change Request.
- Document version trees / check-in-check-out.
- Confidentiality classification levels (all Documents are private/tenant-isolated; levels TBD).
- OCR, full-text content indexing, previewer packages.
- Malware/AV scanner product integration (readiness hook only).
- Bulk upload ZIP, public CDN, signed URLs that bypass Policy.
- Form builder / knowledge management / policies library (future scope).
- Warehouse / Inventory item / Asset / Custody attachment UI until those modules ship (morph aliases `warehouse`, `inventory_item`, `asset`, `custody` reserved; register + UI with Sprint 014+ implementations).

## Personas

| Persona | Usage |
|---|---|
| Tenant Owner / GM / Dept Manager | Upload, link, archive, delete, manage categories |
| Supervisor / Employee | View/download when granted `documents.view` / `documents.download` |
| Auditor | View/download metadata and files for review |

No role-name Policy checks. No Task-assignee self-service bypass of `documents.*` (unlike Tasks ADR-0009).

## References

- [ADR-0010](../../10-decisions/ADR-0010-DOCUMENT-STORAGE-AND-ENTITY-LINKS.md)
- [SECURITY_BASELINE.md](../../06-security/SECURITY_BASELINE.md) · [AUDIT_TRAIL.md](../../06-security/AUDIT_TRAIL.md) · [MULTI_TENANCY.md](../../02-architecture/MULTI_TENANCY.md) §11
- Implemented `App\Core\Tenancy\TenantStorage` (`DOCUMENTS` directory)
- Host modules: Contracts / Meetings / Decisions / Tasks (attachments deferred → owned here)
