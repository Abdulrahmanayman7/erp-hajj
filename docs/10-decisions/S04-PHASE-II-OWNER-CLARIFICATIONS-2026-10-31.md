# S04 Phase II — Owner Clarifications (II-D1–II-D4)

> **Status:** `APPROVED — OWNER CLARIFICATIONS`  
> **Owner decision date:** 2026-10-31  
> **Owner choices:** D1=A, D2=A, D3=A, D4=A (Phase II ratification instruction)  
> **Does not:** authorize Phase II application implementation, migrations, seeding, deployment, or S05.  
> **Does not:** renumber or reopen ADR-0022 decisions **101–130**.  
> **Does not:** replace ADR-0023 G1 Owner decisions **D1–D5** (geo / financial review / cancel_approved / sensitive perms / SoD).  

## 1. Identifier note (collision avoidance)

| Namespace | Meaning |
|---|---|
| ADR-0022 **101–130** | Functional freeze (unchanged) |
| ADR-0023 G1 **D1–D5** | Geo seed, mandatory PR financial review, approved-PR cancel, sensitive-perm seeding, reviewer≠final SoD |
| **This record II-D1–II-D4** | Phase II eligibility / banking / suspension / evaluation-criteria clarifications |

In Owner conversation the Phase II set was labeled “D1–D4”. In repository governance they are recorded as **II-D1–II-D4**.

## 2. Approved clarifications

### II-D1 — Tenant-managed eligibility requirements (= Owner D1=A)

1. Each **tenant** configures required supplier documents by **supplier type**, **country**, and optional **supplier category**.  
2. **No** universal platform-mandated mandatory-document matrix is authorized.  
3. Missing or **unapproved** requirement configuration must **not** make a supplier automatically eligible (**fail closed**).  
4. Eligibility configuration has an explicit tenant **authorization/approval state** (draft vs approved/published — engineering names in II-A design).  
5. Supplier **activation** requires:  
   - an **approved** applicable configuration for that supplier’s applicability keys; **and**  
   - **valid, verified** mandatory documents satisfying those requirements.  
6. Preserve machine code **`SUPPLIER_NOT_ELIGIBLE`** when activation eligibility fails.  
7. Do **not** add a fallback that activates suppliers when no requirements exist or configuration is missing/unapproved.

### II-D2 — Banking independence (= Owner D2=A)

1. A **verified bank account is not required** for supplier activation.  
2. Banking verification remains a **separate** controlled process (ADR-0022 **110**, ADR-0023 §2.5).  
3. **No** payment execution or accounting functionality is authorized in S04.

### II-D3 — No automatic suspension (= Owner D3=A)

1. Document **expiration** or a **low evaluation score** must **not** automatically change the supplier’s administrative lifecycle status (`active` / `temporarily_suspended` / `blocked` / …).  
2. Expired or missing mandatory documents must **prevent ineligible supplier use** on **new purchase requests** (when a supplier is referenced) — binding with ADR-0022 **109** / AC-S09.  
3. Warnings, auditability, and **authorized human** lifecycle transitions remain the only path to suspend/block/archive.

### II-D4 — Tenant-managed evaluation criteria (= Owner D4=A)

1. Each tenant configures evaluation criteria and weights **before** its first evaluation.  
2. **No** default evaluation criteria pack is authorized.  
3. **No** numeric low-score threshold is authorized at platform level.  
4. The evaluation engine must **validate** the approved criteria configuration before scoring (weights sum rules per ADR-0023 / DATA_MODEL ENGINEERING PROPOSAL).

## 3. Traceability to functional decisions

| Clarification | Primarily constrains |
|---|---|
| II-D1 | **107**, **109**, AC-S07 / AC-S09 |
| II-D2 | **110** (activation independence) |
| II-D3 | **108**, **109**, **115** |
| II-D4 | **112–116**, AC-E01 / AC-E04 |

## 4. Implementation authorization

| Item | Status |
|---|---|
| Record these clarifications | **Done** (this file) |
| II-A design (incl. binding controls C1–C6) | **APPROVED** — [S04-PHASE-II-A-DESIGN-APPROVAL-2026-10-31.md](S04-PHASE-II-A-DESIGN-APPROVAL-2026-10-31.md); [PHASE_II_A_ELIGIBILITY_DESIGN.md](../09-modules/19-suppliers-and-procurement/PHASE_II_A_ELIGIBILITY_DESIGN.md) |
| II-A engineering pre-impl gate | **COMPLETE** — [S04-PHASE-II-A-ENGINEERING-GATE-2026-10-31.md](S04-PHASE-II-A-ENGINEERING-GATE-2026-10-31.md) |
| Phase II / II-A **coding** | **NOT GRANTED** |
| Phase I acceptance | Unchanged — [S04_PHASE_I_ACCEPTANCE_STATUS.md](../00-project/S04_PHASE_I_ACCEPTANCE_STATUS.md) |

## 5. References

- [ADR-0022](ADR-0022-S04-SUPPLIERS-AND-PURCHASE-REQUESTS-FUNCTIONAL-DECISIONS.md)  
- [ADR-0023](ADR-0023-S04-ENGINEERING-DESIGN.md)  
- [CHANGE_REQUEST_S04_SUPPLIERS_AND_PROCUREMENT.md](../00-project/CHANGE_REQUEST_S04_SUPPLIERS_AND_PROCUREMENT.md)  
- Module pack `docs/09-modules/19-suppliers-and-procurement/`
