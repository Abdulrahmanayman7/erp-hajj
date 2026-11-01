# S04 Phase II-C — WP-02–WP-06 Acceptance Evidence

> **Status:** WP-02–WP-06 **IMPLEMENTED — ENGINEERING EVIDENCE RECORDED** — **OWNER ACCEPTED** via Phase II-C FINAL functional acceptance (2026-11-01).  
> **Phase II-C:** **COMPLETE — OWNER ACCEPTED — FUNCTIONAL** — [S04-PHASE-II-C-FINAL-FUNCTIONAL-ACCEPTANCE-2026-11-01.md](../../10-decisions/S04-PHASE-II-C-FINAL-FUNCTIONAL-ACCEPTANCE-2026-11-01.md) · [S04_PHASE_II_C_ACCEPTANCE_STATUS.md](../../00-project/S04_PHASE_II_C_ACCEPTANCE_STATUS.md)  
> **WP-01:** **OWNER ACCEPTED — FUNCTIONAL** (2026-11-01) — confirmed within phase FINAL.  
> **Historical note:** Prior to the FINAL acceptance act, this pack correctly stated WP-02–WP-06 were awaiting Owner acceptance.  
> **Coding authorization:** Owner-authorized integrated WP-02–WP-06 (2026-11-01).  
> **Commit / push / merge / deploy:** **NOT PERFORMED** / **NOT AUTHORIZED**.  
> **S04 overall closure / S05:** **NOT AUTHORIZED** by Phase II-C acceptance.

**Branch:** `feature/s04-phase-i-supplier-registry`  
**HEAD (at delivery):** `09a902a0a08aa31a8686ce3824e6ee25f922a112` (+ uncommitted II-A/II-B/II-C work)  
**Authority:** [PHASE_II_C_SUPPLIER_EVALUATIONS_DESIGN.md](PHASE_II_C_SUPPLIER_EVALUATIONS_DESIGN.md) · [API.md](API.md) §14 · [S04-PHASE-II-C-OWNER-DECISIONS-2026-11-01.md](../../10-decisions/S04-PHASE-II-C-OWNER-DECISIONS-2026-11-01.md)

---

## 1. Database isolation

| Check | Result |
|---|---|
| `php tmp_wp01_db_check.php` | `connected_db=erp_hajj_testing` / `ISOLATION_OK` |
| Migrate target | `erp_hajj_testing` only (`DB_DATABASE=erp_hajj_testing`) |
| Default `erp_hajj` | Not migrated |

**Migrations applied (testing DB):**

| Migration | Tables |
|---|---|
| `2026_11_01_220001_…_number_sequences` | `supplier_evaluation_number_sequences` |
| `2026_11_01_220002_…_evaluations` | `supplier_evaluations` |
| `2026_11_01_220003_…_score_snapshots` | `supplier_evaluation_score_snapshots` |
| `2026_11_01_220004_…_appeals` | `supplier_evaluation_appeals` (+ `origin_appeal_id` FK) |

---

## 2. Work package delivery

### WP-02 — Evaluation domain

- Models: `SupplierEvaluation`, `SupplierEvaluationScoreSnapshot`
- Lifecycle: `draft → pending_approval → approved|rejected`
- Scoring: bcmath weighted average, half-up to 2dp; snapshot at **submit** (IIC-D2)
- Preconditions: non-archived supplier, category link, published pack, IIC-F1 season rules
- SoD: preparer ≠ approver/rejector (IIC / AC-E03)
- Monitoring proposal on draft; final on approve (IIC-F3); never mutates supplier lifecycle

### WP-03 — Appeals

- Model: `SupplierEvaluationAppeal` (`open` / `accepted` / `rejected`)
- Accept → new draft version same `evaluation_family_key`, `version_no+1`, `supersedes_evaluation_id`, `origin_appeal_id`
- Prior approved immutable; open-slot uniqueness; SoD filer≠reviewer and preparer≠reviewer

### WP-04 — API / policies / permissions

| Method | Path | Permission |
|---|---|---|
| GET/POST | `/api/v1/supplier-evaluations` | `view` / `prepare` |
| GET/PATCH | `/api/v1/supplier-evaluations/{evaluation}` | `view` / `prepare` |
| POST | `…/submit` | `prepare` |
| POST | `…/approve` \| `…/reject` | `approve` (sensitive) |
| GET/POST | `…/appeals` | `view` / `prepare`\|`handle_appeal` |
| POST | `/api/v1/supplier-evaluation-appeals/{appeal}/accept\|reject` | `handle_appeal` (sensitive) |

Permissions registered: `view`, `prepare`, `approve`*, `manage_criteria`, `approve_criteria`*, `handle_appeal`*  
\*= sensitive explicit-only.

### WP-05 — Frontend RTL

- Routes: `/app/supplier-evaluations`, `/app/supplier-evaluations/:id`
- Nav «تقييم الموردين»; supplier details «تقييمات» tab (`SupplierEvaluationsPanel`)
- List filters, create, detail submit/approve/reject/appeal, permission-aware CTAs
- Validation + weighted preview Vitest

### WP-06 — Notifications & integration

- Type: `SUPPLIER_EVALUATION_MONITORING_RECOMMENDED`
- Emit **only** after successful approve with final `monitoring_recommended=true`
- Recipients: same-tenant holders of `supplier_evaluations.approve` (approver excluded Eng)
- Documents morph alias `supplier_evaluation` registered
- Failures do not roll back approve (`NotificationDispatcher` / afterCommit)

