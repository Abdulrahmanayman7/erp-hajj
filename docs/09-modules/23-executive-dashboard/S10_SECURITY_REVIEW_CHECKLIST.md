# S10 — Independent Security Review Checklist (retrospective + ongoing)

> **Human approval gate:** **PENDING**  
> Automated Cursor / agent reviews **do not** qualify as independent non-author human approval.  
> Do not fabricate approval signatures.

## In-scope merged PRs (retrospective)

| PR | Topic | Independent human approval |
|---|---|---|
| [#52](https://github.com/Abdulrahmanayman7/erp-hajj/pull/52) | GPS ingest security hardening | **PENDING** |
| [#53](https://github.com/Abdulrahmanayman7/erp-hajj/pull/53) | GPS nonce retention / replay window | **PENDING** |
| [#54](https://github.com/Abdulrahmanayman7/erp-hajj/pull/54) | Owner-approved U01/U02/U03 alerts + baseline APIs | **PENDING** |
| [#55](https://github.com/Abdulrahmanayman7/erp-hajj/pull/55) | U02 baseline UI + operational readiness (no SLA activation) | **PENDING** |

Existing evidence may include CI green runs and automated agent review comments. Those are **supporting artifacts only**.

## Checklist for human reviewer

Mark each item only after inspecting code + tests (or documenting residual risk).

### Multi-tenancy & seasons

- [ ] Tenant-owned models use `UsesTenantScope`; cross-tenant API access returns **404**
- [ ] Season scoping on executive alerts/list/dashboard/reports is server-enforced
- [ ] Jobs/commands iterate tenants via `runAsTenant()` only

### Baseline approval (U02)

- [ ] `season_projects.approve_progress_baseline` is sensitive / explicit-only (not in `tenant_owner` template)
- [ ] Draft upsert gated by `season_projects.update`; approve gated by sensitive permission
- [ ] Approved baselines immutable; new versions for changes
- [ ] Draft upsert + approve emit audit events (no secrets in payload)
- [ ] Checkpoint bounds / validation enforced server-side

### Executive alerts & reports

- [ ] Domain omit (no data leakage without domain `*.view`)
- [ ] Acknowledge requires `executive_alerts.view` (+ dual page gates as designed)
- [ ] CSV/report exports authorized; no cross-tenant rows
- [ ] Dedupe keys prevent duplicate opens

### GPS

- [ ] HMAC verification + per-tenant secrets where applicable
- [ ] Replay / nonce controls; stale/future `recorded_at` rejected
- [ ] Device binding to authorized vehicle/tenant
- [ ] Older telemetry cannot overwrite newer last-known

### Complaint SLA (U03)

- [ ] Activation defaults **disabled**
- [ ] Historical complaints excluded when activation set
- [ ] No production activation without separate Owner authorization

### Cross-cutting

- [ ] Unauthorized exports blocked by policy
- [ ] Mass assignment closed on alert/baseline models
- [ ] Correlation IDs present on audited security events

## Approver block (fill only by non-author human)

```
REVIEWER_NAME =
REVIEW_DATE =
PRS_COVERED =
VERDICT = APPROVED | CHANGES_REQUIRED | DEFERRED
NOTES =
```

Until this block is filled by a real non-author reviewer, treat **INDEPENDENT_HUMAN_SECURITY_APPROVAL = PENDING**.
