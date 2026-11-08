# S09 — Validation Debt Risk Register (D13=B)

> **Owner decision D13=B (2026-11-08):** Parallel implementation planning with **tracked** S05/S06/S07 validation debt.  
> Parallel ≠ waiver of security merge blockers.  
> Release / Owner-acceptance certificates remain separate from “code may be designed/built”.

---

## Classification

| Class | Meaning |
|---|---|
| **CRITICAL_RELEASE_BLOCKER** | Must not claim module/phase release or production readiness until cleared |
| **PARALLEL_VALIDATION_DEBT** | May proceed with S09 design/coding **if D16 authorizes**, while debt is tracked |
| **HARD_MERGE_GATE** | Always blocks merge of S09 PRs (tenant isolation, authz, N+1 CI) — independent of S05/S06/S07 certificates |

---

## Register

| ID | Stream | Debt | Class | Evidence (ROADMAP / module docs) | Mitigation while parallel |
|---|---|---|---|---|---|
| VD-S05-01 | S05 Procurement | No dedicated **final Owner-acceptance certificate** located (code merged) | PARALLEL_VALIDATION_DEBT | ROADMAP S05 row | Treat S05 SoR as merged behavior; raise CR if KPI SoR drifts; do not invent acceptance certificate |
| VD-S06-01 | S06 Season projects | **TECHNICAL_VALIDATION_DEFERRED**; MySQL 8 deferred; Browser E2E not run; not Owner Accepted / deploy-ready | CRITICAL_RELEASE_BLOCKER for **S06 release claims**; PARALLEL for **S09 coding** under D13=B | `S06_FINAL_CLOSURE_REPORT.md`; ROADMAP | KPI J\* tests on CI DB; document MySQL 8 residual; never mark S09 deploy-ready on S06 debt alone |
| VD-S06-02 | S06 | Soft-delete / progress rollup correctness for J07 | PARALLEL_VALIDATION_DEBT | Formula matrix SoR | Explicit Pest fixtures for progress_percent + soft deletes |
| VD-S07-01 | S07 Risks & complaints | Merge ≠ final Owner acceptance assumed | PARALLEL_VALIDATION_DEBT | ROADMAP S07; UAT report exists | Rely on module Pest + S09 R\* formula tests; track acceptance separately |
| VD-SEC-01 | All | Cross-tenant / season isolation failures | HARD_MERGE_GATE | MULTI_TENANCY / D05/D08 | Stop coding path; fix before merge |
| VD-SEC-02 | All | Authorization omit / permission leaks | HARD_MERGE_GATE | D08 | Stop; fix before merge |
| VD-PERF-01 | S09 | Provisional 15 queries / 500ms unmet without formula/security change | PARALLEL → escalate Owner | D09 | Report deviation; no silent formula change |

---

## Release vs coding (D13=B)

| Activity | Allowed under D13=B (after D16)? | Blocked until debt cleared? |
|---|---|---|
| S09 design docs | Yes (already) | — |
| S09 application coding | Only after **D16** | — |
| Merge S09 PR with green isolation/authz/N+1 | Per PR policy | Isolation/authz failures always block |
| Claim S06/S07/S05 final Owner Accepted | No | Until their own Owner acts |
| Production deploy of S09 | No | Separate deploy authorization + debt review |

---

## Status

```
S09-D13 = OWNER_APPROVED (B)
VALIDATION_DEBT_TRACKED
NO_APPLICATION_CODE
```
