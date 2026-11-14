# S12 — Defect & Regression Register

> **Package date:** 2026-11-11  
> **Rule:** Do not weaken or skip failing tests to obtain green results.  
> **Production:** **NOT_AUTHORIZED**

## Defects discovered in this S12 execution

| ID | Source | Severity | Summary | Resolution | Regression coverage |
|---|---|---|---|---|---|
| — | — | — | No new application defects discovered during S12 package preparation / mock shell UAT at commit time | N/A | N/A |

## Documentation inconsistencies reconciled (objective evidence)

| ID | Item | Before | After | Evidence |
|---|---|---|---|---|
| DOC-S08-01 | S08 merge posture | `OWNER_ACCEPTED / NOT_MERGED / NOT_DEPLOYED` in matrix/status | `OWNER_ACCEPTED / MERGED / NOT_DEPLOYED` | GitHub PR [#46](https://github.com/Abdulrahmanayman7/erp-hajj/pull/46) **MERGED** 2026-11-08T03:57:25Z @ `3a18b161d4b532fffeb81527357e00fd987e19dc` |

## Known carryover defects / product gaps (not fixed in S12)

| ID | Item | Status | Notes |
|---|---|---|---|
| C-G4 | Live GPS provider | **BLOCKED** | Requires authorized real source |
| C-G5 | GPS history + retention | **BLOCKED** | Legal/retention decision + impl |
| C-U03 | Complaint SLA activation | **BLOCKED** | Production authorization unset |
| C-S04-RES | S04 residual follow-ups | Open | Preserved from S04 closure (`fr_count=2`; MariaDB notes) |

## Regression suites expected green on this branch

| Suite | Scope | Notes |
|---|---|---|
| Pest (Laravel CI) | Full Feature/Unit as in workflow | Do not claim until local/CI results recorded |
| Vitest + `vue-tsc` + production build | Frontend | Do not claim until recorded |
| MySQL 8 concurrency | Disposable CI DB only | Post-merge S11 run tracked separately |
| S12 network-policy + attestation unit tests | Harness | Local `node --test` |
