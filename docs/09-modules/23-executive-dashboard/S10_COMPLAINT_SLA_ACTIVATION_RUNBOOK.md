# S10 — Complaint SLA Activation Runbook (U03)

> **Status:** READY FOR OWNER AUTHORIZATION ONLY  
> **Default:** `EXECUTIVE_COMPLAINT_SLA_ACTIVATED_AT` **unset** → policy inactive (fail-closed)  
> **Do not activate** as part of code merge or S10 operational-readiness PR.

## Binding policy (Owner-approved)

- Duration: **48 calendar hours** (elapsed clock)
- Clock start: `created_at`; on reopen restart from `reopened_at`
- Open set: `new`, `under_review`, `in_progress`
- Prospective only after activation timestamp — **no historical backfill**
- Admin close without resolve ≠ SLA met

## Prerequisites (must all be true)

1. Separate **Owner written authorization** for activation (this document alone is not authorization).
2. Independent human security review of U03 paths accepted for the release.
3. Non-production smoke already passed with a disposable activation timestamp.
4. On-call / monitoring owner identified.

## Activation checklist

| # | Step | Owner | Evidence |
|---|---|---|---|
| 1 | Named authorized release owner | Owner | Name + date |
| 2 | Choose activation instant (timezone explicit, e.g. `Asia/Riyadh`) | Owner | ISO-8601 timestamp |
| 3 | Set `EXECUTIVE_COMPLAINT_SLA_ACTIVATED_AT=<iso8601>` in target env only | Ops | Config diff |
| 4 | Clear/reload config cache if applicable (`config:cache` rebuild) | Ops | Command log |
| 5 | Verify queue workers + `executive-alerts:scan` schedule (*/5) running | Ops | `schedule:list` / horizon |
| 6 | Smoke: create **new** complaint after activation; wait/simulate 48h in test; confirm ALT-COMP-SLA | QA | Ticket IDs |
| 7 | Smoke: pre-activation complaint remains ineligible | QA | Scanner result |
| 8 | Confirm notifications only to same-tenant holders of domain perms | QA | Notification rows |
| 9 | Monitor alert open/clear rates + error logs for 24h | Ops | Dashboard/logs |
| 10 | Document activation in release notes | Owner | Link |

## Rollback

1. Unset `EXECUTIVE_COMPLAINT_SLA_ACTIVATED_AT` (or set empty) and rebuild config cache.
2. Next scanner pass clears live `ALT-COMP-SLA` when policy inactive (`clearUnseen` with empty seen set).
3. Do **not** delete audit history.
4. Record rollback reason and Owner acknowledgment.

## Explicit non-goals

- No automatic activation on deploy.
- No backfill of historical complaints.
- No change to S07 MVP complaint history rules beyond S10 alert overlay.
