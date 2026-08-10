# Documents — Permissions

> **Status:** Specified (Sprint 013) — **not implemented** (named for catalog; seed at implementation)  
> **Last updated:** 2026-08-10

Capabilities use `module.action`. Policies never check role names. Frontend gates are UX-only.

## Catalog (final MVP)

| Permission | Meaning |
|---|---|
| `documents.view` | List/view Document metadata (center + details + entity widgets) |
| `documents.upload` | Upload new Document (and optional initial link) |
| `documents.download` | Download file bytes (audited) |
| `documents.update` | Update title/description/category; change/clear link |
| `documents.archive` | Archive and restore |
| `documents.delete` | Hard-delete metadata + physical file |
| `documents.manage_categories` | CRUD/deactivate document categories |

**Supersedes stub:** archive is **not** folded into `documents.delete`.  
**Host permission:** do **not** seed reserved `contracts.attach_documents` — use `documents.upload` + link.

### Action → permission map

| API / UX action | Permission |
|---|---|
| GET list / show / entity widget | `documents.view` |
| POST upload | `documents.upload` |
| GET download | `documents.download` |
| PATCH metadata / link | `documents.update` |
| POST archive / restore | `documents.archive` |
| DELETE hard | `documents.delete` |
| Category endpoints | `documents.manage_categories` (+ `documents.view` to pick categories on forms) |

## Policy: `DocumentPolicy` / `DocumentCategoryPolicy`

| Ability | Rule |
|---|---|
| `viewAny` / `view` | `documents.view` + same tenant |
| `upload` / `create` | `documents.upload` |
| `download` | `documents.download` + same tenant |
| `update` | `documents.update` + same tenant |
| `archive` / `restore` | `documents.archive` + same tenant |
| `delete` | `documents.delete` + same tenant |
| Category CRUD | `documents.manage_categories` |

No Task-assignee self-service path for Documents.

## Default system role template grants

| Role template | view | upload | download | update | archive | delete | manage_categories |
|---|---|---|---|---|---|---|---|
| Tenant Owner | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| General Manager | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Department Manager | ✓ | ✓ | ✓ | ✓ | ✓ | — | ✓ |
| Supervisor | ✓ | — | ✓ | — | — | — | — |
| Employee (base) | ✓ | — | ✓ | — | — | — | — |
| Auditor | ✓ | — | ✓ | — | — | — | — |

Owner receives all via `*` catalog. Do not wipe custom roles when seeding templates.
