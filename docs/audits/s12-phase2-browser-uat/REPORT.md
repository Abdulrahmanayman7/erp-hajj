# S12 Phase 2 Browser UAT Report

> **Date:** 2026-11-14  
> **Production:** **NOT_AUTHORIZED**  
> **Rule:** Mock PASS ≠ Live PASS  
> **Owner final Phase 2 acceptance:** **NOT GRANTED** by this report alone

## Mock operational shell

| Field | Value |
|---|---|
| Harness | `run-operational-mock-uat.mjs` |
| FE | `http://127.0.0.1:5173` |
| Verdict | **PASS** |
| Journeys | S12-J01…J09 route shells + RTL/a11y + network isolation |
| Isolation | mocked=120 denied=1; same-origin `/api` mocked; external denied |
| Unit policy/attestation | **15/15 PASS** (`node --test`) |

## Live operational

| Field | Value |
|---|---|
| Harness | `run-live-uat.mjs` |
| Verdict | **PASS** |
| Started | 2026-11-14T12:58:04.599Z |
| Database | `erp_hajj_phase2_uat` (attested disposable; `/^erp_hajj_.+_uat$/`) |
| FE | `http://127.0.0.1:5173` |
| API | `http://127.0.0.1:8000` |
| Evidence | `evidence/2026-11-14T12-58-04-599Z_live_report.json` + journey screenshots |
| Journeys | S12-J01…J10 all **PASS** (owner shells + viewer fresh context) |

### Provisioning notes (local disposable only)

1. Created `erp_hajj_phase2_uat`; migrated with MariaDB-safe `executive_alerts` timestamps  
2. Bootstrapped platform admin + tenant owner/viewer (`bootstrap-users.php`)  
3. Laravel serve with `DB_DATABASE=erp_hajj_phase2_uat` + attestation token (workspace `.env` left on `erp_hajj`)  
4. Vite with `VITE_API_URL=http://127.0.0.1:8000` (host alignment)

## Explicit non-claims

- This report does **not** authorize production deploy.  
- This report does **not** grant Phase 2 final Owner acceptance by itself.  
- G4 Live / G5 retention / U03 production activation remain **BLOCKED**.  
- Independent security Approves remain **PENDING**.
