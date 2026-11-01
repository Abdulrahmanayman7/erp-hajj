# S04 Phase III — WP01–WP05 Acceptance Evidence

> **Status:** `OWNER ACCEPTED — FUNCTIONAL` (III-WP01–WP05)  
> **Governance sync:** 2026-11-01 under **IV-D15=B** — documents Phase III Owner functional acceptance accurately; does **not** change the substantive acceptance decision.  
> **Authoritative status record:** [S04_PHASE_III_ACCEPTANCE_STATUS.md](../../00-project/S04_PHASE_III_ACCEPTANCE_STATUS.md)  
> **Not claimed:** Phase IV design OWNER ACCEPTED · Phase IV/V implementation · commit/push/merge/deploy · release readiness  
> **Date:** 2026-11-01  
> **Branch:** `feature/s04-phase-i-supplier-registry` @ `09a902a0a08aa31a8686ce3824e6ee25f922a112` (+ uncommitted Phase I–III worktree)  
> **Database for all writes/tests:** `erp_hajj_testing` only

---

## 1. Environment / isolation

| Check | Result |
|---|---|
| Shell | PASS |
| `SELECT DATABASE()` | `erp_hajj_testing` |
| `phpunit.mysql.xml` | forces `DB_DATABASE=erp_hajj_testing` |
| `erp_hajj` | **not** migrated/modified |
| Disk (remediation run) | C: **0.8 GB** free · D: **19.45 GB** free |
| Git ops | **None** |

---

## 2. Focused remediation (audit gaps)

| Original gap | Resolution | Before → After |
|---|---|---|
| FE no post-create line edit | Detail page editable lines via `PUT …/lines` when draft/returned + `update` | Header-only → add/edit/remove lines, totals refresh, lock on submitted |
| Documents PR access | `DocumentReferenceValidator` + `DocumentPolicy` + `ListDocuments` require `purchase_requests.view` for `purchase_request` links | Docs-only could attach/list → **403** `DOCUMENT_LINK_FORBIDDEN`; XT → **422** `DOCUMENT_LINK_INVALID`; secrets not exposed |
| Duplicate-hints draft payload | `forDraftPayload` + controller accepts `lines` JSON (+ optional `season_id`) | ID-only → ID **and** draft payload modes |
| Policy view/list looseness | `viewAny`/`view` require **`purchase_requests.view` only** | create/update/financial_review no longer substitute for view |
| AC-P test gaps | Expanded Pest coverage (season, OU, supplier eligibility, urgency, creator review, procurement lock, docs, draft hints) | 18 → **27** Phase III tests |

**No new migrations.** Phase IV/V untouched.

---

## 3. Exact files modified (remediation)

**Backend:** `PurchaseRequestPolicy.php`; `FindPurchaseRequestDuplicateHints.php`; `PurchaseRequestController.php` (duplicateHints); `DocumentReferenceValidator.php`; `DocumentDomainException.php` (`linkForbidden`); `DocumentPolicy.php`; `ListDocuments.php`; `DocumentController.php`; `UploadDocument.php`; `UpdateDocument.php`; `SupplierPhase3PurchaseRequestsTest.php`

**Frontend:** `PurchaseRequestDetailsPage.vue`; `purchaseRequestsApi.ts`; `ar.ts` (saveLines / specifications / linesUpdated)

**Docs:** this evidence file

---

## 4. Tests executed (actual)

### Phase III Pest (full file)

```text
php vendor/bin/pest -c phpunit.mysql.xml tests/Feature/SuppliersAndProcurement/SupplierPhase3PurchaseRequestsTest.php
Tests: 27 passed (235 assertions)
```

### Documents smoke (with Phase III filter combo)

```text
DocumentTest D01, D02, D09 — passed
```

### Supplier Phase I sample

```text
php vendor/bin/pest -c phpunit.mysql.xml tests/Feature/SuppliersAndProcurement/SupplierPhase1Test.php --filter="creates a supplier|cross.tenant|unauthorized"
Tests: 4 passed (25 assertions)
```

### Frontend

```text
npx vitest run src/modules/purchase-requests → 4 passed
npx tsc --noEmit → exit 0
npm run build → exit 0
```

**Not claimed (original WP01–05 close):** full I/II-A/II-B/II-C suites; Playwright E2E.  
**Later:** multi-process PR numbering stress + repair — see **§8** (post-acceptance).

---

## 5. Security verification (remediation)

- List/show PR: **`purchase_requests.view` required** (negative test: create-only → 403).
- PR document upload/list/show/download: Documents perm **and** PR view; cross-tenant link fail-closed.
- Sensitive `purchase_requests.financial_review` remains explicit-only (catalog unchanged).
- Duplicate hints tenant-scoped; XT ID → 404.
- Version rows remain immutable (model guards + test).

---

## 6. Remaining limitations

