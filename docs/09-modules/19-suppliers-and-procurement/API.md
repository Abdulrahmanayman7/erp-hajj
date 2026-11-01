# Suppliers & Procurement (S04) — API

> **Status:** Phase I suppliers/geo **IMPLEMENTED**; Phase II-A eligibility/documents **IMPLEMENTED — OWNER ACCEPTED** (2026-11-01); Phase II-B/II-C / Phase III–V **NOT IMPLEMENTED**
> **Last updated:** 2026-11-01
> Base: `/api/v1` · Sanctum · Form Requests · API Resources · Policies · correlation ID header echo
> Errors: stable machine codes in `ApiResponse` envelope; cross-tenant → **404**
> Owner **D1–D4** (G1); Phase II **II-D1–II-D4** + II-A **C1–C6** + **M1=A**
> II-A contracts (§12): **BINDING** — acceptance [S04_PHASE_II_A_ACCEPTANCE_STATUS.md](../../00-project/S04_PHASE_II_A_ACCEPTANCE_STATUS.md)

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

**II-A additions (when implemented):** eligibility config create/update/approve; document-record create/supersede/verify/reject; eligibility check failures; SoD violations. No document storage paths, file contents, or secrets in audit values.

---

## 11. Out of S04 API surface

No `/rfqs`, `/supplier-offers`, `/purchase-orders`, `/goods-receipts`.

---

## 12. Phase II-A — Eligibility & Documents (exact contracts)

> **Status:** **IMPLEMENTED — OWNER ACCEPTED** (2026-11-01); remediation re-audit **ACCEPTABLE**
> **Authority:** [PHASE_II_A_ELIGIBILITY_DESIGN.md](PHASE_II_A_ELIGIBILITY_DESIGN.md) · [S04-PHASE-II-A-DESIGN-APPROVAL-2026-10-31.md](../../10-decisions/S04-PHASE-II-A-DESIGN-APPROVAL-2026-10-31.md) · **C1–C6** · **M1=A** · [acceptance](../../00-project/S04_PHASE_II_A_ACCEPTANCE_STATUS.md)
> Envelope: `ApiResponse::success` / error envelope with `code`, `message`, optional `errors` / `reasons`.
> Isolation: `TenantOwned` scope; unknown / cross-tenant ids → **404**.
> Auth: Sanctum + Policy deny-by-default.
> File bytes: existing Documents module (`POST /api/v1/documents`); II-A stores **eligibility metadata** only.

### 12.1 Eligibility requirement sets (versioned config)

| Method | Path | Permission | Notes |
|---|---|---|---|
| GET | `/api/v1/supplier-eligibility-requirement-sets` | `suppliers.view` **or** `suppliers.manage_eligibility_config` | Filters: `config_key`, `status` (`draft`\|`approved`), `page`, `per_page` |
| GET | `/api/v1/supplier-eligibility-requirement-sets/{id}` | same | Includes nested `requirement_defs` |
| POST | `/api/v1/supplier-eligibility-requirement-sets` | `suppliers.manage_eligibility_config` | Create **draft** version (`201`) |
| PATCH | `/api/v1/supplier-eligibility-requirement-sets/{id}` | `suppliers.manage_eligibility_config` | **Draft only**; approved → `422 SUPPLIER_ELIGIBILITY_CONFIG_IMMUTABLE` (**C1**) |
| POST | `/api/v1/supplier-eligibility-requirement-sets/{id}/approve` | `suppliers.approve_eligibility_config` (**C6** sensitive) | Approves draft → immutable approved; SoD author≠approver (**C3**) |
| POST | `/api/v1/supplier-eligibility-requirement-sets/{id}/new-draft` | `suppliers.manage_eligibility_config` | From an **approved** version: creates next draft (`version_no+1`) copying defs; does not mutate approved (**C1**) |

**POST create body**

```json
{
  "config_key": "default",
  "name": "متطلبات التأهيل الأساسية",
  "requirement_defs": [
    {
      "requirement_key": "commercial_registration",
      "label_ar": "السجل التجاري",
      "supplier_type": "goods",
      "country_code": "SA",
      "supplier_category_id": null,
      "is_mandatory": true,
      "default_validity_days": 365
    }
  ]
}
```

- `config_key`: tenant-scoped slug; versions share the same key.
- `country_code` / `supplier_category_id`: nullable = wildcard (**C2**).
- First version for a `config_key` starts at `version_no = 1`.
- `authored_by` / last editor = authenticated actor (server-set).

**PATCH draft body:** same fields as create (partial); may replace `requirement_defs` array atomically for the draft.

**Approve:** empty body `{}` or optional `{ "comment": "…" }`. Response = approved set Resource. Failures:

