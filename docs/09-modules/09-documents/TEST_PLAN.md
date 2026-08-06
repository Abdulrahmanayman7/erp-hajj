# Documents — Test Plan

> **Status:** Draft
> **Last updated:** 2026-08-06

- **Cross-tenant (mandatory):** tenant A cannot list, view, or download tenant B documents (`404`), including by guessing IDs/paths.
- **File authorization (mandatory):** download without `documents.download` fails; unauthenticated direct URL access fails; storage paths unreachable publicly.
- Upload: valid types succeed; wrong MIME/extension/oversize rejected (`422`).
- Linking: each of the eight entity types; linking to another tenant's entity rejected.
- Audit: upload, delete, and sensitive-document download records.
- Archive: soft-deleted documents excluded from default lists, restorable per policy (TBD).
- Frontend: File Upload component; attachments widget reuse in a host module.
- E2E: upload linked to a contract → protected download → archive.
