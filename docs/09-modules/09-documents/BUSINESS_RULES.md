# Documents — Business Rules

> **Status:** Approved
> **Last updated:** 2026-08-06

- **Private files must never be stored in public paths**; storage paths are tenant-isolated.
- Downloads are authorized (permission-checked); **sensitive document downloads are audited**.
- Uploads validate MIME type, extension, and file size (limits TBD); malware scanning readiness (scanner TBD).
- Documents may link polymorphically to: Employee, Contract, Meeting, Decision, Task, Warehouse, Asset, Custody.
- Upload and deletion are audited events.
- Deletion is archive/soft delete by default; hard deletion policy TBD.
- Categories are managed with `documents.manage_categories`.
- Confidentiality level controls access (level definitions TBD).

## TBD

- Confidentiality level list and their access semantics: TBD.
- File size limits and allowed types list: TBD.
- Retention periods and archival policy: TBD.
- Versioning of documents: not specified — do not build without approval.
