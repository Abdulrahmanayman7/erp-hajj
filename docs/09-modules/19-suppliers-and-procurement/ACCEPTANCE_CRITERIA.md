# Suppliers & Procurement (S04) — Acceptance Criteria

> **Status:** Phase I–V **all Owner-functionally accepted** (II-C/IV/V FINAL 2026-11-01); accepted non-blocking follow-ups preserved; S04 overall **READY FOR OWNER CLOSURE** but **NOT CLOSED**; S05 / Git release unauthorized  
> **Last updated:** 2026-11-01
> Traceability to ADR-0022 decisions **101–130**.

A criterion is met only with automated and/or recorded UAT evidence under DEFINITION_OF_DONE. This file does **not** authorize coding.

---

## A. Supplier registry

| ID | Criterion | Decisions |
|---|---|---|
| AC-S01 | Tenant A cannot read/write Tenant B suppliers (404) | 102 |
| AC-S02 | One supplier may link multiple categories without duplicate identity rows | 101 |
| AC-S03 | Duplicate legal id within tenant rejected; name similarity warns only | 103 |
| AC-S04 | Category in use cannot hard-delete; deactivate works | 104 |
| AC-S05 | Type-specific required fields enforced for supported types | 105 |
| AC-S06 | Coverage uses geo catalog places only (no free-text SoR); initial seed limited to D1 places; extensible without duplicates | 106, **D1** |
| AC-S07 | Cannot activate without mandatory valid documents | 107, 109 |
| AC-S08 | Lifecycle transitions follow allowed graph; audited | 108 |
| AC-S09 | Expired mandatory doc blocks eligibility for new supplier use | 109 |
| AC-S10 | Multiple contacts/banks; primary bank; verify flow; masked default; no payment APIs. II-B detail: AC-B01–**B22** in [PHASE_II_B_SUPPLIER_BANKING_DESIGN.md](PHASE_II_B_SUPPLIER_BANKING_DESIGN.md) — **FUNCTIONALLY CLOSED — OWNER ACCEPTED** (2026-11-01); evidence [PHASE_II_B_WP05_ACCEPTANCE_EVIDENCE.md](PHASE_II_B_WP05_ACCEPTANCE_EVIDENCE.md) | 110 |
| AC-S11 | No forced contract migration; Contracts still work with counterparty_name | 111 |

## B. Evaluations (Phase II-C — design draft; not Owner-accepted as implemented)

| ID | Criterion | Decisions |
|---|---|---|
| AC-E01 | Weighted score 0–100 with category weights; history retained; submit snapshots (**IIC-D2**) | 112, 113, **IIC-D1/D2** |
| AC-E02 | Kinds supported; `post_season` requires same-tenant season; other kinds optional (**IIC-F1**) | 113, **IIC-D5**, **IIC-F1** |
| AC-E03 | Preparer cannot approve own evaluation | 114 |
| AC-E04 | Final monitoring on approve + in-app alert to `supplier_evaluations.approve` holders only when final true (**IIC-D3/D7/F2/F3**); draft proposal does not notify; supplier status unchanged | 115, **II-D3** |
| AC-E05 | Appeal register; accepted correction → new version; no supplier portal (**IIC-D4**) | 116 |

## C. Purchase requests — Phase III acceptance subset