| HTTP | Code |
|---|---|
| 403 | missing perm / Policy |
| 403/422 | `SUPPLIER_SOD_VIOLATION` — actor is author/last editor of this draft (**C3**) |
| 422 | `SUPPLIER_ELIGIBILITY_CONFIG_NOT_DRAFT` |
| 422 | `SUPPLIER_ELIGIBILITY_DEFS_EMPTY` |
| 404 | cross-tenant / missing |

**Requirement set Resource (sketch)**

```json
{
  "id": 10,
  "config_key": "default",
  "name": "…",
  "status": "approved",
  "version_no": 2,
  "authored_by": { "id": 3, "name": "…" },
  "approved_by": { "id": 7, "name": "…" },
  "approved_at": "2026-10-31T12:00:00+00:00",
  "requirement_defs": [
    {
      "id": 100,
      "requirement_key": "commercial_registration",
      "label_ar": "السجل التجاري",
      "supplier_type": "goods",
      "country_code": "SA",
      "supplier_category_id": null,
      "is_mandatory": true,
      "default_validity_days": 365
    }
  ],
  "created_at": "…",
  "updated_at": "…"
}
```

Timestamps ISO-8601 UTC (platform storage convention); calendar “today” for expiry uses **tenant timezone** (see §12.6).

### 12.2 Supplier document records

File upload remains:

`POST /api/v1/documents` — `documents.upload` — multipart with `linkable_type=supplier`, `linkable_id={supplierId}` once morph alias exists (**ADR-0010**). Response yields `document.id`.

| Method | Path | Permission |
|---|---|---|
| GET | `/api/v1/suppliers/{supplier}/document-records` | `suppliers.view` **or** `suppliers.manage_documents` |
| POST | `/api/v1/suppliers/{supplier}/document-records` | `suppliers.manage_documents` |
| POST | `/api/v1/suppliers/{supplier}/document-records/{record}/verify` | `suppliers.manage_documents` + SoD (**C3**) |
| POST | `/api/v1/suppliers/{supplier}/document-records/{record}/reject` | `suppliers.manage_documents` + SoD (**C3**) |
| POST | `/api/v1/suppliers/{supplier}/document-records/{record}/supersede` | `suppliers.manage_documents` |

**POST create body**

```json
{
  "requirement_def_id": 100,
  "document_id": 555,
  "expires_at": "2027-10-31"
}
```

- `expires_at`: calendar **DATE** (nullable). Interpretation: end of that calendar day in **tenant timezone** (§12.6).
- Creates row with `verification_status=pending_verification`, `uploaded_by=actor`.
- `document_id` must be tenant-scoped Documents row; Prefer linked to same supplier.
- If a non-superseded current record exists for same supplier+requirement_key family, prefer **supersede** endpoint instead → else `422 SUPPLIER_DOCUMENT_RECORD_EXISTS`.

**Verify body:** `{}` or `{ "comment": "…" }` → `verification_status=valid`, `verified_by`, `verified_at`.
**Reject body:** `{ "rejection_reason": "…" }` (required) → `rejected`.
**Supersede body:**

```json
{
  "document_id": 556,
  "expires_at": "2028-01-15",
  "requirement_def_id": 100
}
```

Creates new pending row with `supersedes_id=prior`; sets prior to `superseded`. Prior Document row unchanged (immutable).

| HTTP | Code |
|---|---|
| 403/422 | `SUPPLIER_SOD_VIOLATION` — verifier === uploader (**C3**) |
| 422 | `SUPPLIER_DOCUMENT_INVALID_STATE` |
| 404 | cross-tenant supplier/record/document |

**Document record Resource**

```json
{
  "id": 1,
  "supplier_id": 9,
  "requirement_def_id": 100,
  "requirement_key": "commercial_registration",
  "document_id": 555,
  "document_number": "DOC-000123",
  "verification_status": "valid",
  "expires_at": "2027-10-31",
  "uploaded_by": { "id": 3, "name": "…" },
  "verified_by": { "id": 8, "name": "…" },
  "verified_at": "…",
  "rejection_reason": null,
  "supersedes_id": null,
  "created_at": "…",
  "updated_at": "…"
}
```

No storage disk paths in Resource.

### 12.3 Eligibility status

| Method | Path | Permission |
|---|---|---|
| GET | `/api/v1/suppliers/{supplier}/eligibility` | `suppliers.view` |

**Response**

```json
{
  "eligible": false,
  "supplier_status": "under_review",
  "checked_at": "2026-10-31T21:00:00+00:00",
  "reasons": [
    { "code": "SUPPLIER_DOCUMENT_MISSING", "requirement_key": "commercial_registration" },
    { "code": "SUPPLIER_DOCUMENT_EXPIRED", "requirement_key": "zakat_certificate", "expires_at": "2026-01-01" }
  ],
  "applicable_requirement_keys": ["commercial_registration", "zakat_certificate"],
  "config": { "id": 10, "config_key": "default", "version_no": 2, "status": "approved" }
}
```

