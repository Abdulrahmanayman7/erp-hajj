# Documents — Business Rules

> **Status:** Specified (Sprint 013) — **not implemented**  
> **Last updated:** 2026-08-10  
> ADR: [ADR-0010](../../10-decisions/ADR-0010-DOCUMENT-STORAGE-AND-ENTITY-LINKS.md)

## 1. What is a Document

1. A Document is **one file** + **metadata** owned by exactly one tenant.
2. Binaries are **never** stored in MySQL; only metadata and a storage key live in the DB.
3. Documents are the sole MVP attachment mechanism for Contracts, Meetings, Decisions, Tasks, Employees, and Organization Units.

## 2. Identity and numbering

1. `document_number` is server-generated, immutable, tenant-unique: `DOC-` + six zero-padded digits (`DOC-000001`…).
2. Allocation uses per-tenant `document_number_sequences` with `SELECT … FOR UPDATE` (same pattern as Tasks/Decisions). **No `MAX+1`.**
3. Clients never supply `document_number`, `tenant_id`, storage path, stored filename, MIME, size, or checksum.

## 3. Storage security (binding)

1. **Private disk only** (Laravel private / non-public disk). Never `public` disk or web-reachable paths.
2. Paths built **only** via `TenantStorage::path(TenantStorage::DOCUMENTS, …)` →  
   `tenants/{tenant_id}/documents/{uuid}.{ext}`  
   where `{tenant_id}` is numeric from `TenantContext` (not `tenant_code`).
3. **Stored filename** = opaque UUID + normalized extension (server-derived from validated MIME/extension map).
4. **Original filename** stored as metadata only (sanitized for display; never used as storage path).
5. Client must never control disk, directory, or relative path; `..` and absolute paths rejected.
6. Downloads only through authenticated API after Policy; **no public URLs**; signed URLs that bypass Policy are forbidden. MVP download = **streamed response** from the application.
7. Path traversal / foreign-tenant key access must fail closed (`404`).
8. Malware scanning: **deferred** (scanner TBD). Boundary: MIME/extension/size validation + private storage + authz. Do not claim scanned-clean.

## 4. Allowed types and size

| Rule | MVP lock |
|---|---|
| Max size | **20 MiB** per file (`config/documents.php`) |
| Allowed extensions | `pdf`, `jpg`, `jpeg`, `png`, `webp`, `doc`, `docx`, `xls`, `xlsx`, `txt` |
| MIME must match extension map | Server-side MIME detection (e.g. finfo) must agree with declared/extension map |
| Dangerous types | Reject executables, scripts, archives (`exe`, `bat`, `cmd`, `js`, `php`, `sh`, `zip`, `rar`, …) |

Mismatch → `DOCUMENT_INVALID_FILE`. Oversized → `DOCUMENT_FILE_TOO_LARGE`.

## 5. Checksum

1. Store **SHA-256** hex of file bytes at upload (`checksum_sha256`).
2. Used for integrity verification and optional duplicate **warning** in UI (same tenant + same checksum).
3. Duplicates are **allowed** (no unique constraint on checksum) — operational copies may be intentional.

## 6. Categories

1. Tenant-owned `document_categories`: name, description, `is_active`.
2. Unique name per tenant; deactivate preferred over hard delete when referenced.
3. Document `category_id` nullable; inactive category may remain on historical Documents; new assigns require active category.

## 7. Entity link model (MVP)

1. Each Document has **at most one** optional polymorphic link:  
   `linkable_type` (morph alias string) + `linkable_id` (nullable pair).
2. Cardinality: **Document → 0..1 entity**; **Entity → 0..N Documents**.
3. Standalone Documents (no link) are allowed (central archive / general files).
4. One Document **cannot** belong to multiple records in MVP (no `document_links` pivot). Sharing across entities requires a Change Request or a second upload.
5. Morph map aliases (never FQCN in DB):  
   `contract`, `meeting`, `decision`, `task`, `employee`, `organization_unit`  
   Reserved (schema-ready, UI later): `warehouse`, `asset`, `custody`.
6. Link target must exist, same tenant; foreign → `DOCUMENT_LINK_INVALID` / validation 422 (or 404 if treated as missing under tenant scope).
7. Link/unlink via create payload and dedicated actions (or PATCH metadata) per [API.md](API.md) — never trust client morph without TenantExists-style checks.

## 8. Versioning / replacement

**MVP decision (ADR-0010): Option A — immutable files.**

- No `document_versions` table.
- Replacing a file = upload a **new** Document (optionally link to same entity; archive/delete the old one if desired).
- Do not mutate stored bytes of an existing Document.

## 9. Lifecycle

| Status | Meaning |
|---|---|
| `active` | Visible in default lists; downloadable |
| `archived` | Hidden from default active lists; retained; downloadable with permission |

### Transitions

| From | To | Action | Permission |
|---|---|---|---|
| (create) | `active` | upload | `documents.upload` |
| `active` | `archived` | archive | `documents.archive` |
| `archived` | `active` | restore | `documents.archive` |

No generic `PATCH status`. Explicit `POST …/archive` and `POST …/restore`.

### Metadata update

- Allowed while `active` (and optionally while archived for title/description/category/link — **lock: metadata update allowed in both statuses** except storage fields).
- Never change file bytes via update.

## 10. Delete and physical files

1. **Hard delete** (`DELETE /documents/{id}`) requires `documents.delete`.
2. Deletes DB row **and** physical object on the private disk (best-effort; missing file still completes delete with audit note).
3. Allowed for `active` and `archived`.
4. No SoftDeletes column; archive is status-based.
5. Prefer archive for normal “remove from worklists”; hard delete for mistaken uploads / retention cleanup.

## 11. Missing physical file

If metadata exists but the blob is missing: download returns `DOCUMENT_FILE_MISSING` (422 or 404 — **lock: 404** with stable code in envelope where supported, else 422 `DOCUMENT_FILE_MISSING`). List/show still return metadata. Audit download failures without leaking paths.

## 12. Linked entity deletion

1. Application delete guards on host modules: **block hard-delete of an entity while Documents still link to it** (`DOCUMENT_ENTITY_IN_USE` / host `*_IN_USE` pattern), **or** require unlink first.
2. Preferred implementation: host `Delete*` actions check `Document::query()->where morph…->exists()` → domain in-use exception (mirror OrganizationUnit referenced-by-Tasks).
3. Archiving a Document does **not** modify the business entity.
4. Cancelling/closing Contracts/Meetings/Decisions/Tasks does **not** auto-delete Documents.

## 13. Authorization

1. All Document actions via `DocumentPolicy` + `documents.*` capabilities.
2. Viewing an entity page’s Documents widget still requires `documents.view` (plus the entity’s view permission to open the page).
3. Download requires `documents.download` (and same-tenant Document).
4. No assignee self-service bypass for Task-linked Documents without `documents.download`.

## 14. Confidentiality

**TBD (business).** MVP treats every Document as private to the tenant. Do not invent `public`/`internal`/`restricted` enums without a Change Request.

## 15. Notifications

Hooks only (no delivery in Sprint 013): optional future `DOCUMENT_UPLOADED` consumer. No email/SMS.

## 16. Audit

See [API.md](API.md) / [AUDIT_TRAIL.md](../../06-security/AUDIT_TRAIL.md). Never log file bytes or full storage paths; log `document_id`, `document_number`, size, mime, link alias/id, actor, tenant, correlation ID.

## 17. Performance

Paginate metadata; never load blobs on list/show; stream downloads; tenant-leading indexes; checksum computed once at upload.
