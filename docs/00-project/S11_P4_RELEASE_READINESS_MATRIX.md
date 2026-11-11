# S11-P4 Release Readiness Matrix

> **Status:** Evidence-only matrix (no invented Owner/security approvals)  
> **Baseline:** `origin/develop` @ merge of PR #59 (`c7b4f54`)  
> **Branch:** `feature/s11-p4-release-readiness`  
> **Updated:** 2026-11-11

## Legend

| Status | Meaning |
|---|---|
| **PASS** | Automated or browser evidence recorded as passing in this workspace / CI |
| **FAIL** | Evidence of failure |
| **NOT_RUN** | Harness exists or is required, but could not execute safely here |
| **BLOCKED** | Explicit Phase 2 carryover / external dependency |
| **PENDING_APPROVAL** | Needs human Owner or independent security acceptance |

## Matrix

| Module / stream | Automated test evidence | Browser UAT evidence | Security review status | Outstanding defects | Owner acceptance | Release readiness |
|---|---|---|---|---|---|---|
| Foundation (Seasons & Pilgrims) | PASS (historical Pest/Vitest + CI) | PASS (foundation-browser-uat; Owner 2026-10-30) | Merged baseline | Non-blocking season-close UI note | PASS (2026-10-30) | PASS |
| S01 Fleet | PASS | PASS (`s01-fleet-browser-uat`) | Merged baseline | Live GPS out of S01 | PASS (2026-10-30) | PASS (registry); GPS gated |
| S02 Transport / movements | PASS (incl. MySQL concurrency) | PASS (`s02-transport-browser-uat`) | Merged baseline | GPS gated | PASS (2026-10-30) | PASS |
| S03 Accommodation | PASS (Feature + MySQL concurrency) | PASS (`s03-accommodation-browser-uat`) | Merged baseline | ADR-0019 still PROPOSED | PASS (2026-10-31) | PASS |
| S04 Suppliers / PRs | PASS (Feature + MySQL concurrency in CI) | NOT_RUN (no dedicated S04 browser suite in-repo) | Merged baseline | Residual MariaDB notes in closure docs | PASS functional closure 2026-11-02 | PASS functional; browser PENDING |
| S05 Procurement cycle | PASS (MySQL concurrency in CI) | NOT_RUN | Merged (PR #41/#42) | — | PENDING_APPROVAL (certificate not located) | PENDING_APPROVAL |
| S06 Season projects | PASS (SQLite Feature; MySQL number concurrency via S11-P3) | NOT_RUN (prior S06 E2E deferred; S11-P4 live UAT NOT_RUN) | Merged (PR #45) | Historical MySQL deferral mostly lifted by S11-P3 CI wire | PENDING_APPROVAL (not Owner COMPLETE) | PENDING_APPROVAL |
| S07 Risks & complaints | PASS (module tests; MySQL number concurrency) | NOT_RUN (S11-P4 live UAT NOT_RUN) | Merged (PR #44) | — | PENDING_APPROVAL (not assumed) | PENDING_APPROVAL |
| S08 Seasonal templates | PASS on feature branch MySQL CI historically; docs drift vs merge state | UAT-09/10 PASS (historical); UAT-04/08 exceptions | PENDING_APPROVAL / NOT_MERGED stream docs | Documentation drift; merge/deploy not authorized by acceptance alone | PASS Owner 2026-11-08 on feature branch | BLOCKED (NOT_MERGED / NOT_DEPLOYED per docs) |
| S09 Operational KPIs | PASS (Phase 1 merged tests) | NOT_RUN | Technical APPROVE_RECOMMENDED; GitHub Approve NOT_RECORDED | Independent approval gap | PENDING_APPROVAL | PENDING_APPROVAL |
| S10 Executive dashboard | PASS (CI GPS ingest + alerts; Vitest panels) | NOT_RUN / PENDING (S10 Browser UAT still open) | PENDING_APPROVAL (human review PRs #52–#55) | G4_LIVE, G5, U03 SLA unset | Admin CLOSED_WITH_CARRYOVER | BLOCKED for full GPS/SLA claims |
| S11 Integration / hardening | PASS S11-P1/P2/P3 automated; S11-P4 Vitest a11y + CI observability | Live journeys NOT_RUN; shell mock see suite folder | Relies on prior PR reviews | Remaining Phase 2 blockers listed below | PENDING_APPROVAL for S11 closure | NOT_RUN / PARTIAL |
| S12 Phase handoff | NOT_RUN | NOT_RUN | — | No Phase 2 UAT minutes | PENDING_APPROVAL | NOT_RUN |

## Cross-cutting gates (must stay unresolved without evidence)

| Gate | Status |
|---|---|
| S05 Owner acceptance paperwork | PENDING_APPROVAL |
| S08 documentation drift / merge posture | BLOCKED / docs conflict — do not invent merge |
| S09 independent security approval | PENDING_APPROVAL |
| S10 independent human security review (#52–#55) | PENDING_APPROVAL |
| S10 Browser UAT | NOT_RUN / PENDING |
| G4 live GPS provider | BLOCKED |
| G5 GPS history + legal retention | BLOCKED |
| U03 production SLA activation | BLOCKED (`EXECUTIVE_COMPLAINT_SLA_ACTIVATED_AT` unset) |

## S11-P4 deliverables in this branch

- Browser UAT harness for operational journeys + shell RTL/a11y mock
- Arabic RTL / a11y fixes (skip link, Arabic panel `aria-label`s, confirm dialog focus)
- CI MySQL concurrency observability + split Accommodation steps + orphan worker cleanup
- This readiness matrix (evidence-separated statuses only)

## Browser UAT safety guarantees (PR #60 remediation)

| Guarantee | Mechanism | Evidence |
|---|---|---|
| Live UAT never trusts `UAT_DATABASE` alone | `GET /api/v1/browser-uat/attestation` returns the **live** Laravel connection database name only when `BROWSER_UAT_ATTESTATION_ENABLED=true`, `APP_ENV` ∈ {local, testing}, non-empty `BROWSER_UAT_ATTESTATION_TOKEN`, and matching `X-Browser-Uat-Token` (timing-safe); production/staging always 404 | Pest `BrowserUatAttestationTest`; harness `uat-attestation.mjs` + `uat-attestation.test.mjs` |
| Shared UAT token required | Empty/missing/wrong token ⇒ 404; token never logged or returned; harness sends token only to the configured attestation URL | Pest missing/wrong/valid token cases; `attestationRequestHeaders()` unit tests |
| Cache busting on success | Successful attestation sets `Cache-Control: no-store` | Pest cache assertion |
| Public `/api/v1/health` unchanged | No database fields added to health | Pest health assertion in attestation suite |
| Disposable naming policy | Attested name must match `/^erp_hajj_.+_uat$/`; optional `UAT_DATABASE` is expected-value only | `evaluateAttestation()` rejects `erp_hajj` even if env claims `erp_hajj_s11_uat` |
| Fail closed | Timeouts, redirects, non-JSON, 4xx/5xx, missing marker → NOT_RUN / refuse | `run-uat.mjs` uses `redirect: 'manual'` + attestation evaluator |
| Mock UAT network isolation | Classify API/sanctum (including same-origin) **before** allowing FE assets; deny other origins by default; no dependency on `VITE_API_URL` | `network-policy.mjs` + `network-policy.test.mjs`; mock runner probes + `networkLog` asserts no `/api`/`/sanctum` continues |

**Live operational Browser UAT:** **NOT_RUN** unless executed against an API that attests a disposable `erp_hajj_*_uat` database.

## Release claim

**Phase 2 is not release-complete.** S11-P4 improves readiness evidence and hardening but does **not** clear Owner/security/GPS/SLA gates above.
