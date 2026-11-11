# S12 — Phase 2 UAT Test Matrix

> **Sprint:** S12 — UAT & Phase Handoff  
> **Package date:** 2026-11-11  
> **Production release:** **NOT_AUTHORIZED**  
> **Evidence rule:** Mock PASS ≠ Live PASS. No invented Owner/security acceptance.

## Modes

| Mode | Harness | Safe gate | Substitution |
|---|---|---|---|
| **Mock operational shell** | `docs/audits/s12-phase2-browser-uat/run-operational-mock-uat.mjs` | FE only; `/api`+`/sanctum` mocked; network isolation | **Forbidden** as live UAT |
| **Live operational** | `docs/audits/s12-phase2-browser-uat/run-live-uat.mjs` | Attested disposable `erp_hajj_*_uat` DB + shared token | Required for Live PASS |

## Journey coverage

| ID | Journey | Mock routes exercised | Live status |
|---|---|---|---|
| S12-J01 | Authentication & permissions | `/login`, `/app`, `/app/dashboard`, `/app/users`, `/app/roles` | See live report |
| S12-J02 | Seasons & pilgrims | seasons, pilgrims, groups, programs, families | See live report |
| S12-J03 | Fleet & transport movements | vehicles, drivers, fleet, points, movements, breakdowns | See live report |
| S12-J04 | Accommodation & camps | overview, allocation, drafts, scopes | See live report |
| S12-J05 | Suppliers & procurement | suppliers, PRs, approvals, RFQs, POs, GRNs | See live report |
| S12-J06 | Seasonal projects | `/app/season-projects` | See live report |
| S12-J07 | Risks & complaints | risks, complaints | See live report |
| S12-J08 | Executive dashboard & KPIs | operational-kpis, executive-dashboard | See live report |
| S12-J09 | Seasonal templates & safe copy | templates, safe-copy, copy-runs, reminder-rules | See live report |
| S12-J10 | Viewer permission-aware nav | live harness only (viewer login) | See live report |

## Prior sprint Browser UAT (historical evidence — not re-executed in this package)

| Sprint | Evidence | Historical result |
|---|---|---|
| Foundation | `docs/audits/foundation-browser-uat/REPORT.md` | 12/12 on `erp_hajj_foundation_uat` |
| S01 | `docs/audits/s01-fleet-browser-uat/REPORT.md` | 14/14 on `erp_hajj_s01_uat` |
| S02 | `docs/audits/s02-transport-browser-uat/REPORT.md` | 16/16 on `erp_hajj_s02_uat` |
| S03 | `docs/audits/s03-accommodation-browser-uat/REPORT.md` | PASS on `erp_hajj_s03_uat` |
| S04–S07 | Module reports / partial | No unified Phase 2 live matrix |
| S08 | S08 verification gate | UAT-09/10 PASS (branch acceptance era) |
| S09–S11 | Various | Live Browser UAT largely **NOT_RUN** / carryover |

## Results recorded by this S12 package

Update after harness execution:

| Check | Result | Artifact |
|---|---|---|
| Mock operational shell | **PASS** (2026-11-11 local) | `docs/audits/s12-phase2-browser-uat/REPORT.md` |
| Live operational | **NOT_RUN** — API `http://127.0.0.1:8000` unreachable | Fail-closed live harness |
| Network policy unit tests | **8/8 PASS** | `node --test network-policy.test.mjs` |
| Attestation unit tests | **7/7 PASS** | `node --test uat-attestation.test.mjs` |

## Explicit non-claims

- Mock PASS does **not** authorize Owner acceptance or production.
- Historical Foundation–S03 UAT is **not** automatically re-validated by S12 unless live harness PASS is recorded against a current attested stack.
- Production release remains **NOT_AUTHORIZED**.
