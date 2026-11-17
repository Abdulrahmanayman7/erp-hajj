# Phase 2 — Deployment Readiness Checklist (Non-Production)

> **Date:** 2026-11-16  
> **Develop baseline at package start:** `f3c4c44431a533f225d9f0977d26759026642557` (PR #66 merge)  
> **Production deploy:** **NOT_AUTHORIZED** by this checklist  
> CI SUCCESS ≠ production readiness.

## 1. Environment configuration

| Item | Expected | Status |
|---|---|---|
| Target env named (staging vs production) | Explicit | Owner/ops |
| `APP_ENV` / `APP_KEY` / DB creds out of git | Required | Ops |
| Redis / queue connection documented | Required if queues used | Ops |
| `GPS_PROVIDER=null` | Default | Verified in config |
| `GPS_LIVE_OUTBOUND_AUTHORIZED=false` | Default | Verified |
| `GPS_LIVE_OUTBOUND_AUTHORIZED_IN_PRODUCTION=false` | Default | Verified |
| `GPS_SIMULATOR_ENABLED=false` in non-demo | Default | Verified |
| `GPS_HISTORY_ENABLED=false` | Default | Verified |
| `EXECUTIVE_COMPLAINT_SLA_ACTIVATED_AT` unset | Fail-closed | Verified |
| Attestation / UAT tokens not in repo | Required | Ops |

## 2. Required services & workers

| Service | Check | Notes |
|---|---|---|
| PHP-FPM / app server | Health endpoint | `/up` or project health route |
| MySQL 8 compatible schema | CI MySQL 8 job | Post-#66 SUCCESS on develop |
| Queue worker | `queue:work` / Horizon | Needed for `gps:sync-providers`, alerts |
| Scheduler | `schedule:list` includes `executive-alerts:scan`, `gps:sync-providers` if enabled | Ops |
| Frontend static hosting | Vercel/preview or ops host | Not production auth |

## 3. Database migration compatibility

| Check | Evidence |
|---|---|
| Fresh migrate on disposable UAT | GPS hub UAT `erp_hajj_gps_uat` PASS |
| Upgrade path for GPS hub columns | `docs/audits/gps-hub-browser-uat/REPORT.md` |
| MySQL 8 CI | develop post-#66 Backend MySQL 8 SUCCESS |
| No production migrate executed by engineering package | Affirmed |

## 4. Backup / restore / rollback

| Item | Requirement |
|---|---|
| Pre-deploy backup | Owner/ops before any future prod migrate |
| Restore drill on disposable DB | Recommended |
| App rollback | Redeploy previous SHA; keep migrations backward-compatible or documented |
| GPS live rollback | Unset `GPS_LIVE_OUTBOUND_*` |
| U03 rollback | Unset `EXECUTIVE_COMPLAINT_SLA_ACTIVATED_AT` — see SLA runbook |

## 5. Health, logging, monitoring

| Item | Notes |
|---|---|
| Correlation ID on requests | Security baseline |
| Audit trail immutable for normal users | ADR-0015 |
| GPS security events without secrets/coords | PR #64/#66 |
| Error envelope (no stack traces in prod) | Baseline |
| On-call owner identified | Required before prod |

## 6. Release smoke tests (disposable only)

1. Login + RBAC denial sample  
2. Season/pilgrim read  
3. Fleet map DEMO or last-known (no live vendor claim)  
4. `php artisan gps:verify-g4-readiness`  
5. `php artisan executive-alerts:verify-complaint-sla-readiness`  
6. CI green on release-candidate SHA  

## 7. Explicit non-claims

- This checklist does **not** authorize production deployment.  
- G4 Live / G5 history / U03 production activation remain **BLOCKED**.  
- Owner S05/S06/S07/S12 final acceptance remain **PENDING** where not recorded.
