# Suppliers & Procurement (S04) — Business Rules

> **Status:** Designed (G1) — **NOT IMPLEMENTED**  
> **Last updated:** 2026-10-31  
> Binding functional decisions: [ADR-0022](../../10-decisions/ADR-0022-S04-SUPPLIERS-AND-PURCHASE-REQUESTS-FUNCTIONAL-DECISIONS.md) **101–130**  
> Engineering proposals: [ADR-0023](../../10-decisions/ADR-0023-S04-ENGINEERING-DESIGN.md)

Legend: **(Fn)** = functional decision n. Labels **ENGINEERING PROPOSAL** / **OWNER DECISION REQUIRED** / **DEFERRED** mark non-frozen implementation choices.

---

## 0. Global invariants

1. Every business row is **tenant-owned**; `tenant_id` from `TenantContext` only — never from client payload (**102**, ADR-0003).  
2. Cross-tenant lookups → **404** (not 403).  
3. Deny-by-default; Tenant Owner does **not** bypass Policies.  
4. No RFQ / quotation / PO / inventory receive-against-PR in S04 (**S05**, ADR-0020).  
5. No payments, accounting postings, or budget reservations (**110**, **120**).  
6. Contracts category `supplier` ≠ supplier master (**111**).  
7. Critical actions audited with correlation ID; bank secrets never stored in audit values (**130**, ADR-0015).

---

## 1. Supplier registry (101–111)

### 1.1 Identity

- One **unified** supplier record per tenant; many-to-many with service categories (**101**).  
- Server-generated immutable internal number: **ENGINEERING PROPOSAL** `SUP-######` via per-tenant sequence + `FOR UPDATE` (Contracts pattern).  
- Legal identifier uniqueness: tenant-scoped unique on `(tenant_id, legal_id_type, legal_id_normalized)` when legal id present (**103**).  
- Name similarity warnings: **ENGINEERING PROPOSAL** — non-blocking check (normalized Arabic/Latin name) on create/update; does not replace legal-id uniqueness.

### 1.2 Types & categories

- Types (**105**): `company` | `establishment` | `individual` | `government_nonprofit` | `international`. Required legal fields differ by type + country — matrix is **tenant-configurable defaults** seeded for Hajj; exact seed set is **OWNER DECISION REQUIRED** only if defaults must be mandated platform-wide (otherwise ENGINEERING: seed suggested Arabic Hajj categories, tenant-editable).  
- Categories (**104**): tenant-owned catalog; deactivate instead of hard-delete when linked; hard-delete only when unused.

### 1.3 Geographic coverage (**106**) — Owner **D1**

- Coverage is per **(supplier, category)** → one or more geo places from the **minimal geographic catalog**.  
- Country codes reuse ISO-style codes already used on pilgrims (`nationality` / issuing country patterns).  
- No ad-hoc free-text location lists as the system of record.  
- **Initial catalog scope (D1):** Makkah, Madinah, Jeddah, and Hajj holy sites Mina, Arafat, Muzdalifah — plus parent country SA as needed for hierarchy. Catalog is **extensible**; uniqueness prevents duplicate places.  
- Exact seed row identifiers/codes must be documented and validated against authoritative geographic references **before implementation** (engineering checklist; not an excuse to invent a world gazetteer).

### 1.4 Lifecycle (**107**, **108**)

| Status | May use on **new** PRs as selected supplier?* |
|---|---|
| `draft` | No |
| `under_review` | No |
| `active` | Yes, if **eligible** (documents + not blocked) |
| `temporarily_suspended` | No |
| `blocked` | No |
| `archived` | No |

\*PR creation does **not** require selecting a supplier (**ADR-0022 §5**). When a supplier **is** referenced (future optional link or planning fields), eligibility applies.

Transitions: permission-gated; each transition audited. Qualification gate: cannot move to `active` while mandatory documents missing/expired (**107**, **109**).

### 1.5 Documents (**109**)

- Requirements by type × country × (optional) category.  
- File bytes via Documents module (ADR-0010 immutable files).  
- Eligibility metadata (requirement key, `expires_at`, linked `document_id`, status) lives in supplier-domain tables — **not** by mutating Document versioning (forbidden by ADR-0010).  
- New file upload = new Document + new eligibility row / supersede prior row (**ENGINEERING PROPOSAL**).  
- Missing/expired mandatory docs → supplier not eligible for activation / new PR supplier use.