- C: free space critically low historically (**~0.8 GB**) — development risk; prefer builds/temp on D:.
- Work remains **uncommitted** (commit/push/merge/deploy not authorized by Phase III functional acceptance).
- Sibling number generators were **unpatched at original §8.6 inspection**; later post-acceptance repair recorded in **§8.8** (does not alter Phase III functional acceptance substance).

---

## 7. Confirmation

- No unauthorized Git or deployment operations.
- Phase IV / Phase V / S05 product features untouched.

---

## 8. Post-acceptance concurrency verification (2026-11-01)

> **Does not rewrite** Phase III functional Owner acceptance. This section records a post-acceptance defect repair and multi-process re-verification only.

### 8.1 Original failure reproduction

| Run | Evidence | Result |
|---|---|---|
| `r2026110115561286d9` | `backend/tests/Stress/evidence_prior_fail_r2026110115561286d9.json` | Scenario A DB 200 unique (worker parse empty); Scenario B tenant-2 **48/50** lost creates |
| `r2026110116000388be` | `backend/tests/Stress/evidence_fail_r2026110116000388be.json` + `results/r2026110116000388be/A/` | Scenario A **196/200**; **4× `PR_NUMBER_CONFLICT`**; 0 duplicates; `sequence_next=197` |

Engine: **MariaDB 10.4.27**, isolation **`REPEATABLE-READ`**, DB **`erp_hajj_testing`** (`SELECT DATABASE()`).

### 8.2 Root cause

`PurchaseRequestNumberGenerator` used SELECT-miss → INSERT → catch → `lockForUpdate` re-SELECT. Under RR cold-start multi-process races, losers of the first insert could still observe **null** on re-SELECT and throw **`PR_NUMBER_CONFLICT`**, aborting `CreatePurchaseRequest` without persisting a row. This is **not** duplicate number generation; persisted numbers remained unique. Distinct from lock-wait timeout and from successful duplicate-key recovery when the row is visible.

### 8.3 Exact fix (no migration; format unchanged)

- Atomic `INSERT … VALUES (tenant_id, LAST_INSERT_ID(1)+1, …) ON DUPLICATE KEY UPDATE next_value = LAST_INSERT_ID(next_value)+1`, then `SELECT LAST_INSERT_ID()`.
- `CreatePurchaseRequest` outer `DB::transaction(..., 5)` for bounded InnoDB deadlock/lock-wait retries.
- Number format `PR-######` and tenant-scoped semantics preserved. Sequence increments roll back with the create transaction.

### 8.4 Multi-process stress re-test (PASS)

```text
php tests/Stress/run_pr_numbering_stress.php
run_id=r20261101162447c365
VERDICT=PASS elapsed_s=20.393
```

| Scenario | Workers | Attempted | Succeeded | Failed | Unique persisted | seq next | Deadlocks/timeouts/retries |
|---|---|---|---|---|---|---|---|
| A same tenant | 10×20 | 200 | 200 | 0 | 200 | 201 | 0/0/0 |
| B two tenants | 5×10 ×2 | 100 | 100 | 0 | 50+50 | 51/51 | 0/0/0 (shared numbers across tenants expected; id_overlap=0) |
| C contention | 20×5 | 100 | 100 | 0 | 100 | 101 | 0/0/0 |

Report: `backend/tests/Stress/last_pr_numbering_stress_report.json`.

### 8.5 Regression tests (PASS)

```text
php vendor/bin/pest -c phpunit.mysql.xml tests/Concurrency/PurchaseRequestNumberConcurrencyTest.php
Tests: 4 passed (153 assertions)

php vendor/bin/pest -c phpunit.mysql.xml tests/Feature/SuppliersAndProcurement/SupplierPhase3PurchaseRequestsTest.php --filter="number|sequence|rolls back|does not advance|creates draft"
Tests: 5 passed (45 assertions)
```

### 8.6 Sibling generator inspection (historical — at PR-repair close)

At the close of the PR numbering repair, `SupplierNumberGenerator` and `EvaluationNumberGenerator` still implemented the unsafe SELECT-miss/INSERT/re-SELECT pattern. That inspection was **read-only** at the time. Subsequent post-acceptance repair is recorded in **§8.8**.

### 8.7 Remaining risks (at PR-repair close)

- Sibling generators were then unpatched (superseded by §8.8).
- C: free space ~0.78–0.92 GB remains operationally fragile.
- Extreme deadlock storms beyond Laravel’s 5 transaction attempts could still surface as domain conflict after retries exhaust (not observed in A/B/C).

### 8.8 Post-acceptance sibling numbering repair (Supplier + Evaluation)

> **Post-acceptance verification only.** Does **not** rewrite Phase III Owner functional acceptance.

`SupplierNumberGenerator` and `EvaluationNumberGenerator` were later aligned to the same atomic `LAST_INSERT_ID` / `ON DUPLICATE KEY UPDATE` pattern as Purchase Requests. Multi-process stress and regression evidence for those generators are filed with Phase I / Phase II-C evidence updates (testing DB only). Phase III functional scope and acceptance decision are unchanged.
