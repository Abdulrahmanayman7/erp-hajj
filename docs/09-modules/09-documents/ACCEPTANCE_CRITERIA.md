# Documents — Acceptance Criteria

> **Status:** Draft
> **Last updated:** 2026-08-06

- [ ] Upload validates MIME, extension, and size; rejects violations with standardized errors.
- [ ] Files stored on a private disk under tenant-isolated paths; no public path ever.
- [ ] Download requires authorization; direct storage access is impossible; sensitive downloads are audited.
- [ ] Polymorphic linking works for all eight entity types, validated within the tenant.
- [ ] Search, filter, and categorization work; categories manageable with `documents.manage_categories`.
- [ ] Upload and delete are audited; deletion is archive/soft delete.
- [ ] Cross-tenant access (metadata or file) returns `404`.