### 1.6 Contacts & bank accounts (**110**)

- Multiple contacts; multiple bank accounts; exactly one **primary** bank account when any exist.  
- Bank changes that alter IBAN/account number require **verification + approval** workflow (**ENGINEERING PROPOSAL**: pending → approved/rejected).  
- Display: masked (`****1234`); full reveal requires `suppliers.view_banking`.  
- Storage: ciphertext at rest (**ENGINEERING PROPOSAL**, ADR-0023). No payments.

### 1.7 Contracts / ops link (**111**)

- S04 may document optional nullable `contracts.supplier_id` as **future** integration.  
- **Not** in S04 mandatory migrations unless Owner approves separate Contracts CR.  
- No forced backfill of historical contracts.

---

## 2. Supplier evaluation (112–116)

- Multi-criteria weighted score 0–100; weights by service category (**112**).  
- Types: qualification | periodic | operational | post_season; optional `season_id` (**113**).  
- History append-only for approved versions; drafts editable by preparer.  
- Drafter ≠ approver (**114**).  
- Low score → alerts/escalation only; **no auto suspend** (**115**). “Under monitoring” is a flag/descriptor on evaluation outcome — **not** a supplier status.  
- Objections/corrections create new evaluation version under independent review (**116**). No supplier portal.

Criterion catalog: **ENGINEERING PROPOSAL** — tenant-configurable criteria per category with weights summing to 100 (± tolerance 0.01).

---

## 3. Purchase requests (117–123)

### 3.1 Header

- Decentralized create by authorized users; store `requested_by`, `beneficiary_organization_unit_id` (**117**).  
- `request_kind`: `seasonal` | `administrative` (**118**).  
  - `seasonal` → `season_id` **required** (must exist in tenant; use SeasonContext when route is season-scoped **or** validate FK — **ENGINEERING PROPOSAL**: store `season_id` on PR; seasonal routes may still use `X-Season-Id` for list filters).  
  - `administrative` → `season_id` null.  
- Priority: `normal` | `urgent` | `emergency`; urgency justification required if not normal (**122**).  
- Supplier on PR: **optional**; not required at create.

### 3.2 Lines (**119**)

- ≥1 line on submit.  
- Each line: description/specs, qty, unit, need_date, optional attachments, optional `inventory_item_id` **or** free text.  
- Catalog FK does **not** reserve or mutate stock.

### 3.3 Estimates & financial review (**120**) — Owner **D2**

- Per-line optional `unit_estimate` / `line_estimate`; `unpriced_reason` required when price absent.  
- Header shows `estimated_total_complete` vs `estimated_total_partial` (sum of priced lines only) and flags `has_unpriced_lines`.  
- Currency default **SAR** (tenant setting may override later — **DEFERRED** if settings key absent).  
- **Mandatory financial review (D2):** **Every** PR must receive an authorized financial review **before final approval**, including PRs with unknown/unpriced estimated costs.  
- Persist: `reviewer_id`, `outcome` (`cleared` | `not_cleared` | `cleared_with_conditions`), `reviewed_at`, `justification`, `version_no` (PR version reviewed).  
- Final approval step is blocked unless the **current** PR version has a review with outcome `cleared` or `cleared_with_conditions`.  
- Review does **not** create accounting entries, reserve budget, or authorize payment.  
- **Financial reviewer ≠ final approver (D5):** hard system enforcement — see §4.3.1.

### 3.4 Lifecycle & versions (**121**, **128**)

| Status | Meaning |
|---|---|
| `draft` | Editable by author (and update permission) |
| `submitted` | Submitted; awaiting route start / financial gates as designed |
| `under_review` | In approval workflow |
| `returned` | Returned for revision |
| `approved` | Fully approved |
| `rejected` | Final rejection |
| `cancelled` | Cancelled |

Rules:

