# S08 — Implementation Status

> **Status:** `IMPLEMENTED ON FEATURE BRANCH` (full-scope expansion, Option A)  
> **Last updated:** 2026-11-08  
> **Branch:** `feature/s08-seasonal-templates`  
> **Coding authorization:** Owner-granted for implementation (supersedes Part 0 D10 for development); expansion decisions in [OWNER_DECISIONS_EXPANSION.md](OWNER_DECISIONS_EXPANSION.md)  
> **Owner Accepted / merge / deploy:** **NOT** granted — wait for separate Owner acts

## Status vocabulary

| Label | S08 now |
|---|---|
| Implemented | Yes (BE + FE + migrations + tests on feature branch) |
| Merged to develop | **No** |
| Tested | Yes — Pest `--filter=SeasonalTemplates` 43/43; Vitest `src/modules/seasonal-templates` 57/57; vue-tsc 0 errors |
| Owner Accepted | **No** |

## Matrix version

`SafeCopyAllowlist::MATRIX_VERSION = 2026-11-08.1`

### APPROVED kinds (implemented) — nine

| Kind | Fields | Source | Notes |
|---|---|---|---|
| `season_programs` | code, name, description, is_active | live / snapshot | Dedup by code |
| `season_pilgrim_groups` | code, name, notes | live / snapshot | Empty groups only |
| `transport_entitlements` | program_code, vehicle_class, service_level, notes | live / snapshot | Program by code |
| `transport_season_vehicles` | plate_number, operational_capacity, service_level, is_active, notes | live / snapshot | Vehicle by plate; **no** staff FKs; missing vehicle → conflict |
| `accommodation_season_facilities` | facility_code, audience, is_active | live / snapshot | Facility by code; missing → conflict |
| `contract_definitions` | code, title, contract_category_id, counterparty_kind, notes, currency, start_date, end_date | **snapshot only** | `CreateContract` draft; placeholder counterparty; no value/refs/attachments |
| `season_projects` | code, title, description | live / snapshot | `CreateSeasonProject` draft; no budget/owner/progress |
| `season_project_phases` | code, project_code, title, sort_order, starts_on, ends_on | live / snapshot | `CreateSeasonProjectPhase` planned; project must exist in target or copy |
| `task_definitions` | code, project_code, title, description, notes, priority, start_date, due_date, organization_unit_id | live / snapshot | `CreateTask` draft, linked via project only; no assignee |

All other kinds remain **NOT AUTHORIZED FOR COPYING** (deny-by-default). Full matrix: [SAFE_COPY_ENTITY_MATRIX.md](SAFE_COPY_ENTITY_MATRIX.md).

## Expansion delivered

| Decision | Delivered |
|---|---|
| X01 independent snapshots | `season_template_items.snapshot_json`; snapshots supplied or captured from a season (`capture_season_id`); fingerprinted into `content_hash`; `source_season_id` optional for `from_template` when every selected kind has a snapshot (`SEASON_COPY_SOURCE_REQUIRED` otherwise); editing a draft keeps stored snapshots of kept kinds |
| X02 contract definitions | Snapshot-only kind through `CreateContract` (draft), idempotent via provenance `natural_key` |
| X03 projects / phases | Through `CreateSeasonProject` / `CreateSeasonProjectPhase` |
| X04 task definitions | Through `CreateTask` (draft batch, no scheduler) |
| X05 date remap | `season_start_delta`; before/after in `summary.date_transforms`; `DATE_ANCHOR_MISSING` exclusion; invalid remapped range rejected |
| X06 reminder rules | **DISABLED — not implemented** (see blockers) |

Additional guards: per-kind module permission (`contracts.create`, `season_projects.create`, `season_projects.manage_phases`, `tasks.create`) on top of `season_copy.execute`; conflict `TARGET_LOCKED` when a target row left its draft state.

## Migrations

- `2026_11_07_320001_create_seasonal_templates_tables.php`
- `2026_11_08_320002_s08_expand_templates_snapshots_and_optional_source.php` — `snapshot_json`, provenance `natural_key`, nullable `source_season_id` (composite FKs recreated on MySQL/MariaDB)

## Known limitations

1. **Reminder rules (R6 / X06) are DISABLED**: no `reminder_rules` schema exists and the notifications CMS is a module non-goal. An Owner-approved schema is required before any adapter.
2. Contract definitions cannot be captured from live contracts (snapshot-only by decision); the UI offers no row editor for them, so such templates are authored through the API. The template form therefore hides the kind on create and keeps it selectable on edit so a stored snapshot is preserved.
3. Shared masters never auto-created.
4. MySQL-specific suite and the MySQL FK recreate path of migration `320002` were not run in this delivery (SQLite Pest used) — verify on MySQL CI before merge.
5. No browser UAT pack yet.
6. Role labels “Tenant Administrator” / “Operations Manager” mapped as: `general_manager` gets all five verbs; `supervisor` gets `season_templates.view` only; no new `operations_manager` template code added.

## DoD for Owner acceptance (remaining)

- [ ] Owner review of feature branch
- [ ] MySQL CI / UAT (migration `320002`)
- [ ] Owner decision on reminder-rule schema (X06) if wanted
- [ ] Explicit Owner acceptance document
- [ ] Separate merge + deploy authorizations
