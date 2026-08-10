# Documents — API

> **Status:** Implemented (Sprint 013)  
> **Last updated:** 2026-08-10  
> Base: `/api/v1` · Auth: Sanctum SPA · Envelope: [API_STANDARDS.md](../../04-api/API_STANDARDS.md)

All routes: authenticated + tenant-active. Authorization via Policies. Cross-tenant → **404**.  
**Never** return `storage_path`, `storage_disk`, or filesystem absolute paths.

---

## Endpoints — Documents

| Method | Path | Permission | Notes |
|---|---|---|---|
| GET | `/documents` | `documents.view` | Paginated list |
| POST | `/documents` | `documents.upload` | `multipart/form-data` |
| GET | `/documents/{document}` | `documents.view` | Metadata only |
| PATCH | `/documents/{document}` | `documents.update` | Metadata + link |
| GET | `/documents/{document}/download` | `documents.download` | Stream file |
| POST | `/documents/{document}/archive` | `documents.archive` | → archived |
| POST | `/documents/{document}/restore` | `documents.archive` | → active |
| DELETE | `/documents/{document}` | `documents.delete` | Hard delete |

**No** generic `PATCH …/status`.  
**No** nested write APIs under contracts/meetings/… required (filter `linkable_type` + `linkable_id`).

---

## Endpoints — Categories

| Method | Path | Permission |
|---|---|---|
| GET | `/document-categories` | `documents.view` or `documents.manage_categories` |
| POST | `/document-categories` | `documents.manage_categories` |
| PATCH | `/document-categories/{category}` | `documents.manage_categories` |
| DELETE | `/document-categories/{category}` | `documents.manage_categories` (only if unreferenced) |

---

## Upload — `POST /documents`

`Content-Type: multipart/form-data`

| Field | Required | Notes |
|---|---|---|
| `file` | yes | Binary |
| `title` | no | Defaults from sanitized original filename (sans extension) |
| `description` | no | |
| `category_id` | no | Active same-tenant category |
| `linkable_type` | no* | Morph alias |
| `linkable_id` | no* | Required if type set |

\* Both link fields null → standalone; both set → validate pair.

**Server sets:** number, status=`active`, mime, extension, size, checksum, storage key, uploaded_by.

**Audit:** `DOCUMENT_UPLOADED` (+ `DOCUMENT_LINKED` if link set at create).

---

## Update — `PATCH /documents/{document}`

JSON fields: `title`, `description`, `category_id`, `linkable_type`, `linkable_id` (clear link with both null).

Cannot change file bytes. Invalid link → `DOCUMENT_LINK_INVALID`.

**Audit:** `DOCUMENT_UPDATED`; link change may also emit `DOCUMENT_LINKED` / `DOCUMENT_UNLINKED`.

---

## Download — `GET /documents/{document}/download`

- Resolve Document under tenant scope + Policy `download`.
- Stream from private disk; `Content-Type` = stored mime;  
  `Content-Disposition: attachment; filename="{original_filename}"` (RFC-compliant encoding).
- **Audit:** `DOCUMENT_DOWNLOADED` (all MVP downloads treated as sensitive).
- Missing blob → `DOCUMENT_FILE_MISSING`.

---

## Archive / restore

```http
POST /documents/{id}/archive
POST /documents/{id}/restore
```

Optional `{ "comment": "…" }`. Invalid transition → `DOCUMENT_INVALID_STATUS_TRANSITION`.

Audits: `DOCUMENT_ARCHIVED` / `DOCUMENT_RESTORED`.

---

## Delete — `DELETE /documents/{document}`

Hard delete row + blob. Audit `DOCUMENT_DELETED` (ids/number/size/mime only).

---

## List — `GET /documents`

| Param | Notes |
|---|---|
| `search` | `document_number`, `title`, `original_filename` |
| `status` | `active` \| `archived` \| `all` (default **active**) |
| `category_id` | |
| `uploaded_by` | user id |
| `linkable_type` | morph alias |
| `linkable_id` | |
| `uploaded_from` / `uploaded_to` | date |
| `page` / `per_page` | |

### Sorting

Default: `created_at` DESC, then `id` DESC.  
Alternate: `sort=document_number|title|size_bytes` + `direction`.

---

## Resource shape (illustrative)

```json
{
  "id": 1,
  "document_number": "DOC-000001",
  "title": "عقد توريد",
  "description": null,
  "status": "active",
  "original_filename": "contract.pdf",
  "mime_type": "application/pdf",
  "extension": "pdf",
  "size_bytes": 204800,
  "checksum_sha256": "…",
  "category": { "id": 2, "name": "عقود" },
  "link": {
    "type": "contract",
    "id": 15,
    "label": "CTR-000003 — …"
  },
  "uploaded_by": { "id": 3, "name": "…" },
  "archived_at": null,
  "created_at": "…",
  "updated_at": "…"
}
```

`link.label` is a compact eager summary when resolvable; omit heavy payloads.

---

## Error codes

| Code | When |
|---|---|
| `DOCUMENT_NOT_FOUND` | Missing / cross-tenant (prefer generic 404) |
| `DOCUMENT_NUMBER_TAKEN` | Defensive unique race |
| `DOCUMENT_INVALID_FILE` | MIME/extension mismatch or forbidden type |
| `DOCUMENT_FILE_TOO_LARGE` | Over max size |
| `DOCUMENT_LINK_INVALID` | Bad/foreign/inactive target |
| `DOCUMENT_INVALID_STATUS_TRANSITION` | Illegal archive/restore |
| `DOCUMENT_FILE_MISSING` | Metadata without blob |
| `DOCUMENT_CATEGORY_IN_USE` | Delete category while referenced |
| `DOCUMENT_CATEGORY_INVALID` | Inactive/foreign category on assign |
| `DOCUMENT_IMMUTABLE` | Attempt to patch storage fields |

---

## Audit events

| Event | When |
|---|---|
| `DOCUMENT_UPLOADED` | Create |
| `DOCUMENT_UPDATED` | Metadata PATCH |
| `DOCUMENT_LINKED` | Link set/changed to non-null |
| `DOCUMENT_UNLINKED` | Link cleared |
| `DOCUMENT_DOWNLOADED` | Successful download |
| `DOCUMENT_ARCHIVED` | archive |
| `DOCUMENT_RESTORED` | restore |
| `DOCUMENT_DELETED` | hard delete |
| `DOCUMENT_CATEGORY_CREATED` / `_UPDATED` / `_DELETED` | Category mutations |

No `DOCUMENT_VERSION_ADDED` (versioning out of MVP).
