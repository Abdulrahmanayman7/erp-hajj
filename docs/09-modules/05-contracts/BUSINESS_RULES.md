# Contracts — Business Rules

> **Status:** Implemented (Sprint 009)
> **Last updated:** 2026-08-09

Binding workflow: [CORE_WORKFLOWS.md](../../01-business/CORE_WORKFLOWS.md) Workflow 2.

---

## 1. Identity and numbering

- Every contract belongs to exactly one tenant (`tenant_id` from `TenantContext` only — never from payload).
- **`contract_number`** is server-generated, tenant-unique, **immutable** after create.
- Format: `CTR-` + zero-padded sequence (`CTR-000001`, `CTR-000002`, …).
- Allocation: dedicated `contract_number_sequences` row per tenant with `SELECT … FOR UPDATE` inside a transaction (same strategy as employees). **Forbidden:** unprotected `MAX(contract_number)+1`.
- Client must never supply `contract_number` or `tenant_id`.

---

## 2. Categories (المسمى / التصنيف)

- Contracts require a **tenant-owned category** (`contract_category_id`).
- Categories are a minimal catalog (`name`, optional `code`, `is_active`) — **not** a taxonomy engine (ADR-0006).
- **Lifecycle:** `active` / `inactive` only (`is_active` boolean). No versioning, hierarchy, or soft-delete on categories.
- Seeded examples (Arabic display names; stable English `code` where useful):

  | code (optional) | name (AR example) |
  |---|---|
  | `employee` | عقد موظف |
  | `supervisor` | عقد مشرف |
  | `hotel` | عقد إسكان / فندق |
  | `catering` | عقد إعاشة |
  | `transportation` | عقد نقل |
  | `supplier` | عقد توريد |
  | `consultant` | عقد استشاري |

### Category assignment rules

- **New contracts** and **draft category changes** may select **active** categories only.
- Existing contracts **retain** references to inactive categories; deactivation does **not** rewrite `contract_category_id` on any contract.
- Draft PATCH may leave an already-linked inactive category unchanged; switching to a different inactive category is rejected (`CONTRACT_CATEGORY_INVALID`).
- Tenant isolation: categories are `TenantOwned`; cross-tenant ids fail like nonexistent.
- Uniqueness: tenant-scoped unique `name`; tenant-scoped unique `code` when set; `code` immutable after create.

### Category deletion rule (MVP final)

- **Hard delete allowed only when unused** (zero contracts reference the category).
- **While referenced:** hard delete forbidden (`CONTRACT_CATEGORY_IN_USE`); operator must **deactivate** instead.
- Deactivate = set `is_active = false`; does not cascade or modify contracts.

---

## 3. Parties

- **First party** = the tenant organization (implicit; not stored as a free-text first-party field).
- **Counterparty** (الطرف الآخر):
  - `counterparty_name` — required display name (person or organization string).
  - `counterparty_kind` — enum: `person` | `organization` | `other`.
  - `employee_id` — optional FK when the agreement relates to a known Employee (e.g. employment/service agreement).
- Rules for `employee_id` when set:
  - Same tenant only; foreign id → validation fail / no existence leak across tenants.
  - Employee may have **multiple** contracts over time.
  - Inactive employee: **allowed** on historical records; **new** link on create/update requires **active** employee (same pattern as supervisor assign).
  - Employee deactivate/delete-of-account does **not** delete or cascade-clear contracts (`ON DELETE SET NULL` or RESTRICT — prefer **RESTRICT** while employee exists; if User unlink is separate, employee hard-delete is not in MVP).
- **No supplier/vendor table** in Sprint 009. Future vendor FK may be added nullable later; `counterparty_name` remains the stable display fallback.

---

## 4. Organization unit

- `organization_unit_id` optional — responsible / owning unit for operational ownership.
- Same tenant; foreign unit blocked.
- **New** assignment requires **active** unit.
- Historical reference to inactive unit may remain; changing to another inactive unit is rejected.
- Org unit hard-delete blocked while contracts reference it (`ON DELETE RESTRICT` + app check).

---

## 5. Dates

- `start_date` required (DATE, tenant calendar date — no time component).
- `end_date` nullable (open-ended contracts allowed).
- When both set: `end_date >= start_date`.
- Timezone for “today” in expiry jobs: tenant `timezone` setting (default `Asia/Riyadh` for رفيع).

---

## 6. Value and currency

- `value` nullable `DECIMAL(15,2)` — **informational only** (no ledger, no payments).
- `currency` `CHAR(3)` ISO 4217; default **`SAR`** from config; stored for future-proofing; MVP UI may lock to SAR.
- Never use floating-point types.

---

