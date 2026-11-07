# S06 — UAT Report

> **Date:** 2026-11-07 (F-Q04 Owner decision recorded at documentation closure)
> **Mode:** Automated / isolated Pest (sqlite `:memory:`) + frontend Vitest
> **Browser manual UAT / E2E:** **NOT RUN**
> **Owner functional acceptance:** Parts 2–6 accepted per Owner; does **not** equal merge/deploy readiness

| # | Scenario | Classification | Evidence |
|---|---|---|---|
| 1 | Create draft → assign active employee → activate | **PASS** | Part6 UAT1 |
| 2 | Create phases → associate tasks → delete phase → tasks remain | **PASS** | Part6 UAT2 / Part3 |
| 3 | Complete with open tasks → freeze except complete/cancel | **PASS** | Part6 UAT3 (cancel path; complete needs in_progress) |
| 4 | Cancel with open tasks → 409 → close tasks → cancel OK | **PASS** | Part6 UAT4 |
| 5 | Link eligible PO different season same tenant | **PASS** | Part6 UAT5 / Part4 |
| 6 | Currency mismatch → 422 | **PASS** | Part6 UAT6 |
| 7 | Post partial GRN → actual/outstanding/remaining | **PASS** | Part6 UAT7-8 |
| 8 | Close PO → outstanding zero | **PASS** | Part6 UAT7-8 |
| 9 | Unauthorized budget/PO actions denied | **PASS** | Part6 F-Q03 unauthorized |
| 10 | Change tenant/season → no leakage | **PASS** | Part2 season/cross-tenant tests |
| 11 | Financial unavailable → not zero | **PASS** | Part6 currency-drift unavailable |
| 12 | Delay crosses day → scheduled recompute | **PASS** (command + schedule registration); external cron **NOT RUN** | Part6 delay test |

### F-Q04 unlink-after-posted-GRN

Automated Part 6 case: unlink with posted GRN → **422** `SEASON_PROJECT_PO_UNLINK_HAS_POSTED_RECEIPTS` — **PASS** (sqlite). Owner decision **F-Q04=A** confirms this fail-closed behavior as permanent under normal ops.

### Browser E2E / manual UI click-through

**NOT RUN** — no Playwright/browser session in this Part 6 execution.

### Production scheduler

App registers daily `tasks:recompute-delay`; external cron / `schedule:run` in production — **NOT DEPLOYED / NOT RUN**.
