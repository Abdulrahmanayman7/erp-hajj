# Risks & Complaints — Acceptance Criteria

> **Status:** PART 6 — criteria verified via automated Pest/Vitest UAT (SQLite `:memory:`)
> Owner merge review + MySQL 8 staging still required before production.

## Module

| ID | Criterion | Decisions |
|---|---|---|
| **AC-M1** | Risks and complaints are separate aggregates with separate permission prefixes and lifecycles inside one module pack | Q2 |

## Risks

| ID | Criterion | Decisions |
|---|---|---|
| **AC-R1** | Authorized user with `risks.create` can create an `identified` risk; without permission → 403 | P01 |
| **AC-R2** | L/I ∈ [1,5]; score=L×I; severity bands match D02 exactly | Q3, D02 |
| **AC-R3** | Creator cannot approve assessment even with permission; other approver can | P02 |
| **AC-R4** | Only D01 transitions succeed; illegal transitions return `RISK_INVALID_TRANSITION` | D01 |
| **AC-R5** | Approval requires treatment fields title, plan_body, owner_employee_id, status (+ optional due_at) | D11 |

## Complaints

| ID | Criterion | Decisions |
|---|---|---|
| **AC-C0** | Staff user with `complaints.create` can intake; no public/pilgrim create API | P01, D10 |
| **AC-C1** | Status graph matches Q4; happy path reaches `closed` | Q1, Q4 |
| **AC-C2** | Resolve stores resolution_text; close/reopen require reasons; reopen goes to `under_review` with no time cap | P03, D04 |
| **AC-C3** | Every row has season_id; optional links validated for tenant/season/existence | Q5 |
| **AC-C4** | Escalation allowed for assignee or `complaints.escalate`; audited; does not auto-close | D03 |

## Cross-cutting

| ID | Criterion | Decisions |
|---|---|---|
| **AC-D5** | Soft-delete only in risk `identified` / complaint `new` | D05 |
| **AC-D6** | Numbers `RSK/CMP-YYYY-######` unique per tenant+season; no reuse after soft-delete | D06 |
| **AC-D8** | Creating without project works; with valid project works when available; invalid/unavailable handled with stable codes | D08 |
| **AC-D12** | Concurrent conflicting transitions: one success, one stable conflict | D12 |
| **AC-P9** | `approve_assessment`, `complaints.close`, `complaints.reopen` are sensitiveExplicitOnly | D09 |
| **AC-A1** | Critical transitions produce audit rows with actor, tenant, correlation id | A01 |
| **AC-N1** | In-app notifications for assign/escalate/high-critical/resolve/reopen with occurrence dedupe | N01 |
| **AC-X1** | No command-center/crisis UI; no external channels; staff-only intake | Q6, D10 |
| **AC-X2** | No SLA fields or SLA jobs shipped | D07 |
| **AC-S1** | Cross-tenant show → 404; permissions deny-by-default | P04 |

## Part 6 additions

| ID | Criterion | Result |
|---|---|---|
| **AC-T1** | Timeline from `audit_logs` gated by module view; no second store | PASS |
| **AC-L1** | Optional operational link selectors reuse season APIs; server validates | PASS |
| **AC-L2** | Project field disabled when SeasonProjects unavailable | PASS |
| **AC-E1** | Escalate UI for assignee without `complaints.escalate` | PASS |

## Documentation pack

| ID | Criterion |
|---|---|
| **AC-P0-1** | Module docs present under `docs/09-modules/21-risks-and-complaints/` |
| **AC-P0-2** | All 24 Owner decisions referenced consistently |
| **AC-P6-1** | API/UI/permissions/UAT docs reflect implemented Part 6 scope |
