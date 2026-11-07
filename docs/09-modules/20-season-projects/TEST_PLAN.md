# Season Projects — Test Plan

> **Status:** FUNCTIONALLY_ACCEPTED / TECHNICAL_VALIDATION_DEFERRED (2026-11-07)
> Aligns with `docs/07-testing/TESTING_STRATEGY.md`.
> Historical evidence counts are preserved in PART1–PART6 evidence files (do not re-sum as a new run).

## 1. Backend feature matrix (per endpoint)

| Case | Expect |
|---|---|
| Success happy paths | 200/201 |
| Validation | 422 |
| Unauthenticated | 401 |
| Unauthorized | 403 |
| Cross-tenant id | **404** |
| Cross-season id | **404** |

Mandatory scenarios (approved rules):

- Activate without owner → 422
- Complete with open tasks → 200; tasks **not** auto-cancelled; non-allowlist mutations → 422 (**E-Q10**)
- Soft-delete draft → OK; soft-delete active/completed/cancelled → 422 (**E-Q14**)
- Soft-delete draft with tasks or PO links → 422
- Link issued PO twice / two projects → 422 unique (**E-Q06**)
- Unlinked POs still usable in S05
- MVP task create without project unchanged
- Delay: past-due open → `delayed`; complete → `none`; no manual override path (**E-Q05**)
- Concurrent activate / concurrent PO link → one wins
- **F-Q04:** unlink after **any** posted GRN → **422** `SEASON_PROJECT_PO_UNLINK_HAS_POSTED_RECEIPTS` (no role bypass; PO/link rows preserved on reject)
- Cancel with open project tasks → blocked (**C-Q01**)
- Currency mismatch on link → 422 (**E-Q09**)
- Same-tenant cross-season PO link allowed (**E-Q04**)

Deferred / not claimed in S06 automated suite:

- Soft-delete restore (policy unresolved)
- Project reopen (**E-Q02**)
- Financial adjustment workflow after posted GRN (future; out of S06)
- MySQL 8 dialect suite (`phpunit.mysql.xml`) — **NOT RUN / DEFERRED**
- Browser E2E — **NOT RUN**

## 2. Policy / audit / unit

Deny-by-default for `season_projects.*`; audit on activate/complete/cancel/soft-delete/PO link/unlink/freeze/delay change; unit/feature coverage for delay projection + number generator + unique link + budget calculator.

## 3. Frontend

Vitest: activate without owner disabled; completion confirm copy matches E-Q10; draft-only delete; MVP Tasks pages regression; season-projects validation. `tsc` clean (evidence in PART5).

## 4. Concurrency / CI / Browser UAT

Mirror S05 workers for activate + PO link where present. Browser path planned but **NOT RUN** in Part 6: draft → owner → activate → tasks → PO link → complete with open task → freeze → illegal edit → soft-delete separate draft.

## 5. F-Q04 regression (Part 6)

| Case | File | Expect |
|---|---|---|
| Unlink PO with no posted GRN | `SeasonProjectPart6HardeningTest` | 200; association removed |
| Unlink PO with any posted GRN | same | 422 `SEASON_PROJECT_PO_UNLINK_HAS_POSTED_RECEIPTS`; PO + link preserved |
| Role / permission | domain after auth | `link_po` / `tenant_owner` cannot bypass |

## 6. Evidence pointers (do not double-count)

| Part | Evidence | Reported counts (historical) |
|---|---|---|
| 1 | `PART1_IMPLEMENTATION_EVIDENCE.md` | Part1 schema 9 passed / 1 skipped |
| 2 | `PART2_IMPLEMENTATION_EVIDENCE.md` | SeasonProjects 24 passed / 1 skipped |
| 3 | `PART3_IMPLEMENTATION_EVIDENCE.md` | SeasonProjects 34 + Tasks incremental |
| 4 | `PART4_IMPLEMENTATION_EVIDENCE.md` | SeasonProjects+Tasks 55 / 1 skipped |
| 5 | `PART5_IMPLEMENTATION_EVIDENCE.md` | Vitest focused + FE type-check/build |
| 6 | `PART6_HARDENING_EVIDENCE.md` | SeasonProjects+Tasks **70** passed / **1** skipped; Part6 **15** passed; FE Vitest **575**; S05 PO/GRN filter **17 skipped** (MySQL) |