## 7. Status lifecycle (persisted)

Statuses:

| Status | Arabic | Meaning |
|---|---|---|
| `draft` | مسودة | Editable working copy |
| `in_review` | قيد المراجعة | Submitted for review |
| `approved` | معتمد | Formally approved |
| `signed` | موقّع | **Manual attestation** that signing occurred outside the platform (see §7.1) |
| `executing` | قيد التنفيذ | In force / being performed |
| `closed` | مغلق | Closed successfully (terminal) |
| `renewed` | مجدّد | Superseded by exactly one successor contract (terminal) |
| `expired` | منتهي | End date passed while executing (terminal) |
| `cancelled` | ملغى | Abandoned before execution (terminal) |

### Allowed transitions

```text
draft        → in_review | cancelled
in_review    → approved | draft (return) | cancelled
approved     → signed | cancelled
signed       → executing
executing    → closed | renewed | expired
```

Precise cancel rule (MVP):

- `cancelled` reachable from: `draft`, `in_review`, `approved` only (not from `signed`/`executing`).
- Once `signed` or later: use `close`, `renew`, or automatic `expired` — not cancel.

Return rule:

- `in_review` → `draft` allowed with **required** comment via `POST …/return-draft` (`contracts.review`).

### Payload status

- Clients **must not** set `status` via PATCH. Transitions only through action endpoints.

### 7.1 Sign = manual attestation only

`POST …/sign` (`approved` → `signed`) means an authorized user **records that the contract was signed outside the platform**.

It is **not**:

- digital / cryptographic signature
- e-signature provider integration
- embedded PDF signing

Who attested and when is retained in **`contract_status_transitions`** (actor + timestamp + optional comment) and the `CONTRACT_SIGNED` audit event. Optional comment may note “signed on paper / meeting date …” — not a signature blob.

### 7.2 Transition history (authoritative)

**`contract_status_transitions` is the authoritative lifecycle history** for a contract. There is **no** separate approval-history table — the `→ approved` row is the approval record.

Every successful status change (including create `null → draft`, user actions, renew source transition, and scheduler expiry) **appends one immutable row**. Rows are never updated or deleted via API.

| Metadata | Required | Notes |
|---|---|---|
| `tenant_id` | yes | From `TenantContext` (fail-closed) |
| `contract_id` | yes | Contract reference |
| `from_status` | yes* | Previous status; `NULL` only for initial create → `draft` |
| `to_status` | yes | New status |
| `actor_user_id` | conditional | Authenticated user for user-driven actions; **`NULL` for scheduler expiry** |
| `comment` | conditional | **Required** for return-to-draft and cancel; optional otherwise (recommended on sign) |
| `created_at` | yes | Server timestamp of the transition |
| `correlation_id` | yes when request-scoped | Echo/store the request correlation ID for user-driven actions ([AUDIT_TRAIL.md](../../06-security/AUDIT_TRAIL.md)); scheduler may use a job correlation / leave null with `source=scheduler` in audit context |

UI timeline and API `transitions[]` **read this table only**. Audit events are complementary (security/compliance sink), not a second lifecycle store.

---

## 8. Renewal (safety)

**Decision:** Renewal creates a **new** contract record. A source contract may create **exactly one** direct renewal descendant.

### Eligibility

- Source `status` must be `executing`.
- Source must not already have a successor (`renewed_from_contract_id` pointing at it) and must not already be `renewed`.

### Transaction / locking / idempotency

1. Open a DB transaction.
2. `SELECT … FOR UPDATE` the source contract row.
3. Re-check eligibility inside the lock. If already `renewed` or a successor exists → abort with `CONTRACT_ALREADY_RENEWED` (idempotent fail — **no** second draft).
4. Allocate a **new** `CTR-######` via sequence lock.
5. Insert successor `draft` with `renewed_from_contract_id = source.id`.
6. Append transition on **source**: `executing` → `renewed` (actor = renewing user, comment optional).
7. Append initial transition on **successor**: `null` → `draft`.
8. Commit. If any step fails, **rollback entirely** — source must not become `renewed` without a persisted successor.

### Uniqueness constraint

- **Unique** on `contracts.renewed_from_contract_id` (nullable unique: many `NULL`s allowed; each non-null source id appears at most once). Enforces one direct child at the database level (defense in depth vs double-click / concurrent renew).

### Field copy matrix (successor starts as `draft`)

