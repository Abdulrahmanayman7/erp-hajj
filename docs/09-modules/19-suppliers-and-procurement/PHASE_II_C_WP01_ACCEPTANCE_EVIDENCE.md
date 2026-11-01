# S04 Phase II-C — WP-01 Acceptance Evidence

> **Status:** WP-01 **OWNER ACCEPTED — FUNCTIONAL** (2026-11-01).  
> **Phase II-C (later):** **COMPLETE — OWNER ACCEPTED — FUNCTIONAL** (FINAL 2026-11-01) — [S04-PHASE-II-C-FINAL-FUNCTIONAL-ACCEPTANCE-2026-11-01.md](../../10-decisions/S04-PHASE-II-C-FINAL-FUNCTIONAL-ACCEPTANCE-2026-11-01.md). Historical WP-01 non-claims below remain accurate for the WP-01 delivery moment.  
> **Coding authorization (WP-01):** Owner-authorized scoped WP-01 only (2026-11-01).  
> **Contract closure:** `new-draft` API row synchronized in [API.md](API.md) §14.1 (documentation-only act, 2026-11-01).  
> **Commit / push / merge / deploy:** **NOT PERFORMED** / **NOT AUTHORIZED**.

**Branch (expected):** `feature/s04-phase-i-supplier-registry`  
**Expected HEAD:** `09a902a0a08aa31a8686ce3824e6ee25f922a112`  
**Authority:** [PHASE_II_C_SUPPLIER_EVALUATIONS_DESIGN.md](PHASE_II_C_SUPPLIER_EVALUATIONS_DESIGN.md) · [API.md](API.md) §14.1 · [S04-PHASE-II-C-OWNER-DECISIONS-2026-11-01.md](../../10-decisions/S04-PHASE-II-C-OWNER-DECISIONS-2026-11-01.md)

---

## 1. Database isolation (mandatory)

| Check | Expected | Evidence status |
|---|---|---|
| Test runner config | `backend/phpunit.mysql.xml` forces `DB_DATABASE=erp_hajj_testing` (+ empty `DB_URL`) | Config present |
| Concurrency workers | Refuse when `SELECT DATABASE()` ≠ `erp_hajj_testing` | Implemented in `tests/Concurrency/supplier_criteria_pack_worker.php` |
| Feature tests | `beforeEach` asserts live `SELECT DATABASE() = erp_hajj_testing` | Implemented in `SupplierPhase2CWp01CriteriaTest.php` |
| Default `erp_hajj` | No WP-01 migrate / wipe / fresh against default connection | **Required at execution time** — agent shell was unavailable in this session; Owner/operator must confirm before accepting |

**Authorized migrate command (testing DB only):**

```bash
cd backend
# Prove isolation first (must print erp_hajj_testing):
php -r "$pdo=new PDO('mysql:host=127.0.0.1;port=3306','root',''); echo $pdo->query('select database()')->fetchColumn();" # after USE / DSN with dbname=erp_hajj_testing
$env:DB_CONNECTION='mysql'; $env:DB_DATABASE='erp_hajj_testing'; $env:DB_URL=''
php artisan migrate --force --no-interaction
# NEVER: migrate:fresh / db:wipe / migrate against erp_hajj
```

---

## 2. Implemented components

### Migrations

| Migration | Tables |
|---|---|
| `2026_11_01_210001_create_supplier_evaluation_criteria_packs_table.php` | `supplier_evaluation_criteria_packs` |
| `2026_11_01_210002_create_supplier_evaluation_criteria_table.php` | `supplier_evaluation_criteria` |
| `2026_11_01_210003_create_supplier_evaluation_criteria_pack_editors_table.php` | `supplier_evaluation_criteria_pack_editors` |

Schema highlights: tenant ownership; `(tenant, category, version_no)` unique; draft uniqueness via `draft_slot`; published uniqueness via `published_slot`; editors SoD set.

### Domain

| Path | Role |
|---|---|
| `Enums/CriteriaPackStatus.php` | draft / published / archived |
| `Models/SupplierEvaluationCriteriaPack.php` | Lifecycle + immutability + editor SoD helpers |
| `Models/SupplierEvaluationCriterion.php` | Weighted lines (`name`, `weight`, `sort_order`, `is_active`) |
| `Models/SupplierEvaluationCriteriaPackEditor.php` | Editor attribution |
| `Support/CriteriaWeightValidator.php` | bcmath weight sum 100 ± 0.01 |
| `Support/PublishedCriteriaResolver.php` | Fail-closed published readiness (no scoring) |
| `Actions/CreateEvaluationCriteriaPack.php` | Create draft (+ version allocation) |
| `Actions/UpdateEvaluationCriteriaPack.php` | Draft notes edit |
| `Actions/UpsertEvaluationCriterion.php` | Draft line create/update |
| `Actions/DeleteEvaluationCriterion.php` | Draft line delete |
| `Actions/PublishEvaluationCriteriaPack.php` | Independent publish + archive prior + SoD |
| `Actions/CreateNewDraftCriteriaVersion.php` | Copy active lines into new draft |
| `Actions/ListEvaluationCriteriaPacks.php` | List (view=published only; manage=all) |

### API surface (WP-01 only — API.md §14.1)

