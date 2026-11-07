# Module 20 — Seasonal Templates & Safe Copy (S08)

> **Status:** `IMPLEMENTED ON FEATURE BRANCH` (2026-11-07) — see [IMPLEMENTATION_STATUS.md](IMPLEMENTATION_STATUS.md)  
> **Phase:** Phase 2 / Workflow **S08**  
> **Product scope:** [ADR-0020](../../10-decisions/ADR-0020-FULL-PHASE2-OPERATIONAL-SCOPE.md) §S08  
> **Branch:** `feature/s08-seasonal-templates` (from `develop` @ `a21e4ca`)  
> **Merge / deploy / Owner Accepted:** **NOT AUTHORIZED** — separate Owner acts required

## Purpose

Enable **safe reuse** of approved seasonal templates and **safe copy** of permitted reference/planning data into a new **draft** season — without copying pilgrim PII or operational transactional history.

## Binding decisions

All **10** Owner decisions (S08-D01…D10) — [OWNER_DECISIONS.md](OWNER_DECISIONS.md).

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
| [IMPLEMENTATION_STATUS.md](IMPLEMENTATION_STATUS.md) | Feature-branch implementation status (not Owner Accepted) |

## Code placement (implemented)

| Layer | Path |
|---|---|
| Backend | `backend/app/Modules/SeasonalTemplates/` |
| Frontend | `frontend/src/modules/seasonal-templates/` |
| Migration | `backend/database/migrations/2026_11_07_320001_create_seasonal_templates_tables.php` |
| Allowlist | `SafeCopyAllowlist` MATRIX_VERSION `2026-11-07.1` |
| API | `/api/v1/season-templates`, `/api/v1/season-copy/*` |
| Status | [IMPLEMENTATION_STATUS.md](IMPLEMENTATION_STATUS.md) |

## Out of scope (unchanged)

- Full seasonal cloning.
- Pilgrim / identity copy.
- Risks & complaints copy.
- Inventory movements / execution history.
- Speculative S06 project-template integration (D08).
- Auto-creating shared masters (vehicles/facilities).
- Staff/driver/supplier contact duplication.
