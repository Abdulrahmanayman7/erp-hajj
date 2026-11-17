# G4 — Real Vendor Onboarding Runbook (Prep Only)

> **Status:** TECHNICAL READINESS — LIVE BINDING **BLOCKED**  
> **Date:** 2026-11-16  
> **Does not authorize:** production GPS Live, invented vendors, or G4 COMPLETE.  
> **Prerequisite:** GPS Integration Hub merged (PR #66) on `develop`.

## Purpose

Provide a verified, provider-independent onboarding path for a **named** Owner-approved GPS vendor using the existing hub adapters (`rest_pull`, `webhook`, `traccar`). Live outbound remains fail-closed until Owner authorization is recorded.

## Adapter selection

| Vendor shape | Hub method | Notes |
|---|---|---|
| HTTPS JSON pull | `rest_pull` | Field map + SSRF guard + optional host allowlist |
| Vendor push HMAC | `webhook` | Existing `POST /api/v1/gps/ingest` |
| Traccar-compatible | `traccar` | `/api/server` + `/api/positions` |

## Pre-flight (disposable UAT only)

1. Database name matches `/^erp_hajj_.+_uat$/` and Browser UAT attestation is enabled.  
2. `GPS_PROVIDER=null` (process-wide Null binding).  
3. `GPS_LIVE_OUTBOUND_AUTHORIZED=false` until the disposable smoke step.  
4. `GPS_LIVE_OUTBOUND_AUTHORIZED_IN_PRODUCTION=false` always for this prep.  
5. Prefer `GPS_OUTBOUND_HOST_ALLOWLIST=<vendor-host>`.  
6. Secrets only in encrypted provider credentials / vault — never in git.  
7. Run: `php artisan gps:verify-g4-readiness` (refuses production).

## Onboarding steps (after Owner names the vendor)

| # | Step | Evidence |
|---|---|---|
| 1 | Record vendor name, API contract URL, update interval, device identity scheme | Owner decision note |
| 2 | Create hub provider (`/app/gps-providers`) with the matching method | Provider code + masked credentials |
| 3 | Map `external_device_id` ↔ season vehicles (`/app/gps-devices`) | Binding rows |
| 4 | On disposable UAT only: set `GPS_LIVE_OUTBOUND_AUTHORIZED=true` | Env diff (UAT) |
| 5 | Run **Test connection** then **Sync** (or `gps:sync-providers`) | Health status + last-known |
| 6 | Confirm Leaflet fleet map markers are non-simulated | Screenshot + DB `is_simulated=0` |
| 7 | Confirm cross-tenant isolation + secret masking | Pest + manual denial |
| 8 | Unset live outbound flags after UAT unless Owner authorizes persistence | Env rollback |
| 9 | Record Owner G4 Live authorization (separate act) before any production claim | Written approval |

## Mock / disposable verification (no real vendor required)

- Pest: `GpsHubTest` — REST + Traccar `Http::fake` paths when live outbound authorized in non-production; default blocked.  
- Browser UAT: `docs/audits/gps-hub-browser-uat/REPORT.md` — DEMO simulator + fail-closed Traccar test.  
- Optional: local disposable Traccar container is allowed for engineering proof; still does **not** authorize G4 Live production.

## Explicit non-goals

- Do not invent a live production vendor name or credentials.  
- Do not enable `GPS_LIVE_OUTBOUND_AUTHORIZED_IN_PRODUCTION` without Owner written authorization.  
- Do not claim G4 COMPLETE from this runbook alone.  
- Do not create GPS history tables (G5 remains separate).
