# S04 Phase III — Purchase Requests Detailed Design

> **Status:** `DESIGN OWNER ACCEPTED` · **Implementation:** `COMPLETE — OWNER ACCEPTED — FUNCTIONAL` (III-WP01–WP05)  
> **Owner decisions:** 2026-11-01 — [S04-PHASE-III-OWNER-DECISIONS-2026-11-01.md](../../10-decisions/S04-PHASE-III-OWNER-DECISIONS-2026-11-01.md) (**III-D1–D8 APPROVED — 8/8**)  
> **Acceptance status:** [S04_PHASE_III_ACCEPTANCE_STATUS.md](../../00-project/S04_PHASE_III_ACCEPTANCE_STATUS.md)  
> **API contracts:** [API.md](API.md) §7 (Phase III subset) · §15 (Phase III authoritative detail)  
> **Evidence:** [PHASE_III_WP01_TO_05_ACCEPTANCE_EVIDENCE.md](PHASE_III_WP01_TO_05_ACCEPTANCE_EVIDENCE.md)  
> **Last updated:** 2026-11-01 (governance sync under **IV-D15=B** — substantive III acceptance unchanged)  
> **Builds on:** ADR-0022 **117–123**, ADR-0023 §2.7 / **D2–D5**, Phase I suppliers/org/geo, II-A eligibility, Documents (ADR-0010), Notifications (ADR-0013), Tenancy (ADR-0003)  
> **Excludes:** Phase IV approval engine (**124–130**), Phase V demand groups/control reports, S05 RFQ/PO/receive, payments, accounting, budget reservation  
> **Coding:** authorized for III-WP01–WP05 (Owner). **Commit / push / merge / deploy:** not authorized by this acceptance.  
> **Phase IV:** decisions **IV-D1–D15 APPROVED**; design [PHASE_IV_APPROVAL_ENGINE_DESIGN.md](PHASE_IV_APPROVAL_ENGINE_DESIGN.md) **DRAFT — PENDING OWNER DESIGN ACCEPTANCE**; coding **NOT AUTHORIZED**

Legend: **(Owner)** = III-D* / D* / ADR binding; **(Eng)** = non-blocking technical recommendation.

---

## 0. Binding Owner decisions (2026-11-01)

| # | Choice | Rule |
|---|---|---|
| **III-D1** | A | Submit → `submitted` only; **no** matrix/steps/instances in Phase III; FE shows pending approval-engine. |
| **III-D2** | A | Financial review recordable on **draft/submitted**; bound to version; IV requires cleared review before final approve. |
| **III-D3** | A | Creator **may** financial-review if permitted; D5 still blocks that user as **final** approver in IV. |
| **III-D4** | A | Optional supplier; if set → same-tenant, not archived, eligibility; cross-tenant **404**; else **422**. |
| **III-D5** | A | Beneficiary = active same-tenant org unit; **no** membership / row-level org ACL. |
| **III-D6** | A | Duplicate = warning + explicit ack; no hard-block on similarity alone. |
| **III-D7** | A | Structured relational version header + version lines; historical immutable. |
| **III-D8** | A | **No** approve/reject/return UI or placeholder approval workflows. |

Preserved: **D2** (mandatory review before final approve), **D3** (approved cancel + engagement lock — cancel_approved is **Phase IV+**), **D4** (sensitive perms explicit), **D5** (reviewer ≠ final approver — enforced in **Phase IV**).

---

## 1. Business scope

### In scope (Phase III)

- Tenant-owned purchase requests with numbering `PR-######`.  
- Seasonal vs administrative kinds; season FK rules.  
- Requester + beneficiary org unit.  
- Multi-line items, estimates, unpriced reasons, optional inventory item reference (**no stock mutation**).  
- Optional supplier + eligibility.  
- Draft edit, submit → `submitted`, non-approved cancel, priority + verify_priority.  
- Duplicate hints + acknowledgement.  
- Structured version snapshots on submit/resubmit.  
- Financial review **records** (not approval routing).  
- Optional Documents morph `purchase_request`.  
- Arabic RTL list/detail UI without approval CTAs.  
- Audit of material Phase III events.

### Out of scope (this phase)

