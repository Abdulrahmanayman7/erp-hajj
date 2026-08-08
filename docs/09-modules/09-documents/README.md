# Module: Documents and Archiving (الوثائق والأرشفة)

> **Status:** Documented — not implemented
> **Last updated:** 2026-08-06

## Purpose

Provide a centralized, permission-controlled document center: upload, download, search, filter, categorize, link to business entities, and archive/soft delete.

## Scope

- Central document center with categories.
- Polymorphic linking to: Employee, Contract, Meeting, Decision, Task, Warehouse, Asset, Custody.
- Confidentiality levels and permission-controlled access.
- **Private storage only** — private files never in public paths; tenant-isolated storage paths.
- Archive / soft delete.

## Out of scope

- Full dynamic form builder, knowledge management (future scope).
- OCR/content indexing: not specified.

## References

- [SECURITY_BASELINE.md](../../06-security/SECURITY_BASELINE.md) (files section) · [AUDIT_TRAIL.md](../../06-security/AUDIT_TRAIL.md)
