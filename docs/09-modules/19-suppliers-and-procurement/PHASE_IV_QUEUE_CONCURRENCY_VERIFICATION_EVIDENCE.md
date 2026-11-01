# S04 Phase IV — Real Queue & Multi-Process Concurrency Verification Evidence

> **Verification status:** `PASS` (Gate Q + Gate C)  
> **Owner functional acceptance:** **FINAL — GRANTED** 2026-11-01 — [S04-PHASE-IV-FUNCTIONAL-ACCEPTANCE-2026-11-01.md](../../10-decisions/S04-PHASE-IV-FUNCTIONAL-ACCEPTANCE-2026-11-01.md)  
> **Authorization (this verification run):** verification only (no application fixes, migrations, Git mutations, deploy, Phase V/S05)  
> **Run ID:** `qvcv_20261101184549_480488`  
> **Finished:** 2026-11-01T18:46:32+00:00  
> **Machine report:** `backend/storage/app/phase_iv_verification/qvcv_20261101184549_480488/report.json`

---

## 1. Environment & safety preflight

| Check | Actual |
|---|---|
| Branch | `feature/s04-phase-i-supplier-registry` |
| HEAD | `09a902a0a08aa31a8686ce3824e6ee25f922a112` |
| MariaDB | **10.4.27-MariaDB** |
| Host | `127.0.0.1` / `DESKTOP-I7N8ENS` |
| Database (every process) | **`erp_hajj_testing` only** |
| Production DB `erp_hajj` | **not used** |
| Queue driver | **`database`** (not Pest `sync`) |
| Queue tables | `jobs` + `failed_jobs` present on testing DB |
| Disk | C: ~4.47 GB free · D: ~19.43 GB free |
| Fixtures | Tagged `IVVERIFY {runId} …` · isolated tenants 19–22 |
| Migrations executed | **none** |
| Git commit/push/merge/etc. | **none** |

### Identity gate

Every orchestrator phase, every `phase_iv_queue_worker.php` child, and every `phase_iv_contention_worker.php` child independently calls `phaseIvAssertTestingDb()` and refuses unless `config` + `SELECT DATABASE()` are exactly `erp_hajj_testing`.

Sample worker gate (QV-01.1 pid 16724):

```text
database=erp_hajj_testing host=127.0.0.1 version=10.4.27-MariaDB queue=database
```

---

## 2. Real queue backend

| Item | Value |
|---|---|
| Effective `QUEUE_CONNECTION` | `database` |
| Worker entry | `php tests/Verification/phase_iv_queue_worker.php <label>` |
| Inner command | `queue:work database --once --stop-when-empty --tries=5 --timeout=60` |
| Isolation | Testing DB-backed `jobs` table only; queue cleared at run start and between scenarios |
| Orchestrator | `php tests/Verification/run_phase_iv_queue_concurrency_verification.php` |

**Not used:** `QUEUE_CONNECTION=sync`, Pest sync driver, production Redis, shared production brokers.

---

## 3. Gate Q — QV-01 results

| Scenario | Result | Evidence summary |
|---|---|---|
| **QV-01.1** Real async | **PASS** | jobs 0→1 after submit; status `submitted`; independent worker pid **16724** processed `RoutePurchaseRequestApprovalJob` **DONE**; then `under_review`, instances=1, steps=2 |
| **QV-01.2** Interrupt/recover | **PASS** | job_id **24** reserved then released; worker recovered; `under_review`, instances=1 (no duplicate) |
| **QV-01.3** Duplicate delivery | **PASS** | route + second dispatch; instances=1, active_instances=1, steps=2 |
| **QV-01.4** Lost enqueue + reconciler | **PASS** | job deleted after commit; PR remained `submitted`/0 instances; `purchase-requests:reconcile-routing --min-age-seconds=0` → “Dispatched 1…”; worker → `under_review`/1 instance |
| **QV-01.5** Config block | **PASS** | stayed `submitted`; `routing_block_code=PR_MATRIX_MISSING`; 0 instances; admin visibility **true** |
| **QV-01.6** Tenant context | **PASS** | tenants **19** and **20** both `under_review`; cross-tenant PR visibility in A = false; instance tenant leak = false |

