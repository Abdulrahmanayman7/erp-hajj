# S10 — Completion Status

> **Branch workstream:** `feature/s10-gps-replay-security-fix` (post PR #52 merge)  
> **Updated:** 2026-11-10  
> **Do not claim:** `S10_COMPLETED`, `G4 Live`, or `G5` retention.

## Package status

| Package | Status | Notes |
|---|---|---|
| A0–A2 Dashboard shell | **DONE** | Dual page gate + explicit `season_id` |
| A3 Season / KPI panels | **DONE** | Reuses S09 KPIs; no invented occupancy % |
| A4 Operational alerts | **DONE** | ALT-RISK-HIGH + ALT-PROC-OVERDUE only |
| A5 Executive reports + CSV | **DONE** | Six reports; CsvEncoder BOM + injection; domain 403/omit |
| G0 Provider contracts | **DONE** | `GpsProviderInterface`, `NormalizedLocationDto`, `NullGpsProvider` |
| G1 HMAC ingest + last-known | **DONE** | `POST /api/v1/gps/ingest`; per-tenant HMAC; device bindings; **extended nonce retention** (`TTL + max_future + 1`); monotonic last-known; **no history table** |
| G2 Freshness classifier | **DONE** | REAL ≤5min; else STALE; missing → UNAVAILABLE |
| G3 Dashboard markers | **DONE** | Last-known markers behind `gps.map.view`; never SIMULATED |
| **G4_LIVE** | **BLOCKED** | Real vendor / live provider binding not approved |
| **G5** retention/history | **BLOCKED** | U04 — history table intentionally absent |

## GPS ingest security notes (G1 hardening)

| Control | Status |
|---|---|
| Per-tenant `GPS_WEBHOOK_SECRETS` (no shared global secret) | Enforced fail-closed |
| Required `device_id` ↔ `gps_device_bindings` | Enforced fail-closed |
| `recorded_at` skew window (age capped at nominal TTL; future skew configurable) | Enforced |
| Nonce retention = `nonce_ttl + max_future + 1` | Closes future-skew post-nominal-TTL replay |
| Monotonic last-known (`recorded_at` must increase) under row locks | Enforced |
| Live provider / history retention | **G4/G5 BLOCKED** |

**Residual risk:** possession of a valid per-tenant HMAC secret still allows minting *new* telemetry (new nonce + fresh `recorded_at`). That is credential compromise, not unsigned forgery. Independent human security review remains required for merge of related hardenings.

## Deferred / blocked formulas (do not invent)

| Item | Decision | Status |
|---|---|---|
| Occupancy % / ALT-OCC-90 | U01 = C | **DEFERRED_BLOCKED** |
| Planned progress lag / ALT-PROJ-LAG | U02 = C | **DEFERRED_BLOCKED** |
| Complaints SLA / ALT-COMP-SLA | U03 = C | **DEFERRED_BLOCKED** |
| GPS location history retention | U04 / G5 | **BLOCKED** |

## Flags

```
S10_STATUS = IN_PROGRESS
G4_LIVE = BLOCKED
G5 = BLOCKED
S10_COMPLETED = false
```
