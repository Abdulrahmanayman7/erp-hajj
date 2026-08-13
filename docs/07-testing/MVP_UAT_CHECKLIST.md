# MVP UAT Checklist

> **Status:** P1 closure attempt on `release/0.1.0-final-validation` (2026-08-13) — **RELEASE NOT READY** (staging/host + Firefox + Owner sign-off + full human workflows remain open)
> **Last updated:** 2026-08-13
> **RC branch:** `release/0.1.0-final-validation`

**Legend:** `PASS` / `FAIL` / `PARTIAL` / `NOT EXECUTED` / `NOT AVAILABLE`

Disposable local UAT DB: MariaDB `erp_hajj_final_uat` (users `final-*@uat.test`).

---

## Browser matrix

| Browser | Result | Evidence |
|---|---|---|
| Edge | PASS (prior session) | Routes + roles + 1440/768/390 |
| Chrome | PARTIAL | Most critical routes PASS at desktop/tablet/mobile; 3 desktop navigations failed with `net::ERR_INSUFFICIENT_RESOURCES` (host memory), not product 500s |
| Firefox | FAIL / OPEN | System Firefox present at `Program Files (x86)`; Playwright launch aborted (`browser has been closed`). No PASS claimed |

## Responsive

| Width | Edge | Chrome |
|---|---|---|
| Desktop ~1440 | PASS | PARTIAL (resource errors late in run) |
| Tablet ~768 | PASS | PASS |
| Mobile ~390 | PASS | PASS |

## Roles

| Role | Edge UI | API |
|---|---|---|
| Owner | PASS | PASS |
| GM | PASS (Settings view-only) | Settings PATCH 403 PASS |
| DM / Supervisor | PASS (prior) | — |
| Employee | PASS login | Settings/Audit 403 PASS; Dashboard 200 PASS |
| Auditor | PASS | Audit list 200 PASS |

**Product/Owner sign-off:** NOT EXECUTED (cannot be agent-approved).

## Workflows

| Flow | Result |
|---|---|
| Settings Cairo ↔ Riyadh + contacts | PASS (live HTTP) |
| Decision close blocked while Task open | PASS (`DECISION_CLOSE_NOT_ALLOWED`) |
| Full Meeting→Decision→Task→Close chain | PARTIAL / incomplete (schedule/reco/assignee start payload mismatches in harness; not signed as full PASS) |
| Inventory decimal receipt/issue/transfer | NOT EXECUTED as full PASS (unit/payload validation failures in harness) |
| Assets assign/return/maintenance | PARTIAL (asset create/maint/restore PASS; assign failed without employee due to earlier org/employee create failure) |
| Documents UI full flow | NOT EXECUTED this session (prior blob backup evidence retained) |
| Notifications rich UI | PARTIAL (`unread_count` observed; bell/deep-link UI not fully exercised) |
| Dashboard populated | PARTIAL (Owner/Employee GET 200; KPI deep check incomplete) |
| Audit populated | PARTIAL (Auditor list 200; filter/detail deep check incomplete) |

## Queue / scheduler

| Gate | Result |
|---|---|
| `schedule:list` + scanners ×2 | PASS |
| Sustained `queue:work` ~40s | PARTIAL — worker ran; **0 jobs** processed (empty queue; scanners found no candidates). Not sufficient for P1-10 PASS |

## Staging / host

| Gate | Result |
|---|---|
| Staging/real-host smoke | NOT AVAILABLE (no staging credentials/URLs in environment) |
| Host PHP upload limits | NOT AVAILABLE (local XAMPP 40M/40M only — does not close P1-02) |
| Hosted SPA deep routes / HTTPS CORS | NOT AVAILABLE |

## Sign-off

| Role | Result |
|---|---|
| Automated/local tester | Evidence recorded |
| Product/Owner | **NOT EXECUTED** |
| Tech lead | **NOT EXECUTED** |

**Overall UAT:** Failed / incomplete — required P1s remain open.
