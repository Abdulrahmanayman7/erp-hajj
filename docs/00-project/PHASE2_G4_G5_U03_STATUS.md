# Phase 2 — G4 / G5 / U03 Status (Binding Carryover)

> **Updated:** 2026-11-16  
> **Must not relocate G4/G5 to Phase 3.**  
> **Production activation:** **NOT_AUTHORIZED** by this document.  
> **Develop baseline:** `f3c4c44431a533f225d9f0977d26759026642557` (PRs #63–#66 MERGED)

## G4 — Live GPS provider

| Field | Value |
|---|---|
| Status | **BLOCKED** (hub + readiness prep delivered; live binding absent) |
| Delivered foundation | G0–G3 + Integration Hub (PR #66): providers, adapters, device mapping, Leaflet map, DEMO simulator |
| Technical prep | Fail-closed unknown `GPS_PROVIDER`; `GpsOutboundGate`; SSRF; `gps:verify-g4-readiness`; onboarding runbook |
| Missing for G4 | Approved named vendor/API contract; credentials vault; Owner Live authorization; live verification on disposable UAT |
| Invented vendor | **Forbidden** |
| Evidence | `config/gps.php`; [G4_VENDOR_ONBOARDING_RUNBOOK.md](../09-modules/23-executive-dashboard/G4_VENDOR_ONBOARDING_RUNBOOK.md); GPS Browser UAT PASS |

## G5 — GPS history / legal retention

| Field | Value |
|---|---|
| Status | **BLOCKED** (contracts only; no history table) |
| Current design | Last-known only; `gps_vehicle_location_history` intentionally absent |
| Technical prep | Unbound `GpsLocationHistoryWriterInterface`; `gps.history.*` contracts (`enabled=false`, retention TBD); access-control design |
| Missing for G5 | Explicit legal retention period, consent, access, deletion/erasure requirements + Owner auth |
| Invented retention | **Forbidden** |
| Contract | [G5_RETENTION_POLICY_CONTRACT.md](../09-modules/23-executive-dashboard/G5_RETENTION_POLICY_CONTRACT.md) |

## U03 — Complaint SLA production activation

| Field | Value |
|---|---|
| Status | **BLOCKED** (code present; production activation unset) |
| Code | Complaint SLA evaluator + ALT-COMP-SLA scanner paths exist |
| Activation | `EXECUTIVE_COMPLAINT_SLA_ACTIVATED_AT` **unset** → fail-closed |
| Non-production | `executive-alerts:verify-complaint-sla-readiness [--smoke-activate]` refuses production; checklist + deactivation guidance |
| Runbook | [S10_COMPLAINT_SLA_ACTIVATION_RUNBOOK.md](../09-modules/23-executive-dashboard/S10_COMPLAINT_SLA_ACTIVATION_RUNBOOK.md) |

## Live Browser UAT (related gate)

| Field | Value |
|---|---|
| Phase 2 shell | **PASS** (2026-11-14) — `erp_hajj_phase2_uat` |
| S05/S06/S07 | **PASS** (2026-11-15) |
| GPS Integration Hub | **PASS** (2026-11-16 re-verified) — `erp_hajj_gps_uat` |
| Does not authorize | Production deploy or Owner final acceptance by itself |
