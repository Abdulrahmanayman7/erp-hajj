# Module 22 — Operational KPIs (S09)

> **Status:** `S09-D01…D16 = OWNER_APPROVED` · **Phase 1 implemented** on `feature/s09-operational-kpis` (uncommitted · not merged · not deployed)  
> **Last updated:** 2026-11-08  
> **Phase:** Phase 2 / Workflow **S09**  
> **D16:** `OWNER_APPROVED` (C) — Phase 1 S09 core; GPS/S10 **GATED**  
> **Implemented:** API + 24 calculators + RBAC + Vue `/app/operational-kpis` + Pest 65 + Vitest display tests  
> **Not implemented:** GPS · S10 · KPI result cache · auto-merge/deploy  
> **Branch:** `feature/s09-operational-kpis` @ `origin/develop` (`3a18b16`) + local Phase 1 work; `stash@{0}` preserved  

## Purpose

Define **season-aware, permission-aware operational KPIs** for Transport, Accommodation, Procurement, Seasonal Projects, and Risks & Complaints — building **on** [ADR-0014](../../10-decisions/ADR-0014-PERMISSION-AWARE-DASHBOARD-AGGREGATION.md), not a parallel insecure dashboard.

## Package index

| Doc | Role |
|---|---|
| [S09_DISCOVERY_AUDIT.md](S09_DISCOVERY_AUDIT.md) | Existing Dashboard + domain evidence |
| [S09_DATA_SOURCE_MAP.md](S09_DATA_SOURCE_MAP.md) | Tables / columns / season / tenant paths |
| [S09_KPI_CATALOG.md](S09_KPI_CATALOG.md) | Candidate KPI list (v1 scope = D01 24) |
| [S09_FORMULA_MATRIX.md](S09_FORMULA_MATRIX.md) | Structured formula specs + empty/undefined notes |
| [S09_ARCHITECTURE_OPTIONS.md](S09_ARCHITECTURE_OPTIONS.md) | API options + D07 calculator architecture notes |
| [S09_RBAC_TENANT_SEASON_DESIGN.md](S09_RBAC_TENANT_SEASON_DESIGN.md) | Isolation + permissions + season rules |
| [S09_UI_WIREFRAME.md](S09_UI_WIREFRAME.md) | Arabic RTL KPI presentation (textual) |
| [S09_TEST_ACCEPTANCE_MATRIX.md](S09_TEST_ACCEPTANCE_MATRIX.md) | Test / UAT design |
| [S09_OWNER_DECISIONS.md](S09_OWNER_DECISIONS.md) | Decision register S09-D01…D16 |
| [S09_IMPLEMENTATION_PLAN.md](S09_IMPLEMENTATION_PLAN.md) | Post-approval milestones (not started) |
| [S09_PART0B_OWNER_DECISION_REVIEW.md](S09_PART0B_OWNER_DECISION_REVIEW.md) | Part 0B reconciliation |
| [S09_D02_DETAILED_FORMULA_REVIEW.md](S09_D02_DETAILED_FORMULA_REVIEW.md) | D02 per-KPI formula extraction |
| [S09_D02_FORMULA_EVIDENCE_MATRIX.md](S09_D02_FORMULA_EVIDENCE_MATRIX.md) | Evidence matrix (24) |
| [S09_D02_FORMULA_EXAMPLES.md](S09_D02_FORMULA_EXAMPLES.md) | Illustrative worked examples |
| [S09_D02_OPEN_QUESTIONS.md](S09_D02_OPEN_QUESTIONS.md) | Q1–Q18 with Owner answers |
| [S09_D02_OWNER_APPROVAL_2026-11-08.md](S09_D02_OWNER_APPROVAL_2026-11-08.md) | Formal D02 Owner approval |
| [S09_D03_OWNER_APPROVAL_2026-11-08.md](S09_D03_OWNER_APPROVAL_2026-11-08.md) | Formal D03 Owner approval |
| [S09_D04_OWNER_APPROVAL_2026-11-08.md](S09_D04_OWNER_APPROVAL_2026-11-08.md) | Formal D04 Owner approval |
| [S09_D05_OWNER_APPROVAL_2026-11-08.md](S09_D05_OWNER_APPROVAL_2026-11-08.md) | Formal D05 Owner approval |
| [S09_D05_OWNER_CLARIFICATIONS_2026-11-08.md](S09_D05_OWNER_CLARIFICATIONS_2026-11-08.md) | D05 C1–C3 binding clarifications |
| [S09_D06_OWNER_APPROVAL_2026-11-08.md](S09_D06_OWNER_APPROVAL_2026-11-08.md) | Formal D06 Owner approval |
| [S09_D07_OWNER_APPROVAL_2026-11-08.md](S09_D07_OWNER_APPROVAL_2026-11-08.md) | Formal D07 Owner approval (calculators + orchestrator) |
| [S09_D08_OWNER_APPROVAL_2026-11-08.md](S09_D08_OWNER_APPROVAL_2026-11-08.md) | Formal D08 Owner approval (RBAC / omit / order) |
| [S09_D09_OWNER_APPROVAL_2026-11-08.md](S09_D09_OWNER_APPROVAL_2026-11-08.md) | Formal D09 Owner approval (no KPI cache / budgets) |
| [S09_D10_D15_CONSOLIDATED_OWNER_REVIEW_2026-11-08.md](S09_D10_D15_CONSOLIDATED_OWNER_REVIEW_2026-11-08.md) | Fast-track D10–D15 design package (historical recommendations) |
| [S09_D10_D15_OWNER_APPROVAL_2026-11-08.md](S09_D10_D15_OWNER_APPROVAL_2026-11-08.md) | Formal D10–D15 Owner approval |
| [S09_D14_PRECISION_UAT_MATRIX.md](S09_D14_PRECISION_UAT_MATRIX.md) | D14 precision + UAT samples |
| [S09_GPS_S10_SCOPE_IMPACT_2026-11-08.md](S09_GPS_S10_SCOPE_IMPACT_2026-11-08.md) | GPS/S10 gaps under D10=B / D15=B |
| [S09_VALIDATION_DEBT_RISK_REGISTER.md](S09_VALIDATION_DEBT_RISK_REGISTER.md) | S05/S06/S07 debt under D13=B |
| [S09_D16_IMPLEMENTATION_AUTHORIZATION_PROPOSAL_2026-11-08.md](S09_D16_IMPLEMENTATION_AUTHORIZATION_PROPOSAL_2026-11-08.md) | D16 coding authorization proposal (PENDING) |
| [S09_D16_DRAFT_AUTHORIZATION_2026-11-08.md](S09_D16_DRAFT_AUTHORIZATION_2026-11-08.md) | Owner D16 draft + historical + corrected readiness (superseded by the approval below) |
| [S09_D16_OWNER_APPROVAL_2026-11-08.md](S09_D16_OWNER_APPROVAL_2026-11-08.md) | Formal D16 Owner approval — Option C, Phase 1 |
| [S09_PHASE1_IMPLEMENTATION_EVIDENCE_2026-11-08.md](S09_PHASE1_IMPLEMENTATION_EVIDENCE_2026-11-08.md) | Phase 1 implementation evidence |

## Status vocabulary (this package)

| Label | Meaning |
|---|---|
| EXISTING | Present in codebase today |
| OWNER_APPROVED | Explicit Owner act |
| IMPLEMENTED_PHASE1 | S09 core API + Vue + tests on feature branch; uncommitted/unmerged |
| GATED | GPS / S10 — needs separate Owner scope act |

## Explicit non-claims

- No migrations, commits, push, PR, merge, or deploy under D16 Phase 1 implementation.  
- D16 Phase 1 ≠ authorization for S10, GPS, production deploy, formula changes, or KPI result caching.  
- MVP Dashboard counts are **not** Phase 2 operational KPIs.  
