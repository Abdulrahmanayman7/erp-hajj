# S09-D09 — Owner Approval Record (2026-11-08)

> **Authorization boundary:** DESIGN DOCUMENTATION ONLY.  
> Does **not** authorize application code, migrations, permissions, commits, push, merge, PR, or deploy.  
> **Status:** `OWNER_APPROVED`

---

## Authorization

| Field | Value |
|---|---|
| Date | **2026-11-08** |
| Decision | Performance & caching policy for S09 v1 |
| Prior | D01–D08 Owner-approved |
| Exact choices | Q1=A · Q2=15 · Q3=500ms · Q4=A · Q5=MEASURE_THEN_CR · Q6=CI_ENFORCED · Q7=LIVE · Q8=FUTURE_CACHE_PERMISSION_SAFE · Q9=NON_PRODUCTION_OBSERVABILITY · Q10=PEST_FIRST · Q11=DEFER_S10_CACHE |

---

## Binding policies

| Q | Choice | Binding |
|---|---|---|
| Q1 | **A** | **No KPI result caching** in S09 v1 |
| Q2 | **15** | Provisional design budget ≤ **15** DB queries for a fully authorized KPI response |
| Q3 | **500ms** | Provisional **p95** API latency target &lt; 500 ms |
| Q4 | **A** | Safe aggregation / query reuse **without** changing approved formulas |
| Q5 | **MEASURE_THEN_CR** | Measure first; additional indexes only via justified **Change Request** |
| Q6 | **CI_ENFORCED** | Prevent N+1 with **automated CI enforcement** |
| Q7 | **LIVE** | A02/A03 (and other snapshot-sensitive KPIs) calculated **live** with approved shared snapshot |
| Q8 | **FUTURE_CACHE_PERMISSION_SAFE** | No v1 KPI cache; any future cache must preserve tenant + season + permission isolation |
| Q9 | **NON_PRODUCTION_OBSERVABILITY** | Safe query instrumentation / slow-query monitoring in **non-production first** |
| Q10 | **PEST_FIRST** | Pest query-count / performance tests first; load testing later |
| Q11 | **DEFER_S10_CACHE** | Evaluate S10 caching only after S09 is operational and measured |

### Budget caveat

Query-count (**15**) and latency (**500ms p95**) are **provisional engineering targets**, not measured production baselines.

If a target cannot be met without changing an approved formula or weakening security, **stop and request a separate Owner decision** — do not silently alter the 24 KPI calculations.

Framework/`TenantCache` infrastructure may exist for other modules; that is **not** authorization to cache S09 KPI card results in v1.

---

## Compatibility

Compatible with D04 live snapshot, D07 aggregates + N+1 ban, D08 permission omit (no cache of unauthorized sets).

---

## Dependencies

| Topic | Decision |
|---|---|
| GPS | D10 |
| Drill-down / UI routes | D11 |
| Incomplete sources | D12 |
| Validation debt gates | D13 |
| Precision / UAT | D14 |
| S09 vs S10 | D15 |
| Implementation | D16 |

---

## No-code boundary

| Action | Authorized? |
|---|---|
| Record D09 in S09 design docs | **Yes** |
| Application / indexes / cache code / commit / deploy | **No** |

---

## Status lines

```
S09-D09 = OWNER_APPROVED
S09-D10...D16 = PENDING_OWNER_DECISION
NO_APPLICATION_CODE / NO_MIGRATIONS / NO_COMMIT / NO_PUSH / NO_MERGE / NO_DEPLOY
```