| Deferred to | Items |
|---|---|
| **Phase IV** | Matrices, authority ceilings, approval instances/steps, approve/reject/return, delegation, SLA, `cancel_approved`, matrix snapshot fill, D5 final-approve enforcement |
| **Phase V** | Demand groups, control reports |
| **S05** | RFQ, PO, GRN, payments, setting `procurement_engaged_*` |

---

## 2. Domain model

### 2.1 Concepts

| Concept | Meaning |
|---|---|
| **Purchase request (PR)** | Internal requisition header |
| **Working lines** | Editable lines while `draft` / `returned` |
| **Version snapshot** | Immutable structured copy of header+lines at submit (**III-D7**) |
| **Financial review** | Manual control outcome on a PR version (**D2**, **III-D2**) — not an approval step |
| **Pending engine** | `submitted` awaiting Phase IV (**III-D1**) |

### 2.2 Status set (full S04 vocabulary — Phase III writers)

| Status | Phase III may set? | Notes |
|---|---|---|
| `draft` | Yes | Create / after cancel? No — cancel → `cancelled` |
| `submitted` | Yes | Via submit (**III-D1**) |
| `returned` | **Read/display only** until IV | IV return; Phase III may edit when status is `returned` if IV already set it in a later combined env — for III-only delivery, `returned` unused |
| `under_review` | No | Phase IV |
| `approved` | No | Phase IV |
| `rejected` | No | Phase IV |
| `cancelled` | Yes | Non-approved cancel only (`draft`/`submitted`; later also `returned`/`under_review` when those exist) |

**Eng:** Phase III cancel allowed for `draft` and `submitted`. When IV adds `returned`/`under_review`, same `purchase_requests.cancel` applies per BUSINESS_RULES (non-approved).

### 2.3 Table: `purchase_request_number_sequences`

| Column | Type | Notes |
|---|---|---|
| `tenant_id` | BIGINT PK FK | |
| `next_value` | UNSIGNED BIGINT | Allocate with `FOR UPDATE` |
| timestamps | | |

### 2.4 Table: `purchase_requests` (tenant-owned)

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT PK | |
| `tenant_id` | FK | Never from request |
| `pr_number` | VARCHAR(32) | `PR-######` immutable |
| `version_no` | UNSIGNED INT | Current working version (≥1) |
| `status` | VARCHAR(32) | See §2.2 |
| `request_kind` | VARCHAR(32) | `seasonal` \| `administrative` |
| `season_id` | FK NULL | Required iff seasonal (**118**) |
| `title` | VARCHAR | Required |
| `purpose` | TEXT | Required |
| `priority` | VARCHAR | `normal` \| `urgent` \| `emergency` |
| `urgency_justification` | TEXT NULL | Required if priority ≠ normal |
| `priority_verified_at` | TIMESTAMP NULL | Set by verify_priority |
| `priority_verified_by` | FK users NULL | |
| `requested_by` | FK users | Creator / requester (**117**) |
| `beneficiary_organization_unit_id` | FK org units | Active same-tenant (**III-D5**) |
| `optional_supplier_id` | FK suppliers NULL | Optional (**III-D4**) |
| `currency` | CHAR(3) | Default `SAR` (**Eng**) |
| `estimated_total_priced` | DECIMAL(14,2) | Sum of priced lines only |
| `has_unpriced_lines` | BOOL | |
| `matrix_id` | FK NULL | **Phase IV** — remain NULL in III |
| `matrix_version_no` | INT NULL | **Phase IV** |
| `duplicate_warning_acknowledged_at` | TIMESTAMP NULL | (**III-D6**) |
| `procurement_engaged_at` | TIMESTAMP NULL | **D3** lock; unset in S04 writers |
| `procurement_engagement_ref` | VARCHAR(64) NULL | |
| `cancelled_at` / `cancelled_by` / `cancellation_reason` | | Non-approved cancel |
| `submitted_at` | TIMESTAMP NULL | |
| timestamps | | |

**Uniques:** `(tenant_id, pr_number)`.  
**Indexes:** `(tenant_id, status)`, `(tenant_id, season_id)`, `(tenant_id, requested_by)`, `(tenant_id, beneficiary_organization_unit_id)`.

