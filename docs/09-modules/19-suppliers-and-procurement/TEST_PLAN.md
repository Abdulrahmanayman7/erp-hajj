# Suppliers & Procurement (S04) — Test Plan

> **Status:** Phase I + Phase II-A tests **EXECUTED / OWNER ACCEPTED** (II-A 2026-11-01; includes MySQL concurrency); Phase II-B+ plans remain design-only until authorized
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

### PR lifecycle

draft→submit→under_review→approve/reject/return; edit while under_review → 422; return→edit→resubmit increments version and recreates pending steps; cancelled rules.

### Approvals

Multi-level happy path; skip level impossible; conflict reassignment; delegation happy/expired/revoked; matrix snapshot isolation when matrix edited mid-flight.

### Financial review (**D2**) + SoD (**D5**)

- Submit/approve path: final approve without review → `PR_FINANCIAL_REVIEW_REQUIRED`.
- Unpriced PR still requires cleared/cleared_with_conditions review.
- `not_cleared` → final approve blocked.
- Review row stores reviewer, outcome, timestamp, justification, version_no.
- Same user as financial reviewer attempting final approve → `PR_FINANCIAL_REVIEWER_SOD` (hard fail, not warning).
- User with both `financial_review` and `approve` permissions still blocked on final step if they reviewed.
- Delegate whose principal would pass but acting user is reviewer → blocked (**D5**).
- Sole eligible final approver is the reviewer → reassignment attempt; if no alternate → `PR_NO_ELIGIBLE_FINAL_APPROVER` + escalation audit; PR remains unapproved.
- No inventory/finance side effects; no auto-approve.

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
- Bank update audit contains last4/status only — never full IBAN plaintext.
- Feature test spies/`Event::fake` per module convention.

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

## 10. Explicit non-tests (S04)

RFQ/PO/GRN flows, payment posting, supplier portal, forced contract backfill.
