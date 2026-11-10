# S10 — Operational Readiness (2026-11-10)

> **Branch:** `feature/s10-operational-readiness`  
> **Base:** `origin/develop` @ `28a344629df76cc7eb638e1e4620ee5b0906fa62` (PR #54 merged)  
> **Does not authorize:** production deploy, SLA activation, G4 Live, G5 retention, or `S10_COMPLETED`

## Delivered capabilities (this workstream)

| Capability | Status | Notes |
|---|---|---|
| U02 baseline workflow **UI** on Season Project detail | **IMPLEMENTED** | View versions/checkpoints, draft upsert, approve, lag display |
| U01 occupancy % (dashboard/report/alerts) | **IMPLEMENTED** (prior PR #54) | Formula frozen; UAT evidence below |
| U02 lag scanner ALT-PROJ-LAG | **IMPLEMENTED** (prior PR #54) | UI now completes end-user workflow |
| U03 complaint SLA engine | **IMPLEMENTED** (prior PR #54) | Activation **disabled** by default |
| G4 Live / G5 history | **BLOCKED** | Unchanged |

## U01 — Occupancy % UAT evidence

**Binding:** [S10_U01_U02_U03_OWNER_APPROVAL_2026-11-10.md](./S10_U01_U02_U03_OWNER_APPROVAL_2026-11-10.md)  
**Surfaces:** Executive dashboard accommodation panel + executive reports CSV + `ALT-OCC-90` scanner.

| Scenario | Expected | Evidence |
|---|---|---|
| raw &lt; 85 | no open / clear path | Calculator thresholds + scanner hysteresis (Pest `DeferredAlertsU01U02U03Test`, `ExecutiveAlertsScannerTest`) |
| raw = 85 | clear boundary | `OccupancyMetricCalculator::CLEAR_THRESHOLD = 85` |
| 85 ≤ raw &lt; 90 | hysteresis preserve | Scanner `scanOccupancyHigh` preserve band |
| raw = 90 | open | `OPEN_THRESHOLD = 90` |
| raw &gt; 90 | open | Same |
| null effective capacity | UNAVAILABLE | Calculator `null_capacity` |
| denom ≤ 0 | UNAVAILABLE | Calculator `zero_denominator` |
| Season isolation | metrics scoped by `season_id` | Scanner + KPI season tests |
| Cross-tenant | no leakage | ExecutiveAlerts tenant isolation Pest |
| Unauthorized accommodation domain | omit / no panel data | Dashboard domain omit tests |
| CSV export | occupancy when available | ExecutiveReports suite |

**Browser UAT:** **PENDING** (manual steps in §Manual UAT). Automated Pest/Vitest = PASS on branch CI path.

**S09 note:** A03 semantics reused; S09 KPI catalog definitions **not** modified.

## U02 — Baseline workflow UAT evidence

| Scenario | Expected | Evidence |
|---|---|---|
| Draft create/replace via UI | POST progress-baselines | New Vue panel + API client |
| Checkpoint validation | 0–100, unique dates, non-decreasing, max 100; reject empty/whitespace/NaN/Infinity planned % and invalid datetimes | FE validation tests + backend FormRequest |
| Approve with sensitive permission | 200 + audit | Pest approve path + UI PermissionGuard |
| Approve without permission | 403 | `DeferredAlertsU01U02U03Test` |
| Approved immutability | approved rows not mutated; new draft version | Action + UI copy |
| No baseline / no checkpoint | lag UNAVAILABLE in UI + no alert | FE metric helper + scanner |
| Invalid actual progress | lag UNAVAILABLE (`invalid_actual_progress`); never coerce to 0 | FE `parseActualProgressPercent` + Vitest |
| Lag &lt; 8 / = 8–10 / ≥ 10 | clear / hysteresis / open (thresholds 10/8 preserved) | Pest lag boundary + FE helper |
| Mutation buttons without permission / closed season | hidden | `PermissionGuard` + `seasonWritable` |
| Audit | DRAFT_UPSERTED + APPROVED | Pest AuditLog assertions |
| Tenant isolation | cross-tenant 404 | Pest |
| Arabic RTL | locale keys under `seasonProjects.baseline` | `seasonProjectsAr.ts` |

**Browser UAT:** **PENDING** — reproducible steps below.

## U03 — SLA readiness (NOT activation)

| Item | Status |
|---|---|
| Policy | 48 calendar hours; prospective only |
| Config key | `EXECUTIVE_COMPLAINT_SLA_ACTIVATED_AT` / `executive_alerts.complaint_sla_activated_at` |
| Production activation | **UNSET / DISABLED** — do **not** set without separate Owner authorization |
| Isolated tests | Pest validates historical exclusion, 48h breach, reopen clock, disabled-by-default |

Activation runbook: [S10_COMPLAINT_SLA_ACTIVATION_RUNBOOK.md](./S10_COMPLAINT_SLA_ACTIVATION_RUNBOOK.md)

## Security review status

See [S10_SECURITY_REVIEW_CHECKLIST.md](./S10_SECURITY_REVIEW_CHECKLIST.md).

| PR | Independent human approval |
|---|---|
| #52 GPS hardening | **PENDING** |
| #53 GPS replay retention | **PENDING** |
| #54 U01/U02/U03 alerts | **PENDING** |
| #55 operational readiness UI + validation hardening | **PENDING** |

Automated Cursor/security-agent reviews **do not** satisfy independent non-author human approval.

## Outstanding blockers

```
S10_STATUS = IN_PROGRESS
G4_LIVE = BLOCKED
G5 = BLOCKED
PRODUCTION_DEPLOYMENT = NOT_AUTHORIZED
EXECUTIVE_COMPLAINT_SLA_ACTIVATED_AT = UNSET
INDEPENDENT_HUMAN_SECURITY_APPROVAL = PENDING (#52 #53 #54 + this PR)
BROWSER_UAT = PENDING
```

## Production readiness assessment

**Not production-ready for S10 closure.** Code for U01–U03 is merged/implemented and operational UI for U02 drafts/approve is added, but:

1. Human security approval gate is open.
2. Complaint SLA must remain disabled until Owner activation.
3. G4/G5 remain blocked.
4. Browser UAT evidence not yet captured in-repo.

## Manual UAT steps (browser — PENDING)

### U02 baseline UI
1. Login as tenant user with `season_projects.view` + `season_projects.update`.
2. Open an **active** season project detail page.
3. Create draft baseline with ≥1 checkpoint (date + planned %).
4. Confirm validation rejects % &gt; 100 and decreasing planned % by date.
5. Grant `season_projects.approve_progress_baseline` explicitly; approve draft.
6. Confirm approved version shows approver/timestamp; draft editor creates a **new** version after approval.
7. Confirm lag card shows planned/actual/lag or unavailable when no applicable checkpoint.
8. Confirm user **without** approve permission cannot approve (403).

### U01 occupancy
1. With accommodation domain + executive dashboard permissions, select season.
2. Confirm occupancy % appears when capacity available; unavailable when null capacity.
3. Export executive report CSV and confirm occupancy column when available.

### U03
1. Keep `EXECUTIVE_COMPLAINT_SLA_ACTIVATED_AT` unset in non-test environments.
2. Do not run activation smoke in production.