### 2.5 Table: `purchase_request_lines` (working lines, tenant-owned)

| Column | Notes |
|---|---|
| `purchase_request_id` | FK |
| `line_no` | UNSIGNED INT |
| `description` / `specifications` | TEXT |
| `quantity` | DECIMAL(14,4) > 0 |
| `unit` | VARCHAR |
| `need_date` | DATE |
| `inventory_item_id` | Nullable FK — reference only |
| `unit_estimate` | DECIMAL(14,2) NULL |
| `line_estimate` | DECIMAL(14,2) NULL **(Eng:** qty×unit or explicit) |
| `unpriced_reason` | TEXT NULL — required when estimates null |
| timestamps | |

Unique `(purchase_request_id, line_no)`.

### 2.6 Table: `purchase_request_versions` (immutable header snapshot) — **III-D7**

Copied at each successful submit/resubmit:

Mirror of header business fields + `version_no`, `snapshot_at`, `submitted_by`, `duplicate_warning_acknowledged_at`, priced totals flags.  
**No updates/deletes** after insert (model guard).

Unique `(tenant_id, purchase_request_id, version_no)`.

### 2.7 Table: `purchase_request_version_lines` (immutable line snapshot) — **III-D7**

FK to version row; copy of each working line at snapshot time. Immutable.

### 2.8 Table: `purchase_request_financial_reviews` — **D2**, **III-D2**

| Column | Notes |
|---|---|
| `purchase_request_id` | FK |
| `version_no` | Must match a known version **or** current draft version_no when reviewing pre-first-submit (**Eng:** allow review on draft version_no before snapshot; on first submit, reviews for that version_no remain valid) |
| `reviewer_id` | Actor (**III-D3** may equal `requested_by`) |
| `outcome` | `cleared` \| `not_cleared` \| `cleared_with_conditions` |
| `justification` | TEXT required trimmed ≥10 **(Eng)** |
| `reviewed_at` | TIMESTAMP |

Index `(purchase_request_id, version_no, reviewed_at)`.  
Phase IV reads **latest** review for current `version_no`.

### 2.9 Documents

Morph alias `purchase_request` → `purchase_requests.id` (**Eng**). Optional; not required to submit (**IIC-D8 pattern** / ADR-0010).

### 2.10 Monetary rules

- Currency: `SAR` default; no multi-currency conversion in III.  
- DECIMAL(14,2) for money; DECIMAL(14,4) for qty.  
- `estimated_total_priced` = sum of priced `line_estimate`; partial vs complete distinguished via `has_unpriced_lines`.

---

## 3. Lifecycle (Phase III)

```
draft ──submit──► submitted ──(Phase IV)──► under_review / …
  │                  │
  │                  └── cancel ──► cancelled
  └── cancel ──► cancelled

(returned is Phase IV outcome; then edit → submit creates new version)
```

### Submit rules (**III-D1**, **III-D6**, **III-D7**)

1. Status ∈ {`draft`, `returned`}.  
2. ≥1 line; seasonal season present; admin season null; urgency rules; org unit active; optional supplier checks.  
3. Unpriced lines need reasons.  
4. If duplicate hints exist and not acknowledged → `422 PR_DUPLICATE_ACK_REQUIRED`.  
5. Lock PR row; allocate/bump `version_no` as needed; write version + version_lines; set `submitted`, `submitted_at`.  
6. **Do not** create approval artifacts.

### Resubmit

Same as submit from `returned` (IV) or from `draft` after edits; always new immutable version snapshot.

### Cancel (non-approved)

Permission `purchase_requests.cancel`; reason required; statuses `draft`/`submitted` (+ `returned`/`under_review` when IV exists).  
**Not** `cancel_approved` (Phase IV / **D3**).

### Financial review (**III-D2**)

Allowed on `draft`/`submitted`; does not change status; audits `PR_FINANCIAL_REVIEW_RECORDED`.

### Concurrency & integrity (**Eng** — binding for implementation)

