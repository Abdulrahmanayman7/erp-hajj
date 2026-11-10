# S10 — Owner Approval: U01 / U02 / U03

> **Effective approval date:** 2026-11-10  
> **Status:** `OWNER_APPROVED` for implementation on `feature/s10-deferred-alerts-completion`  
> **Does not authorize:** production deployment, automatic merge, GPS Live (G4), history retention (G5), or `S10_COMPLETED`

## Prior decision history (preserved)

| Item | Prior | Status |
|---|---|---|
| U01 Occupancy % / ALT-OCC-90 | **C** deferred/blocked | Superseded for **implementation** by this approval |
| U02 Planned progress lag / ALT-PROJ-LAG | **C** deferred/blocked | Superseded for **implementation** by this approval |
| U03 Complaints SLA / ALT-COMP-SLA | **C** deferred/blocked | Superseded for **implementation** by this approval |

Historical S10 docs that recorded `U01/U02/U03 = C` remain valid as the pre-2026-11-10 record. This document is the binding change for engineering.

## U01 = APPROVED

| Element | Binding rule |
|---|---|
| Numerator | S09 **A03** (unchanged) |
| Denominator | Σ `AccommodationCapacityGuard::effectiveCapacity` for **active** season units in the selected season |
| Formula | `occupancy_percent_raw = 100 * A03 / denom` |
| Display | One decimal place; **thresholds use unrounded raw** |
| Forbidden | A02 as denominator; inventing cross-season physical capacity |
| Incomplete data | Any eligible unit with **null** effective capacity → aggregate **UNAVAILABLE**; denom ≤ 0 → **UNAVAILABLE** (not 0%) |
| OPEN | raw ≥ 90 |
| CLEAR | raw < 85 |
| Hysteresis | 85 ≤ raw < 90 → preserve existing live alert state |
| Dedupe | `ALT-OCC-90:season:{season_id}` |
| Domain | `accommodation.view` |

## U02 = APPROVED

| Element | Binding rule |
|---|---|
| Planned source | Explicit dated **approved** checkpoints only |
| Planned at now | Latest checkpoint with `effective_at <= now` |
| Forbidden | Elapsed-time interpolation; comparison to season J07 average |
| Actual | `season_projects.progress_percent` (SeasonProjectProgressRollup) |
| Lag | `planned_at_now - actual` (percentage points) |
| OPEN | lag ≥ 10 |
| CLEAR | lag < 8 |
| Hysteresis | 8 ≤ lag < 10 → preserve live state |
| Eligibility | Active project + approved baseline + usable checkpoint |
| No baseline | UNAVAILABLE — no alert |
| Dedupe | `ALT-PROJ-LAG:project:{project_id}` |
| Approval permission | `season_projects.approve_progress_baseline` (**sensitive / explicit-only**, never auto-granted) |
| Domain view | `season_projects.view` |
| S09 J08 | Remains a **separate** KPI catalog decision (not auto-unblocked) |

## U03 = APPROVED

| Element | Binding rule |
|---|---|
| Duration | **48 calendar hours** (elapsed, not business hours) |
| Clock start | `created_at`; on reopen → restart from `reopened_at` |
| Open set | `new`, `under_review`, `in_progress` |
| Breach | Still open AND `now >= clock_start + 48h` |
| Resolve | `resolved_at` stops clock |
| Admin close without resolve | **Not** SLA-met / not successful resolution |
| Escalation | Does **not** pause SLA |
| Historical | **Prospective only** after explicit activation timestamp |
| Activation | Config `executive_alerts.complaint_sla_activated_at` — default **disabled** until release sets it |
| Dedupe | `ALT-COMP-SLA:complaint:{complaint_id}` |
| Domain | `complaints.view` |
| S09 R06 | Not auto-added to approved KPI list |
| S07-D07 | See scoped amendment — S07 MVP history preserved; S10 alerts only |

## Flags after merge (still)

```
S10_STATUS = IN_PROGRESS
G4_LIVE = BLOCKED
G5 = BLOCKED
PRODUCTION_DEPLOYMENT = NOT_AUTHORIZED
S10_CLOSURE = NOT_AUTHORIZED
```
