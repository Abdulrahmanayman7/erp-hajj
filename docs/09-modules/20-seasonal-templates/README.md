# Module 20 — Seasonal Templates & Safe Copy (S08)

> **Status:** `OWNER_ACCEPTED / NOT_MERGED / NOT_DEPLOYED` (MATRIX `2026-11-08.2`, 10 kinds) — see [S08_ACCEPTANCE_STATUS.md](../../00-project/S08_ACCEPTANCE_STATUS.md)  
> **Last updated:** 2026-11-08  
> **Phase:** Phase 2 / Workflow **S08**  
> **Product scope:** [ADR-0020](../../10-decisions/ADR-0020-FULL-PHASE2-OPERATIONAL-SCOPE.md) §S08  
> **Branch:** `feature/s08-seasonal-templates` @ `707ca33`  
> **Owner Acceptance:** [S08-OWNER-ACCEPTANCE-2026-11-08.md](../../10-decisions/S08-OWNER-ACCEPTANCE-2026-11-08.md) (2026-11-08)  
> **Merge / PR / deploy:** **NOT AUTHORIZED** by acceptance — separate Owner acts required

## Purpose

Enable **safe reuse** of approved seasonal templates and **safe copy** of permitted reference/planning data into a new **draft** season — without copying pilgrim PII or operational transactional history.

## Binding decisions

- S08-D01…D10 — [OWNER_DECISIONS.md](OWNER_DECISIONS.md)  
- S08-D11…D20 (R6 + contract editor + Controls A/B/C) — [OWNER_DECISIONS_D11_D20.md](OWNER_DECISIONS_D11_D20.md)

## Package index

| Doc | Role |
|---|---|
| [OWNER_DECISIONS.md](OWNER_DECISIONS.md) | Deliverable 1 — decision register |
| [S08_PART0_REPOSITORY_STATUS_AUDIT.md](S08_PART0_REPOSITORY_STATUS_AUDIT.md) | Deliverable 2 — sprint status audit |
| [SAFE_COPY_ENTITY_MATRIX.md](SAFE_COPY_ENTITY_MATRIX.md) | Deliverable 3 — allow/deny matrix |
| [ARCHITECTURE.md](ARCHITECTURE.md) | Deliverable 4 — module architecture |
| [DATA_MODEL.md](DATA_MODEL.md) | Deliverable 5 — schema proposal |
| [API.md](API.md) | Deliverable 6 — API + tenancy errors |
| [PERMISSIONS.md](PERMISSIONS.md) | Deliverable 6 — RBAC |
| [PREVIEW_AND_CONFLICTS.md](PREVIEW_AND_CONFLICTS.md) | Deliverable 7 |
| [AUDIT_AND_TRANSACTIONS.md](AUDIT_AND_TRANSACTIONS.md) | Deliverable 8 |
| [BUSINESS_RULES.md](BUSINESS_RULES.md) | Core invariants |
| [UI.md](UI.md) | Staff UI proposal |
| [TEST_PLAN.md](TEST_PLAN.md) | Deliverable 9 — tests |
| [ACCEPTANCE_CRITERIA.md](ACCEPTANCE_CRITERIA.md) | Deliverable 9 — ACs |
| [S08_IMPLEMENTATION_PLAN.md](S08_IMPLEMENTATION_PLAN.md) | Deliverable 10 — roadmap |
| [S08_PART0_READINESS_REPORT.md](S08_PART0_READINESS_REPORT.md) | Part 0 closure report |
| [IMPLEMENTATION_STATUS.md](IMPLEMENTATION_STATUS.md) | Feature-branch implementation status |
| [S08_FINAL_VERIFICATION_GATE_REPORT.md](S08_FINAL_VERIFICATION_GATE_REPORT.md) | Verification evidence |
| [S08_MERGE_READINESS_AUDIT.md](S08_MERGE_READINESS_AUDIT.md) | Merge-readiness inspection + PR checklist |
| [S08_PROPOSED_PR.md](S08_PROPOSED_PR.md) | Proposed PR title/body (unpublished) |
| [S08_ACCEPTANCE_STATUS.md](../../00-project/S08_ACCEPTANCE_STATUS.md) | Living Owner Acceptance status |
| [S08-OWNER-ACCEPTANCE-2026-11-08.md](../../10-decisions/S08-OWNER-ACCEPTANCE-2026-11-08.md) | Owner Acceptance decision record |

## Code placement (implemented)

| Layer | Path |
|---|---|
| Backend | `backend/app/Modules/SeasonalTemplates/` |
| Frontend | `frontend/src/modules/seasonal-templates/` |
| Migrations | `320001` templates; `320002` snapshots; `320003` `season_reminder_rules` |
| Allowlist | `SafeCopyAllowlist` MATRIX_VERSION `2026-11-08.2` — **10** kinds (adds `season_reminder_rules`) |
| Expansion | [OWNER_DECISIONS_EXPANSION.md](OWNER_DECISIONS_EXPANSION.md), [OWNER_DECISIONS_D11_D20.md](OWNER_DECISIONS_D11_D20.md) |
| Reminder rules | Implemented as season-scoped config + `notifications:scan-season-reminders` (never copies inbox rows) |
| API | `/api/v1/season-templates`, `/api/v1/season-copy/*`, `/api/v1/seasons/{season}/reminder-rules`, `/api/v1/season-reminder-rules/{rule}` |
| Status | [IMPLEMENTATION_STATUS.md](IMPLEMENTATION_STATUS.md) |

## Out of scope (unchanged)

- Full seasonal cloning.
- Pilgrim / identity copy.
- Risks & complaints copy.
- Inventory movements / execution history.
- Project budgets, PO links, expenditures and project owners (X03 DENY).
- Copying delivered `notifications` rows / fire ledgers.
- Auto-creating shared masters (vehicles/facilities).
- Staff/driver/supplier contact duplication.
