# S08 — Database Design Proposal

> **Status:** `IMPLEMENTED` — migrations `2026_11_07_320001_create_seasonal_templates_tables.php` and `2026_11_08_320002_s08_expand_templates_snapshots_and_optional_source.php` on feature branch  
> **Last updated:** 2026-11-08  
> Do **not** run against production/staging without separate Owner authorization.  
> Principles: [DATABASE_PRINCIPLES.md](../../03-database/DATABASE_PRINCIPLES.md), ADR-0003.

## 1. Classification

All tables **tenant-owned** (`tenant_id` NOT NULL, immutable, RESTRICT to `tenants`). No platform tables. Models will implement `TenantOwned` + `UsesTenantScope` when coded.

## 2. Proposed tables (migration order conceptual)

1. `season_template_number_sequences`
2. `season_templates`
3. `season_template_items`
4. `season_copy_preview_number_sequences` (optional if using human numbers)
5. `season_copy_previews`
6. `season_copy_preview_lines`
7. `season_copy_run_number_sequences`
8. `season_copy_runs`
9. `season_copy_run_lines`
10. `season_copy_row_provenance` (optional; may live as columns on target domain rows via adapter — prefer provenance table in this module to avoid invasive domain migrations in v1)

Domain target tables are **not** redesigned here; adapters write through domain APIs/actions where possible.

---

### 2.1 `season_template_number_sequences`

| Column | Notes |
|---|---|
| `tenant_id` | PK |
| `next_number` | unsigned bigint |

### 2.2 `season_templates`

| Column | Type / notes |
|---|---|
| `id` | bigint PK |
| `tenant_id` | FK tenants |
| `template_number` | string, unique per tenant (`TPL-######`) |
| `name` | string |
| `description` | text null |
| `status` | `draft` \| `approved` \| `archived` |
| `matrix_version` | string/int — pinned allowlist version at approval |
| `created_by` | users FK |
| `approved_at` / `approved_by` | null until approved |
| `archived_at` | null |
| timestamps | |
| soft deletes | optional; prefer status=archived |

Indexes: `(tenant_id, status)`, UNIQUE `(tenant_id, template_number)`, UNIQUE `(tenant_id, id)`.

### 2.3 `season_template_items`

| Column | Notes |
|---|---|
| `id`, `tenant_id`, `season_template_id` | composite FK same tenant |
| `entity_kind` | string — must be matrix-approved |
| `sort_order` | int |
| `selection_json` | JSON filters (e.g. include codes) — no PII |
| `snapshot_json` | optional frozen non-PII payload for offline-from-source independence |
| UNIQUE | `(season_template_id, entity_kind, sort_order)` or natural key per kind |

### 2.4 `season_copy_previews`

| Column | Notes |
|---|---|
| `id`, `tenant_id` | |
| `preview_token` | uuid unique per tenant (or global uuid) |
| `mode` | `from_season` \| `from_template` |
| `source_season_id` | null if template mode |
| `source_template_id` | null if season mode |
| `target_season_id` | must be draft at create & execute |
| `matrix_version` | |
| `content_hash` | sha-256 of canonical plan JSON |
| `source_fingerprint` | adapter aggregate fingerprint |
| `target_fingerprint` | |
| `date_mapping_json` | chosen DT rules + anchors |
| `selection_json` | requested kinds/filters |
| `status` | `valid` \| `invalidated` \| `consumed` \| `expired` |
| `expires_at` | |
| `created_by` | |
| `invalidated_reason` | null |
| timestamps | |

UNIQUE `(tenant_id, preview_token)`.

### 2.5 `season_copy_preview_lines`

| Column | Notes |
|---|---|
| `preview_id`, `tenant_id` | |
| `entity_kind` | |
| `action` | `add` \| `change` \| `exclude` \| `conflict` |
| `natural_key` | string |
| `summary_json` | redacted field diffs / reasons — **no PII** |
| `conflict_code` | null or code |
| `proposed_resolution` | null until user sets |

### 2.6 `season_copy_runs`

| Column | Notes |
|---|---|
| `id`, `tenant_id` | |
| `run_number` | `CPY-######` unique per tenant |
| `preview_id` | FK |
| `mode`, source/target FKs | denormalized for history |
| `status` | `succeeded` \| `failed` |
| `error_code` / `error_message` | stable + safe message |
| `counts_json` | added/changed/skipped/excluded |
| `correlation_id` | mandatory |
| `started_at` / `finished_at` | server clock |
| `created_by` | |
| **No** copy of source execution timestamps into this beyond run wall-clock |

### 2.7 `season_copy_run_lines`

Per-entity outcomes for auditability (entity_kind, natural_key, result, reason). No PII payloads.

### 2.8 `season_copy_row_provenance` (proposed)

| Column | Notes |
|---|---|
| `tenant_id`, `target_season_id` | |
| `entity_kind`, `target_row_id` | |
| `source_season_id` / `source_template_id` / `source_row_id` | |
| `copy_run_id` | |
| `system_owned` | bool |
| `manual_touched_at` | set when domain update detected / via observer hook **OPEN-Q** |

UNIQUE `(tenant_id, entity_kind, target_row_id)`.

---

### 2.9 Expansion (migration `2026_11_08_320002`, MATRIX `2026-11-08.1`)

| Change | Notes |
|---|---|
| `season_template_items.snapshot_json` | json null — `{anchor, rows}` of allowlisted fields; validated by `SnapshotNormalizer`; part of `content_hash` |
| `season_copy_row_provenance.natural_key` | string(255) null — idempotency key for rows that are not season-scoped (contracts created from `contract_definitions`) |
| `source_season_id` nullable | on `season_copy_previews`, `season_copy_runs`, `season_copy_row_provenance` — set only when a live source season was used. Composite FKs are dropped/recreated around the change on MySQL/MariaDB |

## 3. Lifecycle states

### Templates

`draft` → `approved` → `archived`  
`approved` → `draft` **forbidden** without new Owner rule (propose: clone-to-new-draft instead).

### Previews

`valid` → `consumed` | `invalidated` | `expired`

### Runs

Terminal: `succeeded` | `failed` (attempt always retained).

---

## 4. Constraints & integrity

- Target season FK composite `(tenant_id, target_season_id) → seasons`.
- Source season same.
- Template items cannot reference DENY kinds (enforced in app; optional check constraint on enum table).
- Cascades: deleting template restricted if referenced by runs; prefer RESTRICT + archive.

## 5. Indexes (performance)

- Previews: `(tenant_id, target_season_id, status)`, `(tenant_id, expires_at)`
- Runs: `(tenant_id, created_at)`, `(tenant_id, target_season_id)`
- Provenance: `(tenant_id, target_season_id, entity_kind)`

## 6. Deployment / rollback (future)

Additive migrations only; `down()` drops in reverse. No destructive alters of domain tables in S08 v1 if provenance stays in this module.
