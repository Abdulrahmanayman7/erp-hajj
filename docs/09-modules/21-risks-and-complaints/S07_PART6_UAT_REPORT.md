# S07 Part 6 — UAT & Integration Hardening Report

> **Date:** 2026-11-07
> **Worktree:** `D:\erp-hajj-s07-risks-complaints` · `feature/s07-risks-complaints` @ `e71d7d9841d156988fecfc62034b03127d2d70e0`
> **Backend harness:** Pest + SQLite `:memory:` only
> **MySQL 8 staging validation:** **PENDING** (not claimed from SQLite)

## UAT matrix

| # | Scenario | Result | Evidence |
|---|---|---|---|
| 1 | Create → assess → approve → treat → close risk | **PASS** | `RisksComplaintsPart6GapClosureTest` UAT risk lifecycle + Part2 lifecycle |
| 2 | Reject creator self-approval | **PASS** | `RiskApiPart2Test` self-approval forbidden |
| 3 | Create → review → assign → escalate → resolve → close complaint | **PASS** | `ComplaintApiPart3Test` full lifecycle |
| 4 | Reopen complaint with mandatory reason | **PASS** | Part3 reopen reasons + Part6 UAT reopen |
| 5 | Assignee escalate without `complaints.escalate` | **PASS** | Part3 + Part6 dual-path escalate; FE escalate UI test |
| 6 | Cross-tenant / cross-season denial | **PASS** | Part2/Part3 isolation + Part6 UAT 404 |
| 7 | Sensitive permissions explicit-only | **PASS** | Catalog registration + gate tests + FE hide without grant |
| 8 | Correct notification recipients | **PASS** | Part4 recipient tests |
| 9 | Event retry deduplication | **PASS** | Part4 retry identical occurrence |
| 10 | New occurrence → new notification | **PASS** | Part4 re-assign / resolve-reopen-resolve / severity re-entry |
| 11 | Audit timeline | **PASS** | Part6 timeline API + FE panel tests |
| 12 | Optional references + project unavailable | **PASS** | Capabilities endpoint; project reject when S06 absent; FE unavailable field |

## Known gaps

| Gap | Severity | Notes |
|---|---|---|
| SeasonProjects picker when S06 merges | Low | Capability auto-enables when route+table exist; no S06 code in this worktree |
| MySQL 8 dialect / staging UAT | Medium | Required before Hostinger deploy; SQLite results do not certify MySQL 8 |
| Global ROADMAP / Phase 2 Scope Matrix | N/A | Intentionally **not** updated in Part 6 |
| Manual browser UAT on Hostinger | Open | Automated API/UI unit coverage only |

## Quality gates (Part 6) — executed

| Gate | Result |
|---|---|
| S07 Pest (`tests/Feature/RisksAndComplaints`, sqlite `:memory:`) | **73 passed** (505 assertions) |
| Notifications + Audit regression | **30 passed** |
| Frontend Vitest | **112 files / 597 tests passed** |
| `vue-tsc -b` | **PASS** |
| `vite build` | **PASS** |
| Pint (`app/Modules/RisksAndComplaints` + AuditEventMapper) | **PASS** (auto-fixed style) |
| MySQL 8 staging | **PENDING** |
