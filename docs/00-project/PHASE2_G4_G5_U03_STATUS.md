# Phase 2 — G4 / G5 / U03 Status (Binding Carryover)

> **Updated:** 2026-11-14  
> **Must not relocate G4/G5 to Phase 3.**  
> **Production activation:** **NOT_AUTHORIZED** by this document.

## G4 — Live GPS provider

| Field | Value |
|---|---|
| Status | **BLOCKED** (technical prep delivered; live binding absent) |
| Delivered foundation | G0–G3: `NullGpsProvider`, HMAC ingest, last-known, freshness, dashboard markers |
| Technical prep | Unknown `GPS_PROVIDER` keys fail closed via `GpsProviderNotApprovedException`; checklist in [G4_G5_TECHNICAL_PREPARATION.md](../09-modules/23-executive-dashboard/G4_G5_TECHNICAL_PREPARATION.md) |
| Missing for G4 | Approved real GPS/location vendor or API contract; credentials; accuracy/update timing docs; live verification |
| Invented vendor | **Forbidden** |
| Evidence | `config/gps.php` `provider=null` default; PR #64 follow-up |

## G5 — GPS history / legal retention

| Field | Value |
|---|---|
| Status | **BLOCKED** (writer seam only; no history table) |
| Current design | Last-known only; `gps_vehicle_location_history` intentionally absent |
| Technical prep | Unbound `GpsLocationHistoryWriterInterface`; tests assert table absent |
| Missing for G5 | Explicit legal retention period, consent, access, deletion/erasure requirements |
| Invented retention | **Forbidden** |

## U03 — Complaint SLA production activation

| Field | Value |
|---|---|
| Status | **BLOCKED** (code present; production activation unset) |
| Code | Complaint SLA evaluator + ALT-COMP-SLA scanner paths exist |
| Activation | `EXECUTIVE_COMPLAINT_SLA_ACTIVATED_AT` **unset** → fail-closed |
| Non-production | `php artisan executive-alerts:verify-complaint-sla-readiness [--smoke-activate]` refuses production; in-process smoke only |
| Runbook | [S10_COMPLAINT_SLA_ACTIVATION_RUNBOOK.md](../09-modules/23-executive-dashboard/S10_COMPLAINT_SLA_ACTIVATION_RUNBOOK.md) |

## Live Browser UAT (related gate)

| Field | Value |
|---|---|
| Status | **PASS** (2026-11-14 attested disposable run) |
| Stack | `erp_hajj_phase2_uat` + attestation token |
| Does not authorize | Production deploy or Owner final acceptance by itself |
| Detail | [PHASE2_LIVE_UAT_ATTEMPT_2026-11-14.md](PHASE2_LIVE_UAT_ATTEMPT_2026-11-14.md) |
