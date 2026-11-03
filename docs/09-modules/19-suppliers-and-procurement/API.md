# Suppliers & Procurement (S04) — API

> **Status:** S04 APIs Owner-accepted (FINAL FUNCTIONAL CLOSURE 2026-11-02). **S05 §16:** Parts 1–3 Owner-accepted; Part 4 goods-receipts **IMPLEMENTED locally (2026-11-03)** awaiting Owner review; Parts 5–6 not started. No commit/push/merge/deploy.
> **Last updated:** 2026-11-03
> S05 design: [PHASE_S05_PROCUREMENT_CYCLE_DESIGN.md](PHASE_S05_PROCUREMENT_CYCLE_DESIGN.md)
> Base: `/api/v1` · Sanctum · Form Requests · API Resources · Policies · correlation ID header echo
> Errors: stable machine codes in `ApiResponse` envelope; cross-tenant → **404**
> Owner **D1–D5** (G1); **II-D1–II-D4**; II-A **C1–C6** + **M1=A**; II-B **IIB-D1–IIB-D12**; II-C **IIC-D1–IIC-D8** + **IIC-F1–F3**; Phase III **III-D1–D8**
> II-A contracts (§12): **BINDING** — [acceptance](../../00-project/S04_PHASE_II_A_ACCEPTANCE_STATUS.md)
> II-B contracts (§13): **BINDING — FUNCTIONALLY CLOSED** — [PHASE_II_B_SUPPLIER_BANKING_DESIGN.md](PHASE_II_B_SUPPLIER_BANKING_DESIGN.md) · [S04_PHASE_II_B_ACCEPTANCE_STATUS.md](../../00-project/S04_PHASE_II_B_ACCEPTANCE_STATUS.md)
> II-C contracts (§14): see [PHASE_II_C_SUPPLIER_EVALUATIONS_DESIGN.md](PHASE_II_C_SUPPLIER_EVALUATIONS_DESIGN.md)
> Phase III contracts (**§15**): **DESIGN DRAFT** — [PHASE_III_PURCHASE_REQUESTS_DESIGN.md](PHASE_III_PURCHASE_REQUESTS_DESIGN.md) · [S04-PHASE-III-OWNER-DECISIONS-2026-11-01.md](../../10-decisions/S04-PHASE-III-OWNER-DECISIONS-2026-11-01.md); coding **NOT AUTHORIZED**

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
| GET/POST | `/suppliers/{id}/documents` | manage_documents |
| GET/POST | `/suppliers/{id}/coverages` | update |
| *(bank accounts)* | see **§13** (II-B **FUNCTIONALLY CLOSED**) | banking permissions |

**Resource rules:** Similarity warning returned as non-blocking `warnings[]` on create/update (**103**). Banking: masked by default; see §13.

### Stable error codes (examples)

`SUPPLIER_INVALID_TRANSITION`, `SUPPLIER_NOT_ELIGIBLE`, `SUPPLIER_CATEGORY_IN_USE`, `SUPPLIER_LEGAL_ID_DUPLICATE`, `SUPPLIER_DOCUMENT_EXPIRED` (+ banking codes in §13)

---

## 5. Evaluations (outline — see **§14** for Phase II-C draft contracts)

Outline retained for navigation. **Authoritative II-C draft:** §14 + [PHASE_II_C_SUPPLIER_EVALUATIONS_DESIGN.md](PHASE_II_C_SUPPLIER_EVALUATIONS_DESIGN.md).

---

## 6. Approval configuration — **Phase IV (design draft; coding not authorized)**

> **Authoritative Phase IV design:** [PHASE_IV_APPROVAL_ENGINE_DESIGN.md](PHASE_IV_APPROVAL_ENGINE_DESIGN.md) §12 · Owner decisions [S04-PHASE-IV-OWNER-DECISIONS-2026-11-01.md](../../10-decisions/S04-PHASE-IV-OWNER-DECISIONS-2026-11-01.md) (**IV-D1–D15**).  
> Outline below retained for navigation. **Do not implement** until Owner design acceptance **and** separate coding authorization (**IV-D15**).

| Method | Path | Perm |
|---|---|---|
| CRUD-ish | `/purchase-approval-matrices` | `manage_approval_config` |
| POST | `/purchase-approval-matrices/{id}/publish` | manage_approval_config |
| CRUD | `/purchase-approver-authorities` | manage_approval_config |
| CRUD | `/purchase-approval-sla-policies` | manage_approval_config |
| GET/POST/DELETE | `/purchase-approval-delegations` | manage_delegations |

**Not in Phase III.** Do not implement or expose in III FE (**III-D8**).

---

## 7. Purchase requests — outline

