# Suppliers & Procurement (S04) — API

> **Status:** **ENGINEERING PROPOSAL** — **NOT IMPLEMENTED**  
> **Last updated:** 2026-10-31  
> Base: `/api/v1` · Sanctum · Form Requests · API Resources · Policies · correlation ID header echo  
> Errors: stable machine codes in standard envelope; cross-tenant → **404**  
> Owner **D1–D4** applied: mandatory financial review; `cancel_approved`; geo seed scope; sensitive perms not implied by routes alone.

---

## 1. Conventions

- All mutating routes: CSRF/session as existing SPA pattern.  
- `tenant_id` never accepted from body.  
- Seasonal PR lists may accept `season_id` query **or** honor `X-Season-Id` when present — **ENGINEERING PROPOSAL**: validate consistency if both sent.  
- Pagination: existing platform defaults.  
- Idempotency: `Idempotency-Key` optional on approval/financial-review actions (**ENGINEERING PROPOSAL**).

---

## 2. Geographic places (read)

| Method | Path | Perm |
|---|---|---|
| GET | `/geo-places` | `suppliers.view` or authenticated + suppliers context |
| GET | `/geo-places/{id}` | same |

Filters: `country_code`, `kind`, `parent_id`, `q`.

---

## 3. Supplier categories

| Method | Path | Perm |
|---|---|---|
| GET | `/supplier-categories` | `suppliers.view` |
| POST | `/supplier-categories` | `suppliers.update` |
| PATCH | `/supplier-categories/{id}` | `suppliers.update` |
| POST | `/supplier-categories/{id}/deactivate` | `suppliers.update` |
| DELETE | `/supplier-categories/{id}` | `suppliers.update` (unused only) |

---

## 4. Suppliers

| Method | Path | Perm |
|---|---|---|
| GET | `/suppliers` | `suppliers.view` |
| POST | `/suppliers` | `suppliers.create` |
| GET | `/suppliers/{id}` | `suppliers.view` |
| PATCH | `/suppliers/{id}` | `suppliers.update` |
| POST | `/suppliers/{id}/transition` | `suppliers.transition` body: `{ "to": "...", "reason": "..." }` |
| GET/POST | `/suppliers/{id}/contacts` | view/update |
| GET/POST/PATCH | `/suppliers/{id}/bank-accounts` | view_banking / manage_banking |
| POST | `/suppliers/{id}/bank-accounts/{bankId}/verify` | `suppliers.verify_banking` |
| GET/POST | `/suppliers/{id}/documents` | manage_documents |
| GET/POST | `/suppliers/{id}/coverages` | update |

**Resource rules:** Bank fields masked unless `view_banking`. Similarity warning returned as non-blocking `warnings[]` on create/update (**103**).

### Stable error codes (examples)

`SUPPLIER_INVALID_TRANSITION`, `SUPPLIER_NOT_ELIGIBLE`, `SUPPLIER_CATEGORY_IN_USE`, `SUPPLIER_LEGAL_ID_DUPLICATE`, `SUPPLIER_BANK_PRIMARY_REQUIRED`, `SUPPLIER_DOCUMENT_EXPIRED`

---

## 5. Evaluations

| Method | Path | Perm |
|---|---|---|
| GET/POST | `/supplier-evaluations` | view / prepare |
| GET/PATCH | `/supplier-evaluations/{id}` | view / prepare (draft) |
| POST | `/supplier-evaluations/{id}/submit` | prepare |
| POST | `/supplier-evaluations/{id}/approve` | approve |
| POST | `/supplier-evaluations/{id}/reject` | approve |
| POST | `/supplier-evaluations/{id}/appeals` | handle_appeal |
| CRUD | `/supplier-evaluation-criteria` | manage_criteria |

Errors: `EVALUATION_SOD_VIOLATION`, `EVALUATION_WEIGHTS_INVALID`, `EVALUATION_NOT_DRAFT`

---

## 6. Approval configuration

| Method | Path | Perm |
|---|---|---|
| CRUD-ish | `/purchase-approval-matrices` | `manage_approval_config` |
| POST | `/purchase-approval-matrices/{id}/publish` | manage_approval_config |
| CRUD | `/purchase-approver-authorities` | manage_approval_config |
| CRUD | `/purchase-approval-sla-policies` | manage_approval_config |
| GET/POST/DELETE | `/purchase-approval-delegations` | manage_delegations |

