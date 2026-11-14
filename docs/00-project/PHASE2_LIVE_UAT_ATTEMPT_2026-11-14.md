# Phase 2 Live Browser UAT Attempt — 2026-11-14

> **Verdict:** **NOT_RUN**  
> **Mode separation:** Mock PASS must not be reported as Live PASS.

## Environment inspection

| Check | Result |
|---|---|
| `backend/.env` `APP_ENV` | `local` |
| `backend/.env` `DB_DATABASE` | `erp_hajj` — **fails** disposable policy `/^erp_hajj_.+_uat$/` |
| Attestation endpoint | Not exercised against a safe stack (would fail closed without token + disposable attested name) |
| Ports 8000 / 5173 | Not serving UAT stack at inspection time |
| Synthetic-only policy | Respected — no production DB access attempted |

## Decision

Live operational Browser UAT was **not** executed. Provisioning or migrating against `erp_hajj` is forbidden by workspace safety rules.

## Required to run Live UAT later

1. Disposable DB named `erp_hajj_<stream>_uat`  
2. Laravel with `BROWSER_UAT_ATTESTATION_ENABLED` + shared token  
3. Positive attestation response matching the live connection name  
4. Frontend + API reachable  
5. Synthetic seed users only  

Harness: `docs/audits/s12-phase2-browser-uat/run-live-uat.mjs`
