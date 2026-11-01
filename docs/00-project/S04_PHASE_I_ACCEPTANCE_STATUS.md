# S04 — G2 Phase I — Acceptance Status

> **G2 Phase I status:** `COMPLETE — OWNER ACCEPTED`
> **Overall S04 implementation status:** `IN PROGRESS` (Phase II-A + II-B later Owner-accepted; II-C / Phase III–V **NOT_STARTED**)
> **Owner decision date:** 2026-10-31
> **Branch:** `feature/s04-phase-i-supplier-registry`
> **Design baseline commit:** `14bb9234d9ed7801e8fc6185a2d7135fc8b35b6f`

## Owner decision

Project Owner formally accepted **S04 G2 Phase I** on **2026-10-31** as `COMPLETE — OWNER ACCEPTED`.

**Phase I scope:** geographic foundation (`geo_places` D1 seed) and supplier registry (categories, contacts, category-specific coverage, lifecycle with activation blocked pending eligibility, Phase I APIs/UI/permissions/tests).

## Acceptance evidence (executed)

| Gate | Result |
|---|---|
| Authorization defect (`SupplierPolicy::viewAny`) | Remediated; `geo_places.view` alone cannot list suppliers |
| Tenant-isolation Pest matrix | PASS (cross-tenant supplier/category/contact/coverage → 404; unauthorized → 403) |
| Feature Pest (`--filter=SuppliersAndProcurement`) | 20 PASS (101 assertions) |
| Supplier-number concurrency (MySQL `erp_hajj_testing`, independent workers) | 1 PASS (11 assertions) |
| Contracts regression (`tests/Feature/Contracts`) | 16 PASS (254 assertions) |
| Inventory regression (`--filter=Inventory`) | 33 PASS (236 assertions) |
| Frontend Vitest (suppliers + nav) | 24 PASS |
| TypeScript (`tsc --noEmit`) | PASS (exit 0) |

## Explicit verification gaps (recorded, not claimed as executed)

- Independent **Documents** suite: **not executed** (Inventory morph guards covered in Inventory suite only).
- Primary-contact **multi-process** race test: **not executed**; MySQL-compatible generated unique + transactional locks implemented.
- Entire S04 sprint (evaluation, PR, approvals, banking, eligibility docs): **not accepted**.

## Explicitly out of Phase I acceptance

- At Phase I close, Phase II–V (banking, eligibility documents, evaluations, purchase requests, approval engine) were **out of this acceptance**. Later Owner acts accepted II-A and II-B separately; II-C / Phase III–V remain **NOT_STARTED**.
- **S05** procurement cycle: **NOT_STARTED**.
- **[ADR-0019](../10-decisions/ADR-0019-ACCOMMODATION-CAMPS-DRAFT.md):** remains **`PROPOSED / NOT ACCEPTED`**.
- **[ADR-0022](../10-decisions/ADR-0022-S04-SUPPLIERS-AND-PURCHASE-REQUESTS-FUNCTIONAL-DECISIONS.md)** / **[ADR-0023](../10-decisions/ADR-0023-S04-ENGINEERING-DESIGN.md):** remain **APPROVED** (functional / engineering design). Design approval status unchanged.
- Engineering CR design/scope approval unchanged; later phases and release acts still require **separate** Owner authorization.

## Next

- Phase I acceptance **unchanged**.
- Phase II-A subsequently **COMPLETE — OWNER ACCEPTED** (2026-11-01): [S04_PHASE_II_A_ACCEPTANCE_STATUS.md](S04_PHASE_II_A_ACCEPTANCE_STATUS.md).
- Phase II-B subsequently **COMPLETE — FUNCTIONALLY CLOSED — OWNER ACCEPTED** (2026-11-01): [S04_PHASE_II_B_ACCEPTANCE_STATUS.md](S04_PHASE_II_B_ACCEPTANCE_STATUS.md).
- II-C / Phase III–V / S05 remain **NOT_STARTED — NOT AUTHORIZED**. Commit / push / merge / deploy require separate Owner acts.

---

## Post-acceptance concurrency verification — Supplier numbering (2026-11-01)

> **Does not rewrite** Phase I Owner acceptance. Records a later cold-start concurrency repair for `SupplierNumberGenerator` only.

### Root cause

Same SELECT-miss → INSERT → re-SELECT FOR UPDATE pattern as the repaired PR generator. Under MariaDB 10.4 **REPEATABLE-READ**, concurrent first allocation could fail with `SUPPLIER_NUMBER_TAKEN` when the loser observed a null sequence row.

### Fix

Atomic `INSERT … LAST_INSERT_ID(1)+1 … ON DUPLICATE KEY UPDATE next_value = LAST_INSERT_ID(next_value)+1` on `supplier_number_sequences` (`tenant_id` PK). `CreateSupplier` uses `DB::transaction(..., 5)`. Format `SUP-######` (config prefix/pad) unchanged; tenant-scoped; rolls back with create transaction.

### Stress (`erp_hajj_testing`)

```text
php tests/Stress/run_supplier_numbering_stress.php
run_id=r202611011642243f2d VERDICT=PASS elapsed_s=33.651
```

| Scenario | Result |
|---|---|
| S-A 10×20 | 200/200, 200 unique, seq=201, fail=0 |
| S-B 2 tenants 5×10 | 50+50, seq=51/51, shared numbers expected, fail=0 |
| S-C 20×5 | 100/100, seq=101, fail=0 |

Deadlocks/lock-waits/retries: **0/0/0**. Report: `backend/tests/Stress/last_supplier_numbering_stress_report.json`.

### Regression

`SupplierNumberConcurrencyTest` — 4 passed (cold-start, multi-tenant, rollback, prior concurrent workers).