| Operation | Protection |
|---|---|
| Allocate `pr_number` | `FOR UPDATE` on `purchase_request_number_sequences` row (tenant PK) inside transaction |
| Submit / cancel / lines replace | `lockForUpdate()` on `purchase_requests` row; assert expected status |
| Double submit | First → `submitted` + version; second → `409 PR_INVALID_TRANSITION` |
| Version uniqueness | Unique `(tenant_id, purchase_request_id, version_no)`; insert-only snapshots |
| Duplicate ack | Persist `duplicate_warning_acknowledged_at` on PR (and copy onto version snapshot) under same submit transaction |
| Financial review | Append-only; concurrent reviews both insert; Phase IV reads **latest** by `(version_no, reviewed_at desc, id desc)` |
| Cross-tenant | TenantOwned scope → **404**; never trust `tenant_id` from body |

Historical `purchase_request_versions` / `_version_lines` / financial review rows: **immutable** (model `updating`/`deleting` guards). Working lines mutable only while `draft`/`returned`.

---

## 4. Permissions (Phase III seed)

| Permission | Sensitive? | Phase |
|---|---|---|
| `purchase_requests.view` | No | III |
| `purchase_requests.create` | No | III |
| `purchase_requests.update` | No | III |
| `purchase_requests.submit` | No | III |
| `purchase_requests.cancel` | No | III |
| `purchase_requests.verify_priority` | No | III |
| `purchase_requests.financial_review` | **Yes** | III |
| `purchase_requests.approve` / `reject` / `return` / `cancel_approved` / `manage_approval_config` / `manage_delegations` | Yes | **IV** |
| `consolidate_planning` / `view_control_reports` | No | **V** |

**D4:** sensitive verbs never auto-granted to default templates.  
**SoD Phase III:** none beyond permission checks for financial review (**III-D3**). Persist `reviewer_id` for IV **D5**.

---

## 5. API (summary)

Authoritative detail: **[API.md §15](API.md)**. Phase III paths:

| Method | Path | Perm |
|---|---|---|
| GET/POST | `/purchase-requests` | view / create |
| GET/PATCH | `/purchase-requests/{id}` | view / update |
| PUT | `/purchase-requests/{id}/lines` | update |
| POST | `/purchase-requests/{id}/submit` | submit |
| POST | `/purchase-requests/{id}/cancel` | cancel |
| POST | `/purchase-requests/{id}/verify-priority` | verify_priority |
| POST | `/purchase-requests/{id}/financial-reviews` | financial_review |
| GET | `/purchase-requests/{id}/versions` | view |
| GET | `/purchase-requests/{id}/financial-reviews` | view |
| GET | `/purchase-requests/duplicate-hints` | view |

**Deferred (Phase IV):** approve, reject, return, cancel-approved, matrix config APIs (§6).

Errors (III): `PR_INVALID_TRANSITION`, `PR_SEASON_REQUIRED`, `PR_EDIT_LOCKED`, `PR_UNPRICED_REASON_REQUIRED`, `PR_DUPLICATE_ACK_REQUIRED`, `PR_SUPPLIER_NOT_FOUND`, `PR_SUPPLIER_ARCHIVED`, `PR_SUPPLIER_NOT_ELIGIBLE`, `PR_ORG_UNIT_INVALID`, `PR_CANCEL_REASON_REQUIRED`, `PR_LINES_REQUIRED`, …

---

## 6. Frontend (Arabic RTL) — **III-D8**

Route: `/app/purchase-requests` (+ `/:id`) if `purchase_requests.view`.

| Surface | Behavior |
|---|---|
| List | Filters: status, kind, season, priority, supplier, q; pagination; badge for `submitted` = «بانتظار محرك الاعتماد» |
| Create/Edit | Kind toggles season field; org unit picker (active); optional supplier; lines editor; estimate preview; unpriced warnings |
| Submit | Confirm; show duplicate warning modal + ack checkbox (**III-D6**) |
| Detail | Read-only when submitted; financial review panel if `financial_review`; version history; documents; cancel if permitted |
| **Forbidden** | Approve / reject / return buttons or stubs (**III-D8**) |
| Nav | «طلبات الشراء»; hide approval-settings until IV |

---

## 7. Audit events (Phase III)

