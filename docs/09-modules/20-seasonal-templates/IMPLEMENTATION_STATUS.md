# S08 — Implementation Status

> **Status:** `IMPLEMENTED ON FEATURE BRANCH` (D11–D20: R6 + contract editor + Controls A/B/C)  
> **Last updated:** 2026-11-08  
> **Branch:** `feature/s08-seasonal-templates`  
> **Coding authorization:** Owner-granted (D01–D10 + D11–D20); see [OWNER_DECISIONS_D11_D20.md](OWNER_DECISIONS_D11_D20.md)  
> **Owner Accepted / merge / push / deploy:** **NOT** granted

## Status vocabulary

| Label | S08 now |
|---|---|
| Implemented | Yes (BE + FE + migrations + tests on feature branch) |
| Merged to develop | **No** |
| Tested | Yes — see evidence below (SQLite Pest + Vitest). MySQL 8: **deferred / unverified** |
| Technically validated | Partial (local SQLite + frontend); MySQL FK path of prior `320002` + new `320003` composite FK need MySQL CI |
| Owner Accepted | **No** |
| Deployed | **No** |

## Matrix version

`SafeCopyAllowlist::MATRIX_VERSION = 2026-11-08.2` — **10 APPROVED kinds**

| Kind | Notes |
|---|---|
| `season_programs` … `accommodation_season_facilities` | Unchanged foundation five |
| `contract_definitions` | Snapshot-only; FE Arabic row editor (D18/D19); placeholder counterparty + Control C |
| `season_projects` / `season_project_phases` / `task_definitions` | Unchanged X03/X04 |
| `season_reminder_rules` | **NEW (D11–D16)** — copies as **draft**; scanner delivers later |

Legacy alias `reminder_rules` and `notifications` rows remain **DENIED**.

## D11–D20 delivery

| Decision | Delivered |
|---|---|
| D11 ownership | Rules in SeasonalTemplates; delivery via Notifications |
| D12 model/lifecycle | `season_reminder_rules`; draft/active/paused/archived; no fire ledger |
| D13 triggers | `absolute_date` + `offset_from_season_start`; tenant TZ; date-only |
| D14 recipients | `permission_holders` + allowlisted view permissions only |
| D15 scanner | `NotificationType::SeasonReminderDue` + `notifications:scan-season-reminders` daily |
| D16 safe-copy | Kind + adapter; force draft; MATRIX `2026-11-08.2` |
| D17 counterparty | Placeholder `من القالب`; `TransitionContract` blocks draft→in_review |
| D18–D19 editor | `ContractDefinitionRowsEditor` + `items[].snapshot` |
| D20 RBAC | `season_reminders.view` / `season_reminders.manage` |
| Control A | Scanner requires tenant active + season **active** + rule active |
| Control B | Dedupe on stable `rule.id` + recipient + tenant-local date (no ledger) |
| Control C | Placeholder blocks binding transitions; cancel still allowed |

## Migrations

- `2026_11_07_320001_create_seasonal_templates_tables.php`
- `2026_11_08_320002_s08_expand_templates_snapshots_and_optional_source.php`
- `2026_11_08_320003_create_season_reminder_rules_table.php` — composite FK `(tenant_id, season_id)` → `seasons(tenant_id, id)`

## Test evidence (local)

| Suite | Result |
|---|---|
| Pest `--filter=SeasonalTemplates` | **85 passed** (1220 assertions) — templates + expansion + reminder API/copy + scanner |
| Pest `ContractTest` | **18 passed** (incl. C17/C18 Control C) |
| Pest SeasonProjects (filter) | **92 passed** (2 skipped MySQL dialect) |
| Vitest `src/modules/seasonal-templates` | **60 passed** |
| Vitest `useAppNavigation` | **18 passed** |
| vue-tsc `--noEmit` | 0 errors |
| MySQL 8 | **Not run — deferred** |

## DoD for Owner acceptance (remaining)

- [ ] Owner review of feature branch
- [ ] MySQL CI for migrations `320002` + `320003`
- [ ] Explicit Owner acceptance document
- [ ] Separate merge + deploy authorizations
