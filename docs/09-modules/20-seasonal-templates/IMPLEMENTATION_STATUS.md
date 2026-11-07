# S08 — Implementation Status

> **Date:** 2026-11-07  
> **Branch:** `feature/s08-seasonal-templates`  
> **Coding authorization:** Owner-granted for implementation (supersedes Part 0 D10 for development)  
> **Owner Accepted / merge / deploy:** **NOT** granted — wait for separate Owner acts

## Status vocabulary

| Label | S08 now |
|---|---|
| Implemented | Yes (BE + FE + migration + tests on feature branch) |
| Merged to develop | **No** |
| Tested | Yes — Pest SeasonalTemplates 25/25; Vitest seasonal-templates + full FE suite; vue-tsc 0 |
| Owner Accepted | **No** |

## Matrix version

`SafeCopyAllowlist::MATRIX_VERSION = 2026-11-07.1`

### APPROVED kinds (implemented)

| Kind | Fields | Notes |
|---|---|---|
| `season_programs` | code, name, description, is_active | Dedup by code |
| `season_pilgrim_groups` | code, name, notes | Empty groups only |
| `transport_entitlements` | program_code, vehicle_class, service_level, notes | Program by code |
| `transport_season_vehicles` | plate_number, operational_capacity, service_level, is_active, notes | Vehicle by plate; **no** staff FKs; missing vehicle → conflict |
| `accommodation_season_facilities` | facility_code, audience, is_active | Facility by code; missing → conflict |

All other kinds remain **NOT AUTHORIZED FOR COPYING** (deny-by-default).

## Known limitations

1. Template mode still requires `source_season_id` (reads live season rows filtered by template item kinds) — snapshot-only offline templates not implemented.
2. Shared masters never auto-created.
3. No S06 integration (D08).
4. MySQL-specific suite not re-run in this delivery (SQLite Pest used).
5. No browser UAT pack yet.
6. Role labels “Tenant Administrator” / “Operations Manager” mapped as: `general_manager` gets all five verbs; `supervisor` gets `season_templates.view` only; no new `operations_manager` template code added.

## DoD for Owner acceptance (remaining)

- [ ] Owner review of feature branch
- [ ] Optional MySQL CI / UAT
- [ ] Explicit Owner acceptance document
- [ ] Separate merge + deploy authorizations
