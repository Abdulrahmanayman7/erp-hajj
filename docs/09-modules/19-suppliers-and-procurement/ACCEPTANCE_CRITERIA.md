# Suppliers & Procurement (S04) — Acceptance Criteria

> **Status:** Designed (G1) — applies when implementation is later authorized  
> **Last updated:** 2026-10-31  
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
| AC-S10 | Multiple contacts/banks; primary bank; verify flow; masked default; no payment APIs | 110 |
| AC-S11 | No forced contract migration; Contracts still work with counterparty_name | 111 |

## B. Evaluations

| ID | Criterion | Decisions |
|---|---|---|
| AC-E01 | Weighted score 0–100 with category weights; history retained | 112, 113 |
| AC-E02 | Optional season on evaluation; kinds supported | 113 |
| AC-E03 | Preparer cannot approve own evaluation | 114 |
| AC-E04 | Low score alerts; supplier status unchanged automatically | 115 |
| AC-E05 | Appeal/correction produces new version; no supplier portal | 116 |

## C. Purchase requests

| ID | Criterion | Decisions |
|---|---|---|
| AC-P01 | Authorized dept user creates PR with requester + beneficiary unit | 117 |
| AC-P02 | Seasonal PR requires season; administrative forbids/omits season | 118 |
| AC-P03 | Multi-line PR; catalog or free text; attachments optional | 119 |
| AC-P04 | Unpriced lines need reason; partial vs complete totals visible | 120 |
| AC-P05 | **Every** PR requires financial review before final approval (incl. unpriced); records reviewer, outcome, time, justification, version; no ledger/reservation/payment | 120, **D2** |
| AC-P05b | Final approve blocked when review missing or `not_cleared` | **D2** |
| AC-P05c | Approved PR cancel only with `cancel_approved` + reason + audit; blocked when procurement engagement lock set | **D3** |
| AC-P05d | Financial reviewer cannot complete final approval (hard block); multi-role does not bypass | **D5** |
| AC-P05e | Delegation cannot bypass D5 (acting delegate who reviewed finance cannot final-approve) | **D5** |
| AC-P05f | Conflict reassigns to independent eligible approver; if none → block + escalate; never auto-approve/waive | **D5** |
| AC-P05g | Reassignment/escalation/blocked attempts audited | **D5**, 130 |
| AC-P06 | Supplier selection not required to create/submit PR | ADR-0022 §5 |
| AC-P07 | Under-review not directly editable; return → revise → new version; approvals re-run | 121, 128 |
| AC-P08 | Priority urgent/emergency requires justification; no approval bypass | 122 |
| AC-P09 | Duplicate warnings; planning group does not merge into RFQ/PO | 123 |

## D. Approvals & control

| ID | Criterion | Decisions |
|---|---|---|
| AC-A01 | Route resolved from tenant matrix; snapshot stored on PR | 124, 125 |
| AC-A02 | Matrix change does not rewrite in-flight snapshot | 125 |
| AC-A03 | Approve above user ceiling rejected | 125 |
| AC-A04 | Creator cannot approve own PR; conflict reassignment keeps level | 126 |
| AC-A05 | Delegation time-bounded, audited, cannot raise authority | 127 |
| AC-A06 | Reject vs return distinct; resubmit invalidates prior step validity | 128 |
| AC-A07 | SLA reminder/escalation without auto-approve | 129 |
| AC-A08 | Material events in audit; reports tenant-scoped; no bank secrets in audit | 130 |

## E. Nonfunctional / platform

| ID | Criterion |
|---|---|
| AC-N01 | Permissions deny-by-default; Owner still Policy-bound; default role templates **omit** sensitive S04 verbs (**D4**) |
| AC-N02 | PR/supplier numbers allocated under row lock; concurrent creates unique |
| AC-N03 | Approval step double-submit idempotent / second fails safely |
| AC-N04 | Arabic RTL UI for list/detail/approve flows |
| AC-N05 | Regression: Contracts + Inventory receive still pass existing suites |
| AC-N06 | **No** RFQ/PO/GRN endpoints or UI shipped in S04 |

## F. Gate exclusions

- ADR-0019 status unchanged.  
- S04 implementation status remains NOT_STARTED until Owner authorizes coding.  
- S05 scope not accepted as done by any S04 criterion.
