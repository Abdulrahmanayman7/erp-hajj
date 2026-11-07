# S06 Part 3 — Implementation Evidence

> **Status:** `IMPLEMENTED — PENDING OWNER REVIEW`
> **Date:** 2026-11-07
> **Worktree:** `D:\erp-hajj-s06-season-projects` @ `feature/s06-season-projects` (`e71d7d9` + local Part 0–3)
> **Does not:** claim Owner acceptance, Part 4 authorization, commit/push/merge/deploy

## Owner decisions incorporated

| Decision | Implementation |
|---|---|
| **E-Q03 = A** | Phases are informational; delete sets `tasks.phase_id = NULL`; no auto-complete/cancel |
| **E-Q12 = A** | Reuse `tasks.*` + `SeasonProjectTaskGuard` / TaskPolicy freeze — no new task permission |
| **C-Q01 = A** | `CancelSeasonProject` rejects with `SEASON_PROJECT_CANCEL_HAS_OPEN_TASKS` (409) while open tasks exist |
| **E-Q10 = A** | `CompleteSeasonProject` enabled; open tasks remain; mutations frozen except complete/cancel |
| **E-Q05 = A** | `TaskDelaySynchronizer` + `tasks:recompute-delay`; projection `none`/`delayed` |

## Scope delivered

- Phase API: `GET/POST /season-projects/{id}/phases`, `PATCH/DELETE .../phases/{phase}`
- Task create/update/list accept `project_id` / `phase_id` / season filters; legacy NULL project unchanged
- Complete endpoint unblocked after freeze enforcement
- Cancel guard concurrency: project + open-task row locks
- Progress rollup excluding cancelled tasks
- No Part 4 budget/PO APIs; no frontend; no restore/reopen

## Tests executed

| Suite | DB | Result |
|---|---|---|
| `tests/Feature/SeasonProjects` (Part1+2+3) | sqlite `:memory:` | **34 passed**, 1 skipped (Part1 MySQL rollback) |
| `tests/Feature/Tasks/TaskTest.php` | sqlite `:memory:` | **15 passed** |
| Combined | sqlite `:memory:` | **49 passed**, **1 skipped**, **0 failed** |
| MySQL 8 | — | **NOT RUN / DEFERRED** |

## Remaining deferred

E-Q02, E-Q04, E-Q07, E-Q08, E-Q09, E-Q13, soft-delete restore, Part 4 finance, Part 1 MySQL validation debt.
