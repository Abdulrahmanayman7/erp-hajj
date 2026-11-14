# S12 — Deployment Prerequisites

> **Package date:** 2026-11-11  
> **Production release:** **NOT_AUTHORIZED**  
> This document lists prerequisites only. It does **not** authorize deploy.

## 1. Environment & infrastructure

| Prerequisite | Status expectation | Notes |
|---|---|---|
| Target environment identity documented | Required | Staging vs production names must be explicit |
| Disposable / non-production DBs for UAT | Required for live UAT | Pattern `erp_hajj_*_uat` only |
| Production DB migrations plan | Required before prod | Not executed by S12 package |
| Secrets management (no repo secrets) | Required | Attestation tokens, APP_KEY, DB creds out of git |
| Backup / rollback plan | Required before prod | Owner/ops act |
| TLS / domain / reverse proxy | Required before prod | Ops |

## 2. Application gates

| Prerequisite | Blocking for production? | Current posture |
|---|---|---|
| `develop` baseline with S01–S11 merges | Yes | Evidence-based on GitHub |
| CI SUCCESS on release candidate SHA | Yes | Record actual run; do not invent |
| Live Browser UAT PASS on attested disposable DB | Strongly yes for acceptance | Often **NOT_RUN** until stack provided |
| Owner acceptance of S11 closure | Yes for S11 admin close | **APPROVED** 2026-11-14 (`CLOSED_WITH_CARRYOVER`) |
| Owner acceptance of S12 / Phase 2 handoff | Yes for phase close | Pending |
| Independent security approvals (S09/S10/S11 as required) | Yes where governance requires | Pending / not recorded |
| G4 live GPS provider | Blocks “ops map Live complete” | **BLOCKED** |
| G5 GPS retention | Blocks GPS history claims | **BLOCKED** |
| U03 SLA activation | Blocks SLA-on claims | **BLOCKED** |

## 3. Explicitly out of this package

- Running migrations against unknown or production databases
- Creating production credentials
- Merging to `main` / tagging a production release
- Claiming production authorization
