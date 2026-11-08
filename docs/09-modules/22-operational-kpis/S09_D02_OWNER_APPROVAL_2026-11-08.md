# S09-D02 — Owner Approval Record (2026-11-08)

> **Authorization boundary:** DESIGN DOCUMENTATION ONLY.  
> Does **not** authorize application code, migrations, commits, push, merge, PR, or deploy.  
> Does **not** approve S09-D03…D16.  
> Does **not** authorize S09 implementation.

---

## Authorization

| Field | Value |
|---|---|
| Date | **2026-11-08** |
| Decision | S09-D02 business formula semantics |
| Prior | S09-D01 `OWNER_APPROVED` — exactly **24** KPI identifiers |
| Result | `S09-D02 = OWNER_APPROVED_WITH_DEFERRED_PRESENTATION_POLICY` |

---

## Exact 24 KPI scope (unchanged from D01)

| Domain | IDs |
|---|---|
| Transport | T01, T02, T08 |
| Accommodation | A02, A03 |
| Procurement | P01, P02, P03, P04, P05, P06, P07, P08 |
| Seasonal Projects | J01, J02, J03, J07, J09 |
| Risks & Complaints | R01, R02, R03, R04, R05, R09 |

No add / replace / rename of identifiers under this approval.

---

## Exact Q1–Q18 choices (Owner, 2026-11-08)

| Q | Choice | Binding summary |
|---|---|---|
| Q17 | **A** | Procurement KPIs: `season_id = :selected` only; **exclude null**; no PR/join inference |
| Q1 | **A** | T01: `status IN ('planned','in_progress')` |
| Q2 | **B** | T08: `status IN ('open','replaced','closed')`; exclude `cancelled`; **not** “currently open only” |
| Q3 | **A** | A02: **assignment count**; `booking_status IN ('confirmed','completed')` |
| Q4 | **A** | A02/A03: operational snapshot **now**; historical/custom → D04 |
| Q5 | **A** | A03: `operational_status IN ('arrived','checked_in')` + confirmed booking (formula review) |
| Q6 | **A** | P01/P04: exclude `status = 'cancelled'` |
| Q7 | **A** | P03: `status IN ('published','awarded')` |
| Q8 | **C** | P06: status `received`/`closed` **AND** all lines `quantity_received >= quantity_ordered` |
| Q9 | **A** | P07: `SUM(purchase_order_lines.quantity_received)` only; never + GRN |
| Q10 | **A** | P07 auth: `purchase_orders.view` (D08 architecture still pending) |
| Q11 | **A** | J01/J09: exclude cancelled; respect SoftDeletes |
| Q13 | **A** | J09 denom: `status IN ('active','completed')` |
| Q12 | **A** | J07: arithmetic `AVG(progress_percent)` over non-cancelled |
| Q14 | **A** | R04: `new`,`under_review`,`in_progress` |
| Q15 | **A** | R05: `resolved` only (not `closed`) |
| Q16 | **A** | R02: high/critical **and** `status != 'closed'`; exclude null severity |
| Q18 | **A** | **Defer** precision/rounding/zero-denom/N/A to **D06/D14**; temp assumptions only |

---

## Approved formula semantics (condensed)

See `S09_FORMULA_MATRIX.md` and `S09_D02_DETAILED_FORMULA_REVIEW.md` for full cards.

### Cross-cutting (within D02)

- Tenant: `TenantContext` / `UsesTenantScope` only.
- Season: required selected season on each entity’s own `season_id` where applicable.
- SoftDeletes: exclude deleted where trait exists (projects, risks, complaints).
- GPS: none of the 24.
- P05 wording: **issued** pipeline — never “approved PO”.
- A02 ≠ A03.

### Q18 deferral (explicit)

Owner approved **deferral**, not final presentation policy.

| Temporary design assumption | Status |
|---|---|
| Ratios: 1 decimal place | **NOT Owner Approved policy** |
| Denominator 0 → display N/A | **NOT Owner Approved policy** |

Final: `S09-D06` / `S09-D14` = `PRESENTATION_POLICY_PENDING`.

---

## Unresolved dependencies (outside D02 freeze)

| ID | Status |
|---|---|
| S09-D03 | PENDING_OWNER_DECISION (enum freeze confirmation package) |
| S09-D04 | PENDING — historical/custom windows; TZ day bounds; on-time tolerance |
| S09-D05 | PENDING — no-season UX; archived seasons (Q17 season filter accepted for procurement null) |
| S09-D06 / D14 | PRESENTATION_POLICY_PENDING |
| S09-D07…D13, D15 | PENDING_OWNER_DECISION |
| S09-D16 | PENDING — implementation **not** authorized |

Technical clarifications recorded in the consistency audit (do not invent rules):

- A02/A03: clock source for “now” (tenant TZ vs UTC storage) → align under D04; interval fields themselves are schema-clear.
- P06: short-close (`closed` with incomplete quantities) is a **workflow-allowed** conflict vs Q8=C — such POs are **not** fulfilled under the approved dual condition.
- Full S09 permission architecture → D08 (Q10 only freezes P07’s view key for the PO SoR).

---

## No-code authorization boundary

| Action | Authorized by this document? |
|---|---|
| Update S09 design docs under `docs/09-modules/22-operational-kpis/` | **Yes** |
| Application code / migrations / DB writes | **No** |
| Commit / push / merge / PR / deploy | **No** |
| Start S09 implementation | **No** |
| Mark D03–D16 approved | **No** |

---

## Status lines

```
S09-D01 = OWNER_APPROVED
S09-D02 = OWNER_APPROVED_WITH_DEFERRED_PRESENTATION_POLICY
S09-D03...D16 = PENDING_OWNER_DECISION
S09-D06/D14 = PRESENTATION_POLICY_PENDING
NO_APPLICATION_CODE / NO_MIGRATIONS / NO_COMMIT / NO_PUSH / NO_MERGE / NO_DEPLOY
```