| ID | Criterion | Decisions | Phase |
|---|---|---|---|
| AC-P01 | Authorized user creates PR with requester + beneficiary active org unit | 117, **III-D5** | III |
| AC-P02 | Seasonal PR requires season; administrative omits season | 118 | III |
| AC-P03 | Multi-line PR; catalog or free text; attachments optional | 119 | III |
| AC-P04 | Unpriced lines need reason; partial vs complete totals visible | 120 | III |
| AC-P05 | Financial review **recordable** (draft/submitted) with reviewer/outcome/time/justification/version; no ledger/reservation/payment; creator may review if permitted | 120, **D2**, **III-D2**, **III-D3** | III (record) |
| AC-P05b | Final approve blocked when review missing or `not_cleared` | **D2** | **IV** |
| AC-P05c | Approved PR cancel only with `cancel_approved` + reason + audit; blocked when procurement engagement lock set | **D3** | **IV** |
| AC-P05d | Financial reviewer cannot complete final approval (hard block); multi-role does not bypass | **D5** | **IV** |
| AC-P05e | Delegation cannot bypass D5 | **D5** | **IV** |
| AC-P05f | Conflict reassigns / escalate; never auto-approve | **D5** | **IV** |
| AC-P05g | Reassignment/escalation/blocked attempts audited | **D5**, 130 | **IV** |
| AC-P06 | Supplier optional; if set → tenant + not archived + eligibility; cross-tenant 404 | ADR-0022 §5, **III-D4** | III |
| AC-P07a | Submit → `submitted` only; no matrix/steps; structured version snapshot; submitted not editable | 121, **III-D1**, **III-D7** | III |
| AC-P07b | Return → revise → new version; approvals re-run | 121, 128 | **IV** |
| AC-P08 | Priority urgent/emergency requires justification; verify_priority; no approval bypass | 122 | III |
| AC-P09a | Duplicate warnings require acknowledgement; no hard-block on similarity alone | 123, **III-D6** | III |
| AC-P09b | Planning group does not merge into RFQ/PO | 123 | **V** |
| AC-P10 | FE has no approve/reject/return controls in Phase III | **III-D8** | III |

## D. Approvals & control — Phase IV (+ reports Phase V)

| ID | Criterion | Decisions | Phase |
|---|---|---|---|
| AC-A01 | Route resolved from tenant matrix; snapshot stored on PR | 124, 125 | IV |
| AC-A02 | Matrix change does not rewrite in-flight snapshot | 125 | IV |
| AC-A03 | Approve above user ceiling rejected | 125 | IV |
| AC-A04 | Creator cannot approve own PR; conflict reassignment keeps level | 126 | IV |
| AC-A05 | Delegation time-bounded, audited, cannot raise authority | 127 | IV |
| AC-A06 | Reject vs return distinct; resubmit invalidates prior step validity | 128 | IV |
| AC-A07 | SLA reminder/escalation without auto-approve | 129 | IV |
| AC-A08 | Material events in audit; reports tenant-scoped; no bank secrets in audit | 130 | IV/V |
| AC-V01…V14 | Demand groups + four reports + CSV (see [PHASE_V_DETAILED_DESIGN.md](PHASE_V_DETAILED_DESIGN.md) §15) | 123, 130, **V-D1…V-D8** | **V** |

## E. Nonfunctional / platform

| ID | Criterion |
|---|---|
| AC-N01 | Permissions deny-by-default; Owner still Policy-bound; default role templates **omit** sensitive S04 verbs (**D4**) |
| AC-N02 | PR/supplier numbers allocated under row lock; concurrent creates unique |
| AC-N03 | Approval step double-submit idempotent / second fails safely |
| AC-N04 | Arabic RTL UI for list/detail; Phase III **without** approve CTAs (**III-D8**); approve flows = Phase IV |
| AC-N05 | Regression: Contracts + Inventory receive still pass existing suites |
| AC-N06 | **No** RFQ/PO/GRN endpoints or UI shipped in S04 |

## F. Gate exclusions

- ADR-0019 status unchanged.
- S04 overall is **READY FOR OWNER CLOSURE** but remains **NOT OWNER-CLOSED** until a separate FINAL FUNCTIONAL CLOSURE act — [S04_OWNER_FINAL_CLOSURE_DECISION_PACKET.md](../../00-project/S04_OWNER_FINAL_CLOSURE_DECISION_PACKET.md).
- Phase II-C evaluations are **Owner-functionally accepted** — [S04-PHASE-II-C-FINAL-FUNCTIONAL-ACCEPTANCE-2026-11-01.md](../../10-decisions/S04-PHASE-II-C-FINAL-FUNCTIONAL-ACCEPTANCE-2026-11-01.md).
- Phase V AC-V\* / AC-P09b / AC-A08 report+CSV are **Owner-functionally accepted**; MariaDB 1213 residual **non-blocking**.
- Commit/push/merge/deploy / S05 remain **NOT AUTHORIZED** without separate Owner acts.
