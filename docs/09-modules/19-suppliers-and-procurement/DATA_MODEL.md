# Suppliers & Procurement (S04) — Data Model

> **Status:** Phase I–IV schemas implemented/accepted per stream evidence; Phase V demand-group tables (`250001`…`250004`) **IMPLEMENTED (engineering)** — Owner Phase V functional acceptance **PENDING**; S05 tables **NOT STARTED**  
> **Last updated:** 2026-11-01
> Functional binding: ADR-0022 · Engineering: ADR-0023 · Owner G1 **D1–D5** · Phase III **III-D1–D8**
> All tenant business tables: `TenantOwned` + `UsesTenantScope` unless marked **platform reference**.

Classification labels follow DATABASE_PRINCIPLES / tenancy docs.

---

## 1. Proposed migration order (conceptual — do not create files yet)

1. Platform minimal geo catalog (if adopted as platform)
2. Sequences: `supplier_number_sequences`, `purchase_request_number_sequences` (alias Eng name `purchase_request_sequences` — prefer `*_number_sequences` for consistency), `supplier_evaluation_number_sequences`
3. `supplier_categories`
4. `suppliers` + `supplier_category_supplier` (pivot) + coverage + contacts + bank accounts
5. Document requirement & eligibility tables
6. Evaluation criteria, evaluations, evaluation lines/scores, evaluation versions/appeals
7. Approval matrices / bands / steps / delegations / SLA policies
8. `purchase_requests` + lines + versions + financial reviews + approval instances/steps
9. Planning demand groups (optional)
10. *(Future / separate CR)* nullable `contracts.supplier_id`

---

## 2. Minimal geographic catalog (**106**)

### Alternative considered

| Option | Pros | Cons |
|---|---|---|
| A. ISO country code string only | Minimal | Insufficient for region/city coverage |
| B. **Platform `geo_places`** (chosen proposal) | Shared canonical refs; tenant isolation on coverage links | Small shared table to seed |
| C. Full GIS subsystem | Rich | **Rejected** — violates G0 minimal catalog |

### `geo_places` — **platform reference** (no `tenant_id`)

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT PK | |
| `country_code` | CHAR(2) | ISO 3166-1 alpha-2 (align with pilgrim nationality style) |
| `kind` | VARCHAR(16) | `country` \| `region` \| `city` \| `holy_site` |
| `code` | VARCHAR(32) | Stable code within country — immutable after seed |
| `name_ar` / `name_en` | VARCHAR(150) | |
| `parent_id` | BIGINT NULL | Self-FK |
| `is_active` | BOOLEAN | |
| timestamps | | |

**Uniques:** `(country_code, kind, code)` — prevents duplicate geographic records.
**Extensibility:** new places added later as additional rows; do not fork parallel location lists.

### Initial seed scope — Owner **D1** (places, not final DB ids)

| Proposed stable `code` | `kind` | Display (AR / EN) | Notes |
|---|---|---|---|
| `SA` | `country` | المملكة العربية السعودية / Saudi Arabia | Parent; reuse ISO `SA` |
| `MAKKAH` | `city` | مكة المكرمة / Makkah | |
| `MADINAH` | `city` | المدينة المنورة / Madinah | |
| `JEDDAH` | `city` | جدة / Jeddah | |
| `MINA` | `holy_site` | منى / Mina | |
| `ARAFAT` | `holy_site` | عرفات / Arafat | |
| `MUZDALIFAH` | `holy_site` | مزدلفة / Muzdalifah | |

**Pre-implementation gate:** validate names/codes against authoritative geographic references; assign final surrogate `id` values only at migration/seed time; do not invent coordinates or a full KSA gazetteer in S04.

Tenant coverage rows FK → `geo_places.id` (RESTRICT).

---

## 3. Suppliers

### `supplier_categories` (tenant-owned)

| Column | Notes |
|---|---|
| `tenant_id`, `name`, `code` nullable, `is_active`, timestamps | Mirror contract_categories pattern |
| Unique `(tenant_id, name)`; unique `(tenant_id, code)` when set | |
| Hard delete only if unused | **104** |

### `suppliers` (tenant-owned)

| Column | Notes |
|---|---|
| `tenant_id` | RESTRICT |
| `supplier_number` | Immutable `SUP-######` |
| `status` | draft / under_review / active / temporarily_suspended / blocked / archived |
| `supplier_type` | enum **105** |
| `display_name` | Required |
| `legal_name` | Nullable |
| `country_code` | CHAR(2) required |
| `legal_id_type` / `legal_id_value` | Nullable; normalized unique when present |
| `notes` | Non-secret |
| `created_by` | users FK |
| `activated_at` | Nullable |
| timestamps | |

