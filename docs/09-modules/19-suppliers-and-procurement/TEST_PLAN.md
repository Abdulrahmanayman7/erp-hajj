# Suppliers & Procurement (S04) — Test Plan

> **Status:** Phase I–IV tests executed/accepted per stream evidence (Phase IV Owner functionally accepted 2026-11-01). **Phase V tests:** **EXECUTED — ENGINEERING PASS** (2026-11-01) — [PHASE_V_FINAL_IMPLEMENTATION_AND_ACCEPTANCE_EVIDENCE.md](PHASE_V_FINAL_IMPLEMENTATION_AND_ACCEPTANCE_EVIDENCE.md); Owner functional acceptance **PENDING**; commit/push/merge/deploy **NOT AUTHORIZED**  
> **Last updated:** 2026-11-01
> Tooling: **Pest** (backend) · **Vitest** (frontend) · optional MySQL concurrency job pattern from Transport/Accommodation
> Binding: [TESTING_STRATEGY.md](../../07-testing/TESTING_STRATEGY.md) · tenancy matrix in `00-tenancy/TEST_PLAN.md`

---

## 1. Backend feature API (per resource family)

For suppliers, evaluations, PRs, matrices, delegations, financial reviews:

| Case | Expect |
|---|---|
| Happy path | 200/201 |
| Validation | 422 + stable codes |
| Unauthenticated | 401 |
| Missing permission | 403 |
| Cross-tenant id | **404** |
| Suspended/archived tenant lifecycle | existing tenancy codes |

**Mandatory:** ≥1 explicit cross-tenant attack test per tenant-owned aggregate (suppliers, evaluations, PRs, bank accounts, approval steps).

---

## 2. Policy / SoD

- Evaluation approve by preparer → 403/`EVALUATION_SOD_VIOLATION`.
- PR approve by creator → denied.
- Approve above `purchase_approver_authority` → denied.
- Bank verify by submitter of pending change → denied.
- `view_banking` absent → masked resource only / 403 on reveal endpoint.

---

## 3. Workflow transition suites

### Supplier lifecycle

Valid/invalid transitions matrix; activate blocked when docs expired.

### PR lifecycle — Phase III (**III-D1**, **III-D7**)

draft→submit→**`submitted`** (no matrix/steps); edit while submitted → 422; structured version snapshot on submit; cancel non-approved; optional supplier eligibility; org unit active; duplicate ack (**III-D6**); FE has no approve CTAs (**III-D8**).

### Approvals — Phase IV

Multi-level happy path; skip level impossible; conflict reassignment; delegation happy/expired/revoked; matrix snapshot isolation when matrix edited mid-flight; return→edit→resubmit increments version and recreates pending steps.

### Financial review (**D2** / **III-D2**) + SoD (**D5**)

- Phase III: review on draft/submitted stores reviewer, outcome, timestamp, justification, version_no; creator may review (**III-D3**); no status change; no inventory/finance side effects.
- Phase IV: final approve without review → `PR_FINANCIAL_REVIEW_REQUIRED`; unpriced still needs cleared/cleared_with_conditions; `not_cleared` blocks; reviewer final-approve → `PR_FINANCIAL_REVIEWER_SOD`; multi-role and delegation cannot bypass (**D5**); no alternate → `PR_NO_ELIGIBLE_FINAL_APPROVER`; never auto-approve.

### Approved cancel (**D3**)

- `cancel` on approved → 403.
- `cancel-approved` without permission → 403; without reason → 422.
- With lock `procurement_engaged_at` set → `PR_PROCUREMENT_ENGAGED`.
- Audit contains actor + reason; S05 entities absent.

---

## 4. Concurrency

| Scenario | Assert |
|---|---|
| Parallel PR creates | Unique `pr_number`; sequence integrity |
| Parallel supplier creates | Unique `supplier_number` |
| Double approve same step | One success; second idempotent or 409/422 stable |
| Primary bank race | Single primary remains |

Use MySQL + `lockForUpdate` patterns; do not weaken tests.

---

## 5. Audit

- Critical actions create audit rows with `tenant_id` + correlation id.
- Bank update / reveal audit contains last4/status/reason only — never full IBAN plaintext.
- Reveal success **and** denied attempts audited where feasible (`_REVEALED` / `_REVEAL_DENIED`).
- Feature test spies/`Event::fake` per module convention.

### 5.1 Phase II-B banking (FUNCTIONALLY CLOSED — OWNER ACCEPTED 2026-11-01)

> Evidence: [PHASE_II_B_WP05_ACCEPTANCE_EVIDENCE.md](PHASE_II_B_WP05_ACCEPTANCE_EVIDENCE.md) · closure [S04_PHASE_II_B_ACCEPTANCE_STATUS.md](../../00-project/S04_PHASE_II_B_ACCEPTANCE_STATUS.md).

See design pack §11–§12 (AC-B01–**B22**; controls **B1–B8**; doc closure **M1–M3**). Must cover when coding authorized:

