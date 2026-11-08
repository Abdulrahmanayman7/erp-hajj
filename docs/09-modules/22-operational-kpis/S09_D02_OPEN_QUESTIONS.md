# S09-D02 — Open Questions → Owner Answers (2026-11-08)

> **Status:** All Q1–Q18 **answered** by Owner on 2026-11-08.  
> D02 freeze: `OWNER_APPROVED_WITH_DEFERRED_PRESENTATION_POLICY`  
> Record: `S09_D02_OWNER_APPROVAL_2026-11-08.md`  
> D03–D08 remain separate decisions; Q18 defers presentation to D06/D14.

| # | KPI(s) | Question | Options | Owner choice | Binding |
|---|---|---|---|---|---|
| Q1 | T01 | Include `in_progress` with `planned`? | Yes / planned-only | **A = Yes** | `status IN ('planned','in_progress')` |
| Q2 | T08 | Count which breakdown statuses? | open-only / all except cancelled / all | **B = all except cancelled** | `open`,`replaced`,`closed`; label ≠ “open only” |
| Q3 | A02 | Unit of “capacity”? | assignment count / unit capacity peak / positions | **A = assignment count** | COUNT eligible assignments |
| Q4 | A02/A03 | As-of window? | now / whole season / custom (→D04) | **A = now** | Snapshot now; custom → D04 |
| Q5 | A03 | Occupied ops set? | arrived+checked_in / checked_in | **A = both** | `arrived`,`checked_in` + confirmed booking |
| Q6 | P01/P04 | Exclude cancelled? | yes / no | **A = yes** | `status != 'cancelled'` |
| Q7 | P03 | Include `awarded`? | yes / published-only | **A = yes** | `published`,`awarded` |
| Q8 | P06 | Fulfilled definition? | A status / B lines / C both | **C = both** | status + all lines full |
| Q9 | P07 | Qty SoR? | PO lines / posted GRN | **A = PO lines** | `SUM(quantity_received)` |
| Q10 | P07/P08 | Permission if GRN path? | PO view / also GRN view | **A = PO view** | `purchase_orders.view` (SoR=A) |
| Q11 | J01/J09 | Exclude cancelled? | yes / no | **A = yes** | exclude cancelled; SoftDeletes |
| Q12 | J07 | Season AVG? | avg non-cancelled / active-only / no avg | **A = avg non-cancelled** | `AVG(progress_percent)` |
| Q13 | J09 | Denominator? | active+completed / non-cancelled / all | **A = active+completed** | draft/cancelled out of denom |
| Q14 | R04 | Open complaint statuses? | new+under_review+in_progress | **A** | as proposed |
| Q15 | R05 | Resolved includes closed? | resolved-only / +closed | **A = resolved-only** | not closed |
| Q16 | R02 | High/critical include closed? | open-only / all-time | **A = open-only** | `status != 'closed'` |
| Q17 | All procurement | Null season excluded? | exclude / PR join | **A = exclude** | no join inference |
| Q18 | Global | Ratio precision / N/A? | defer D06/D14 + temp defaults | **A = defer** | temp 1 d.p. + N/A **not** final policy |

## Non-questions for D02 (already clear in code)

- No GPS for T01/T02/T08.  
- PO has no `approved` status — P05 = issued pipeline.  
- A02 ≠ A03.  
- R06 SLA not in scope (not in 24).  
- R07 not in D01 list.

## Still outside D02 (not reopened as Q1–Q18)

- Full enum confirmation package → **D03**  
- Historical windows / TZ day bounds → **D04**  
- No-season UX / archived seasons → **D05**  
- Final N/A & precision → **D06 / D14**  
- Architecture / RBAC / cache / GPS / drill-down / coding auth → **D07–D16**
