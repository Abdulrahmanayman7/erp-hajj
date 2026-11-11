# S11-P4 Browser UAT Report

> **Suite:** `docs/audits/s11-p4-browser-uat`  
> **Branch intent:** `feature/s11-p4-release-readiness`  
> **Date:** 2026-11-11

## Harnesses

| Script | Mode | Requires |
|---|---|---|
| `run-uat.mjs` | Live API + disposable `erp_hajj_*_uat` DB | Vite + Laravel bound to verified UAT DB |
| `run-shell-rtl-a11y-mock-uat.mjs` | Mocked `/api` + `/sanctum` | Vite only (no Laravel/DB) |

## Journeys covered (live harness)

1. Login and permission-aware navigation (owner + viewer)
2. Season selection / tenant shell
3. Pilgrim → Transport → Movements
4. Pilgrim → Accommodation
5. Suppliers → Procurement approval inbox
6. Seasonal Projects
7. Risks / Complaints → Executive alerts surface
8. Operational KPIs → Executive Dashboard
9. RTL + skip-to-content check on authenticated shell

## Execution results (this workspace)

| Harness | Result | Reason |
|---|---|---|
| Live operational UAT (`run-uat.mjs`) | **NOT_RUN** | Laravel API unreachable at `http://127.0.0.1:8000` (connection refused). No positively verified disposable `erp_hajj_*_uat` DB bound to an API process. Harness refuses unknown/production DBs. |
| Shell RTL/a11y mock UAT (`run-shell-rtl-a11y-mock-uat.mjs`) | **PASS** (2026-11-11 local) | Vite @ `127.0.0.1:5173` with mocked `/api`+`/sanctum`. 17/17 steps: RTL `html[dir=rtl][lang=ar]`, skip-to-content, main landmark, keyboard focus, mobile+desktop screenshots, Phase 2 route shell. Does **not** substitute for live operational UAT. |

## Safety gate

`run-uat.mjs` exits **NOT_RUN** unless `GET /api/v1/browser-uat/attestation` succeeds and the **attested** live database matches `/^erp_hajj_.+_uat$/`.  
`UAT_DATABASE` is optional and only compared to the attestation — never treated as proof.  
`/api/v1/health` is not used for database identity.  
Mock harness classifies `/api` and `/sanctum` (including same-origin) before any FE `continue()`, so traffic never depends on `VITE_API_URL`.  
No migrations or browser UAT are run against production or unnamed databases.

## Evidence

Screenshots and JSON reports under `evidence/` (generated when a harness runs).
