# S04 Phase V — Real Multi-Process Demand Group Concurrency Verification Evidence

> **Verification status:** `PASS`  
> **Owner functional acceptance (Phase V):** **FINAL — GRANTED** 2026-11-01 — [S04-PHASE-V-FINAL-FUNCTIONAL-ACCEPTANCE-2026-11-01.md](../../10-decisions/S04-PHASE-V-FINAL-FUNCTIONAL-ACCEPTANCE-2026-11-01.md)  
> **MariaDB 1213 deadlocks:** **3 observed** — Owner-classified **NON-BLOCKING — ACCEPTED RESIDUAL RISK** (not claimed fixed)  
> **Mode (this verification run):** Verification only — no application logic changes · no migrations · no Git mutations · no deploy · no S05  
> **Run ID:** `dgcv_20261101200437_ddb993`  
> **Finished:** 2026-11-01T20:06:51+00:00 (orchestrator wall ~136s)  
> **Machine report:** `backend/storage/app/phase_v_verification/dgcv_20261101200437_ddb993/report.json`

---

## 1. Environment & safety preflight

| Check | Actual |
|---|---|
| Branch | `feature/s04-phase-i-supplier-registry` |
| HEAD | `09a902a0a08aa31a8686ce3824e6ee25f922a112` |
| MariaDB | **10.4.27-MariaDB** |
| Host | `127.0.0.1` |
| Database (every process) | **`erp_hajj_testing` only** |
| APP_ENV | `testing` |
| Connection | `mysql` |
| Production DB `erp_hajj` | **not used** |
| Migrations executed | **none** |
| Application code changes | **none** |
| Git commit/push/merge/etc. | **none** |
| Fixtures | Tagged `PVVERIFY {runId} …`; tracked IDs only |

### Identity gate

Every orchestrator phase and every `phase_v_demand_group_worker.php` child independently calls `phaseVAssertTestingDb()` and refuses unless:

- `app()->environment() === testing`
- config + `SELECT DATABASE()` === `erp_hajj_testing`
- host ∈ `{127.0.0.1, localhost}`
- driver/connection mysql

Sample orchestrator gate:

```json
{"app_env":"testing","connection":"mysql","database":"erp_hajj_testing","host":"127.0.0.1","version":"10.4.27-MariaDB","driver":"mysql"}
```

---

## 2. Execution method

| Item | Value |
|---|---|
| Orchestrator | `php tests/Verification/run_phase_v_demand_group_concurrency_verification.php` |
| Worker | `php tests/Verification/phase_v_demand_group_worker.php …` |
| Sync | Independent OS processes + filesystem start barrier |
| Not used | Sequential same-process “fake concurrency” |

Actions exercised via application Actions (not HTTP): `link`, `unlink`, `cancel_approved`.  
`set_engagement` is a **verification fixture only** (simulates S05 writer); not a Phase V API.

---

## 3. Scenario results

| Scenario | Result | Processes | Rounds | Attempts | Accepted | Rejected | Errors | Deadlocks |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| **DG-CV01** Same PR → different groups | **PASS** | 4 | 5 | 20 | 5 | 15 | 0 | 0 |
| **DG-CV02** Same PR → same group | **PASS** | 4 | 5 | 20 | 5 | 15 | 0 | 0 |
| **DG-CV03** Unlink vs relink | **PASS** | 4 | 5 | 20 | 10 | 10 | 0 | 0 |
| **DG-CV04** Cancel-approved vs mutate | **PASS** | 4 | 5 | 20 | 7 | 10 | 0 | **3** |
| **DG-CV05** Engagement freeze race | **PASS** | 4 | 5 | 20 | 5 | 15 | 0 | 0 |
| **DG-CV06** Cross-tenant | **PASS** | 4 | 3 | 12 | 6 | 6 | 0 | 0 |

**Totals:** 112 process attempts · unexplained non-deadlock errors **0** · overall **PASS**.

---

## 4. Final membership invariants observed

| Invariant | Evidence |
|---|---|
| At most one active membership per PR | CV01/CV02: every round `active_count=1`; losers `DG_PR_ALREADY_GROUPED` |
| No duplicate active rows | UNIQUE + observed `active_count≤1` across all scenarios |
| Historical membership retained | CV03/CV04: unlinked history rows present with `unlinked_at` when inactive |
| Cancelled PR cannot remain active member | CV04: all rounds `pr_status=cancelled`, `active_count=0`, `open_history=0` |
| Engagement freeze | CV05: `engaged_ref=S05-VERIFY-FIXTURE`, group `frozen`, mutate rejects; fixture fields not cleared by Phase V |
| Cross-tenant isolation | CV06: cross attempts not accepted; leak counters `0/0`; legitimate same-tenant links accepted |

---

## 5. DG-CV04 cancel-approved notes

- Exactly **one** `cancel_approved` accepted per round; second cancel → `PR_INVALID_TRANSITION`.
- Concurrent link after cancel → `DG_PR_NOT_APPROVED`.
- **3 MariaDB 1213 deadlocks** observed across rounds 2/4/5 when manual `unlink` raced cancel’s internal unlink.
- Deadlocks did **not** leave cancelled PRs with active membership.
- Documented as residual contention risk; **no application retry added** (verification-only mandate).

---

## 6. Engagement freeze (DG-CV05)

- Fixture `set_engagement` wrote `procurement_engaged_at` + `procurement_engagement_ref=S05-VERIFY-FIXTURE`.
- After each round: ref preserved; group status `frozen`; active membership for engaged PR remained consistent (`active_count=1`).
- Competing link/unlink attempts rejected (no unexplained errors).
- Confirms Phase V path did not rewrite/clear engagement fields in these races.

---

## 7. Cleanup

Tracked fixture deletion only (`PVVERIFY` titles / tracked IDs):

| Deleted | Count |
|---|---:|
| active memberships | 26 |
| history items | 36 |
| demand groups | 46 |
| purchase requests | 36 |
| cleanup errors | 0 |

Tenants/users preserved (`tracked_tenants_preserved`: 9, 10).

---

## 8. Residual risks

1. **Cancel vs manual unlink deadlock (1213)** — final invariants held; optional future Action-level deadlock retry is an Owner/engineering decision (not done here).  
2. **No HTTP-layer multi-process suite** — Actions-level contention verified; Sanctum/HTTP serialization not re-proven here.  
3. **Deadlock retry not implemented** — Owner accepted residual risk; investigation/retry remains follow-up.  
4. **S04 overall closure still PENDING** — Phase V acceptance does not close S04.

---

## 9. Files created (verification only)

- `backend/tests/Verification/phase_v_db_gate.php`
- `backend/tests/Verification/phase_v_demand_group_worker.php`
- `backend/tests/Verification/run_phase_v_demand_group_concurrency_verification.php`
- `backend/storage/app/phase_v_verification/dgcv_20261101200437_ddb993/report.json` (+ per-round worker JSON)
- This evidence document

---

## 10. Confirmations

- No application logic changes for this verification task.  
- No migrations / no migrate execution.  
- No Git-changing operations.  
- No production access or deployment.  
- No S05 implementation.  
- Dirty working tree preserved.