**Gate Q: PASS** · unexplained failed jobs remaining: **none** (defects array empty).

---

## 4. Gate C — CV-01 results

Contention via independent OS processes (`phase_iv_contention_worker.php`) + barrier file. Same-step races used a **single-level** published matrix + FR so all actors contended on one final pending step. CV-01.5/01.6 used the two-level matrix on tenant A.

| Scenario | Processes | Rounds | Attempts | Accepted | Rejected/stale | Errors | Dup instances/steps | Result |
|---|---:|---:|---:|---:|---:|---:|---:|---|
| **CV-01.1** same-step approve | 4 | 5 | 20 | **5** (1/round) | 15 (`PR_CONCURRENT_DECISION`) | 0 | 0 | **PASS** |
| **CV-01.2** approve vs reject | 4 | 1 | 4 | **1** | 3 | 0 | — | **PASS** (final `approved`) |
| **CV-01.3** approve vs return | 4 | 1 | 4 | **1** | 3 | 0 | — | **PASS** (final `returned`) |
| **CV-01.4** concurrent route | 4 | 1 | 4 | 4 idempotent route OK | 0 | 0 | **instances=1** | **PASS** |
| **CV-01.5** FR vs final approve | 4 | 1 | 4 | FR+approve winners; loser `PR_CONCURRENT_DECISION` | — | 0 | — | **PASS** (final `approved` with `fr_count≥1`; never approved without FR) |
| **CV-01.6** cancel_approved race | 3 | 1 | 3 | **1** | 2 (`PR_INVALID_TRANSITION`) | 0 | — | **PASS** (pre=`approved` → `cancelled`) |

**Gate C: PASS** · deadlocks/timeouts/unexplained errors: **none**.

### CV-01.6 applicability note

Approve was already terminal. Meaningful contention substituted: concurrent `cancel_approved` on an approved PR (engagement lock not set). Lock/precondition enforcement for engaged PRs remains covered by WP06 feature tests, not re-stressed here.

---

## 5. Cleanup

| Item | Status |
|---|---|
| IVVERIFY PR rows (this run) | **17** deleted by explicit IDs + title guard |
| Tenants 19–22 / roles / users | **preserved** (no broad tenant wipe) |
| Testing `jobs` / `failed_jobs` | cleared at start / between scenarios; empty at end of scenarios |
| Truncates / `migrate:fresh` / `db:wipe` | **not used** |

---

## 6. Observed defects

**None** blocking Gate Q or Gate C.

Residual observation: CV-01.5 recorded **`fr_count=2`** when two FR writers both returned `accepted` under race; final approval still required FR and never persisted `approved` with zero FR.

**Owner classification (2026-11-01):** **Non-blocking follow-up — accepted.** Does **not** reopen Phase IV acceptance. Required follow-up items recorded in [S04-PHASE-IV-FUNCTIONAL-ACCEPTANCE-2026-11-01.md](../../10-decisions/S04-PHASE-IV-FUNCTIONAL-ACCEPTANCE-2026-11-01.md) §4.

---

## 7. Residual risks

1. Verification harness is temporary tooling under `backend/tests/Verification/` — not a production worker supervisor.  
2. C: free space remains tight (~4.5 GB).  
3. Prior verification tenants (earlier runs) may still exist on `erp_hajj_testing`; this run cleaned only its tracked IVVERIFY PRs.  
4. Worktree remains **uncommitted**; deploy not authorized.  
5. Non-blocking `fr_count=2` follow-up remains open for future engineering (not Phase IV rework).

---

## 8. Safety confirmations

- No production DB writes.  
- No migrations / migrate execution.  
- No Git commit, push, merge, rebase, reset, clean, stash, checkout, switch, or branch creation.  
- No deployment.  
- No Phase V / S05 implementation during verification.  
- No application business-logic fixes (harness/docs only).

---

## 9. Owner acceptance (recorded)

Gate Q + Gate C evidence supported Owner **FINAL FUNCTIONAL ACCEPTANCE** of Phase IV on **2026-11-01**.  
This file remains the verification evidence record; it is not a deployment approval.