Evaluation rules: **C2** applicability; **C4** live expiry vs `expires_at` (stored `expired` status cannot override a still-valid timestamp, and a stale `valid` cannot override an elapsed `expires_at`). Banking ignored (**II-D2**).

### 12.4 Activation integration (existing transition)

| Method | Path | Permission |
|---|---|---|
| POST | `/api/v1/suppliers/{supplier}/transition` | `suppliers.transition` |

**Body (unchanged shape):** `{ "to": "active", "reason": "…" }`

**II-A behavior when `to=active`:**

1. Only allowed inbound edge for II-A: **`under_review` → `active`** (see design § activation).
2. Inside DB transaction + `lockForUpdate` on supplier: run `EligibilityService::assertActivatable` (**C4**).
3. On failure → **422** `SUPPLIER_NOT_ELIGIBLE` with `reasons[]` (same shape as §12.3).
4. On success → set `status=active`, `activated_at=now()` (UTC instant), transition row + audit.
5. **No** other endpoint may set `status` / `activated_at` (PATCH supplier must reject status mass-assignment — Phase I hardened).

Other `to` values: existing Phase I graph; eligibility gate does not apply.

### 12.5 II-A error codes (stable)

| Code | Typical HTTP | When |
|---|---|---|
| `SUPPLIER_NOT_ELIGIBLE` | 422 | Activation or (future) use-time gate failed |
| `SUPPLIER_ELIGIBILITY_CONFIG_MISSING` | 422 | No approved applicable config |
| `SUPPLIER_ELIGIBILITY_CONFIG_DRAFT` | 422 | Only draft exists / no approved version |
| `SUPPLIER_ELIGIBILITY_CONFIG_IMMUTABLE` | 422 | Mutate approved version (**C1**) |
| `SUPPLIER_ELIGIBILITY_CONFIG_NOT_DRAFT` | 422 | Approve non-draft |
| `SUPPLIER_ELIGIBILITY_DEFS_EMPTY` | 422 | Approve empty defs |
| `SUPPLIER_ELIGIBILITY_DEFS_NO_MANDATORY` | 422 | Approve optional-only pack (**Owner M1=A**, 2026-11-01) |
| `SUPPLIER_ELIGIBILITY_NO_MANDATORY_REQUIREMENTS` | 422 | Applicable approved defs exist but none mandatory (fail closed) |
| `SUPPLIER_DOCUMENT_MISSING` | 422 | Mandatory key unsatisfied |
| `SUPPLIER_DOCUMENT_EVIDENCE_INVALID` | 422 | Verified record but backing Document archived/unlinked/reassigned/cross-tenant (**H1**) |
| `SUPPLIER_DOCUMENT_EXPIRED` | 422 | Effective expiry elapsed |
| `SUPPLIER_DOCUMENT_REJECTED` | 422 | Latest record rejected |
| `SUPPLIER_DOCUMENT_PENDING_VERIFICATION` | 422 | Not yet verified |
| `SUPPLIER_DOCUMENT_RECORD_EXISTS` | 422 | Use supersede |
| `SUPPLIER_DOCUMENT_INVALID_STATE` | 422 | Illegal verify/reject/supersede |
| `SUPPLIER_SOD_VIOLATION` | 403 or 422 | Author=approver or uploader=verifier (**C3**) |
| `SUPPLIER_INVALID_TRANSITION` | 422 | Graph violation |

### 12.6 Expiration calendar convention

- `expires_at` stored as **DATE** (no time component).
- A document is expired when tenant-local **calendar date** (from `tenants.timezone`, default/fallback `Asia/Riyadh` per Settings/Dashboard conventions) is **strictly greater than** `expires_at` (expiry date is inclusive through end of that day).
- Instants (`verified_at`, `activated_at`, `checked_at`) remain UTC in DB / ISO-8601 responses.
- Scheduled job may flip stored `verification_status` to `expired` — **supplementary only** (**C4**); gates always recompute from `expires_at`.

### 12.7 Audit events (II-A)

| Event | When |
|---|---|
| `SUPPLIER_ELIGIBILITY_CONFIG_CREATED` | Draft created |
| `SUPPLIER_ELIGIBILITY_CONFIG_UPDATED` | Draft patched |
| `SUPPLIER_ELIGIBILITY_CONFIG_APPROVED` | Approve |
| `SUPPLIER_DOCUMENT_RECORD_CREATED` | New record |
| `SUPPLIER_DOCUMENT_RECORD_SUPERSEDED` | Supersede (prior + new ids) |
| `SUPPLIER_DOCUMENT_RECORD_VERIFIED` | Verify |
| `SUPPLIER_DOCUMENT_RECORD_REJECTED` | Reject |
| `SUPPLIER_TRANSITIONED` | Existing; include eligibility snapshot ids when activating |
| `SUPPLIER_ELIGIBILITY_CHECK_FAILED` | Blocked activation (and future use-time) |
| `SUPPLIER_SOD_VIOLATION` | Rejected SoD attempt (optional security) |