`PR_CREATED`, `PR_UPDATED`, `PR_LINES_REPLACED`, `PR_SUBMITTED`, `PR_CANCELLED`, `PR_PRIORITY_VERIFIED`, `PR_FINANCIAL_REVIEW_RECORDED`, `PR_DUPLICATE_ACKNOWLEDGED`.  
Include `tenant_id`, actor, correlation ID, `pr_id`, `pr_number`, `version_no`. No secrets.

---

## 8. Testing matrix (design — not executed)

| ID | Coverage |
|---|---|
| AC-P01 | Create with requester + beneficiary |
| AC-P02 | Seasonal season required; admin omits |
| AC-P03 | Multi-line; catalog or free text |
| AC-P04 | Unpriced reason; partial totals |
| AC-P05 | Review recordable (III); final-approve gate = **IV** |
| AC-P05b–g | **Phase IV** |
| AC-P05c | cancel_approved = **IV** |
| AC-P06 | Supplier optional; eligibility when set (**III-D4**) |
| AC-P07 | Edit lock when submitted; versioning (**III-D7**) — return path IV |
| AC-P08 | Urgency justification; verify_priority |
| AC-P09 | Duplicate warn + ack (**III-D6**) |
| SEC | Cross-tenant 404; perm 401/403; sequence concurrency; Documents ownership |

---

## 9. Work packages (ready — not authorized)

| WP | Scope | Gate |
|---|---|---|
| **III-WP01** | Sequences, tables, models, permission catalog seed | G1 schema |
| **III-WP02** | Draft/lines/submit/cancel/priority/duplicates/versions | G2 domain |
| **III-WP03** | HTTP API, policies, financial review, Documents morph | G3 API |
| **III-WP04** | Arabic RTL UI (**III-D8**) | G4 FE |
| **III-WP05** | Concurrency, acceptance evidence, regressions | G5 accept |

Integrated delivery allowed **after** design acceptance + coding auth, with internal G1–G5 — **excluding** Phase IV/V.

### Per-WP detail

**III-WP01** — Depends: design accept + coding auth. Deliver: migrations (no run until auth), TenantOwned models, factories, `PermissionCatalog` III perms + sensitive list, number generator. Tests: model/tenant unit + sequence uniqueness harness. Out: Actions/API/FE/IV tables.

**III-WP02** — Depends: WP01. Deliver: Create/Update/ReplaceLines/Submit/Cancel/VerifyPriority/DuplicateHints Actions; version snapshot writer; eligibility/org/season validators. Tests: lifecycle Pest on `erp_hajj_testing` only when authorized. Out: approve/reject/return.

**III-WP03** — Depends: WP02. Deliver: Controller, Form Requests, Resources, routes §15, Policy, financial review Action, morph map. Tests: feature API matrix. Out: §6 matrix APIs.

**III-WP04** — Depends: WP03. Deliver: pages, queries, mutations, nav, locales; **no** approval CTAs. Tests: Vitest validation + permission visibility. Out: approval-settings UI.

**III-WP05** — Depends: WP01–04. Deliver: concurrency workers, evidence doc, AC-P mapping, supplier regression smoke. Out: Owner acceptance claim; IV AC-A*.

---

## 10. Phase boundaries checklist

| Concern | Phase III | Phase IV | Phase V |
|---|---|---|---|
| Submit → submitted | ✓ | | |
| Matrix snapshot / steps | | ✓ | |
| Financial review record | ✓ | gate on final | |
| Approve/reject/return UI/API | ✗ **III-D8** | ✓ | |
| cancel_approved | | ✓ | |
| Demand groups / reports | | | ✓ |

---

## 11. Remaining Eng items (non-blocking)

- Duplicate lookback default days (suggest 30).  
- Justification min length (suggest 10).  
- Whether draft financial review before first submit uses `version_no=1` pre-snapshot (recommend yes).  
- Exact Arabic microcopy for pending-engine banner.

## 12. Governance

| Item | Status |
|---|---|
| III-D1–D8 | **APPROVED** |
| This design pack | **DRAFT — PENDING OWNER DESIGN ACCEPTANCE** |
| Coding | **NOT AUTHORIZED** |
| Implemented / tested / OWNER ACCEPTED | **No** |