---

## 3. Tests executed

| Suite | Result |
|---|---|
| Feature `SupplierPhase2CWp02to06EvaluationsTest.php` | **14 passed** (171 assertions) |
| Concurrency `SupplierEvaluationConcurrencyTest.php` | **2 passed** (19 assertions) |
| Feature WP-01 regression | **12 passed** (151 assertions) |
| Vitest `evaluationValidation.test.ts` | **3 passed** |
| `tsc --noEmit` | **PASS** (exit 0) |

**Commands:**

```bash
cd backend
$env:DB_CONNECTION='mysql'; $env:DB_DATABASE='erp_hajj_testing'
php tmp_wp01_db_check.php
php artisan migrate --force --no-interaction
php vendor/bin/pest -c phpunit.mysql.xml tests/Feature/SuppliersAndProcurement/SupplierPhase2CWp02to06EvaluationsTest.php
php vendor/bin/pest -c phpunit.mysql.xml tests/Concurrency/SupplierEvaluationConcurrencyTest.php
php vendor/bin/pest -c phpunit.mysql.xml tests/Feature/SuppliersAndProcurement/SupplierPhase2CWp01CriteriaTest.php

cd ../frontend
npx vitest run src/modules/suppliers/validation/evaluationValidation.test.ts
npx tsc --noEmit -p tsconfig.json
```

### AC / SEC coverage

| ID | Covered by |
|---|---|
| AC-E01 | Snapshot immutability + scoring formula tests |
| AC-E02 / IIC-F1 | post_season season required; others optional; cross-tenant season 404 |
| AC-E03 | Dual-perm preparer SoD on approve/reject |
| AC-E04 / IIC-F3 | Draft/submit no notify; approve false no notify; approve true notifies |
| AC-E05 | Appeal accept → new draft version; prior immutable |
| SEC-T1 | Cross-tenant evaluation 404 |
| SEC-T3 | Appeal SoD filer + preparer |
| SEC-T4 | Missing published pack fail-closed |
| SEC-T6 | Concurrent submit / concurrent approve |
| SEC-T2 / T5 | Covered under WP-01 (criteria) |
| SEC-T7 | Morph registered; dedicated evidence link API deferred to Documents patterns (optional IIC-D8) |
| SEC-T8 | FE validation Vitest + permission-aware pages (component CTAs) |

---

## 4. Explicit non-claims / post-acceptance distinctions

- Owner functional acceptance for WP-02–WP-06 / Phase II-C is recorded in [S04-PHASE-II-C-FINAL-FUNCTIONAL-ACCEPTANCE-2026-11-01.md](../../10-decisions/S04-PHASE-II-C-FINAL-FUNCTIONAL-ACCEPTANCE-2026-11-01.md) — **not** claimed as previously granted before that act.  
- No commit / push / merge / deploy.  
- S04 overall closure **not** granted by Phase II-C acceptance.  
- S05 untouched / unauthorized.  
- Default database `erp_hajj` not migrated under this evidence pack.

## 5. Remaining risks

1. Optional evidence attach UX relies on existing Documents flows + morph `supplier_evaluation` — no dedicated evaluation-evidence endpoint beyond Documents module patterns.  
2. Full frontend production build not re-run in this session (tsc + targeted Vitest passed; prior known memory risk on full build remains).  
3. Owner formal **Phase II-C** acceptance is now granted (2026-11-01); S04 overall closure and release acts remain separate Owner decisions.

---

## 6. Post-acceptance concurrency verification — Evaluation numbering (2026-11-01)

> **Does not rewrite** WP-02–WP-06 / Phase II-C acceptance status. Records a later cold-start concurrency repair for `EvaluationNumberGenerator` only.

### Root cause

Same SELECT-miss → INSERT → re-SELECT FOR UPDATE pattern. Under MariaDB 10.4 **REPEATABLE-READ**, concurrent first allocation could raise `EVALUATION_NUMBER_CONFLICT` when the loser observed a null sequence row.

### Fix

Atomic `INSERT … LAST_INSERT_ID(1)+1 … ON DUPLICATE KEY UPDATE next_value = LAST_INSERT_ID(next_value)+1` on `supplier_evaluation_number_sequences` (`tenant_id` PK). `CreateSupplierEvaluation` uses `DB::transaction(..., 5)`. Format `SEV-######` unchanged; tenant-scoped; rolls back with create transaction. No migration / contract change.

### Stress (`erp_hajj_testing`, domain create path with Active supplier + published pack)

```text
php tests/Stress/run_evaluation_numbering_stress.php
run_id=r20261101164326116f VERDICT=PASS elapsed_s=27.473
```

| Scenario | Result |
|---|---|
| E-A 10×20 | 200/200, 200 unique, seq=201, fail=0 |
| E-B 2 tenants 5×10 | 50+50, seq=51/51, shared numbers expected, fail=0 |
| E-C 20×5 | 100/100, seq=101, fail=0 |

Deadlocks/lock-waits/retries: **0/0/0**. Report: `backend/tests/Stress/last_evaluation_numbering_stress_report.json`.

### Regression

`EvaluationNumberConcurrencyTest` — 3 passed (cold-start, post-init, multi-tenant independence).