**Indexes:** `(tenant_id, status)`, `(tenant_id, display_name)`, unique `(tenant_id, supplier_number)`, unique `(tenant_id, legal_id_type, legal_id_normalized)` (nullable-safe strategy per MySQL norms).

### `supplier_category_supplier` pivot

`(tenant_id, supplier_id, supplier_category_id)` unique; both FKs RESTRICT.

### `supplier_category_coverages`

`(tenant_id, supplier_id, supplier_category_id, geo_place_id)` unique.

### `supplier_contacts`

name, role_title, email, phone, is_primary_contact, soft operational flags.

### `supplier_bank_accounts` — Phase II-B (**IMPLEMENTED — FUNCTIONALLY CLOSED — OWNER ACCEPTED** 2026-11-01)

> Authority: [PHASE_II_B_SUPPLIER_BANKING_DESIGN.md](PHASE_II_B_SUPPLIER_BANKING_DESIGN.md) · [S04-PHASE-II-B-DESIGN-APPROVAL-2026-11-01.md](../../10-decisions/S04-PHASE-II-B-DESIGN-APPROVAL-2026-11-01.md) · [IIB-D1–IIB-D12](../../10-decisions/S04-PHASE-II-B-OWNER-DECISIONS-2026-11-01.md) · functional closure [S04_PHASE_II_B_ACCEPTANCE_STATUS.md](../../00-project/S04_PHASE_II_B_ACCEPTANCE_STATUS.md). Implementation exists on the feature branch; **commit/push/merge/deploy** and default-DB migrate remain **NOT AUTHORIZED** without a separate Owner act.

| Column | Notes |
|---|---|
| `tenant_id`, `supplier_id` | TenantOwned; FK same-tenant supplier |
| `account_key`, `version_no` | Versioned family (**IIB-D8**) |
| `label` | Optional |
| `account_holder_name` | **Required** (**IIB-D1**) |
| `bank_name` | Free text **required** (**IIB-D2**); no catalog |
| `iban_ciphertext` / `account_ciphertext` | App-encrypted; ≥1 of IBAN or local account (**IIB-D3**) |
| `iban_last4` / `account_last4` | Mask display |
| `iban_fingerprint` | Keyed HMAC on every version (history); **not** uniquely constrained on this table |
| `currency` | Fixed `SAR` (**IIB-D10**) |
| `status` | `pending` \| `verified` \| `rejected` \| `revoked` (**IIB-D7**) |
| `current_slot` | `0` = family edit head; historical `= id` (orthogonal to primary) |
| `is_primary` / generated `primary_flag` | Verified-only (**IIB-D9**); **independent of `current_slot`** (**IIB-D11**) |
| `supersedes_id` | Prior version FK |
| `evidence_document_id` | Optional Document (**IIB-D5**) |
| `created_by`, `last_edited_by`, verify/reject/revoke actors + timestamps | |
| timestamps | |

**Editors:** `supplier_bank_account_editors` — SoD (**IIB-D4**).  

**IBAN claims (C1 + M1/M2):** `supplier_bank_iban_claims` with `UNIQUE (tenant_id, iban_fingerprint)` mapping fingerprint → owning `account_key` / `supplier_id` / `holder_bank_account_id`.

| Field | Integrity |
|---|---|
| `tenant_id` | FK; TenantOwned; must match supplier + holder |
| `supplier_id` | FK → suppliers |
| `account_key` | Owning family; app-enforced match to holder |
| `holder_bank_account_id` | FK → `supplier_bank_accounts.id` (`ON DELETE RESTRICT` while pointed); must belong to same tenant/supplier/family |
| `iban_fingerprint` | Keyed HMAC; unique with tenant |

Rules: same-family same-IBAN supersession refreshes holder; different-IBAN supersession keeps claim on old primary’s IBAN until no pending/verified row retains that fingerprint; **pending PATCH** that changes IBAN acquires/releases claims (**M1**); metadata-only PATCH must not touch claims; safe release **DELETE**s the claim row (**M2**); no cross-family reassignment; no orphan claims; failed txn rolls back version+claim. **Do not** unique version-level fingerprints. See design pack §2.4.

