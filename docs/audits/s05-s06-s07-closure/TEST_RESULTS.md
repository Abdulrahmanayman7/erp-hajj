# S05/S06/S07 Closure — Test Results Log

> **Date:** 2026-11-14  
> **Branch:** `feature/s05-s06-s07-closure`  
> **Baseline tip at start:** `384592c` (`origin/develop`)

## Local executions

| Suite | Harness | Result | Notes |
|---|---|---|---|
| S05 Parts 1–6 Pest | SQLite | **33 skipped** | Expected — MySQL gate |
| S05 Parts 1–6 Pest | MariaDB 10.4 `erp_hajj_testing` | **33 passed** (672 assertions) | After `executive_alerts` fix; log `s05-pest-mariadb.txt` |
| S06 SeasonProjects Pest | SQLite | **59 passed / 1 skipped** | `s06-pest-sqlite.txt` |
| S06 SeasonProjects Pest | MariaDB 10.4 | **60 passed** (461 assertions) | `s06-pest-mariadb.txt` — includes fixed Part1 rollback |
| S07 RisksAndComplaints Pest | SQLite | **78 passed** | `s07-pest-sqlite.txt` |
| S07 RisksAndComplaints Pest | MariaDB 10.4 | **78 passed** (524 assertions) | `s07-pest-mariadb.txt` |
| FE Vitest suppliers + season-projects + risks-complaints | Vitest | **114 passed** | |
| FE Vitest RFQ/PO/GRN | Vitest | **10 passed** | |
| ExecutiveAlerts schema regression | SQLite | **PASS** | Dual TIMESTAMP migrate fix |

## CI evidence

### Closure Draft PR #63 (run `43580842495` @ `5553267`) — **SUCCESS**

| Job step | Result |
|---|---|
| Backend (Laravel) / Frontend (Vue) | **success** |
| S04 Suppliers Feature (MySQL; includes S05) | **success** |
| S05 procurement concurrency | **success** |
| **S06 SeasonProjects Feature tests (MySQL)** — new | **success** |
| **S07 RisksAndComplaints Feature tests (MySQL)** — new | **success** |
| S06/S07 number concurrency | **success** |
| S08 / S10 MySQL steps | **success** |

### Prior develop baseline (run `42966539217` @ `de90c5f`)

| Job step | Result |
|---|---|
| S04 Feature + S05 concurrency + S06/S07 number concurrency | **success** |

## Defects fixed this branch

1. `executive_alerts.opened_at` / `last_seen_at` — `useCurrent()` to satisfy MariaDB `NO_ZERO_DATE` with two NOT NULL TIMESTAMPs.
2. CI — add MySQL Feature steps for `tests/Feature/SeasonProjects` and `tests/Feature/RisksAndComplaints`.
3. S06 Part1 rollback test — `--step=5` drifted after later sprints; now rolls back from tip through first S06 migration.
