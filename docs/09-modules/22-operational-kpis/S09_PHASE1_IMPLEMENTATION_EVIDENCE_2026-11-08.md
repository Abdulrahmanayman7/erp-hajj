# S09 Phase 1 — Implementation Evidence (2026-11-08)

> Branch: `feature/s09-operational-kpis`  
> Base: `origin/develop` @ `3a18b16`  
> Authorization: D16=C Phase 1 only  
> **Not committed / not pushed / not merged / not deployed** (await Owner Git approval)

---

## Delivered

| Area | Evidence |
|---|---|
| 24 KPIs | Five calculators under `Modules/OperationalKpis/Calculators/` |
| Orchestrator | `Actions/GetOperationalKpis.php` — one `DashboardClock` snapshot |
| API | `GET /api/v1/operational-kpis?season_id=` |
| RBAC | `operational_kpis.view` in `PermissionCatalog`; domain omit; `OPERATIONAL_KPIS_VIEW_DENIED` audit |
| D14 | `Support/KpiPrecision.php` + Pest precision suite |
| Vue | `/app/operational-kpis` + season confirm + RTL cards + drill-down |
| Tests | Pest Feature OperationalKpis **65 passed**; Vitest operational-kpis + localization **8 passed** |

## Explicit non-delivery

GPS · S10 · KPI result cache · schema migrations · merge/deploy (until Owner authorizes)

## UX follow-ups (Owner 2026-11-09)

- **M1:** Arabic drill-down disclaimer «قائمة عامة — قد لا تطابق فلتر المؤشر» on navigable cards + page note.
- **L1:** `state=error` cards are not RouterLinks / not keyboard-navigable to destinations.
- **M2:** Documented only — `seasons.view` still required for the season picker (v1 approved behavior).

## Query budget

Performance Pest: **≤15 queries** budget asserted (measured ~12 in Sanctum::actingAs test env). Latency p95 not load-tested (D09 PEST_FIRST).

## Inherited validation debt (D13=B)

See `S09_VALIDATION_DEBT_RISK_REGISTER.md` — S05/S06/S07 certificates not waived; isolation/authz remain hard merge gates.
