# Documents — Acceptance Criteria

> **Status:** Implemented (Sprint 013) — acceptance verified via Pest/Vitest  
> **Last updated:** 2026-08-10

Implementation is **done** only when all critical items below pass and docs say **Implemented**.

## Functional

- [x] Users with `documents.view` can open الوثيقة list and details
- [x] Upload creates `DOC-######`, stores file on private tenant path, returns metadata without storage paths
- [x] Allowed types/size enforced; dangerous types rejected
- [x] Documents can be standalone or linked to one allowed entity
- [x] Download streams only after `documents.download`; audited
- [x] Archive/restore via explicit actions; default list shows active
- [x] Hard delete removes metadata + blob with `documents.delete`
- [x] Categories manageable with `documents.manage_categories`
- [x] Entity details show **المستندات** for Contracts, Meetings, Decisions, Tasks, Employees, Organization Units

## Security / tenancy

- [x] Cross-tenant metadata or download → **404**
- [x] No public disk / guessable URL access
- [x] Path traversal impossible via API
- [x] Policies capability-based only
- [x] Mandatory Pest file-authorization + cross-tenant suites green

## Quality

- [x] Pest matrix for critical DATABASE/NUMBERING/UPLOAD/DOWNLOAD/TENANCY/RBAC/AUDIT green
- [x] Vitest coverage for validation + permission visibility
- [x] `composer`/pint/`php artisan test` and frontend type-check/test/build green at implementation
- [x] ADR-0010 and module docs marked Implemented only after above

## Explicitly not required for Sprint 013 acceptance

- [ ] Malware scanner product
- [ ] Versioning
- [ ] Confidentiality levels
- [ ] OCR / preview
- [ ] Warehouse/Asset/Custody widgets
- [ ] Multi-record link pivot
