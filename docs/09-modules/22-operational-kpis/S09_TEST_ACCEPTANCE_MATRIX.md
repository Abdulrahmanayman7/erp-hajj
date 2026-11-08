# S09 — Test & Acceptance Matrix (Design Only)

> **Status:** PROPOSED — scenarios not executed in Part 0  
> **Date:** 2026-11-08

---

## Backend (Pest)

| ID | Scenario |
|---|---|
| BE-01 | Numerator/denominator match fixtures |
| BE-02 | Empty season → counts 0 or ratios N/A per rules |
| BE-03 | Missing timestamps excluded from on-time numerator |
| BE-04 | Zero denominator → N/A envelope |
| BE-05 | Soft-deleted excluded |
| BE-06 | Archived season read policy |
| BE-07 | Cross-season isolation |
| BE-08 | Cross-tenant → 404/empty (never other tenant counts) |
| BE-09 | Unauthorized domain keys omitted |
| BE-10 | Partial GRN: PO rollup xor GRN sum — no double count |
| BE-11 | PO “approved” mapping = issued semantics |
| BE-12 | Allocation ≠ occupancy fixtures |
| BE-13 | Complaint overdue KPI absent/unavailable (no SLA) |
| BE-14 | Milestone KPI unavailable |
| BE-15 | Query count budget / no N+1 smoke |

## Frontend (Vitest)

| ID | Scenario |
|---|---|
| FE-01 | RTL structure |
| FE-02 | Number formatting |
| FE-03 | Permission-sparse cards |
| FE-04 | Season switch refetch |
| FE-05 | Loading/error |
| FE-06 | N/A vs 0 rendering |
| FE-07 | No unauthorized API domain calls |
| FE-08 | Responsive breakpoints |

## Integration

| Domain | Probe |
|---|---|
| S01/S02 | Movements/runs/breakdowns |
| S03 | Capacity guard semantics |
| S04/S05 | PR/RFQ/PO/GRN |
| S06 | Projects/phases/tasks |
| S07 | Risks/complaints severity |
| S08 | Season context coexistence |

## Security

| ID | Probe |
|---|---|
| SEC-01 | Cross-tenant attack suite |
| SEC-02 | Cross-season |
| SEC-03 | Permission matrix |
| SEC-04 | No client tenant_id trust |
| SEC-05 | Omit ≠ zero leakage |

## UAT (Owner S09-D14)

Arabic walkthrough per domain with known fixture season; verify cards match hand counts; verify hidden domains for limited role.
