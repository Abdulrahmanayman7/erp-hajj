# S08 — Owner Decisions S08-D11 … S08-D20 (Binding)

> **Status:** **APPROVED / BINDING** by project Owner (2026-11-08)  
> **Coding authorization:** GRANTED on `feature/s08-seasonal-templates`  
> **Merge / push / deploy / Owner Accepted:** **NOT** granted  
> **Baseline:** `e0c7933` (matrix `2026-11-08.1`) → implementation bumps matrix to `2026-11-08.2`  
> **Design source:** S08 R6 & Contract Editor Design Audit (recommendations adopted)

## Summary

| ID | Title | Binding outcome |
|---|---|---|
| **S08-D11** | Module ownership | `season_reminder_rules` in SeasonalTemplates domain; delivery via existing Notifications |
| **S08-D12** | Data model & lifecycle | Tenant+season scoped; `draft/active/paused/archived`; archived terminal; no fire ledger v1 |
| **S08-D13** | Trigger semantics | `absolute_date` + `offset_from_season_start`; date-only; tenant TZ (`Asia/Riyadh` fallback); no season-end offsets |
| **S08-D14** | Recipients | `permission_holders` only; catalog-validated allowlist; never store/copy employee/user IDs |
| **S08-D15** | Notifications integration | One new type + `notifications:scan-season-reminders`; no notify on template/preview/execute |
| **S08-D16** | Safe-copy kind | `season_reminder_rules`; force target `draft`; MATRIX bump |
| **S08-D17** | Contract counterparty | Keep placeholder `من القالب`; block binding transitions until real counterparty |
| **S08-D18** | Contract editor fields | X02 fields only (+ local template `code`) |
| **S08-D19** | Editor UX & API | Enable kind on create; `items[].snapshot`; backend authoritative |
| **S08-D20** | Permissions | `season_reminders.view` / `season_reminders.manage`; copy execute + manage; contracts.create for contracts |

## Additional Owner-approved controls

| Control | Rule |
|---|---|
| **A** | Copied rules start `draft`; scanner only when tenant eligible, season **active** (never draft), rule `active`, trigger matches tenant-local date, recipient eligible |
| **B** | Dedupe identity stable across rule title/body/permission/trigger edits same day (rule id + recipient + date bucket); no silent fire ledger unless dispatcher insufficient |
| **C** | Placeholder counterparty blocks transitions out of draft into review/approved/operational paths until replaced with validated real name |

## Explicit non-approvals

- Push, PR, merge to `develop`, deploy, production migrations, Owner Accepted / S08 COMPLETE.