- Claims table: same-IBAN supersession OK; different-IBAN keeps old primary’s IBAN claimed; cross-family duplicate fails; reuse only after safe **DELETE** release (**E3**, **M2**, C1 remediation).
- Pending PATCH IBAN change: acquire new claim, reject foreign ownership, update ciphertext/last4, safe-release old; metadata-only PATCH must not release claims (**M1**, AC-B20). Real-MySQL concurrency for PATCH vs foreign create / release vs acquire.
- Claim ownership integrity: no cross-family reassignment; no stale holder; no orphans; rollback of version+claim on failure (**M2**, AC-B21).
- D11 primary retention; verify no transfer; explicit set-primary; revoke clears (**E2**).
- Reveal reason ≥10; revoked reveal via reveal only; deny audits (**E1**/**E4**).
- Frontend: reveal modal blank/short/valid reasons; Arabic message; no persistence; no plaintext in FE logs (**M3**, AC-B22).
- MySQL: primary races; dual claim; supersede-while-primary; foreign claim of retained-primary IBAN; PATCH IBAN races; release/acquire races.

### 5.2 Phase II-C evaluations (DESIGN DRAFT — not executed)

> Matrix: [PHASE_II_C_SUPPLIER_EVALUATIONS_DESIGN.md](PHASE_II_C_SUPPLIER_EVALUATIONS_DESIGN.md) §11 · decisions [S04-PHASE-II-C-OWNER-DECISIONS-2026-11-01.md](../../10-decisions/S04-PHASE-II-C-OWNER-DECISIONS-2026-11-01.md).

When coding authorized: AC-E01–E05; criteria publish SoD; evaluation SoD; appeal independence; weight ±0.01; snapshot immutability; cross-tenant 404; submit/approve races; **IIC-F1** season matrix; monitoring — draft proposal does **not** notify; approve+true notifies `supplier_evaluations.approve` holders only (**IIC-F2/F3**); notification idempotency; no supplier lifecycle mutation; evidence ownership; FE RTL permission CTAs.

---

## 6. Notifications

- Approver receives in-app notification on assign/submit.
- SLA reminder job/command dry-run in test (if implemented).
- Tenant isolation of notification recipient.

---

## 7. Frontend Vitest

- Validation: seasonal without season; unpriced without reason; urgency without justification.
- Permission visibility: approve/banking/financial-review/cancel-approved/delegation buttons hidden when `can()` false (**D4**).
- Geo coverage picker lists only seeded D1 places (+ later extensions when added).
- Page states: loading/empty/error for list pages.
- Mutation hooks: submit/return/approve payloads.
- `tsc` clean for new module.

---

## 8. Regression

- Existing Contracts Pest + Inventory receive/transfer suites remain green.
- Documents upload still works; new morph aliases authorized.
- No new routes under `/rfqs` or `/purchase-orders`.

---

## 9. Decision coverage map (tests ↔ 101–130)

| Decisions | Primary tests |
|---|---|
| 101–111 | Supplier feature + eligibility + banking + geo coverage + cross-tenant |
| 112–116 | Evaluation scoring, SoD, low-score no auto-suspend, appeal version |
| 117–123 | PR CRUD, season rules, lines, financial review, versioning, duplicate/group |
| 124–130 | Matrix snapshot, authority, SoD, delegation, SLA no auto-approve, audit reports |

---

## 10. Phase V — Demand groups & control reports (**V-D1…V-D8**)

> Design only until coding auth. Binding AC: [PHASE_V_DETAILED_DESIGN.md](PHASE_V_DETAILED_DESIGN.md) §15.

| Area | Cases |
|---|---|
| Exclusive membership | Concurrent link same PR → one win / `DG_PR_ALREADY_GROUPED`; re-link after unlink OK |
| Mixed season/OU | Link succeeds; warning payloads present; null scope OK |
| Lifecycle | Transition matrix; frozen/closed reject membership edits |
| Cancel auto-unlink | `cancel_approved` unlinks; history retained; idempotent; IV cancel suite still green |
| Engagement freeze | Fixture `procurement_engaged_at` blocks link/unlink; Phase V never writes field |
| Tenant isolation | Cross-tenant group/PR → 404 |
| Partial visibility | Under III-D5 no-op; forward-compat redaction unit tests if helpers land |
| Reports RPT-V01…V04 | Fixture aggregates; unpriced not zeroed; cancelled/returned semantics |
| CSV | Auth 401/403; UTF-8 BOM; injection prefix; size 422; empty headers; audit `CONTROL_REPORT_EXPORTED` |
| Concurrency / audit | Dual transition; export audit; unlink audit |
| Regression | Phase III/IV samples; zero `/rfqs` `/purchase-orders` |

---

## 11. Explicit non-tests (S04)

RFQ/PO/GRN flows, payment posting, supplier portal, forced contract backfill, XLSX export.