**Never** put full IBAN in audit — last4 + status/reason only; reveal = dedicated audited action (**IIB-D12**, **E1**/**E4**).  
**D11:** supersede retains old primary → verify (no transfer) → explicit `set-primary` → revoke clears primary without auto-promote.

### Document eligibility

> **II-A:** **IMPLEMENTED — OWNER ACCEPTED** (2026-11-01) — [PHASE_II_A_ELIGIBILITY_DESIGN.md](PHASE_II_A_ELIGIBILITY_DESIGN.md), [API.md §12](API.md).

#### `supplier_eligibility_requirement_sets` (tenant-owned)

| Column | Type / notes |
|---|---|
| `id` | BIGINT PK |
| `tenant_id` | FK tenants; indexed |
| `config_key` | VARCHAR(64) — family slug across versions |
| `name` | VARCHAR |
| `status` | `draft` \| `approved` |
| `version_no` | UNSIGNED INT |
| `authored_by` | FK users (creator / tracked for SoD) |
| `last_edited_by` | FK users NULL |
| `approved_by` | FK users NULL |
| `approved_at` | TIMESTAMP NULL |
| timestamps | |

**Uniques:** `UNIQUE (tenant_id, config_key, version_no)`; draft helper `draft_slot` (0 = draft, else `version_no`) with `UNIQUE (tenant_id, config_key, draft_slot)`.
**Editors (M5):** `supplier_eligibility_set_editors` — additive history of every user who authored/edited the draft; approve SoD rejects any contributor.
**Approve (Owner M1=A):** ≥1 `is_mandatory=true` def required.
**Immutability (C1 / M4):** application + model hooks reject mutate/delete of approved versions; residual: raw SQL bypass (documented; no triggers without further Owner auth).

#### `supplier_document_requirement_defs` (tenant-owned)

| Column | Notes |
|---|---|
| `tenant_id`, `requirement_set_id` | FK set; cascade with set |
| `requirement_key` | VARCHAR |
| `label_ar`, description | |
| `supplier_type` | VARCHAR — required |
| `country_code` | CHAR(2) NULL = wildcard |
| `supplier_category_id` | BIGINT NULL = wildcard (FK categories when not null) |
| `country_code_norm` | **MySQL STORED generated** `IFNULL(country_code,'')` (migration `190015`); **SQLite tests:** physical column maintained by model `saving` |
| `supplier_category_id_norm` | **MySQL STORED generated** `IFNULL(supplier_category_id,0)`; SQLite as above |
| `is_mandatory` | BOOLEAN — approve requires ≥1 true (**M1=A**) |
| `default_validity_days` | INT NULL — guidance only |
| timestamps | |

**UNIQUE** `(tenant_id, requirement_set_id, requirement_key, supplier_type, country_code_norm, supplier_category_id_norm)`.

#### `supplier_document_records` (tenant-owned)

| Column | Notes |
|---|---|
| `tenant_id`, `supplier_id`, `requirement_def_id` | FKs |
| `requirement_key` | Denormalized for current-slot uniqueness across def versions |
| `document_id` | FK `documents.id` |
| `verification_status` | pending_verification / valid / rejected / expired / superseded |
| `current_slot` | **M2:** `0` = current row; superseded rows use `current_slot = id` |
| `expires_at` | DATE NULL |
| `uploaded_by`, `verified_by`, `verified_at`, `rejection_reason` | SoD uploader ≠ verifier |
| `supersedes_id` | Self FK NULL |
| timestamps | |

**UNIQUE** `(tenant_id, supplier_id, requirement_key, current_slot)` — at most one current record per key.
**H1:** Eligibility requires backing Document active + linked to same supplier/tenant.
**Host-delete:** morph docs + eligibility records block supplier hard-delete.

#### II-A migration order (IMPLEMENTED — OWNER ACCEPTED 2026-11-01)

1. `190010` sets (+ draft_slot)
2. `190011` defs
3. `190012` document records
4. `190013` current_slot + denormalized requirement_key
5. `190014` set editors
6. `190015` MySQL generated norms

#### Transaction boundaries

| Operation | Boundary |
|---|---|
| Approve config | Lock draft; SoD contributors; ≥1 mandatory; approve |
| Supersede document | Lock supplier + prior; set prior `current_slot=id`; insert current `current_slot=0` |
| Activate | Lock supplier + evidence Documents `FOR UPDATE`; H1 recheck; `under_review→active` |

Aligns with ADR-0010. Expiry does **not** auto-change lifecycle (**II-D3**). Banking **not** required (**II-D2**).

### Sequences

`supplier_number_sequences (tenant_id PK, next_value)`.

---

## 4. Evaluations — Phase II-C (**DESIGN DRAFT** — IIC-D1–D8)

> Authoritative detail: [PHASE_II_C_SUPPLIER_EVALUATIONS_DESIGN.md](PHASE_II_C_SUPPLIER_EVALUATIONS_DESIGN.md). **Not migrated.** Design **not** Owner-accepted.

### `supplier_evaluation_criteria_packs` / `supplier_evaluation_criteria`

Versioned packs per category (**IIC-D1**): draft → publish → immutable; active weights sum **100 ±0.01** (**IIC-D2**). Editors table for SoD. See design pack §2.2–2.3.

### `supplier_evaluations`

| Column | Notes |
|---|---|
| `evaluation_number` | `SEV-######` |
| `supplier_id`, `supplier_category_id` | Category **required**; must be linked to supplier (**IIC-D5**) |
| `evaluation_kind` | qualification / periodic / operational / post_season |
| `season_id` | Nullable; **required iff** `evaluation_kind=post_season` (**IIC-F1=A**); same-tenant FK |
| `status` | draft / pending_approval / approved / rejected |
| `criteria_pack_id` + `criteria_pack_version_no` | Bound published pack |
| `total_score` | 0–100 after submit |
| `monitoring_recommended` | BOOL — **115** / **IIC-D3** |
| `prepared_by` / `approved_by` | SoD (**114**) |
| `version_no` / `evaluation_family_key` / `supersedes_evaluation_id` | Correction family |
| `origin_appeal_id` | From accepted appeal |

### `supplier_evaluation_score_snapshots`

Immutable rows at **submit** (**IIC-D2**): snapped name/weight/score/note.

### `supplier_evaluation_appeals` (**IIC-D4** — required, not optional)

Links approved evaluation, reason, reviewer, outcome; accepted → new evaluation version.

---

## 5. Approval configuration

### `purchase_approval_matrices` (tenant-owned)

`name`, `is_active`, `version_no`, `effective_from`, `approved_at`, `approved_by`.

### `purchase_approval_matrix_rules`

Match predicates: `min_amount`, `max_amount` (nullable open end), `request_kind` nullable, `organization_unit_id` nullable, `season_required` bool nullable, **`applies_when_unpriced`** (BOOL — **Phase IV / EC-03**).
**Amounts are tenant-configured — no platform defaults mandated.** Unpriced/mixed PRs match only rules with `applies_when_unpriced=true` (never null-as-zero; never priced-subtotal bypass).

### `purchase_approval_matrix_levels`

`matrix_id`, `level_order`, `name`.
**ENGINEERING PROPOSAL (binding preference):** resolve eligibility via `purchase_requests.approve` + `purchase_approver_authority` ceiling + matrix level order — **no role-name checks in Policies** (aligns with PERMISSION_MODEL).

### `purchase_approver_authority` (tenant-owned)

`user_id`, `max_amount`, `currency`, `covers_unpriced` (BOOL — **Phase IV / EC-03**), `effective_from`/`to` — enforces **125** ceilings. No platform-default amounts.

### `purchase_approval_delegations`

`from_user_id`, `to_user_id`, `max_amount`, `starts_at`, `ends_at`, `authorized_by`, `revoked_at`.  
**Phase IV / EC-02:** depth-1 only; `from_user_id ≠ to_user_id`; no overlapping active identical pairs; ceiling ≤ delegator direct authority.

### `purchase_approval_sla_policies`

`priority`, `level_order`, `target_hours` — tenant-configurable; empty until configured (**129**).

---

## 6. Purchase requests

### `purchase_requests` (tenant-owned)

| Column | Notes |
|---|---|
| `pr_number` | `PR-######` immutable |
| `version_no` | INT |
| `status` | draft/submitted/under_review/returned/approved/rejected/cancelled |
| `request_kind` | seasonal \| administrative |
| `season_id` | Required iff seasonal |
| `title` / `purpose` | |
| `priority` + `urgency_justification` | |
| `requested_by` | users |
| `beneficiary_organization_unit_id` | org units |
| `optional_supplier_id` | Nullable FK suppliers — **not required** |
| `currency` | CHAR(3) |
| `estimated_total_priced` | DECIMAL |
| `has_unpriced_lines` | BOOL |
| `matrix_id` / `matrix_version_no` | Snapshot refs — **NULL in Phase III** (**III-D1**); filled by Phase IV |
| `duplicate_warning_acknowledged_at` | Nullable |
| `procurement_engaged_at` | TIMESTAMP NULL — **D3** future S05 lock; unset in S04 |
| `procurement_engagement_ref` | VARCHAR(64) NULL — opaque future RFQ/PO ref; no S05 FK |
| `cancelled_at` / `cancelled_by` / `cancellation_reason` | Set on cancel |
| timestamps | |

### `purchase_request_lines`

`line_no`, specs, `quantity`, `unit`, `need_date`, `inventory_item_id` nullable, `unit_estimate` nullable, `unpriced_reason` nullable, optional document links via Documents morph.

### `purchase_request_versions` + `purchase_request_version_lines` — Owner **III-D7=A**

**Binding:** structured relational snapshots (not opaque JSON). On each submit/resubmit, insert immutable version header + version lines copying business fields from working PR/lines. Unique `(tenant_id, purchase_request_id, version_no)`. Historical rows are immutable.

### `purchase_request_financial_reviews` — Owner **D2** + **III-D2** / **III-D3**

`purchase_request_id`, `version_no`, `reviewer_id`, `outcome`, `justification`, `reviewed_at`.

**Phase III:** reviews may be recorded while PR is `draft` or `submitted`; `reviewer_id` may equal `requested_by` if permitted (**III-D3**). Does not mutate status.

**Phase IV gate (D2):** **Every** PR version must have a latest financial review with outcome `cleared` or `cleared_with_conditions` before **final** approval — including fully unpriced PRs. Outcome `not_cleared` blocks final approval.
Does not post accounting, reserve budget, or authorize payment.

**SoD gate (D5 — Phase IV):** `reviewer_id` of the current version’s financial review **must not equal** the user completing final approval (including when acting via delegation).  
**Owner IV-D10=C (binding for Phase IV):** automatically **exclude** the financial reviewer from the eligible final-approver **pool**; another eligible actor may approve without manual reassignment; if no eligible final approver remains → **block** final approval and notify an authorized approval administrator — never grant new authority or auto-approve.  
*(ADR-0023 §2.8 “reassign” wording is reconciled by pool exclusion + escalation; see [PHASE_IV_APPROVAL_ENGINE_DESIGN.md](PHASE_IV_APPROVAL_ENGINE_DESIGN.md) §0. Prior Eng columns `reassigned_from_user_id` are not required under IV-D10; prefer `conflict_code` / escalation timestamps on the step.)*

### `purchase_request_approval_instances` / `_steps` — **Phase IV**

Instance per PR version; steps with `level_order`, `status` (pending/approved/rejected/returned/skipped_conflict_reassigned), `assignee_user_id`, `acted_by`, `acted_at`, `comment`, `due_at`, `escalated_at`.  
**Phase III:** do not create these tables’ rows; leave `purchase_requests.matrix_id` / `matrix_version_no` null (**III-D1**).

### `purchase_demand_groups` + `purchase_demand_group_items` + `purchase_demand_group_active_memberships` — **Phase V**

Planning-only grouping of **approved** PR **headers** (**123**, **V-D1=A**). No RFQ entity.

| Table | Role |
|---|---|
| `purchase_demand_groups` | Tenant-owned header: `group_number`, optional `season_id` / `beneficiary_organization_unit_id` (**V-D2=B**), status `draft\|active\|frozen\|closed` (**V-D3=A**), freeze metadata |
| `purchase_demand_group_items` | Membership **history** (link/unlink timestamps, `pr_version_no_at_link`, unlink reasons incl. `cancel_approved`) |
| `purchase_demand_group_active_memberships` | MariaDB 10.4–safe **UNIQUE(tenant_id, purchase_request_id)** active index (EC-V01) |

Binding design: [PHASE_V_DETAILED_DESIGN.md](PHASE_V_DETAILED_DESIGN.md) §2. Coding unauthorized until Owner design acceptance + coding auth.

---

## 7. Documents morph aliases (ADR-0010 extension — implementation time)

Add aliases: `supplier`, `purchase_request`, `supplier_evaluation` (names **ENGINEERING PROPOSAL**).
No change to Document immutability.

---

## 8. Explicitly absent (S04)

`rfqs`, `supplier_offers`, `purchase_orders`, `goods_receipts`, `budget_reservations`, `payment_*`, GPS, finance ledgers.

### Contracts bridge

`contracts.supplier_id` nullable — **DEFERRED** to separate engineering CR; keep `counterparty_name`.

---

## 9. Concurrency

- Number sequences: `SELECT … FOR UPDATE` inside transaction.
- Approval step action: row lock on step + verify still `pending` (idempotent replay by action key **ENGINEERING PROPOSAL**).
- Bank primary flag: transactional demote/promote + generated unique (II-B); MySQL concurrency required.
- Bank IBAN: claims table unique `(tenant_id, iban_fingerprint)`; version fingerprints non-unique; create/supersede/**PATCH**/reject/revoke claim acquire/**DELETE**-release under supplier→claims→family lock order (design §2.4; **M1**/**M2**).
