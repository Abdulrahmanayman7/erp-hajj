# Documents — Data Model

> **Status:** Specified (Sprint 013) — **not implemented** (no migrations in this sprint)  
> **Last updated:** 2026-08-10

## Tables (future migration order)

1. `document_number_sequences`
2. `document_categories`
3. `documents`

No `document_versions`. No `document_links` pivot (MVP single morph on `documents`).

---

## 1. `document_number_sequences`

| Column | Type | Notes |
|---|---|---|
| `tenant_id` | FK → tenants | PK |
| `next_number` | unsigned bigint | Next value to allocate (starts at 1) |
| `created_at` / `updated_at` | timestamps | |

Allocation: `SELECT … FOR UPDATE`; format `DOC-` + pad 6.

---

## 2. `document_categories`

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | bigint PK | no | |
| `tenant_id` | FK → tenants | no | TenantOwned |
| `name` | string(120) | no | |
| `description` | text | yes | |
| `is_active` | boolean | no | default true |
| `created_at` / `updated_at` | timestamps | no | |

Constraints: UNIQUE (`tenant_id`, `name`). Indexes: (`tenant_id`, `is_active`).

ON DELETE: categories referenced by documents → **RESTRICT** (deactivate instead).

---

## 3. `documents`

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | bigint PK | no | |
| `tenant_id` | FK → tenants | no | TenantOwned + UsesTenantScope |
| `document_number` | string(20) | no | Immutable `DOC-######` |
| `title` | string(255) | no | Display title (default may derive from original filename) |
| `description` | text | yes | |
| `category_id` | FK → document_categories | yes | RESTRICT |
| `status` | string(32) | no | `active` \| `archived` |
| `original_filename` | string(255) | no | Client original name (metadata) |
| `stored_filename` | string(64) | no | Opaque UUID + ext (basename only) |
| `storage_disk` | string(32) | no | e.g. `local` private disk name |
| `storage_path` | string(512) | no | Relative key under disk root (tenant path); **API never exposes** |
| `mime_type` | string(127) | no | |
| `extension` | string(16) | no | Lowercase |
| `size_bytes` | unsigned bigint | no | |
| `checksum_sha256` | char(64) | no | Hex |
| `linkable_type` | string(64) | yes | Morph alias |
| `linkable_id` | unsigned bigint | yes | |
| `uploaded_by` | FK → users | no | RESTRICT |
| `archived_at` | timestamp | yes | |
| `archived_by` | FK → users | yes | nullOnDelete |
| `created_at` / `updated_at` | timestamps | no | |

### Constraints

| Intent | Definition |
|---|---|
| `documents_tenant_number_unique` | UNIQUE (`tenant_id`, `document_number`) |
| Link pair | CHECK app-level: both null or both non-null |
| Morph index | INDEX (`tenant_id`, `linkable_type`, `linkable_id`) |
| Status / category / uploader / created | tenant-leading indexes |
| Checksum lookup | INDEX (`tenant_id`, `checksum_sha256`) |

### Explicitly absent

- SoftDeletes column
- Version number / parent_document_id
- Confidentiality enum
- Public URL / signed URL columns
- Multiple link pivot

### Model contracts

- `Document`, `DocumentCategory`: `TenantOwned` + `UsesTenantScope`.
- Mass-assignment: never fill `tenant_id`, `document_number`, storage fields, checksum, mime, size, status via generic update.
- API Resources only; **omit `storage_path`, `storage_disk`, `stored_filename`** from JSON (or expose stored basename only if needed for support — **lock: omit all three from API**).

### Morph map (Laravel)

Register aliases in AppServiceProvider at implementation:

```
contract, meeting, decision, task, employee, organization_unit,
warehouse, asset, custody  // last three reserved
```

## 4. Relationships

```
Tenant 1──* Document
Tenant 1──* DocumentCategory
User (uploaded_by) 1──* Document
DocumentCategory 0..1──* Document
Document 0..1──morph──> Contract|Meeting|Decision|Task|Employee|OrganizationUnit|…
```

Host models may add `morphMany(Document::class, 'linkable')` at implementation (no persisted arrays on hosts).

## 5. Config (future)

`config/documents.php`:

- `number_prefix` = `DOC-`
- `number_pad` = 6
- `max_size_bytes` = 20971520
- `allowed_extensions` / `mime_map`
- `disk` = private disk name

## 6. Migration notes

- Do **not** run in Sprint 013 specification.
- Depends on: tenants, users, and optionally existing entity tables for FK-less morph (no DB FK to morph targets).
- Category FK is real; morph is application-enforced same-tenant existence.
