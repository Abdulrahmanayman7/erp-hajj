# S06 Part 6 — Hardening Evidence

> **Status:** `HARDENING COMPLETE — FUNCTIONALLY ACCEPTED` (Owner); technical validation deferred
> **F-Q04:** Owner decision **A** recorded 2026-11-07 — see BUSINESS_RULES / S06_FINAL_CLOSURE_REPORT
> **Date:** 2026-11-07
> **Worktree:** `D:\erp-hajj-s06-season-projects` @ `feature/s06-season-projects`
> **Base HEAD (unchanged):** `e71d7d9841d156988fecfc62034b03127d2d70e0`
> **Does not:** commit / push / merge / deploy / MySQL 8 / browser E2E

## Phase 0 — Safety baseline

| Check | Result |
|---|---|
| Worktree path | `D:/erp-hajj-s06-season-projects` |
| Branch | `feature/s06-season-projects` |
| HEAD | `e71d7d9…` (same as prior Part 5 report) |
| Parts 0–5 changes | Preserved (no reset/clean/stash/rebase/switch) |
| Backend Pest DB | `phpunit.xml` → `sqlite` `:memory:` `force=true` |
| MySQL/MariaDB scripts | Not executed (`phpunit.mysql.xml` skipped by policy) |
| Shared MariaDB | Not accessed |

## Phase 1 — F-Q03 permissions

### Decision reconciliation

| Permission | `sensitiveExplicitOnly` | `tenant_owner` template | Operational templates |
|---|---|---|---|
| `season_projects.manage_budget` | **NO** (F-Q03=A) | **YES** via `$all` | **NO** (not listed) |
| `season_projects.link_po` | **NO** (F-Q03=A) | **YES** via `$all` | **NO** |
| `season_projects.complete` | **YES** | **NO** | **NO** |
| `season_projects.cancel` | **YES** | **NO** | **NO** |

**Do not** put `manage_budget`/`link_po` back into `sensitiveExplicitOnly` — that would revoke Owner-approved tenant_owner access.

Backend policy + controller remain authoritative; PO link also authorizes `purchase_orders.view`.

Regression: `SeasonProjectPart6HardeningTest` F-Q03 catalog + unauthorized deny path.

## Phase 2 — Financial integrity

Verified against `SeasonProjectBudgetCalculator` + link/unlink actions:

- Eligible PO statuses only; same-tenant; cross-season allowed; currency 422; unique tenant+PO; `lockForUpdate` + unique catch.
- Actual = posted GRN qty × PO line `unit_price` (`bcmath`); draft GRNs excluded.
- Closed outstanding = 0; cancelled outstanding+actual = 0; remaining = budget − outstanding − actual.
- Unavailable → `available=false` + reason (never fabricated zeros).
- Link/unlink do not mutate PO/GRN rows.

### Fail-closed change (Owner question)

**Unlink after posted GRN** was previously allowed (association delete only). Part 6 now rejects with:

`422 SEASON_PROJECT_PO_UNLINK_HAS_POSTED_RECEIPTS`

until Owner defines budget-release policy when posted receipts exist.

Also: **terminal projects** (completed/cancelled) cannot link new POs.

## Phase 3 — Lifecycle / tasks / concurrency

- Activate requires active same-tenant employee (E-Q11).
- Complete with open tasks; freeze update/create; cancel still allowed (complete requires `in_progress`).
- Cancel → `409 SEASON_PROJECT_CANCEL_HAS_OPEN_TASKS` then allowed after terminal tasks.
- Soft delete draft-only; no restore/reopen routes.
- Lock order: project `lockForUpdate` then link/PO rows. Residual race: multi-node without DB row locks still mitigated by unique constraint on PO link.

## Phase 4 — Delay scheduler

- `tasks:recompute-delay` already registered under `bootstrap/app.php` `withCommands`.
- **Added** `Schedule::command('tasks:recompute-delay')->daily()` in `routes/console.php`.
- External cron / `schedule:run` remains an **ops prerequisite** (not deployed here).
- Idempotent recompute covered by Part6 test.

## Phase 5 — Frontend

- Error map + AR locale for `SEASON_PROJECT_PO_UNLINK_HAS_POSTED_RECEIPTS`.
- Validation unit test updated.
- Financial display remains backend-only; unavailable → `—` not zero.
- Vitest full suite PASS; typecheck PASS; build PASS.

## Phase 6 — Manifest hygiene

| Item | Finding |
|---|---|
| Tracked? | Yes |
| Content vs HEAD | Byte-identical after Part 6 |
| Cause of prior warning | Windows CRLF / `text=auto` on `.webmanifest` during PWA sync |
| Remediation | Added `*.webmanifest text eol=lf` to `.gitattributes`; normalized LF |
| `git diff --check` | No whitespace errors reported for meaningful diffs |

## Test evidence (sqlite)

| Suite | Command | Passed | Failed | Skipped |
|---|---|---|---|---|
| SeasonProjects + Tasks | `php artisan test tests/Feature/SeasonProjects tests/Feature/Tasks` | **70** | 0 | **1** (MySQL rollback) |
| Part6 only | `…SeasonProjectPart6HardeningTest.php` | **15** | 0 | 0 |
| S05 PO/GRN filter | `php artisan test --filter=PurchaseOrder\|GoodsReceipt` | 0 | 0 | **17** (require MySQL dialect — NOT RUN per policy) |
| Frontend type-check | `npm run type-check` | PASS | | |
| Frontend Vitest | `npx vitest run` | **575** | 0 | 0 |
| Frontend build | `npm run build` | PASS | | |
| Pint | `vendor/bin/pint --dirty` | fixed dirty PHP | | |
| MySQL 8 | — | **NOT RUN / DEFERRED** | | |
| Browser E2E | — | **NOT RUN** | | |

## Owner decisions closed at documentation closure

1. **Unlink after posted GRN (F-Q04 = A):** Forbidden through normal project/PO-link operations once any posted GRN exists. Error `SEASON_PROJECT_PO_UNLINK_HAS_POSTED_RECEIPTS`. No role bypass. Future correction requires a separately designed Owner-approved financial adjustment workflow — **not implemented in S06**.
2. Soft-delete draft with PO links remains blocked by dependency guard (unlink/resolve first). Soft-delete **restore** policy remains deferred.

## Readiness (see S06_READINESS_REPORT.md / S06_FINAL_CLOSURE_REPORT.md)

FUNCTIONALLY ACCEPTED: **YES**
TECHNICALLY VALIDATED (MySQL 8): **NO**
MERGE READY: **NO**
DEPLOY READY: **NO**
