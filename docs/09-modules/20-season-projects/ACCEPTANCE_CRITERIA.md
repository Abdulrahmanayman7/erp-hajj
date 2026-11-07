# Season Projects — Acceptance Criteria & Gates

> **Status:** FUNCTIONALLY_ACCEPTED / TECHNICAL_VALIDATION_DEFERRED (2026-11-07)
> Does **not** claim MERGE READY, DEPLOY READY, or MySQL 8 validation PASS.

## A. Part 0 (documentation)

- [x] D01–D06 reflected immutable
- [x] Approved E-Qs / C-Q01 / F-Q01–F-Q04 incorporated
- [x] MODULE_TEMPLATE package present
- [x] Implementation Parts 1–6 documented with gates
- [x] Owner design approval (Part 0) granted prior to coding

## B. Functional acceptance (Parts 1–6)

| Part | Owner status |
|---|---|
| 0 Documentation | Approved |
| 1 Schema/models | Implemented; MySQL 8 **DEFERRED** |
| 2 Project API | Functionally accepted |
| 3 Phases/tasks/delay | Functionally accepted |
| 4 Budget/PO | Functionally accepted (F-Q01–03) |
| 5 Frontend | Functionally accepted |
| 6 Hardening | Functionally accepted; **F-Q04=A** recorded |

## C. Technical / release gates (still open)

- [ ] MySQL 8 dialect validation (`phpunit.mysql.xml` / `erp_hajj_testing`) — **NOT RUN / DEFERRED**
- [ ] S05 PO/GRN MySQL-dependent suites — **SKIPPED / NOT RUN** in S06 Part 6
- [ ] Browser E2E — **NOT RUN**
- [ ] Production scheduler activation for `tasks:recompute-delay` — **NOT DEPLOYED** (app schedule registered only)
- [ ] Soft-delete restore policy — unresolved (E-Q14 restore)
- [ ] Project reopen (**E-Q02**) — deferred / not implemented
- [ ] Financial adjustment workflow after posted GRN — **out of S06** (F-Q04 permanent for normal unlink)

## D. F-Q04 acceptance

- [x] Unlink with any posted GRN → `422 SEASON_PROJECT_PO_UNLINK_HAS_POSTED_RECEIPTS`
- [x] No role bypass (`link_po` / `tenant_owner` included)
- [x] Automated regression in `SeasonProjectPart6HardeningTest`
- [x] Documented in BUSINESS_RULES / API / PERMISSIONS / TEST_PLAN

## E. Explicit non-claims

S06 is **functionally accepted** with **technical validation deferred**.
It is **not** merge-ready, deploy-ready, or production-certified.