---

## 7. Purchase requests

| Method | Path | Perm |
|---|---|---|
| GET | `/purchase-requests` | view |
| POST | `/purchase-requests` | create |
| GET | `/purchase-requests/{id}` | view |
| PATCH | `/purchase-requests/{id}` | update (draft/returned only) |
| PUT | `/purchase-requests/{id}/lines` | update |
| POST | `/purchase-requests/{id}/submit` | submit |
| POST | `/purchase-requests/{id}/financial-reviews` | financial_review (**D2** — required before final approve) |
| POST | `/purchase-requests/{id}/approve` | approve |
| POST | `/purchase-requests/{id}/reject` | reject |
| POST | `/purchase-requests/{id}/return` | return |
| POST | `/purchase-requests/{id}/cancel` | cancel (non-approved only) |
| POST | `/purchase-requests/{id}/cancel-approved` | cancel_approved (**D3** — reason required) |
| POST | `/purchase-requests/{id}/verify-priority` | verify_priority |
| GET | `/purchase-requests/duplicate-hints` | view (or on submit response) |

### Submit / approve payloads (sketch)

```json
{
  "acknowledge_duplicate_warning": true,
  "comment": "optional"
}
```

```json
{
  "step_id": 123,
  "comment": "موافق",
  "idempotency_key": "optional-uuid"
}
```

### Financial review body (**D2**)

```json
{
  "outcome": "cleared",
  "justification": "…",
  "version_no": 2
}
```

Required on **every** PR before final approval (including unpriced). Persists reviewer from auth context, outcome, timestamp, justification, version.

**D5:** `POST …/approve` that would complete the **final** step fails with `PR_FINANCIAL_REVIEWER_SOD` if `actor_id === financial_review.reviewer_id` for the current version (including delegated actors). Server attempts reassignment to another eligible approver when configured; if none, returns `PR_NO_ELIGIBLE_FINAL_APPROVER` and records escalation — **never** auto-approves.

### Cancel approved body (**D3**)

```json
{
  "reason": "…"
}
```

Errors if `procurement_engaged_at` is set: `PR_PROCUREMENT_ENGAGED` (future S05 lock — no S05 body in S04).

### Errors

`PR_INVALID_TRANSITION`, `PR_SEASON_REQUIRED`, `PR_EDIT_LOCKED`, `PR_SOD_VIOLATION`, `PR_AUTHORITY_EXCEEDED`, `PR_FINANCIAL_REVIEW_REQUIRED`, `PR_FINANCIAL_REVIEW_NOT_CLEARED`, `PR_FINANCIAL_REVIEWER_SOD`, `PR_NO_ELIGIBLE_FINAL_APPROVER`, `PR_STEP_NOT_PENDING`, `PR_MATRIX_MISSING`, `PR_UNPRICED_REASON_REQUIRED`, `PR_CANCEL_REASON_REQUIRED`, `PR_PROCUREMENT_ENGAGED`

---

## 8. Planning consolidation

| Method | Path | Perm |
|---|---|---|
| GET/POST | `/purchase-demand-groups` | consolidate_planning |
| POST | `/purchase-demand-groups/{id}/items` | consolidate_planning |

Only **approved** PRs linkable. No RFQ side effects.

---

## 9. Control reports

| Method | Path | Perm |
|---|---|---|
| GET | `/purchase-control-reports/approval-latency` | view_control_reports |
| GET | `/purchase-control-reports/outcomes` | view_control_reports |
| GET | `/purchase-control-reports/overdue` | view_control_reports |

Tenant-scoped aggregates only.

---

## 10. Audit

Domain Actions call `AuthorizationSecurity` / module audit helpers for: supplier transitions, bank verify, evaluation approve, PR submit/approve/reject/return/cancel, matrix publish, delegation create/revoke, financial review.  
Payloads exclude secrets.

---

## 11. Out of S04 API surface

No `/rfqs`, `/supplier-offers`, `/purchase-orders`, `/goods-receipts`.
