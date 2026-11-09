# S09-D10…D15 — Owner Approval Record (2026-11-08)

> **Status:** `OWNER_APPROVED`  
> **Authorization boundary:** Design documentation only — does **not** authorize D16 coding, permissions, migrations, commit, or deploy.  
> **Prior pack:** `S09_D10_D15_CONSOLIDATED_OWNER_REVIEW_2026-11-08.md` (recommendations were not approvals).  
> **Reconciliation:** Repository previously left D10–D15 as `PENDING` after the consolidated review. That was a **documentation synchronization gap**. The Project Owner had already approved the six decisions on **2026-11-08**. This file records those binding acts.

---

## Exact Owner decisions

| ID | Official title | Owner choice | Binding summary |
|---|---|---|---|
| **D10** | GPS dependency | **B** | **Include GPS in S09** (product direction). Does **not** invent provider, API, or data model. |
| **D11** | Drill-down navigation | **A** | **Authorized drill-down** — allow-listed module list routes only; permission-safe. |
| **D12** | Incomplete source data | **A** | **Preserve D06 states**; no additional “forbidden-source” UX state for the frozen 24. |
| **D13** | Validation debt (S05/S07/S06) | **B** | **Parallel** implementation planning with **tracked** S05/S06/S07 validation debt. |
| **D14** | Acceptance thresholds & UAT / precision | **(matrix below)** | COUNT integers; P07 ≤4 dp trim trailing zeros; J07 1 dp; J09 1 dp; **server** `ROUND_HALF_UP` for display values. |
| **D15** | S09 vs S10 responsibilities | **B** | **Combined S09/S10 implementation direction**. Does **not** invent unspecified S10 features. |

---

## D14 binding precision policy

| Metric | Presentation | Rounding authority |
|---|---|---|
| COUNT KPIs | Integer only | N/A (exact integer) |
| **P07** (SUM quantities) | Up to **4** decimal places; **trailing zeros removed** | Server `ROUND_HALF_UP` for `display_value` |
| **J07** (AVG progress) | **One** decimal place | Server `ROUND_HALF_UP` for `display_value` |
| **J09** (completion rate %) | **One** decimal place | Server `ROUND_HALF_UP` for `display_value` |
| JSON numeric `value` | Preserve sufficient precision for calculation audit; `null` when undefined (D06) | Client formats locale; **must not** re-round differently from server display |
| Undefined / empty AVG / zero denom | D06 unchanged — `value: null` + machine state; UI «غير متاح» | — |

Detail matrix: `S09_D14_PRECISION_UAT_MATRIX.md`.

**Closes** D02-Q18 / D06-Q8 presentation deferral. Prior “temp 1 d.p.” sketches are superseded by this Owner act.

---

## Scope boundary clarifications (binding)

| Decision | Is | Is not |
|---|---|---|
| D10=B | GPS is **in** the S09 product direction / combined workstream intent | Automatic approval of a vendor, tracking API, schema, or mock-as-live map |
| D11=A | Allow-listed drill-down from authorized KPI cards | Deep entity URLs without permission; disclosure of omitted KPI IDs |
| D12=A | D06 empty/undefined/error states only | New “source forbidden” card state for the 24 |
| D13=B | Code may be planned/built in parallel with debt tracking | Waiver of tenant-isolation / authz merge blockers; waiver of release certificates |
| D14 | Presentation frozen as above | Changing D02 formulas |
| D15=B | Combined delivery **direction** for S09+S10 | Blanket authorization of undefined executive widgets, alerts, or live map |

**D01 unchanged:** exactly 24 KPI IDs.  
**D08 unchanged:** `operational_kpis.view` + domain omit (permission **not created** until D16).  
**D09 unchanged:** no KPI-result cache in v1; provisional budgets; N+1 CI; measure-then-CR indexes.

---

## Classification legend (for downstream packs)

| Class | Meaning |
|---|---|
| **Approved Owner decision** | Recorded in this file / register |
| **Documented implementation requirement** | Must be built when coding is authorized (e.g. D14 rounding) |
| **Open technical specification** | Still needs engineering freeze (GPS provider; S10 widget list) |
| **Unapproved scope expansion** | New KPI IDs, formula changes, invented vendors — requires CR / Owner act |

---

## Related artifacts

| Doc | Role |
|---|---|
| `S09_D14_PRECISION_UAT_MATRIX.md` | Precision + UAT acceptance |
| `S09_GPS_S10_SCOPE_IMPACT_2026-11-08.md` | GPS/S10 gaps vs D10=B / D15=B |
| `S09_VALIDATION_DEBT_RISK_REGISTER.md` | S05/S06/S07 debt under D13=B |
| `S09_D16_DRAFT_AUTHORIZATION_2026-11-08.md` | D16 still **NOT AUTHORIZED**; reassessment appended |

---

## Status lines

```
S09-D10 = OWNER_APPROVED (B)
S09-D11 = OWNER_APPROVED (A)
S09-D12 = OWNER_APPROVED (A)
S09-D13 = OWNER_APPROVED (B)
S09-D14 = OWNER_APPROVED (precision matrix)
S09-D15 = OWNER_APPROVED (B)
S09-D16 = PENDING
NO_APPLICATION_CODE
```
