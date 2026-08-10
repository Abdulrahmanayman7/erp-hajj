# Documents — Test Plan

> **Status:** Specified (Sprint 013) — **not implemented**  
> **Last updated:** 2026-08-10  
> Tooling: **Pest** (backend) · **Vitest** (frontend)

Failing security / cross-tenant / file-authorization tests block merge.

---

## Backend (Pest)

### DATABASE

- [ ] Sequences / categories / documents FKs and unique (`tenant_id`, `document_number`)
- [ ] Tenant-leading indexes (status, morph, checksum, category, created_at)
- [ ] No SoftDeletes; no versions/links pivot tables

### NUMBERING

- [ ] First → `DOC-000001`; increments; per-tenant isolation; immutable; concurrency-safe; client number ignored

### UPLOAD

- [ ] Valid PDF/Office/image/txt accepted
- [ ] Forbidden extension/MIME rejected (`DOCUMENT_INVALID_FILE`)
- [ ] Oversize rejected (`DOCUMENT_FILE_TOO_LARGE`)
- [ ] MIME/extension mismatch rejected
- [ ] Original filename preserved as metadata; stored name opaque
- [ ] Path under `tenants/{id}/documents/` only; no `..`
- [ ] Checksum set; duplicate checksum allowed
- [ ] `tenant_id` injection ignored
- [ ] Standalone + linked create

### METADATA

- [ ] PATCH title/description/category
- [ ] Storage fields not patchable
- [ ] Inactive category blocked on new assign; historical OK

### LINKING

- [ ] Valid same-tenant Contract/Meeting/Decision/Task/Employee/OrgUnit
- [ ] Foreign tenant target rejected
- [ ] Clear link (unlink)
- [ ] Both-or-neither morph pair validation
- [ ] Host entity hard-delete blocked while Documents linked (integration)

### DOWNLOAD

- [ ] Authorized stream with correct Content-Type / Disposition
- [ ] Unauthorized → 403
- [ ] Cross-tenant → 404
- [ ] Missing blob → `DOCUMENT_FILE_MISSING`
- [ ] No public URL; path not in JSON
- [ ] Audit `DOCUMENT_DOWNLOADED`

### ARCHIVE / RESTORE

- [ ] active→archived; archived→active
- [ ] Invalid transitions rejected
- [ ] Default list excludes archived unless filtered

### DELETE

- [ ] Hard delete removes DB row + file
- [ ] Permission gated
- [ ] Audit without path/bytes

### VERSIONING

- [ ] Confirm no version endpoints/tables exist

### TENANCY

- [ ] Full matrix list/show/update/download/archive/restore/delete isolation (404)
- [ ] Cross-tenant download impossible even with guessed storage key

### RBAC

- [ ] Each `documents.*` permission gates mapped actions
- [ ] Category permissions
- [ ] Default role template expectations (Dept Mgr no delete; Employee view+download)

### SECURITY

- [ ] Path traversal attempts
- [ ] Malicious filenames
- [ ] Fake MIME
- [ ] Oversized
- [ ] Unauthorized download/archive/delete
- [ ] Direct disk URL inaccessible
- [ ] Audit payload has no storage path / file content

### AUDIT

- [ ] Upload/update/link/unlink/download/archive/restore/delete (+ category events)

### LIST / FILTERS

- [ ] search, status, category, uploader, linkable_type/id, date range, pagination, default newest first

### INTEGRATION

- [ ] Entity widget filter returns only linked docs
- [ ] Upload with preselected link
- [ ] Decision/Task/Contract/Meeting/Employee/Org delete guards

---

## Frontend (Vitest)

- [ ] List loading/empty/error
- [ ] Filters + archived segment
- [ ] Upload validation (size/type) client helpers
- [ ] Details metadata (no path leakage in types/resources mocks)
- [ ] Download action gated by permission
- [ ] Archive/restore/delete UX visibility
- [ ] Categories manager permission
- [ ] Entity المستندات section (at least one host page test or shared widget test)
- [ ] Sidebar visibility `documents.view`
- [ ] Responsive/RTL smoke where applicable

## Explicit non-goals

- Malware scanner integration tests
- OCR / previewer
- Multi-link pivot
- Version tree UI