- No direct field edit while `under_review` (except system/approval actions).  
- Return → `returned`; author edits create **new version** (version_no++); prior approval steps retained historically but **invalidated** for the new version; path re-resolved (**128**).  
- Reject = terminal unless product later allows reopen (**DEFERRED** — S04: rejected stays rejected).  
- **Cancel (D3):**  
  - Non-approved (`draft` / `submitted` / `returned` / `under_review`): `purchase_requests.cancel` + reason + audit.  
  - **Approved:** only via dedicated `purchase_requests.cancel_approved`; mandatory cancellation reason; full audit trail.  
  - **Downstream guard:** if the PR has entered a future purchasing process, cancellation is **prohibited**. S04 stores a nullable engagement lock (`procurement_engaged_at` / ref) that remains **unset** until S05 (or later) sets it — S04 does **not** implement RFQ/PO; it only enforces “if lock set → reject cancel”.  

Numbering: **ENGINEERING PROPOSAL** `PR-######` per tenant, `FOR UPDATE`.

### 3.5 Duplicates & consolidation (**123**)

- Soft warning when similar item/season/need_date windows match (**ENGINEERING PROPOSAL**: configurable lookback days; default suggestion 30 — not a frozen SLA).  
- Planning-only **demand group**: links approved PRs; does not merge quantities into one RFQ/PO (S05). Each PR remains independent for approvals.

---

## 4. Approvals (124–130)

### 4.1 Dynamic routes (**124**)

- Tenant configures matrices by estimated value band, department, request_kind, and optional season applicability.  
- On submit / resubmit, system **snapshots** the selected matrix version onto the PR (**125**).  
- Later matrix edits do not rewrite in-flight snapshots.

### 4.2 Authority matrix (**125**)

- Bands and levels are tenant data.  
- **No default numeric thresholds are approved** in ADR-0022 — tenants must configure before production use (**OWNER DECISION REQUIRED** per tenant go-live; platform may ship empty matrix requiring setup).  
- User cannot approve above their level’s ceiling.

### 4.3 Segregation & conflicts (**126**)

- Creator cannot approve own PR at any level.  
- Conflict detection: same user as requester; optional same org-unit dual-control rules (**ENGINEERING PROPOSAL**: minimum = creator ≠ approver; extended COI rules tenant flags).  
- On conflict, reassign to eligible alternate; record reason; do not skip level.

#### 4.3.1 Financial reviewer ≠ final approver (**D5** — Owner 2026-10-31)

Hard enforcement (not a warning) on every PR version:

1. The `reviewer_id` of the current version’s financial review **must differ** from the user who completes **final** approval.  
2. Applies even when the user holds multiple roles/permissions.  
3. **Delegation must not bypass** — the acting user (delegate) is checked against `reviewer_id`.  
4. If the assigned final-step assignee is the financial reviewer, **reassign** to another qualified independent approver (matrix eligibility + authority ceiling + SoD).  
5. If no eligible final approver exists, **block** final approval and **escalate** to an authorized administrator — never auto-approve, waive, or skip mandatory steps.  
6. Audit conflict detection, reassignment, escalation, and blocked attempts.

### 4.4 Delegation (**127**)

- Time-bounded delegation from approver A → B within A’s authority ceiling.  
- Requires authorizing permission to create delegation; conflict checks; auto-expire; revocable.  
- Delegate acts as A’s level but audit records both actors.  
- **D5:** delegate who is the financial reviewer for the PR version cannot complete final approval.

### 4.5 SLA (**129**)

- Target durations by priority × level are **tenant-configurable**; no approved global durations.  
- Reminders then escalation to configured escalation role/user.  
- **Never** auto-approve on timeout.

### 4.6 Audit & reports (**130**)

- All material events append-only via platform audit + domain transition tables.  
- Control reports: latency, overdue, outcomes, compliance — permission-gated, tenant-scoped.  
- Retention policy detail: **DEFERRED** to ops/security baseline unless Owner sets a number.

---

## 5. Notifications (supporting)

Emit in-app notifications (ADR-0013) for: PR submitted to approver, returned, rejected, approved, SLA reminder/escalation, low evaluation, bank-change pending approval. Exact event catalog: see API/ADR-0023.

---

## 6. Explicit non-goals (S04)

RFQ, quotes, vendor selection, PO, GRN against PO, payments, e-invoice, supplier self-service portal, forced contract backfill, inventory mutations from PR.
