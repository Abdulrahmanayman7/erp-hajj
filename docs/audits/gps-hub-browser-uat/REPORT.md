# GPS Integration Hub — Live Browser UAT Report

> **Date:** 2026-11-16  
> **PR:** [#66](https://github.com/Abdulrahmanayman7/erp-hajj/pull/66)  
> **Production:** **NOT_AUTHORIZED**  
> **G4 Live:** **BLOCKED**  
> **G5 History:** **BLOCKED**  
> **Independent security Approve:** **PENDING**

## Live Browser UAT

| Field | Value |
|---|---|
| Harness | `docs/audits/gps-hub-browser-uat/run-live-uat.mjs` |
| Verdict | **PASS** |
| Started | 2026-11-16T13:49:44.306Z |
| Database | `erp_hajj_gps_uat` (attested disposable; `/^erp_hajj_.+_uat$/`) |
| Driver note | Local XAMPP MariaDB 10.4.32 (disposable). MySQL 8 migration/CI proof is separate (PR #66 CI SUCCESS). |
| FE | `http://127.0.0.1:5173` |
| API | `http://127.0.0.1:8000` |
| Evidence | `evidence/2026-11-16T13-49-44-305Z_*` |
| Attestation | `BROWSER_UAT_ATTESTATION_ENABLED=true` + token header → database=`erp_hajj_gps_uat` |

### Journeys (all PASS)

| Step | Result |
|---|---|
| Owner login + RTL | PASS |
| Provider admin page | PASS |
| Create REST / Webhook / Traccar / Simulator | PASS |
| Credentials masking (raw secrets absent from DOM) | PASS |
| Traccar connection test fail-closed (`last_health_status=blocked`) | PASS (DB confirmed) |
| DEMO simulator controls | PASS |
| Device link + second vehicle | PASS |
| Duplicate vehicle assignment attempt | PASS |
| Simulator sync (`Applied 2 position(s).`) | PASS |
| Leaflet fleet map + DEMO banner | PASS |
| Map filters + mobile RTL | PASS |
| Viewer lacks manage controls; map allowed with `gps.map.view` | PASS |

### DB confirmation after UAT

- Providers: `rest-uat`, `wh-uat`, `traccar-uat` (**blocked**), `demo-sim` (**healthy**)
- Bindings: `bus-device-1`→vehicle 1, `bus-device-2`→vehicle 2
- Last-known: 2 simulated rows (`source=simulator`, `is_simulated=1`)

## Migration compatibility

| Check | Result |
|---|---|
| Fresh install on `erp_hajj_gps_uat` (`migrate:fresh`) | **PASS** — includes `360001`–`360003` |
| Upgrade path on `erp_hajj_gps_upgrade_uat` (rollback hub → seed binding/last-known → migrate hub) | **PASS** — binding + lat preserved; hub columns present; history table absent |
| MySQL 8 CI on PR #66 HEAD | **PASS** (Backend MySQL 8 job SUCCESS, including S10 GPS ingest suite) |

## Defects / findings addressed

1. **UAT harness** used Playwright `getByTestId` (`data-testid`) while the app uses `data-test` — fixed in harness.  
2. **GPS permissions are S10 explicit-assignment-only** (not templated onto Tenant Owner). Bootstrap now grants GPS perms explicitly for admin journeys; viewer remains without `gps.providers.manage` / `gps.devices.manage`.  
3. **Flash race** after Test/Sync — cleared flash before mutation (frontend polish).  
4. No product defect required changing PR #64 HMAC/oracle protections.

## Explicit non-claims

- No live vendor connection was established or claimed.  
- Traccar/REST outbound remain fail-closed without G4 authorization.  
- Simulator is DEMO only; not operational truth.  
- This report does **not** authorize merge, production deploy, G4, or G5.
