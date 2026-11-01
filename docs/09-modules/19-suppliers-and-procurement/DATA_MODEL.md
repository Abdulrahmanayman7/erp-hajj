# Suppliers & Procurement (S04) — Data Model

> **Status:** Phase I + Phase II-A tables **IMPLEMENTED — OWNER ACCEPTED** (II-A 2026-11-01); Phase II-B/II-C / Phase III–V schema **NOT IMPLEMENTED**
> **Last updated:** 2026-11-01
> Functional binding: ADR-0022 · Engineering: ADR-0023 · Owner G1 decisions **D1–D4**
> All tenant business tables: `TenantOwned` + `UsesTenantScope` unless marked **platform reference**.

Classification labels follow DATABASE_PRINCIPLES / tenancy docs.

---

## 1. Proposed migration order (conceptual — do not create files yet)

1. Platform minimal geo catalog (if adopted as platform)
2. Sequences: `supplier_number_sequences`, `purchase_request_number_sequences`, `supplier_evaluation_number_sequences`
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

### `supplier_bank_accounts`

| Column | Notes |
|---|---|
| `tenant_id`, `supplier_id` | |
| `label` | |
| `bank_name` | |
| `iban_ciphertext` / `account_ciphertext` | App-encrypted (**ENGINEERING PROPOSAL**) |
| `iban_last4` / `account_last4` | For UI mask |
| `currency` | CHAR(3) default SAR |
| `is_primary` | Partial unique: one primary per supplier |
| `verification_status` | `pending` \| `verified` \| `rejected` |
| `verified_by` / `verified_at` | |
| timestamps | |

**Never** put full IBAN in audit `old`/`new` values — store last4 + status only.

### Document eligibility

> **II-A (2026-10-31):** **DESIGN APPROVED**; **ENGINEERING PRE-IMPLEMENTATION GATE** — see [PHASE_II_A_ELIGIBILITY_DESIGN.md](PHASE_II_A_ELIGIBILITY_DESIGN.md), [API.md §12](API.md), [S04-PHASE-II-A-ENGINEERING-GATE-2026-10-31.md](../../10-decisions/S04-PHASE-II-A-ENGINEERING-GATE-2026-10-31.md). **Not implemented** — do not author migrations until coding authorization.

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

## 4. Evaluations

### `supplier_evaluation_criteria` (tenant-owned, per category)

`supplier_category_id`, `name`, `weight` DECIMAL, `is_active`. Sum of active weights for category = 100 (**ENGINEERING PROPOSAL** enforce on save).

### `supplier_evaluations`

| Column | Notes |
|---|---|
| `evaluation_number` | `SEV-######` |
| `supplier_id`, optional `supplier_category_id` | |
| `evaluation_kind` | qualification / periodic / operational / post_season |
| `season_id` | Nullable |
| `status` | draft / pending_approval / approved / rejected |
| `total_score` | 0–100 |
| `monitoring_recommended` | BOOL — **115** descriptor |
| `prepared_by` / `approved_by` / timestamps | SoD: prepared_by ≠ approved_by |
| `version_no` | INT ≥ 1 |
| `supersedes_evaluation_id` | Nullable |

### `supplier_evaluation_scores`

Per criterion score + note.

### `supplier_evaluation_appeals` (optional thin table)

Links evaluation, reason, reviewer, outcome — **116**.

---

## 5. Approval configuration

### `purchase_approval_matrices` (tenant-owned)

`name`, `is_active`, `version_no`, `effective_from`, `approved_at`, `approved_by`.

### `purchase_approval_matrix_rules`

Match predicates: `min_amount`, `max_amount` (nullable open end), `request_kind` nullable, `organization_unit_id` nullable, `season_required` bool nullable.
**Amounts are tenant-configured — no platform defaults mandated.**

### `purchase_approval_matrix_levels`

`matrix_id`, `level_order`, `name`.
**ENGINEERING PROPOSAL (binding preference):** resolve eligibility via `purchase_requests.approve` + `purchase_approver_authority` ceiling + matrix level order — **no role-name checks in Policies** (aligns with PERMISSION_MODEL).

### `purchase_approver_authority` (tenant-owned)

`user_id`, `max_amount`, `currency`, `effective_from`/`to` — enforces **125** ceilings.

### `purchase_approval_delegations`

`from_user_id`, `to_user_id`, `max_amount`, `starts_at`, `ends_at`, `authorized_by`, `revoked_at`.

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
| `matrix_id` / `matrix_version_no` | Snapshot refs |
| `duplicate_warning_acknowledged_at` | Nullable |
| `procurement_engaged_at` | TIMESTAMP NULL — **D3** future S05 lock; unset in S04 |
| `procurement_engagement_ref` | VARCHAR(64) NULL — opaque future RFQ/PO ref; no S05 FK |
| `cancelled_at` / `cancelled_by` / `cancellation_reason` | Set on cancel |
| timestamps | |

### `purchase_request_lines`

`line_no`, specs, `quantity`, `unit`, `need_date`, `inventory_item_id` nullable, `unit_estimate` nullable, `unpriced_reason` nullable, optional document links via Documents morph.

### `purchase_request_versions` (**ENGINEERING PROPOSAL**)

Snapshot JSON or mirrored header+lines at each resubmit for auditability. Prefer **structured snapshot table** over opaque JSON for queryability.

### `purchase_request_financial_reviews` — Owner **D2**

`purchase_request_id`, `version_no`, `reviewer_id`, `outcome`, `justification`, `reviewed_at`.

**Gate (mandatory — D2):** **Every** PR version must have a financial review with outcome `cleared` or `cleared_with_conditions` before **final** approval completes — including fully unpriced PRs. Outcome `not_cleared` blocks final approval.
Does not post accounting, reserve budget, or authorize payment.

**SoD gate (D5):** `reviewer_id` of the current version’s financial review **must not equal** the user completing final approval (including when acting via delegation). On conflict: reassign final step or block + escalate; persist reassignment/escalation audit fields on the approval step (**ENGINEERING PROPOSAL**: `conflict_reason`, `reassigned_from_user_id`, `escalated_to_admin_at`).

### `purchase_request_approval_instances` / `_steps`

Instance per PR version; steps with `level_order`, `status` (pending/approved/rejected/returned/skipped_conflict_reassigned), `assignee_user_id`, `acted_by`, `acted_at`, `comment`, `due_at`, `escalated_at`.

### `purchase_demand_groups` + `purchase_demand_group_items`

Planning-only grouping of **approved** PRs (**123**). No RFQ entity.

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
- Bank primary flag: transactional demote/promote.