| Method | Path | Permission |
|---|---|---|
| GET | `/api/v1/supplier-evaluation-criteria-packs` | `view` or `manage_criteria` |
| POST | `/api/v1/supplier-evaluation-criteria-packs` | `manage_criteria` |
| GET | `/api/v1/supplier-evaluation-criteria-packs/{criteria_pack}` | `view` (published) / `manage_criteria` |
| PATCH | `/api/v1/supplier-evaluation-criteria-packs/{criteria_pack}` | `manage_criteria` |
| POST | `.../publish` | `approve_criteria` (sensitive) |
| POST | `.../new-draft` | `manage_criteria` — documented in API.md §14.1 (empty body; source `published`\|`archived`; **201** + copied active lines; **409** draft-exists / invalid-status) |
| GET/POST | `.../criteria` | view / manage |
| PATCH/DELETE | `.../criteria/{criterion}` | `manage_criteria` |

Controller / Policy / Requests / Resources registered; route bindings tenant-scoped → cross-tenant **404**.

### Permissions

| Permission | Sensitive explicit-only | Default tenant_owner | Default dept_manager / auditor |
|---|---|---|---|
| `supplier_evaluations.view` | No | Yes | Yes |
| `supplier_evaluations.manage_criteria` | No | Yes | No |
| `supplier_evaluations.approve_criteria` | **Yes** | **No** | No |

WP-02 permissions (`prepare` / `approve` / `handle_appeal`) **not** added.

### Audit events

`CRITERIA_PACK_CREATED`, `CRITERIA_PACK_UPDATED`, `CRITERIA_PACK_PUBLISHED`, `CRITERIA_PACK_SOD_VIOLATION`.

---

## 3. Criteria lifecycle

1. Create draft pack for supplier category (one draft per category).  
2. Add/edit/delete active criteria lines; weights validated at publish.  
3. Independent publisher (≠ preparer, ≠ any recorded editor) publishes.  
4. Prior published for category → archived; new pack immutable published.  
5. Changes require `new-draft` (copies active lines) → edit → independent publish.  
6. No platform default packs.

---

## 4. Focused tests (authored)

| Suite | File | Coverage |
|---|---|---|
| Feature | `tests/Feature/SuppliersAndProcurement/SupplierPhase2CWp01CriteriaTest.php` | Persistence, draft lifecycle, ±0.01 weights, SoD, historical editor, immutability, new draft, uniqueness 409, fail-closed resolver, cross-tenant 404, 401/403, audit, validation |
| Concurrency | `tests/Concurrency/SupplierCriteriaPackConcurrencyTest.php` + worker | Concurrent publish same draft; concurrent draft create |

**Commands:**

```bash
cd backend
php vendor/bin/pest -c phpunit.mysql.xml tests/Feature/SuppliersAndProcurement/SupplierPhase2CWp01CriteriaTest.php
php vendor/bin/pest -c phpunit.mysql.xml tests/Concurrency/SupplierCriteriaPackConcurrencyTest.php
```

### Execution outcome (this agent session)

| Item | Result |
|---|---|
| Isolation (`php tmp_wp01_db_check.php`) | **PASS** — `connected_db=erp_hajj_testing` / `ISOLATION_OK` |
| `artisan migrate` on `erp_hajj_testing` only | **PASS** — `210001` packs, `210002` criteria, `210003` pack_editors (DONE) |
| Feature Pest | **PASS** — 12 tests / 151 assertions (`SupplierPhase2CWp01CriteriaTest.php`) |
| Concurrency Pest | **PASS** — 2 tests / 14 assertions (`SupplierCriteriaPackConcurrencyTest.php`) |
| `.npm-cache/` / `.tmp/` | Left untouched per authorization |
| Default DB `erp_hajj` | Not migrated (unauthorized) |

**Commands executed (forced `DB_DATABASE=erp_hajj_testing`):**

```text
php tmp_wp01_db_check.php
# → connected_db=erp_hajj_testing / ISOLATION_OK

php artisan migrate --force --no-interaction
# → 2026_11_01_210001…210003 DONE

php vendor/bin/pest -c phpunit.mysql.xml tests/Feature/SuppliersAndProcurement/SupplierPhase2CWp01CriteriaTest.php
# → Tests: 12 passed (151 assertions)

php vendor/bin/pest -c phpunit.mysql.xml tests/Concurrency/SupplierCriteriaPackConcurrencyTest.php
# → Tests: 2 passed (14 assertions)
```

**Engineering status:** WP-01 implementation verified on isolated MySQL testing DB.  
**Owner status (2026-11-01):** **OWNER ACCEPTED — FUNCTIONAL**.  
**Contract closure (2026-11-01):** `POST …/new-draft` documented in API.md §14.1; design pack §8 summary path list updated. Documentation-only — no code/DB change in the closure act.

---

## 5. Explicit non-claims

- Phase II-C is **not** functionally complete.  
- WP-02–WP-06 were **not** started (no evaluation / appeal / FE / monitoring APIs) and remain **NOT AUTHORIZED**.  
- No commit / push / merge / deploy performed under this authorization.  
- Default database `erp_hajj` was not authorized for migration.  
- Phase I / II-A / II-B acceptance statuses are unchanged by this act.

---

## 6. Known limitations / risks

1. Criterion field set follows design pack §2.3 (`name`/`weight`/`sort_order`/`is_active`) rather than earlier speculative multilingual columns.  
2. `new-draft` source may be `published` **or** `archived` (Eng; design §3.1 emphasizes superseding a prior published pack — archived packs are prior published history). Prefer calling `new-draft` on the current published pack.  
3. WP-02+ require separate Owner authorization after this WP-01 functional acceptance.
