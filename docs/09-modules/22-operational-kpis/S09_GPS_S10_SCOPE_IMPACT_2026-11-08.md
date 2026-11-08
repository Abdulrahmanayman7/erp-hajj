# S09 — GPS & S10 Scope Impact (D10=B · D15=B)

> **Date:** 2026-11-08  
> **Owner decisions:** D10=**B** (Include GPS in S09) · D15=**B** (Combined S09/S10 direction)  
> **Class:** Approved decision ≠ complete technical specification ≠ coding authorization  
> Read-only evidence from ADR-0020/0021, ROADMAP, PHASE2_SCOPE_MATRIX, Transportation module code.

---

## 1. Distinction table

| Item | Class |
|---|---|
| D10=B Include GPS in S09 | **Approved Owner decision** |
| D15=B Combined S09/S10 direction | **Approved Owner decision** |
| 24 KPI formulas without GPS inputs (D01/D02) | **Approved** — none of the 24 require GPS fields today |
| ADR-0021 completion gates (real source, isolation, RBAC, stale UX, tests, UAT) | **Documented implementation requirement** (Phase 2) |
| GPS provider / API / schema / ingest pipeline | **Open technical specification** |
| Mock coordinates as “live” | **Unapproved** (forbidden by ADR-0021) |
| S10 executive widgets / alerts / live map detail design | **Open technical specification** |
| New KPI IDs for GPS | **Unapproved scope expansion** without CR |

---

## 2. GPS — what can be specified without inventing requirements

### Official constraints (must preserve)

From ADR-0020 §GPS and ADR-0021 Decision 01:

- GPS / live tracking / ops map are **in Phase 2 product scope**.  
- Completion **gated** on: approved real source; documented accuracy & update timing; tenant/season isolation; RBAC; last-known update UX; stale/unavailable handling; integration tests; UAT.  
- **No vendor, API, or protocol invented** by ADRs.  
- Static/mock coordinates **≠** completed live tracking.  
- Location/ops data binding: **S01/S02**; executive live map: **S10**.  
- Engineering conflict with ADR-0018 → separate Engineering ADR/CR.

### Codebase evidence (read-only)

| Finding | Implication |
|---|---|
| No GPS provider config / client in Transportation module | Cannot implement live feed without new engineering design |
| No latitude/longitude / last_fix columns found on vehicle/run models in current module surface | No SoR for “live position” KPIs or map pins today |
| S01/S02 Owner acceptance explicitly **excluded** live GPS | GPS remains open Phase 2 obligation |
| S09 DATA_SOURCE_MAP: T01/T02/T08 independent of GPS | D10=B does **not** rewrite the 24 formulas to depend on GPS |

### Minimum GPS scope that is **documentable now** (not invented product)

| Layer | Minimum (spec-ready for Owner freeze later) | Still open |
|---|---|---|
| Product | Acknowledge GPS workstream in combined S09/S10 plan; do not claim Live | Provider selection |
| Data | Placeholder for future tenant-scoped location entity **only after** Engineering ADR/CR | Columns, retention, PII |
| KPI engine | Keep 24 KPIs GPS-independent; optional future GPS KPIs need CR | Which metrics |
| S09 UI | Optional “GPS status” placeholder **only if** Owner freezes UX that never fakes live | Widget design |
| Security | Any location read inherits tenant + season + RBAC; audit sensitive access | Permission keys |

**D10=B meaning for S09 coding (recommended interpretation until Owner picks D16 option):**  
GPS is **in the delivery direction** and must be **planned/gated** inside the combined workstream — **not** that the first coding slice may invent a provider or ship mock live map.

---

## 3. S10 — official scope vs gaps

### Official S10 bullets (ADR-0020)

- Unified operational indicators  
- Season status  
- Occupancy and progress  
- Alerts and reports  
- Maps / live tracking **only** with trustworthy integration  

ROADMAP / PHASE2_SCOPE_MATRIX: **S10 = NOT_STARTED**; MVP admin dashboard ≠ Phase 2 executive dashboard.

### What D15=B authorizes as **direction**

- Plan S09 KPI engine and S10 executive composition as a **combined** implementation direction (shared services, coordinated branch/PR strategy).  
- Reuse S09 calculators / domain omit (D07-Q9, D08-Q10) for S10 surfaces when built.

### What remains **open technical specification**

| Gap | Notes |
|---|---|
| Page permission key(s) for S10 | D08 allows separate page perm; not created |
| Widget inventory & layout | Not frozen beyond ADR bullets |
| Alert rules & thresholds | Not specified |
| Report exports | Not specified |
| Live map component contract | Blocked on GPS gates |
| API shape for executive rollup | Not frozen (must not weaken D08 omit) |
| Caching | D09-Q11: evaluate S10 cache only after S09 measured |

**D15=B does not** authorize unspecified widgets, fake maps, or formula drift.

---

## 4. Impact on the frozen 24 KPIs

- **No formula change** from D10=B or D15=B.  
- Transport KPIs remain movement/fleet SoR without GPS.  
- If a future GPS-derived KPI is desired → **CR** + Owner (D01 freeze).

---

## 5. Safe combined-workstream staging (design advice)

1. **Core S09** (24 KPIs + RBAC + D14 + drill-down) — fully specified under D01–D14.  
2. **S10 shell** (permission-aware composition of existing KPI DTOs / season status) — needs short Owner widget freeze.  
3. **GPS / live map** — only after provider + Engineering ADR/CR + ADR-0021 gates.

This staging is **not** D16 authorization; see D16 options in the reassessment.

---

## Status

```
D10=B / D15=B = OWNER_APPROVED
GPS_TECH_SPEC = OPEN
S10_TECH_SPEC = OPEN
NO_APPLICATION_CODE
```
