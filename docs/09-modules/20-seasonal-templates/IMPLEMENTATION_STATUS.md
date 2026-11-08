# S08 — Implementation Status

> **Status:** `OWNER_ACCEPTED / NOT_MERGED / NOT_DEPLOYED` (D11–D20: R6 + contract editor + Controls A/B/C)  
> **Last updated:** 2026-11-08  
> **Branch:** `feature/s08-seasonal-templates` @ `707ca33`  
> **Verified MySQL implementation SHA:** `e0743b3`  
> **Coding authorization:** Owner-granted (D01–D10 + D11–D20); see [OWNER_DECISIONS_D11_D20.md](OWNER_DECISIONS_D11_D20.md)  
> **Owner Accepted:** **YES** — [S08-OWNER-ACCEPTANCE-2026-11-08.md](../../10-decisions/S08-OWNER-ACCEPTANCE-2026-11-08.md) (2026-11-08)  
> **Merged / deploy:** **NOT** granted

## Status vocabulary

| Label | S08 now |
|---|---|
| Implemented | Yes (BE + FE + migrations + tests on feature branch) |
| Merged to develop | **No** |
| Tested | Yes — SQLite Pest + Vitest + **MySQL 8.0.46 CI** @ `e0743b3` |
| Technically validated | Yes for S08 MySQL migrations/FK/JSON + SeasonalTemplates + Control C on CI |
| Owner Accepted | **Yes** (2026-11-08) |
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

## Test evidence

| Suite | Result |
|---|---|
| MySQL 8 SeasonalTemplates (CI @ `e0743b3`) | **85 passed** — run [42437115976](https://github.com/Abdulrahmanayman7/erp-hajj/actions/runs/42437115976) |
| MySQL 8 Control C `C17\|C18` | **2 passed** |
| Pest `--filter=SeasonalTemplates` (local SQLite) | **85 passed** |
| Vitest seasonal-templates + navigation | **115 passed** |
| Frontend production build | **PASS** |
| Browser UAT-09 / UAT-10 | **PASS** |
| UAT-04 / UAT-08 | Owner-accepted Pest/API/CLI exceptions |

## DoD after Owner acceptance (remaining)

- [x] Owner review of feature branch
- [x] MySQL CI for migrations `320002` + `320003`
- [x] Explicit Owner acceptance document
- [ ] Separate merge authorization (+ PR open)
- [ ] Separate deploy authorization
