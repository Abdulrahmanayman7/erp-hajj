# S05 / S06 / S07 — Requirements Evidence Matrix

> **Prepared:** 2026-11-14  
> **Baseline:** `origin/develop` @ `384592c` (post PR #61)  
> **Branch:** `feature/s05-s06-s07-closure`  
> **Rule:** statuses are evidence-only. **Owner acceptance is NOT recorded here.**  
> Legend: **PASS** · **FAIL** · **NOT_RUN** · **BLOCKED** · **PENDING_APPROVAL**

---

## S05 — Procurement lifecycle (AC-S05-*)

| ID | Criterion | Status | Evidence |
|---|---|---|---|
| AC-S05-R01 | RFQ only from approved PR | **PASS** | `SupplierPhaseS05Part1RfqTest` (CI MySQL 8 `tests/Feature/SuppliersAndProcurement` on run `42966539217`; local MariaDB 10.4 after migration fix) |
| AC-S05-R02 | Seasonal/admin season rules | **PASS** | Part1 `AC-S05-R02` case |
| AC-S05-R03 | Publish writes engagement | **PASS** | Part1 publish engagement cases |
| AC-S05-R04 | Engaged PR blocks cancel_approved | **PASS** | Part1 |
| AC-S05-R05 | DG membership lock when engaged | **PASS** | Part1 |
| AC-S05-R06 | Cross-tenant RFQ → 404 | **PASS** | Part1 authz/cross-tenant |
| AC-S05-R07 | Publish/cancel audited | **PASS** | Part1 |
| AC-S05-R08 | Pre-award cancel clears engagement | **PASS** | Part1 |
| AC-S05-O01 | Invite eligibility | **PASS** | `SupplierPhaseS05Part2OffersTest` |
| AC-S05-O02 | Award eligibility re-check | **PASS** | Part2 |
| AC-S05-O03 | Single selected supplier | **PASS** | Part2 |
| AC-S05-O04 | Rationale required | **PASS** | Part2 |
| AC-S05-O05 | Offer submit/withdraw rules | **PASS** | Part2 |
| AC-S05-O06 | Cross-tenant offer → 404 | **PASS** | Part2 |
| AC-S05-O07 | Selection audited | **PASS** | Part2 |
| AC-S05-O08 | No auto-award from evaluations | **PASS** | Part2 (selection is explicit action) |
| AC-S05-P01 | PO from awarded RFQ only | **PASS** | `SupplierPhaseS05Part3PurchaseOrderTest` |
| AC-S05-P02 | Issue gated; draft editable | **PASS** | Part3 |
| AC-S05-P03 | Cancel after receipt → 422 | **PASS** | Part3 / Part4 |
| AC-S05-P04 | Optional same-tenant contract | **PASS** | Part3 |
| AC-S05-P05 | contracts.supplier_id nullable/no backfill | **PASS** | Part3 + Contracts regression in CI Backend |
| AC-S05-P06 | No payment/banking on PO | **PASS** | Part3 API shape + FE modules |
| AC-S05-P07 | Cross-tenant PO → 404 | **PASS** | Part3 |
| AC-S05-P08 | Contracts suites still pass | **PASS** | CI Backend Laravel Pest (develop `42966539217`) |
| AC-S05-G01 | Partial receipt | **PASS** | `SupplierPhaseS05Part4GoodsReceiptTest` |
| AC-S05-G02 | Over-receipt rejected | **PASS** | Part4 |
| AC-S05-G03 | Duplicate post safe | **PASS** | Part4 |
| AC-S05-G04 | Concurrent receipts bound | **PASS** | CI `GoodsReceiptPostConcurrencyTest` (MySQL 8) |
| AC-S05-G05 | Stock movement correct | **PASS** | Part4 |
| AC-S05-G06 | Non-catalog without movement | **PASS** | Part4 |
| AC-S05-G07 | Manual ReceiveStock independent | **PASS** | Part4 |
| AC-S05-G08 | Inactive warehouse blocked | **PASS** | Part4 |
| AC-S05-G09 | Cross-tenant GRN → 404 | **PASS** | Part4 |
| AC-S05-G10 | Inventory regression | **PASS** | CI Backend Pest (Inventory within full suite) |
| AC-S05-C01 | Fully received close | **PASS** | `SupplierPhaseS05Part5ClosePurchaseOrderTest` |
| AC-S05-C02 | Short-close reason | **PASS** | Part5 |
| AC-S05-C03 | Post-close GRN → 422 | **PASS** | Part5 |
| AC-S05-C04 | Close audited | **PASS** | Part5 |
| AC-S05-C05 | Engagement through close | **PASS** | Part5 / Part6 |
| AC-S05-N01 | Sensitive perms not on templates | **PASS** | Part6 |
| AC-S05-N02 | Arabic RTL RFQ/PO/GRN pages | **PASS** | FE modules present; Vitest API/hints **10 PASS** (2026-11-14) |
| AC-S05-N03 | Demand-group planning-only | **PASS** | Retained banner (Part6 readiness) |
| AC-S05-N04 | Vitest + tsc/build | **PASS** | Local Vitest RFQ/PO/GRN **10 PASS**; CI Frontend type-check/build SUCCESS |
| AC-S05-N05 | S04 Phase1–5 regression | **PASS** | CI MySQL `S04 Suppliers and Procurement Feature tests` SUCCESS |
| AC-S05-N06 | DoD evidence pack | **PASS** | This matrix + Owner decision packet (acceptance still **PENDING_APPROVAL**) |
| S05 Browser UAT | Interactive browser journeys | **NOT_RUN** | No dedicated S05 browser UAT suite executed in this task |
| S05 Owner final acceptance | Formal Owner certificate | **PENDING_APPROVAL** | Package ready; do not invent approval |

**SQLite note:** S05 Feature tests intentionally **skip** on SQLite (`requireSuppliersMysqlTestingDatabase`). Automated functional evidence is MySQL/MariaDB only.

---

## S06 — Seasonal projects

| Gate / criterion | Status | Evidence |
|---|---|---|
| Part 0 documentation | **PASS** | Module pack under `docs/09-modules/20-season-projects/` |
| Parts 1–6 functional (SQLite) | **PASS** | Local Pest `tests/Feature/SeasonProjects` **59 passed / 1 skipped** (2026-11-14) |
| F-Q04 unlink-with-posted-GRN | **PASS** | `SeasonProjectPart6HardeningTest` |
| UAT scenarios 1–12 (API automated) | **PASS** | Historical `S06_UAT_REPORT.md` + Part6 hardening |
| Number concurrency (MySQL) | **PASS** | CI `SeasonProjectNumberConcurrencyTest` on run `42966539217` |
| MySQL 8 Feature suite | **PASS** (wired) / prior **DEFERRED** lifted | CI step added: `S06 SeasonProjects Feature tests (MySQL)`; local MariaDB **60 passed** |
| Frontend Vitest | **PASS** | `season-projects` validation **14 PASS** (2026-11-14) |
| Browser E2E | **NOT_RUN** | Explicitly deferred historically; not executed here |
| Production scheduler activation | **NOT_RUN** / **NOT_DEPLOYED** | App schedule registered only |
| E-Q02 project reopen | **BLOCKED** / deferred | Out of implemented S06 scope |
| Soft-delete restore | **BLOCKED** / deferred | E-Q14 restore unresolved |
| Owner final acceptance as COMPLETE | **PENDING_APPROVAL** | Prior label was FUNCTIONALLY_ACCEPTED / TECHNICAL_VALIDATION_DEFERRED |

---

## S07 — Risks & complaints

| ID | Criterion | Status | Evidence |
|---|---|---|---|
| AC-M1 | Separate aggregates/permissions | **PASS** | Module pack + Pest Part2/Part3 |
| AC-R1…AC-R5 | Risk create/score/self-approve/transitions/treatment | **PASS** | `RiskApiPart2Test` |
| AC-C0…AC-C4 | Complaint intake/lifecycle/reopen/escalate | **PASS** | `ComplaintApiPart3Test` |
| AC-D5/D6/D8/D12 | Soft-delete, numbers, project optional, concurrency | **PASS** | Part1 schema + Part2/3 + number concurrency CI |
| AC-P9 | Sensitive explicit-only | **PASS** | Catalog + gate tests |
| AC-A1 | Audit on critical transitions | **PASS** | Part2/Part3 audit cases |
| AC-N1 | Notifications + dedupe | **PASS** | `RisksComplaintsNotificationsPart4Test` |
| AC-X1/X2 | No crisis UI; no SLA jobs in S07 | **PASS** | Scope docs + Part6 |
| AC-S1 | Cross-tenant 404 | **PASS** | Part2/Part3/Part6 |
| AC-T1/L1/L2/E1 | Timeline + links + escalate UI | **PASS** | Part6 + FE Vitest |
| SQLite Feature suite | **PASS** | Local **78 passed** (2026-11-14) |
| MySQL 8 Feature suite | **PASS** (wired) | CI step added; local MariaDB **78 passed** |
| Frontend Vitest | **PASS** | risks-complaints suites in combined **114** module-related PASS |
| Browser UAT | **NOT_RUN** | Manual Hostinger UAT not executed |
| Owner final acceptance | **PENDING_APPROVAL** | Merge ≠ acceptance |

---

## Cross-cutting / defects addressed in this closure branch

| Item | Status | Notes |
|---|---|---|
| `executive_alerts` dual bare TIMESTAMP migrate fail (MariaDB NO_ZERO_DATE) | **PASS** (fixed) | Migration `useCurrent()` + regression test |
| S06/S07 MySQL Feature CI coverage gap | **PASS** (fixed) | Steps added to `.github/workflows/ci.yml` |
| Invented Owner approvals | **N/A** | None recorded |
