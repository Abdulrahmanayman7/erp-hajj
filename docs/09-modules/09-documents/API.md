# Documents — API (planned)

> **Status:** Planned contract — no endpoints exist yet
> **Last updated:** 2026-08-06

All endpoints follow [API_STANDARDS.md](../../04-api/API_STANDARDS.md).

```text
GET    /api/v1/documents                       # documents.view; search + filters: category, type, linked entity, confidentiality, date
POST   /api/v1/documents                       # documents.upload (multipart; validates MIME/extension/size)
GET    /api/v1/documents/{document}            # documents.view (metadata)
GET    /api/v1/documents/{document}/download   # documents.download (authorized streaming/signed; sensitive downloads audited)
PATCH  /api/v1/documents/{document}            # documents.update
DELETE /api/v1/documents/{document}            # documents.delete (archive/soft delete, audited)
```

## Categories

```text
GET/POST/PATCH/DELETE /api/v1/document-categories   # documents.manage_categories (view: documents.view)
```

## Behavior

- Download never exposes storage paths; served via authorized streaming or short-lived signed URLs (mechanism TBD).
- Upload attaches optional polymorphic link (entity type + id, validated within tenant).

## TBD

- Signed URL vs. streamed download decision: TBD.
- Bulk upload: not specified — do not build without approval.
