# Phase 2 — G4 / G5 / U03 Status (Binding Carryover)

> **Updated:** 2026-11-14  
> **Must not relocate G4/G5 to Phase 3.**  
> **Production activation:** **NOT_AUTHORIZED** by this document.

## G4 — Live GPS provider

| Field | Value |
|---|---|
| Status | **BLOCKED** |
| Delivered foundation | G0–G3: `NullGpsProvider`, HMAC ingest, last-known, freshness, dashboard markers |
| Missing for G4 | Approved real GPS/location vendor or API contract; credentials; accuracy/update timing docs; live verification |
| Invented vendor | **Forbidden** |
| Evidence | [S10_COMPLETION_STATUS.md](../09-modules/23-executive-dashboard/S10_COMPLETION_STATUS.md); `config/gps.php` `provider=null` default |

## G5 — GPS history / legal retention

| Field | Value |
|---|---|
| Status | **BLOCKED** |
| Current design | Last-known only; `gps_vehicle_location_history` intentionally absent |
| Missing for G5 | Explicit legal retention period, consent, access, deletion/erasure requirements |
| Invented retention | **Forbidden** |

## U03 — Complaint SLA production activation

| Field | Value |
|---|---|
| Status | **BLOCKED** (code present; production activation unset) |
| Code | Complaint SLA evaluator + ALT-COMP-SLA scanner paths exist |
| Activation | `EXECUTIVE_COMPLAINT_SLA_ACTIVATED_AT` **unset** → fail-closed |
| Runbook | [S10_COMPLAINT_SLA_ACTIVATION_RUNBOOK.md](../09-modules/23-executive-dashboard/S10_COMPLAINT_SLA_ACTIVATION_RUNBOOK.md) |
| Non-production | Safe to smoke only on disposable env with explicit temporary activation |

## Live Browser UAT (related gate)

| Field | Value |
|---|---|
| Status | **NOT_RUN** (this delivery cycle) |
| Blocker | No positively attested disposable `erp_hajj_*_uat` Laravel stack; workspace `.env` points at `erp_hajj` (non-disposable) |
| Mock | S12 mock operational UAT **PASS** (not a substitute) |
