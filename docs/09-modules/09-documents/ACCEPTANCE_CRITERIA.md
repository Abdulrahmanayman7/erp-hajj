# Documents — Acceptance Criteria

> **Status:** Specified (Sprint 013) — **not implemented**  
> **Last updated:** 2026-08-10

Implementation is **done** only when all critical items below pass and docs say **Implemented**.

## Functional

- [ ] Users with `documents.view` can open الوثيقة list and details
- [ ] Upload creates `DOC-######`, stores file on private tenant path, returns metadata without storage paths
- [ ] Allowed types/size enforced; dangerous types rejected
- [ ] Documents can be standalone or linked to one allowed entity
- [ ] Download streams only after `documents.download`; audited
- [ ] Archive/restore via explicit actions; default list shows active
- [ ] Hard delete removes metadata + blob with `documents.delete`
- [ ] Categories manageable with `documents.manage_categories`
- [ ] Entity details show **المستندات** for Contracts, Meetings, Decisions, Tasks, Employees, Organization Units

## Security / tenancy

- [ ] Cross-tenant metadata or download → **404**
- [ ] No public disk / guessable URL access
- [ ] Path traversal impossible via API
- [ ] Policies capability-based only
- [ ] Mandatory Pest file-authorization + cross-tenant suites green

## Quality

- [ ] Pest matrix for critical DATABASE/NUMBERING/UPLOAD/DOWNLOAD/TENANCY/RBAC/AUDIT green
- [ ] Vitest coverage for validation + permission visibility
- [ ] `composer`/pint/`php artisan test` and frontend type-check/test/build green at implementation
- [ ] ADR-0010 and module docs marked Implemented only after above

## Explicitly not required for Sprint 013 acceptance

- [ ] Malware scanner product
- [ ] Versioning
- [ ] Confidentiality levels
- [ ] OCR / preview
- [ ] Warehouse/Asset/Custody widgets
- [ ] Multi-record link pivot