> **Authoritative Phase III contracts:** **[§15](#15-phase-iii-purchase-requests-authoritative)** · [PHASE_III_PURCHASE_REQUESTS_DESIGN.md](PHASE_III_PURCHASE_REQUESTS_DESIGN.md) · **III-D1–D8**.  
> Phase IV actions (`approve` / `reject` / `return` / `cancel-approved`) remain listed for full-S04 orientation but are **deferred**.

| Method | Path | Perm | Phase |
|---|---|---|---|
| GET | `/purchase-requests` | view | III |
| POST | `/purchase-requests` | create | III |
| GET | `/purchase-requests/{id}` | view | III |
| PATCH | `/purchase-requests/{id}` | update (draft/returned only) | III |
| PUT | `/purchase-requests/{id}/lines` | update | III |
| POST | `/purchase-requests/{id}/submit` | submit → **`submitted`** only (**III-D1**) | III |
| POST | `/purchase-requests/{id}/financial-reviews` | financial_review (**D2**/**III-D2**) | III |
| GET | `/purchase-requests/{id}/financial-reviews` | view | III |
| GET | `/purchase-requests/{id}/versions` | view | III |
| POST | `/purchase-requests/{id}/cancel` | cancel (non-approved only) | III |
| POST | `/purchase-requests/{id}/verify-priority` | verify_priority | III |
| GET | `/purchase-requests/duplicate-hints` | view | III |
| POST | `/purchase-requests/{id}/approve` | approve | **IV** |
| POST | `/purchase-requests/{id}/reject` | reject | **IV** |
| POST | `/purchase-requests/{id}/return` | return | **IV** |
| POST | `/purchase-requests/{id}/cancel-approved` | cancel_approved (**D3**) | **IV** |

### Errors (shared vocabulary)

Phase III subset + Phase IV codes (IV-only marked):  
`PR_INVALID_TRANSITION`, `PR_SEASON_REQUIRED`, `PR_EDIT_LOCKED`, `PR_UNPRICED_REASON_REQUIRED`, `PR_CANCEL_REASON_REQUIRED`, `PR_DUPLICATE_ACK_REQUIRED`, `PR_SUPPLIER_NOT_FOUND`, `PR_SUPPLIER_ARCHIVED`, `PR_SUPPLIER_NOT_ELIGIBLE`, `PR_ORG_UNIT_INVALID`, `PR_LINES_REQUIRED`, `PR_PROCUREMENT_ENGAGED`,  
`PR_SOD_VIOLATION` *(IV)*, `PR_AUTHORITY_EXCEEDED` *(IV)*, `PR_FINANCIAL_REVIEW_REQUIRED` *(IV)*, `PR_FINANCIAL_REVIEW_NOT_CLEARED` *(IV)*, `PR_FINANCIAL_REVIEWER_SOD` *(IV)*, `PR_NO_ELIGIBLE_FINAL_APPROVER` *(IV)*, `PR_STEP_NOT_PENDING` *(IV)*, `PR_MATRIX_MISSING` *(IV)*

---

## 8. Planning consolidation — **Phase V** (**V-D1…V-D5**, **V-D7**)

| Method | Path | Perm |
|---|---|---|
| GET | `/purchase-demand-groups` | `consolidate_planning` **or** `purchase_requests.view` |
| POST | `/purchase-demand-groups` | `consolidate_planning` |
| GET/PATCH | `/purchase-demand-groups/{id}` | view or consolidate / consolidate |
| POST | `/purchase-demand-groups/{id}/transition` | `consolidate_planning` |
| POST | `/purchase-demand-groups/{id}/items` | `consolidate_planning` |
| DELETE | `/purchase-demand-groups/{id}/items/{purchaseRequestId}` | `consolidate_planning` |

Only **approved** PR **headers** linkable; exclusive active membership; no RFQ side effects. Full contract: [PHASE_V_DETAILED_DESIGN.md](PHASE_V_DETAILED_DESIGN.md) §7.1.

---

## 9. Control reports — **Phase V** (**V-D6=A**, **V-D8=A**)

| Method | Path | Perm |
|---|---|---|
| GET | `/purchase-control-reports/approval-latency` | `view_control_reports` |
| GET | `/purchase-control-reports/overdue` | `view_control_reports` |
| GET | `/purchase-control-reports/outcomes` | `view_control_reports` |
| GET | `/purchase-control-reports/compliance` | `view_control_reports` |
| GET | `/purchase-control-reports/approval-latency/export.csv` | `view_control_reports` |
| GET | `/purchase-control-reports/overdue/export.csv` | `view_control_reports` |
| GET | `/purchase-control-reports/outcomes/export.csv` | `view_control_reports` |
| GET | `/purchase-control-reports/compliance/export.csv` | `view_control_reports` |

Tenant-scoped aggregates + CSV (UTF-8 BOM, injection-safe). **No XLSX.** Export audited. See [PHASE_V_DETAILED_DESIGN.md](PHASE_V_DETAILED_DESIGN.md) §§8–9.

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

---

## 13. Phase II-B — Supplier banking (**IMPLEMENTED — FUNCTIONALLY CLOSED — OWNER ACCEPTED**)

> **Status:** **FUNCTIONALLY CLOSED — OWNER ACCEPTED** 2026-11-01 — [S04_PHASE_II_B_ACCEPTANCE_STATUS.md](../../00-project/S04_PHASE_II_B_ACCEPTANCE_STATUS.md) · [S04-PHASE-II-B-FUNCTIONAL-CLOSURE-2026-11-01.md](../../10-decisions/S04-PHASE-II-B-FUNCTIONAL-CLOSURE-2026-11-01.md) · evidence [PHASE_II_B_WP05_ACCEPTANCE_EVIDENCE.md](PHASE_II_B_WP05_ACCEPTANCE_EVIDENCE.md)  
> Design authority: [PHASE_II_B_SUPPLIER_BANKING_DESIGN.md](PHASE_II_B_SUPPLIER_BANKING_DESIGN.md) · **IIB-D1–IIB-D12** · IBAN **claims** · **E1–E5** · **B1–B8** · doc closure **M1–M3 VERIFIED**  
> **Commit / push / merge / deploy / default `erp_hajj` migrate:** **NOT AUTHORIZED**  
> **II-D2:** Banking is **not** an activation prerequisite.  
> **IIB-D11=A / IIB-D12=A:** Primary retention; audited reveal (reason ≥10).

Prefix: `/api/v1/suppliers/{supplier}` · tenant-scoped · cross-tenant → **404**.

### 13.1 Endpoints

| Method | Path | Permission | Notes |
|---|---|---|---|
| GET | `/bank-accounts` | `suppliers.view` | List; `?include=history`; **always masked** |
| POST | `/bank-accounts` | `suppliers.manage_banking` | Create pending (new `account_key`); acquire IBAN claim |
| GET | `/bank-accounts/{id}` | `suppliers.view` | Show; **always masked** |
| PATCH | `/bank-accounts/{id}` | `suppliers.manage_banking` | Edit **pending** only; record editor; see **§13.2a** (**M1**) |
| POST | `/bank-accounts/{id}/supersede` | `suppliers.manage_banking` | Full identifiers required (**E5**); retain prior primary (**IIB-D11**); IBAN claims §2.4 |
| POST | `/bank-accounts/{id}/verify` | `suppliers.verify_banking` | pending → verified; SoD; **no** primary transfer |
| POST | `/bank-accounts/{id}/reject` | `suppliers.verify_banking` | pending → rejected; **DELETE** claim if safe (**M2**); clear primary if set |
| POST | `/bank-accounts/{id}/revoke` | `suppliers.manage_banking` (**E2**) | verified → revoked; clear primary; **DELETE** claim if safe; no auto-promote |
| POST | `/bank-accounts/{id}/set-primary` | `suppliers.manage_banking` | Target `status=verified` (any `current_slot`); explicit demote others |
| POST | `/bank-accounts/{id}/clear-primary` | `suppliers.manage_banking` | Clear; no auto-promote |
| POST | `/bank-accounts/{id}/evidence` | `suppliers.manage_banking` | Optional `{ "document_id": n }` |
| POST | `/bank-accounts/{id}/reveal` | `suppliers.view_banking` | `{ "reason" }` trimmed length ≥10 (**E1**); audited; `Cache-Control: no-store` |

### 13.2 Create body (binding)

```json
{
  "label": "الحساب الرئيسي",
  "account_holder_name": "شركة …",
  "bank_name": "مصرف …",
  "iban": "SA…",
  "account_number": null,
  "evidence_document_id": null
}
```

Rules: holder required (**IIB-D1**); free-text bank_name (**IIB-D2**); ≥1 of iban/account_number (**IIB-D3**); currency SAR only (**IIB-D10**).

### 13.2a Pending PATCH — IBAN claim maintenance (**M1**)

- **Path:** `PATCH …/bank-accounts/{id}` — **pending only**; otherwise `422 SUPPLIER_BANK_INVALID_STATUS` / `SUPPLIER_BANK_IMMUTABLE`.  
- **Partial body** allowed.  
- **When `iban` is present and normalized value differs from the version’s current IBAN:**  
  1. Normalize → compute tenant-bound keyed fingerprint.  
  2. Acquire or validate claim for the new fingerprint (same family OK; other family → `422 SUPPLIER_BANK_IBAN_DUPLICATE`).  
  3. Update `iban_ciphertext`, `iban_last4`, and version `iban_fingerprint` consistently.  
  4. Release (**DELETE**) the previous fingerprint’s claim **only if** no pending/verified version in that family still requires it.  
  5. Preserve historical fingerprints on other versions.  
  6. Execute version + claim mutations **atomically**; lock order supplier → claim rows (fingerprint asc) → family rows → editors; deadlock retry once.  
- **When only non-identifier metadata changes** (e.g. `label`, `account_holder_name`, `bank_name`, evidence, or local `account_number` without IBAN change): **must not** acquire or release IBAN claims; **must not** alter IBAN ciphertext/fingerprint.  
- **When IBAN is cleared** while local account remains (still ≥1 identifier): treat as change-away; safe-release prior IBAN claim; clear this version’s IBAN fields only.  
- **Audit:** `SUPPLIER_BANK_ACCOUNT_UPDATED` (last4/status/ids only — never plaintext).

### 13.3 Supersede (binding) — **E5**

- **Path:** `POST …/bank-accounts/{id}/supersede`  
- **Body (required identifiers):** same shape as create — `account_holder_name`, `bank_name`, and ≥1 of `iban` / `account_number` (**IIB-D3**). Optional `label` (if omitted, may copy prior label).  
- **Forbidden:** empty body that silently reuses prior ciphertext.  
- **Effects:** new pending `current_slot=0`, `is_primary=false`; prior `current_slot=id`; prior status unchanged; prior primary retained if set (**IIB-D11**); IBAN claims acquired/refreshed per design §2.4 (same-IBAN OK; different-IBAN keeps old claim while prior remains pending/verified).  
- **Concurrency:** lock order supplier → claims → family rows.  
- **Audit:** `SUPPLIER_BANK_ACCOUNT_SUPERSEDED` (prior_id, new_id, last4, primary_retained).

### 13.4 Verify / reject / revoke / primary (binding)

| Action | Perm | Body | Primary / claim | Audit |
|---|---|---|---|---|
| verify | `verify_banking` | `{}` / `{ "comment" }` | No primary change; claim unchanged | `_VERIFIED` |
| reject | `verify_banking` | `{ "reason" }` | Clear primary if any; **DELETE** claim if safe (**M2**) | `_REJECTED` |
| revoke | `manage_banking` (**E2**) | `{ "reason" }` | Clear primary; no auto-promote; **DELETE** claim if safe | `_REVOKED` |
| set-primary | `manage_banking` | `{}` | Demote others; set verified target | `_PRIMARY_SET` |
| clear-primary | `manage_banking` | `{}` | Clear; no auto-promote | `_PRIMARY_CLEARED` |

### 13.5 Reveal (binding) — **IIB-D12=A** / **E1** / **E4**

```http
POST /api/v1/suppliers/{supplier}/bank-accounts/{id}/reveal
Permission: suppliers.view_banking
Body: { "reason": "مراجعة امتثال قبل التحويل" }
```

**Validation:** trim reason; length ≥ **10** or `422 SUPPLIER_BANK_REVEAL_REASON_REQUIRED`.  
**Success 200:** plaintext `iban` / `account_number` once; headers `Cache-Control: no-store, private`.  
Allowed for pending/verified/**revoked**/historical ids in tenant (**E4**).  
**403** missing perm; **404** missing/cross-tenant.  
Audit `_REVEALED` / `_REVEAL_DENIED`: reason + ids + outcome — **never** plaintext.

### 13.6 Masked resource (binding)

```json
{
  "id": 1,
  "account_key": "…",
  "version_no": 1,
  "label": "…",
  "account_holder_name": "…",
  "bank_name": "…",
  "iban_masked": "****1234",
  "account_masked": null,
  "currency": "SAR",
  "status": "verified",
  "is_primary": true,
  "is_current": false,
  "supersedes_id": null,
  "evidence_document_id": null,
  "created_by": 10,
  "verified_by": 11,
  "verified_at": "…"
}
```

Note: `is_primary=true` with `is_current=false` is valid during replacement (**IIB-D11**). List/show **never** include plaintext `iban` / `account_number`.

### 13.7 Status codes

| HTTP | When |
|---|---|
| 200/201 | Success |
| 401 | Unauthenticated |
| 403 | Missing permission / SoD / reveal denied |
| 404 | Missing / cross-tenant |
| 409/422 | Duplicate IBAN, invalid status, immutable, primary not verified, currency, reveal reason |
| 429 | Reveal rate limit |

### 13.8 Domain error codes (binding)

`SUPPLIER_BANK_NOT_FOUND`, `SUPPLIER_BANK_INVALID_STATUS`, `SUPPLIER_BANK_SOD_VIOLATION`, `SUPPLIER_BANK_IBAN_DUPLICATE`, `SUPPLIER_BANK_IDENTIFIER_REQUIRED`, `SUPPLIER_BANK_CURRENCY_UNSUPPORTED`, `SUPPLIER_BANK_PRIMARY_NOT_VERIFIED`, `SUPPLIER_BANK_IMMUTABLE`, `SUPPLIER_BANK_EVIDENCE_INVALID`, `SUPPLIER_BANK_REVEAL_REASON_REQUIRED`, `DOCUMENT_USED_AS_BANK_EVIDENCE`

### 13.9 Audit (binding)

`…_CREATED` · `_UPDATED` · `_SUPERSEDED` · `_VERIFIED` · `_REJECTED` · `_REVOKED` · `_PRIMARY_SET` · `_PRIMARY_CLEARED` · `_REVEALED` · `_REVEAL_DENIED` — last4 + ids + reason (reveal) only; **never** full IBAN/account or ciphertext.

### 13.10 Concurrency (binding)

Lock order: supplier → IBAN claim rows (fingerprint asc) → family bank rows → editors.  
Uniques: claims `(tenant_id, iban_fingerprint)`; `primary_flag`; family `current_slot`.  
Claim release = **DELETE** claim row when safe; ownership checks on acquire (**M2**).  
Failed transactions roll back version **and** claim writes together.  
MySQL tests: primary transfer; supersede-while-primary; same-IBAN supersession; cross-family claim race; foreign claim of primary’s IBAN after different-IBAN supersession (must fail); pending PATCH IBAN vs foreign create (**M1**); concurrent release vs acquire (**M2**).

---

## 14. Phase II-C — Supplier evaluations (**DESIGN DRAFT — NOT OWNER ACCEPTED — NOT IMPLEMENTATION-AUTHORIZED**)

> **Status:** Owner decisions **IIC-D1–D8 + IIC-F1–F3 APPROVED** (2026-11-01; **11/11**); detailed design **DRAFT** pending **final Owner design acceptance** (not coding auth).  
> Authority: [PHASE_II_C_SUPPLIER_EVALUATIONS_DESIGN.md](PHASE_II_C_SUPPLIER_EVALUATIONS_DESIGN.md) · [S04-PHASE-II-C-OWNER-DECISIONS-2026-11-01.md](../../10-decisions/S04-PHASE-II-C-OWNER-DECISIONS-2026-11-01.md)  
> **Coding / migrations / commit / push / merge / deploy:** **NOT AUTHORIZED**  
> **II-D3 / II-D4:** No auto lifecycle; tenant criteria; no platform threshold/pack.  
> **IIC-F1:** `post_season` requires same-tenant `season_id`; other kinds optional.  
> **IIC-F2 / F3:** Notify only holders of `supplier_evaluations.approve`; only after approve with final monitoring `true`.

Prefix base: `/api/v1` · tenant-scoped · cross-tenant → **404**. Envelope: existing `ApiResponse`.

### 14.1 Criteria packs

| Method | Path | Perm |
|---|---|---|
| GET | `/supplier-evaluation-criteria-packs` | `manage_criteria` **or** `view` (list published for scorers — **Eng**: `view` sees published only; `manage_criteria` sees all) |
| POST | `/supplier-evaluation-criteria-packs` | `manage_criteria` |
| GET | `/supplier-evaluation-criteria-packs/{pack}` | view / manage_criteria |
| PATCH | `/supplier-evaluation-criteria-packs/{pack}` | `manage_criteria` (draft only) |
| POST | `/supplier-evaluation-criteria-packs/{pack}/publish` | `approve_criteria` |
| POST | `/supplier-evaluation-criteria-packs/{pack}/new-draft` | `manage_criteria` |
| GET/POST | `/supplier-evaluation-criteria-packs/{pack}/criteria` | manage_criteria (POST draft) |
| PATCH/DELETE | `/supplier-evaluation-criteria-packs/{pack}/criteria/{criterion}` | manage_criteria (draft) |

**POST pack body (allowlist):** `supplier_category_id` (required), `notes?`, `supersedes_pack_id?`.

**PATCH pack body:** `notes?` only while draft.

**POST criterion body:** `name`, `weight`, `sort_order?`, `is_active?` (default true).

**PATCH criterion:** `name?`, `weight?`, `sort_order?`, `is_active?`.

**Publish:** empty body; server validates weight sum ∈ [99.99, 100.01], ≥1 active line, SoD, draft status → `published`; prior published for category → `archived`.

**POST `…/{pack}/new-draft` (version spawn — II-A eligibility pattern):**

- **Purpose:** From an immutable source pack, create the next **draft** for the same `supplier_category_id` without mutating the source (`published` / `archived` remain immutable). Aligns with design §3.1 (`supersedes_pack_id` → prior pack) and WP-01 “new draft version” requirement.
- **Permission:** `supplier_evaluations.manage_criteria` (+ same-tenant policy). Cross-tenant / unknown pack → **404**. Missing auth → **401**; missing permission → **403**.
- **Request body:** empty (no Form Request fields). Optional JSON `{}` ignored.
- **Source constraints:** path `{pack}` must be `published` **or** `archived`; otherwise **409** `CRITERIA_PACK_INVALID_STATUS`. At most one draft per `(tenant, supplier_category)` — if a draft already exists → **409** `CRITERIA_PACK_DRAFT_EXISTS`.
- **Server behavior:** allocates `version_no = max(category)+1`; sets `supersedes_pack_id = {pack}`; copies **active** criteria lines (`name`/`weight`/`sort_order`/`is_active=true`); sets `prepared_by` / `last_edited_by` / editor SoD set to the actor; emits `CRITERIA_PACK_CREATED`. Does **not** publish or archive any pack. SoD (preparer/editors ≠ publisher) is enforced later on **publish**, not on `new-draft`.
- **Response:** **201** + `EvaluationCriteriaPackResource` (includes nested `criteria`, `supersedes_pack_id`, `version_no`, `status=draft`).
- **Concurrency:** row/category locks + draft uniqueness; concurrent `new-draft` / create for the same category yields one draft and **409** for the loser (see concurrency suite).
- **Equivalent path:** `POST /supplier-evaluation-criteria-packs` with `supersedes_pack_id` also creates a draft successor but does **not** copy criteria lines — prefer `new-draft` when copying active lines is required.

### 14.2 Evaluations

| Method | Path | Perm |
|---|---|---|
| GET | `/supplier-evaluations` | `view` |
| POST | `/supplier-evaluations` | `prepare` |
| GET | `/supplier-evaluations/{evaluation}` | `view` |
| PATCH | `/supplier-evaluations/{evaluation}` | `prepare` (draft only) |
| POST | `/supplier-evaluations/{evaluation}/submit` | `prepare` |
| POST | `/supplier-evaluations/{evaluation}/approve` | `approve` |
| POST | `/supplier-evaluations/{evaluation}/reject` | `approve` |

**Filters (GET list):** `supplier_id`, `supplier_category_id`, `evaluation_kind`, `status`, `season_id`, `monitoring_recommended`, `q` (number), pagination `page`/`per_page`.

**POST create allowlist:** `supplier_id`, `supplier_category_id`, `evaluation_kind`, `season_id?`, `monitoring_recommended?` (proposal only — **IIC-F3**), `scores?` (array), `notes?`.

**Season validation (**IIC-F1**):** if `evaluation_kind=post_season` → `season_id` required (same tenant); other kinds may omit; supplied `season_id` always same-tenant or **404**.

**Score line object:** `{ "criterion_id": number, "score": number, "note?": string }` — `criterion_id` must belong to current **published** pack for category when drafting; at submit, server snapshots name/weight from pack.

**PATCH draft allowlist:** `evaluation_kind?`, `season_id?`, `monitoring_recommended?` (proposal — **does not notify**), `scores?` (replace-all **Eng**).

**Submit:** empty body; builds immutable snapshots; computes `total_score`; → `pending_approval`; **must not** emit monitoring notification (**IIC-F3**).

**Approve body:** `{ "monitoring_recommended?": boolean }` — **final** monitoring decision (**IIC-F3**). If omitted, carry forward draft proposal **(Eng)**. On success with final `true` → enqueue in-app notification to same-tenant `supplier_evaluations.approve` holders (**IIC-F2**). Never mutates supplier lifecycle.

**Reject body:** `{ "reason": string }` trimmed ≥10 **(Eng)**.

### 14.3 Appeals

| Method | Path | Perm |
|---|---|---|
| GET | `/supplier-evaluations/{evaluation}/appeals` | `view` |
| POST | `/supplier-evaluations/{evaluation}/appeals` | `prepare` **or** `handle_appeal` |
| POST | `/supplier-evaluation-appeals/{appeal}/accept` | `handle_appeal` |
| POST | `/supplier-evaluation-appeals/{appeal}/reject` | `handle_appeal` |

**POST appeal:** `{ "reason": string }` (≥10). Evaluation must be `approved`; no other `open` appeal.

**Accept:** creates new draft evaluation version; returns appeal + `resulting_evaluation`.

**Reject body:** `{ "review_outcome_note?": string }`.

### 14.4 Resources (Eng field sets)

**CriteriaPackResource:** `id`, `supplier_category_id`, `version_no`, `status`, `prepared_by`, `last_edited_by`, `approved_by`, `approved_at`, `supersedes_pack_id`, `notes`, `criteria[]` (when loaded), `created_at`, `updated_at`.

**EvaluationResource:** `id`, `evaluation_number`, `supplier_id`, `supplier_category_id`, `evaluation_kind`, `season_id`, `status`, `criteria_pack_id`, `criteria_pack_version_no`, `total_score`, `monitoring_recommended`, `prepared_by`, `submitted_at`, `approved_by`, `approved_at`, `version_no`, `evaluation_family_key`, `supersedes_evaluation_id`, `origin_appeal_id`, `score_snapshots[]` (after submit), `created_at`, `updated_at`.

**AppealResource:** `id`, `evaluation_id`, `reason`, `status`, `filed_by`, `filed_at`, `reviewed_by`, `reviewed_at`, `resulting_evaluation_id`.

Never expose internal sequence counters or unrelated supplier banking fields.

### 14.5 Error codes

`EVALUATION_SOD_VIOLATION`, `EVALUATION_WEIGHTS_INVALID`, `EVALUATION_NOT_DRAFT`, `EVALUATION_CRITERIA_MISSING`, `EVALUATION_SUPPLIER_ARCHIVED`, `EVALUATION_CATEGORY_NOT_LINKED`, `EVALUATION_SEASON_REQUIRED`, `EVALUATION_INVALID_STATUS`, `EVALUATION_SCORE_OUT_OF_RANGE`, `EVALUATION_APPEAL_NOT_OPEN`, `EVALUATION_APPEAL_EXISTS`, `CRITERIA_PACK_SOD_VIOLATION`, `CRITERIA_PACK_NOT_DRAFT`, `CRITERIA_PACK_INVALID_WEIGHTS`, `CRITERIA_PACK_EMPTY`.

HTTP: `401` / `403` / `404` / `409` (status races) / `422` (validation + domain envelope codes).

### 14.6 Idempotency & audit

- Submit/approve/reject/publish under row locks; replay on terminal state → `409` + stable code.  
- Audit events per design pack §10; correlation ID mandatory.  
- Monitoring notification **only** after approve commit when final flag true; recipients = `supplier_evaluations.approve` only; dedupe per design pack §6.1 (**IIC-F2**/ **IIC-F3**).

### 14.7 Evidence

Use Documents module: upload + link `documentable_type=supplier_evaluation`, `documentable_id={evaluation}`. Optional. Requires Documents permissions + `supplier_evaluations.view` to list in eval UI.

---

## 15. Phase III Purchase Requests (authoritative)

> **Status:** `OWNER ACCEPTED — FUNCTIONAL` (III-WP01–WP05). Design **OWNER ACCEPTED**; Phase IV approval engine **designed (draft)** — coding **not** authorized.  
> Design pack: [PHASE_III_PURCHASE_REQUESTS_DESIGN.md](PHASE_III_PURCHASE_REQUESTS_DESIGN.md) · Decisions: [S04-PHASE-III-OWNER-DECISIONS-2026-11-01.md](../../10-decisions/S04-PHASE-III-OWNER-DECISIONS-2026-11-01.md) · Evidence: [PHASE_III_WP01_TO_05_ACCEPTANCE_EVIDENCE.md](PHASE_III_WP01_TO_05_ACCEPTANCE_EVIDENCE.md)

Prefix: `/api/v1` · tenant-scoped · cross-tenant → **404**. Envelope: existing `ApiResponse`.

### 15.1 Endpoints (Phase III only)

| Method | Path | Perm | Notes |
|---|---|---|---|
| GET | `/purchase-requests` | `purchase_requests.view` | Filters: `status`, `request_kind`, `season_id`, `priority`, `optional_supplier_id`, `beneficiary_organization_unit_id`, `requested_by`, `q` (number/title), `page`/`per_page` |
| POST | `/purchase-requests` | `create` | **201** draft |
| GET | `/purchase-requests/{id}` | `view` | Includes lines, latest financial review summary, version_no, `pending_approval_engine` flag **(Eng)** when `status=submitted` |
| PATCH | `/purchase-requests/{id}` | `update` | `draft`/`returned` only; else `422 PR_EDIT_LOCKED` |
| PUT | `/purchase-requests/{id}/lines` | `update` | Replace-all lines; draft/returned only |
| POST | `/purchase-requests/{id}/submit` | `submit` | → `submitted` (**III-D1**); writes structured version snapshot (**III-D7**); **no** matrix/steps |
| POST | `/purchase-requests/{id}/cancel` | `cancel` | Non-approved; body `{ "reason": "…" }` |
| POST | `/purchase-requests/{id}/verify-priority` | `verify_priority` | Records verifier; does not bypass future approvals |
| GET | `/purchase-requests/duplicate-hints` | `view` | Query by draft payload or `purchase_request_id` |
| POST | `/purchase-requests/{id}/financial-reviews` | `financial_review` | **III-D2**; draft or submitted |
| GET | `/purchase-requests/{id}/financial-reviews` | `view` | History for PR |
| GET | `/purchase-requests/{id}/versions` | `view` | Structured version headers (+ optional `?include=lines`) |

**Deferred Phase IV (do not implement in III):** `POST …/approve|reject|return|cancel-approved` and §6 matrix APIs.

### 15.2 Create body (allowlist)

```json
{
  "request_kind": "seasonal",
  "season_id": 12,
  "title": "…",
  "purpose": "…",
  "priority": "normal",
  "urgency_justification": null,
  "beneficiary_organization_unit_id": 5,
  "optional_supplier_id": null,
  "currency": "SAR",
  "lines": [
    {
      "line_no": 1,
      "description": "…",
      "specifications": "…",
      "quantity": 10,
      "unit": "قطعة",
      "need_date": "2026-12-01",
      "inventory_item_id": null,
      "unit_estimate": 50.00,
      "unpriced_reason": null
    }
  ]
}
```

**Validation:** seasonal ⇒ `season_id` required same-tenant; administrative ⇒ `season_id` null; org unit active same-tenant (**III-D5**); optional supplier eligibility (**III-D4**); priority ≠ normal ⇒ justification required; unpriced line ⇒ `unpriced_reason` required.

### 15.3 Submit body

```json
{
  "acknowledge_duplicate_warning": true,
  "comment": "optional"
}
```

If duplicate hints exist and ack false/missing → `422 PR_DUPLICATE_ACK_REQUIRED` (**III-D6**). On success: `status=submitted`, version snapshot written, `matrix_id` remains null (**III-D1**). Concurrent double-submit → one success + `409 PR_INVALID_TRANSITION`.

### 15.3a Cancel body (non-approved)

```json
{
  "reason": "…"
}
```

Allowed when `status` ∈ {`draft`, `submitted`} (and later `returned`/`under_review` when Phase IV exists). Reason trimmed required (**Eng** ≥10). Errors: `422 PR_CANCEL_REASON_REQUIRED`, `409 PR_INVALID_TRANSITION`, `422 PR_PROCUREMENT_ENGAGED` if lock set. **Not** `cancel-approved` (Phase IV / **D3**).

### 15.3b Verify priority body

```json
{
  "comment": "optional"
}
```

Records `priority_verified_by` / `priority_verified_at`. Does **not** change status and does **not** bypass future Phase IV approvals (**122**). Allowed on `draft`/`submitted` (**Eng**).

### 15.4 Financial review body (**III-D2**)

```json
{
  "outcome": "cleared",
  "justification": "…",
  "version_no": 1
}
```

`version_no` must match current PR `version_no` (or Eng-allowed draft version). `reviewer_id` = auth user (may equal requester — **III-D3**). Does **not** transition status. Phase IV later requires latest cleared/cleared_with_conditions before final approve (**D2**).

### 15.5 Resources (Eng)

**PurchaseRequestResource:** `id`, `pr_number`, `version_no`, `status`, `request_kind`, `season_id`, `title`, `purpose`, `priority`, urgency fields, `requested_by`, `beneficiary_organization_unit_id`, `optional_supplier_id`, currency/totals flags, `duplicate_warning_acknowledged_at`, `submitted_at`, cancel fields, `pending_approval_engine` (true when `submitted`), `lines[]`, `latest_financial_review?`, timestamps.  
**Never** invent approval step UI fields in III responses beyond null placeholders for `matrix_id`.

### 15.6 Documents

Optional: Documents link `documentable_type=purchase_request`. Requires Documents perms + `purchase_requests.view`.

### 15.7 Audit (III)

`PR_CREATED`, `PR_UPDATED`, `PR_LINES_REPLACED`, `PR_SUBMITTED`, `PR_CANCELLED`, `PR_PRIORITY_VERIFIED`, `PR_FINANCIAL_REVIEW_RECORDED`, `PR_DUPLICATE_ACKNOWLEDGED`.

---

## 16. S05 — Procurement cycle API (DESIGN ONLY)

Base conventions §1 apply. **Do not ship these routes in S04.** Implement only under [S05_IMPLEMENTATION_PLAN.md](S05_IMPLEMENTATION_PLAN.md) Parts 1–5.

### 16.1 RFQs (Part 1)

| Method | Path | Perm |
|---|---|---|
| GET | `/rfqs` | `rfq.view` |
| POST | `/rfqs` | `rfq.create` — body includes `purchase_request_id` (approved) |
| GET | `/rfqs/{rfq}` | `rfq.view` |
| PATCH | `/rfqs/{rfq}` | `rfq.update` — draft only |
| PUT | `/rfqs/{rfq}/lines` | `rfq.update` |
| POST | `/rfqs/{rfq}/publish` | `rfq.publish` — sets PR `procurement_engaged_*` |
| POST | `/rfqs/{rfq}/cancel` | `rfq.cancel` — reason required |

Stable errors (Eng): `RFQ_PR_NOT_APPROVED`, `RFQ_INVALID_STATUS`, `PR_PROCUREMENT_ENGAGED` (existing), `RFQ_SEASON_MISMATCH`.

**PurchaseRequestResource (S05 additive):** expose `procurement_engaged_at`, `procurement_engagement_ref` when present (read-only).

### 16.2 Invitations & offers (Part 2)

| Method | Path | Perm |
|---|---|---|
| GET/POST | `/rfqs/{rfq}/invitations` | `rfq.view` / `rfq.invite` |
| GET | `/rfqs/{rfq}/offers` | `rfq.view` |
| POST | `/rfqs/{rfq}/offers` | `rfq.record_offer` |
| PATCH | `/offers/{offer}` | `rfq.record_offer` — draft |
| POST | `/offers/{offer}/submit` | `rfq.record_offer` |
| POST | `/offers/{offer}/withdraw` | `rfq.record_offer` |
| POST | `/rfqs/{rfq}/select-supplier` | `rfq.select_supplier` — body: `supplier_id`, `selection_rationale`, optional `offer_id` |

Errors: `SUPPLIER_NOT_ELIGIBLE`, `RFQ_ALREADY_AWARDED`, `OFFER_INVALID_STATUS`, `SELECTION_RATIONALE_REQUIRED`.

### 16.3 Purchase orders (Part 3) — IMPLEMENTED locally

| Method | Path | Perm |
|---|---|---|
| GET | `/purchase-orders` | `purchase_orders.view` |
| POST | `/purchase-orders` | `purchase_orders.create` — from awarded `rfq_id` |
| GET | `/purchase-orders/{po}` | `purchase_orders.view` |
| PATCH | `/purchase-orders/{po}` | `purchase_orders.update` — draft; optional `contract_id` |
| POST | `/purchase-orders/{po}/issue` | `purchase_orders.issue` |
| POST | `/purchase-orders/{po}/cancel` | `purchase_orders.cancel` — body `reason` (≥10 chars) |
| POST | `/purchase-orders/{po}/close` | `purchase_orders.close` — **Part 5 IMPLEMENTED locally** — body optional `close_reason` (mandatory ≥10 when remainder > 0) |

Body (create): `{ rfq_id, notes?, contract_id?, currency? }`. Lines copied from winning selected offer; supplier/lines immutable after create. Unique one PO per RFQ (`tenant_id`,`procurement_rfq_id`).

Errors: `PO_RFQ_NOT_AWARDED`, `PO_ALREADY_EXISTS`, `PO_WINNING_OFFER_MISSING`, `PO_HAS_RECEIPTS`, `PO_INVALID_STATUS`, `PO_CANCEL_REASON_REQUIRED`, `PO_CLOSE_REASON_REQUIRED`, `PO_ALREADY_CLOSED`, `CONTRACT_SUPPLIER_MISMATCH` (cross-tenant contract → **404** via tenant scope).

Detail resource includes per-line `quantity_remaining` and header `quantity_summary` (`ordered`/`received`/`remaining`) when lines are loaded. Close sets `closed_at` / `closed_by` / `close_reason`; does not mutate historical `quantity_received` or invent GRN/stock for abandoned remainder. Engagement on PR remains through close.

### 16.4 Goods receipts (Part 4) — IMPLEMENTED locally

| Method | Path | Perm |
|---|---|---|
| GET | `/goods-receipts` | `goods_receipts.view` |
| POST | `/goods-receipts` | `goods_receipts.create` — `purchase_order_id`, lines[], optional `warehouse_id`, optional `idempotency_key` / `Idempotency-Key` |
| GET | `/goods-receipts/{grn}` | `goods_receipts.view` |
| POST | `/goods-receipts/{grn}/post` | `goods_receipts.post` — posts stock via `ReceiveStock` for catalog lines; updates PO `quantity_received` + status |

Create allowed only for PO `issued`\|`partially_received`. Post is once (`draft→posted`). Over-receipt checked under row locks. Non-catalog lines fulfill PO qty without movement.

Errors: `GRN_OVER_RECEIPT`, `GRN_DUPLICATE_POST`, `GRN_PO_CLOSED`, `GRN_PO_NOT_RECEIVABLE`, `GRN_WAREHOUSE_REQUIRED`, `WAREHOUSE_INACTIVE`, `ITEM_INACTIVE`, `GRN_INVALID_STATUS`, `GRN_LINES_REQUIRED`, `GRN_LINES_INVALID`.

### 16.5 Audit events (S05 Eng names)

`RFQ_CREATED`, `RFQ_PUBLISHED`, `RFQ_CANCELLED`, `RFQ_SUPPLIER_INVITED`, `OFFER_SUBMITTED`, `OFFER_WITHDRAWN`, `RFQ_SUPPLIER_SELECTED`, `PO_CREATED`, `PO_ISSUED`, `PO_CANCELLED`, `PO_CLOSED`, `GRN_CREATED`, `GRN_POSTED`, `PR_PROCUREMENT_ENGAGED`, `PR_PROCUREMENT_ENGAGEMENT_CLEARED`.

### 16.5.1 In-app notifications (Part 6 / ADR-0013)

Requester lifecycle types (best-effort, deduped): `PROCUREMENT_RFQ_PUBLISHED`, `PROCUREMENT_RFQ_SUPPLIER_SELECTED`, `PURCHASE_ORDER_ISSUED`, `GOODS_RECEIPT_POSTED`, `PURCHASE_ORDER_CLOSED`. Recipient = PR `requested_by` only. Offer-deadline notifications deferred (no RFQ deadline column).

### 16.6 Out of S05 API

Payment endpoints, supplier portal, budget reservation, XLSX export mandates.