| Field | Behavior |
|---|---|
| `contract_number` | **New** sequence value — never copied |
| `status` | Always `draft` |
| `created_by` | Renewing actor |
| `renewed_from_contract_id` | Set to source id |
| `title`, `contract_category_id`, `counterparty_*`, `employee_id`, `organization_unit_id`, `value`, `currency`, `notes` | **Copied** as draft defaults (operator may edit before submit) |
| `start_date`, `end_date` | **Not copied** — must be set consciously on the new draft before leaving draft |
| Transition history | **Not copied** — successor starts with only `null → draft` |

Operator completes the successor through the normal lifecycle. Do not auto-activate.

---

## 9. Expiration

**Hybrid (source of truth = persisted status + dates):**

1. While `status = executing` and `end_date` is set and `end_date < today(tenant timezone)` → scheduled command transitions to `expired` (audited as system/actor-null or platform scheduler identity — record `actor_user_id` null + meta `source=scheduler`).
2. List filter `expiring_soon=1` uses: `executing` AND `end_date` between today and today+N days.
3. `N` = `config('contracts.expiring_soon_days')` — **recommended default 30**; product may change without schema change.
4. Open-ended (`end_date` null) never auto-expire.
5. Job must be idempotent (skip if already `expired`).

Notifications: owned by Sprint 016 ([12-notifications/](../12-notifications/), ADR-0013). Types `CONTRACT_EXPIRING_SOON` / `CONTRACT_EXPIRED`; recipients = `created_by` User (+ linked `employee.user_id` when present). Delivery is in-app; Contracts remains SoR for expiry status + scheduler transition.

---

## 10. Documents / attachments

- Sprint 009 did not store files.
- **Sprint 013 owns** document/file infrastructure ([09-documents/](../09-documents/), ADR-0010): private storage, `DOC-######`, optional single polymorphic link `linkable_type=contract`.
- Do **not** seed `contracts.attach_documents` — use `documents.upload` + link.
- At Documents implementation: replace placeholder panel with **المستندات** widget; until then UI may keep “المرفقات — قريباً”.
- CORE_WORKFLOWS “attachments must be preserved” is satisfied by Documents linking (not by deleting blobs when Contracts archive/close).

---

## 11. Delete / archive policy

- Prefer preservation of auditable records.
- **Hard DELETE** allowed only when: `status = draft` AND no transition rows except implicit create (i.e. never left draft). Permission: `contracts.delete`.
- Otherwise: **cancel** (pre-sign) or **close** / auto-expire / renew — no hard delete.
- No Laravel SoftDeletes on `contracts` in Sprint 009 (status is the retention mechanism). Aligns with Employees/Org preference for explicit lifecycle over dual soft-delete semantics.
- Cross-cutting note: “prefer soft deletion” in DATABASE_PRINCIPLES is satisfied by non-destructive terminal statuses for post-draft contracts.

---

## 12. Update rules

- Editable via PATCH primarily in `draft` (and limited fields in `in_review` — **decision:** full profile edit only in `draft`; `in_review` and beyond: PATCH forbidden except notes? **MVP: PATCH only while `draft`.** After submit, corrections require return-to-draft).
- Immutable always: `tenant_id`, `contract_number`, `created_by`, `renewed_from_contract_id` (once set).

---

## 13. Created by

- `created_by` = authenticated user id at create; immutable; must be same-tenant user.

---

## 14. Audit events (required)

| Event | When |
|---|---|
| `CONTRACT_CREATED` | Create |
| `CONTRACT_UPDATED` | Draft PATCH |
| `CONTRACT_DELETED` | Draft hard delete |
| `CONTRACT_SUBMITTED_REVIEW` | → in_review |
| `CONTRACT_RETURNED_DRAFT` | → draft (return) |
| `CONTRACT_APPROVED` | → approved |
| `CONTRACT_SIGNED` | → signed (**manual attestation** — not e-sign) |
| `CONTRACT_EXECUTED` | → executing |
| `CONTRACT_CLOSED` | → closed |
| `CONTRACT_CANCELLED` | → cancelled |
| `CONTRACT_RENEWED` | source → renewed (+ successor create may also emit CREATED) |
| `CONTRACT_EXPIRED` | scheduler → expired |
| `CONTRACT_CATEGORY_*` | category create/update/delete as needed at implementation |

Include tenant, actor (nullable for scheduler), target ids, safe before/after, correlation ID. No secrets.

## Remaining TBD (non-blocking if defaults above used)

| Item | Recommendation | Blocking? |
|---|---|---|
| Exact default role → permission matrix beyond Owner/GM/Auditor | See PERMISSIONS.md suggested grants | No |
| Expiring-soon days | Config default 30 | No (configurable) |
| Cancel after `approved` | Allowed per §7 | Locked |
| Return after `approved` | Not in MVP | Locked out |
| E-signature | Out of scope | N/A |
| Vendor FK | Future additive column | N/A |
