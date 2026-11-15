# S05 / S06 / S07 — Live Browser UAT Report

> **Date:** 2026-11-15  
> **Database:** `erp_hajj_phase2_uat` (attested disposable `/^erp_hajj_.+_uat$/`)  
> **Attestation token:** configured (`BROWSER_UAT_ATTESTATION_TOKEN`)  
> **Harness:** `docs/audits/s05-s06-s07-browser-uat/run-live-uat.mjs`  
> **Final report JSON:** `evidence/2026-11-15T09-38-13-722Z_report.json`  
> **Verdict:** **PASS**

## Isolation

| Check | Result |
|---|---|
| Disposable DB gate | PASS |
| Owner auth | PASS |
| Viewer create denied (S05) | PASS (403 / no submit) |
| Viewer risk create hidden (S07) | PASS |
| Unknown PR id isolation | PASS (404) |

## S05 — Procurement

| Step | Status | Evidence |
|---|---|---|
| Create purchase request | PASS | PR id=9 `draft` persisted |
| Submit (similarity ack + submit) | PASS | status=`submitted` HTTP 200 |
| Isolation unknown id | PASS | 404 |
| Viewer create denied | PASS | `/app/403` |

## S06 — Seasonal Projects

| Step | Status | Evidence |
|---|---|---|
| Create project | PASS | id=8 `draft` |
| Activate (owner set + activate) | PASS | status=`active` HTTP 200 |

## S07 — Risks & Complaints

| Step | Status | Evidence |
|---|---|---|
| Create risk | PASS | id=11 title persisted |
| Assess risk | PASS | status=`under_assessment` HTTP 200 |
| Create complaint | PASS | id=5 status=`new` persisted |
| Assign complaint | PASS | assignee_employee_id=1 HTTP 200 |
| Resolve complaint | PASS | status=`resolved` HTTP 200 |
| Viewer create hidden | PASS | risk-create-btn count=0 |

## Notes

- Persistence verified via authenticated API reads after UI actions (not navigation-only).
- Screenshots under `evidence/2026-11-15T09-38-13-722Z_*.png`.
- This is **engineering Live Browser UAT PASS**. Owner functional acceptance remains a separate Owner decision (do not invent).
