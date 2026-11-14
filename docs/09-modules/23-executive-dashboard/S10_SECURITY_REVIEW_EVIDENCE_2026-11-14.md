# S10 — Security Hardening Evidence (2026-11-14)

> **Independent human approval of PRs #52–#55:** still **PENDING** (see [S10_SECURITY_REVIEW_CHECKLIST.md](S10_SECURITY_REVIEW_CHECKLIST.md))  
> This document records engineering evidence + one confirmed fix. It does **not** grant security Approve.

## In-scope PRs

| PR | Topic | Human approval |
|---|---|---|
| #52 | A4–A5 + G0–G3 GPS | **PENDING** |
| #53 | GPS nonce / skew retention | **PENDING** |
| #54 | U01/U02/U03 deferred alerts | **PENDING** |
| #55 | U02 UI + readiness (no SLA activation) | **PENDING** |

## Confirmed vulnerability fixed (this branch)

| ID | Finding | Fix |
|---|---|---|
| GPS-ORACLE-1 | Distinct 403 messages for unknown tenant / missing webhook secret / bad HMAC enabled tenant & secret-config enumeration | Uniform `GpsIngestDeniedException` → `403` + `GPS_INGEST_DENIED` + message `Invalid device.`; internal `Log::notice` reason only (no secrets) |

Regression: `GpsIngestTest` — identical body for the three denial classes (**PASS**).

## Review areas re-checked (no new confirmed exploit)

| Area | Status |
|---|---|
| Tenant isolation / cross-tenant 404 | Covered by Executive* + OperationalKpis Pest |
| Dual-page + domain omit | Covered |
| Complaint SLA fail-closed when unset | Covered (`DeferredAlertsU01U02U03Test`) |
| GPS HMAC / nonce / monotonic last-known | Covered (`GpsIngestTest`) |
| CSV formula injection / export audit | Covered (`ExecutiveReportsTest`) |
| Complaint bodies in alerts/notifications | Not exposed (titles/numbers) |

## Hardening residuals (not claimed fixed)

- Successful GPS ingest still lacks dedicated security-event audit row  
- Executive report JSON GET remains unaudited (CSV is audited)  
- Independent human Approve still required  

## Approver block

Use [S10_SECURITY_REVIEW_CHECKLIST.md](S10_SECURITY_REVIEW_CHECKLIST.md) approver block. Do not fill here from automation.
