# S09 Part 0B — Baseline Reconciliation & Owner Decision Review

> **Status:** `S09_PART0_DESIGN_IN_PROGRESS` · design review only  
> **Date:** 2026-11-08  
> **Preserves:** original 11 Part 0 files (this file is additive)  
> **No Owner Approved formulas or coding authorization**

---

## 1. Baseline reconciliation

| Check | Result |
|---|---|
| `origin/develop` tip | `3a18b16` Merge PR #46 |
| `3a18b16` ancestor of `origin/develop` | **Yes** |
| Local branch (unchanged) | `feature/s08-seasonal-templates` @ `d1dc512` |
| `git diff d1dc512 origin/develop` | **Empty** (tree identical; merge commit only) |
| Branch switch / pull | **Not performed** (protects uncommitted S09 docs) |
| Stash | `stash@{0}` preserved |
| Application code / migrations / commit / push | **None** |

### Source spot-check on `origin/develop`

Confirmed present: `MovementStatus`, `RunStatus`, `BreakdownStatus`, `AccommodationCapacityGuard`, `PurchaseOrderStatus` (no `approved`), `PurchaseRequestStatus`, `SeasonProjectStatus`, `RiskScoreCalculator`, `GetDashboard`, S07 BR-05 (no SLA), dashboard BUSINESS_RULES.

**Conclusion:** Part 0 data-source assumptions remain valid against merged `develop`. No KPI source invalidation from S08 merge content.

---

## 2. Design changes required (documentation only — not applied as approvals)

| Item | Action |
|---|---|
| Catalog totals | Correct FEASIBLE/PARTIAL/BLOCKED counts (see §5) — counting error in catalog footer |
| P10 label | Standardize as **BLOCKED_BY_DATA_SOURCE** (proxy only if Owner invents formula later) |
| Formula matrix completeness | Expand full 30-field cards for MVP subset after Owner freezes D01/D02 (not blocking Part 0B) |
| Living ROADMAP/MATRIX S08 status | Stale `NOT_MERGED` vs reality `MERGED` @ `3a18b16` — out of S09 package; fix when docs commit authorized |
| S09-D03 | Add explicit Options A/B like other decisions when Owner reviews |

**No application redesign required** from develop reconciliation.

---

## 3. Catalog ↔ Formula inconsistencies

1. **Totals:** Catalog claimed F=24 / P=18 / B=6; recount = **F=27 / P=16 / B=5** (IDs below).  
2. **P10:** Catalog “BLOCKED (proxy PARTIAL)” vs Formula “BLOCKED” — treat as **BLOCKED** for v1.  
3. **Coverage:** Formula has full cards for subset; compact appendix covers rest — classifications align, depth differs.  
4. **R04/R07:** Catalog FEASIBLE but notes “Status/cohort TBD” — functionally **PARTIAL until D02**, still listed FEASIBLE meaning “data exists”. Part 0B treats them as **FEASIBLE-with-definition-gate**.

---

## 4. Corrected feasibility (48 IDs) — evidence on develop

### FEASIBLE (27)

T01, T02, T08 · A02, A03 · P01–P08 · J01, J02, J03, J05, J07, J09 · R01, R02, R03, R04, R05, R07, R08, R09  

Evidence anchors: movement/PO/PR/RFQ/project/risk/complaint tables & enums on `origin/develop` (paths in `S09_DATA_SOURCE_MAP.md`).

### PARTIAL (16)

T03–T07, T09 · A01, A04–A06, A08, A09 · P09 · J04, J10 · R10  

Need Owner formula/tolerance/denominator/window (D01/D02/D04).

### BLOCKED_BY_DATA_SOURCE (5)

| ID | Reason | Evidence |
|---|---|---|
| A07 | No facility readiness SoR | facilities `is_active` only |
| P10 | No promised delivery date | PO/PR/RFQ lack promise; offer `lead_time_days` insufficient alone |
| J06 | No milestones table | no model/migration |
| J08 | No planned progress | only `progress_percent` actual cache |
| R06 | No complaint SLA | BR-05 |

---

## 5. Recommended S09 MVP subset (`PROPOSED` / **NOT Owner Approved**)

**Principle:** FEASIBLE counts first; exclude BLOCKED; defer PARTIAL unless D02 freezes them in the same act.

| Domain | MVP IDs | Why |
|---|---|---|
| Transport | T01, T02, T08 | Clear statuses; no GPS; breakdown table exists |
| Accommodation | A02, A03 | Allocation vs occupancy both supported |
| Procurement | P01–P08 | Full pipeline counts + receipt SoR (pick one in D02) |
| Projects | J01, J02, J03, J07, J09 | Status + progress cache + completion rate |
| Risks/Complaints | R01, R02, R03, R04, R05, R09 | Open/severity/mitigated/escalation without SLA |

**MVP count: 26 KPIs** (optional add after freeze: T03, A06, R07, R08).

**Explicitly out of MVP:** all BLOCKED; T04–T07,T09; A01,A04,A05,A08,A09; P09–P10; J04–J06,J08,J10; R06,R10.

---

## 6. D01–D16 decision matrix (for one-by-one Owner review)

See Arabic report body / same content as `S09_OWNER_DECISIONS.md` with blocking flags:

| ID | Blocking for coding? |
|---|---|
| D01 list/units | **Yes** |
| D02 formulas | **Yes** |
| D03 status freezes | **Yes** (overlaps D02) |
| D04 time/TZ | **Yes** |
| D05 season rules | **Yes** |
| D06 N/A UX | **Yes** |
| D07 architecture | **Yes** |
| D08 permissions | **Yes** |
| D09 cache | Soft (default none) |
| D10 GPS | **Yes** (must be No for S09) |
| D11 drill-down | Soft |
| D12 blocked handling | Soft for MVP if dropped |
| D13 validation debt | Soft (recommend parallel) |
| D14 UAT | Soft before acceptance, not first commit |
| D15 vs S10 | Soft boundary |
| D16 coding auth | **Hard gate** |

---

## 7. GO/NO-GO for freezing design

| | |
|---|---|
| Freeze design **now** without Owner answers | **NO-GO** |
| Start Owner one-by-one decision review using this pack | **GO** |
| Start implementation | **NO-GO** until D01–D08+D10 frozen and **D16** granted |
