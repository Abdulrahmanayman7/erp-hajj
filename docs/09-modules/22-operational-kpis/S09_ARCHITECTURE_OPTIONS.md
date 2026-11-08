# S09 — Architecture Options

> **Date:** 2026-11-08  
> **S09-D07 calculation architecture:** `OWNER_APPROVED` — five domain calculators + orchestrator.  
> **S09-D09:** `OWNER_APPROVED` — **no KPI result cache** in v1; provisional ≤15 queries / p95 &lt;500ms; N+1 CI; live snapshots; measure-then-CR indexes.  
> **D11=A:** authorized allow-listed drill-down; dedicated operational-kpis route remains the design target.  
> **D10=B / D15=B:** GPS included in S09 direction; combined S09/S10 direction — tech specs still open (`S09_GPS_S10_SCOPE_IMPACT_2026-11-08.md`).  
> **D16:** NOT AUTHORIZED.

---

## Option A — Extend `GET /api/v1/dashboard`

Add Phase 2 metric builders into `GetDashboard` / same payload.

| Criterion | Assessment |
|---|---|
| ADR-0014 | Compatible if omit-unauthorized preserved |
| Security | Single gate + section gates |
| Season | Must add season query param / header — **new contract** on existing endpoint |
| Maintainability | Risk of monolithic payload |
| Query complexity | All domains on every home load |
| Testing | Extend DashboardTest |
| FE | Reuse DashboardPage |
| Performance | Heavier home request |
| S10 reuse | Weak separation of concerns |

---

## Option B — Separate `GET /api/v1/kpis` (or `/api/v1/operational-kpis`)

Dedicated read-only KPI resource; Dashboard remains MVP admin counts.

| Criterion | Assessment |
|---|---|
| ADR-0014 | Compatible if same omit semantics + policy |
| Security | Clear permission surface |
| Season | First-class `season_id` server-validated |
| Maintainability | Clean module boundary (`Modules/OperationalKpis` or Dashboard sub-namespace) |
| Query complexity | Can load by domain sections |
| Testing | Focused Pest suite |
| FE | New section/route or embed cards |
| Performance | Optional domain query params |
| S10 reuse | Strong — S10 can consume same aggregators |

---

## Option C — Hybrid HTTP + approved calculators

- **Internal (D07 OWNER_APPROVED):** five domain KPI calculators + central orchestrator; one `DashboardClock` instant; normalized DTO; S09/S10 reusable services.  
- **External read API:** dedicated `GET /api/v1/operational-kpis?season_id=` **or** embed under dashboard — **routing still Owner/D11**; must require explicit `season_id` (D05-C1).  
- **Presentation:** Arabic RTL cards — Owner/D11.  
- MVP Dashboard keys remain unchanged for non-Phase-2 modules.  
- **No** second authorization-bypass dashboard.  
- **No** new tables for MVP S09 unless Owner later requires snapshots (S09-D09).

### Alignment with D07

Domain calculators + orchestrator match Owner Q1=B / Q2=A. HTTP Option A vs B remains a delivery choice that must not weaken season/auth contracts.

---

## Explicit non-goals

- No Dashboard mutations.  
- No client-supplied `tenant_id`.  
- No inventing GPS streams.  
- No finance KPI ledger.
