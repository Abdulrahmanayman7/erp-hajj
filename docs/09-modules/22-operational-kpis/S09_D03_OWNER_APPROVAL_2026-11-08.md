# S09-D03 — Owner Approval Record (2026-11-08)

> **Authorization boundary:** DESIGN DOCUMENTATION ONLY.  
> Does **not** authorize application code, migrations, commits, push, merge, PR, or deploy.  
> Does **not** approve S09-D04…D16.  
> Does **not** authorize S09 implementation.

---

## Authorization

| Field | Value |
|---|---|
| Date | **2026-11-08** |
| Decision | Freeze existing enum/status values for all 24 S09 v1 KPIs |
| Prior | D01 `OWNER_APPROVED` · D02 `OWNER_APPROVED_WITH_DEFERRED_PRESENTATION_POLICY` |
| Result | `S09-D03 = OWNER_APPROVED` |

---

## Exact 24 KPI scope (unchanged)

| Domain | IDs |
|---|---|
| Transport | T01, T02, T08 |
| Accommodation | A02, A03 |
| Procurement | P01–P08 |
| Seasonal Projects | J01, J02, J03, J07, J09 |
| Risks & Complaints | R01–R05, R09 |

No add / replace / rename of identifiers under this approval.

---

## Owner decision (binding)

1. Use existing Laravel enum values and actual model/database status values.  
2. Do **not** introduce any new enum values.  
3. Do **not** modify lifecycle transitions.  
4. Do **not** modify business workflow guards.  
5. Preserve exact D02-approved inclusion and exclusion status sets.  
6. Preserve current database CHECK constraints (including accommodation `acc_asg_booking_ck`).  
7. Preserve existing SoftDeletes behavior.  
8. Preserve approved treatment of known edge cases (below).

---

## Compatibility outcome (audit evidence, 2026-11-08)

| Metric | Result |
|---|---|
| KPIs compatible with existing enums | **24 / 24** |
| Formula-to-enum conflicts | **0** |
| New status values required | **0** |
| Reopen D02 required | **No** |

---

## Domain-by-domain status summary

### Transport

| Enum | Values (frozen) | KPI use |
|---|---|---|
| `MovementStatus` | draft, planned, in_progress, completed, cancelled | T01 = planned+in_progress; T02 = completed |
| `BreakdownStatus` | open, replaced, closed, cancelled | T08 = open+replaced+closed (exclude cancelled) |

### Accommodation

| Field | Values (frozen) | KPI use |
|---|---|---|
| `booking_status` | draft, confirmed, cancelled, completed | A02 = confirmed+completed @ now; A03 booking = confirmed |
| `operational_status` | null≡assigned, assigned, arrived, checked_in, checked_out | A03 = arrived+checked_in; not assigned/null/checked_out |
| CHECK | completed ⇒ operational_status = checked_out | Prevents completed+arrived/checked_in distortion |

### Procurement

| Enum | Values (frozen) | KPI use |
|---|---|---|
| `PurchaseRequestStatus` | draft…approved, rejected, cancelled | P01 ≠ cancelled; P02 = approved |
| `ProcurementRfqStatus` | draft, published, awarded, cancelled | P03 = published+awarded |
| `PurchaseOrderStatus` | draft, issued, partially_received, received, closed, cancelled | P04–P08 as D02; **no** invented `approved` PO status |
| Line qtys | `quantity_ordered`, `quantity_received` (decimal:4) | P06 dual condition; P07 SUM; P08 short lines |

### Seasonal Projects

| Enum | Values (frozen) | KPI use |
|---|---|---|
| `SeasonProjectStatus` | draft, active, completed, cancelled | J01 ≠ cancelled; J02 active; J03 completed; J07 AVG ≠ cancelled (incl. draft); J09 denom active+completed |
| SoftDeletes | yes | Always exclude deleted |

### Risks & Complaints

| Enum / field | Values (frozen) | KPI use |
|---|---|---|
| `RiskStatus` | identified, under_assessment, approved, treating, closed | R01 ≠ closed; R02 open high/critical |
| `RiskSeverity` | low, medium, high, critical | R02 high+critical; exclude null |
| `RiskClosureOutcome` | mitigated, accepted, transferred, cancelled | R03 = closed + mitigated |
| `ComplaintStatus` | new, under_review, in_progress, resolved, closed | R04 open set; R05 resolved only (disjoint) |
| `escalation_level` | integer ≥ 0 | R09 level > 0 |
| SoftDeletes | yes on risk & complaint | Exclude deleted |

---

## Approved edge-case treatment (preserved)

| Case | Treatment |
|---|---|
| T08 | Non-cancelled breakdown **events** — not “currently open only” |
| A03 | Does **not** count completed / checked_out as current occupancy |
| P05 | **Issued** PO pipeline — not invented approved PO status |
| P06 | Status received/closed **and** all lines full; short-closed incomplete **excluded** |
| J07 | Arithmetic AVG includes **non-cancelled draft** projects |
| R04 / R05 | Disjoint status groups |
| R09 | May include already-resolved complaints when `escalation_level > 0` |

---

## Relationship to D02

- D03 **confirms** enum/status packages used by D02 formulas.  
- D03 does **not** change any D02-approved formula.  
- Q18 presentation policy remains deferred to D06/D14.

---

## Remaining decisions

| ID | Status |
|---|---|
| S09-D04…D16 | `PENDING_OWNER_DECISION` |
| S09-D06 / D14 | `PRESENTATION_POLICY_PENDING` |
| S09-D16 | Implementation **not** authorized |

---

## No-code authorization boundary

| Action | Authorized? |
|---|---|
| Record D03 in S09 design docs | **Yes** |
| Application code / migrations / DB writes | **No** |
| Commit / push / merge / PR / deploy | **No** |
| Start S09 implementation | **No** |
| Approve D04 on Owner’s behalf | **No** |

---

## Status lines

```
S09-D01 = OWNER_APPROVED
S09-D02 = OWNER_APPROVED_WITH_DEFERRED_PRESENTATION_POLICY
S09-D03 = OWNER_APPROVED
S09-D04...D16 = PENDING_OWNER_DECISION
NO_APPLICATION_CODE / NO_MIGRATIONS / NO_COMMIT / NO_PUSH / NO_MERGE / NO_DEPLOY
```
