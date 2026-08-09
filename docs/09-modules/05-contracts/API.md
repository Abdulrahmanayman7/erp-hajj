# Contracts — API

> **Status:** Implemented (Sprint 009) — endpoints live under `/api/v1`
> **Last updated:** 2026-08-09

Envelope: [API_STANDARDS.md](../../04-api/API_STANDARDS.md). Auth + tenant middleware as existing modules.

---

## Contract resource (`ContractResource`)

```json
{
  "id": 1,
  "contract_number": "CTR-000001",
  "title": "…",
  "status": "draft",
  "counterparty_name": "…",
  "counterparty_kind": "organization",
  "start_date": "2026-01-01",
  "end_date": "2026-12-31",
  "value": "150000.00",
  "currency": "SAR",
  "notes": null,
  "is_expiring_soon": false,
  "category": { "id": 1, "name": "عقد توريد", "code": "supplier" },
  "employee": { "id": 5, "employee_number": "EMP-000001", "full_name": "…" },
  "organization_unit": { "id": 10, "name": "…", "code": "OPS" },
  "created_by": { "id": 2, "name": "…" },
  "renewed_from_contract_id": null,
  "created_at": "…",
  "updated_at": "…"
}
```

- `employee`, `organization_unit`, `end_date`, `value`, `notes`, `renewed_from_contract_id` may be null.
- `is_expiring_soon`: computed for `executing` within configured window (not a DB column).
- Do **not** embed full transition history on list; include on show (or separate endpoint).

### Transition summary (show)

```json
"transitions": [
  {
    "id": 1,
    "from_status": null,
    "to_status": "draft",
    "comment": null,
    "actor": { "id": 2, "name": "…" },
    "correlation_id": "…",
    "created_at": "…"
  }
]
```

- Sourced exclusively from `contract_status_transitions` (authoritative history).
- `actor` may be `null` for scheduler-driven expiry rows.
- Sign rows represent **manual attestation** only (who recorded / when / optional comment) — no signature file fields.

---

## Contracts endpoints

### List

| | |
|---|---|
| **Route** | `GET /api/v1/contracts` |
| **Permission** | `contracts.view` |
| **Query** | `search` (number, title, counterparty_name) · `status` · `category_id` · `employee_id` · `organization_unit_id` · `expiring_soon=1` · `start_date_from` / `start_date_to` · `end_date_from` / `end_date_to` · `sort` · `direction` · `page` · `per_page` |

**Default sort:** `created_at` descending (newest first), stable secondary `id` desc.

Eager-load: `category`, `employee`, `organizationUnit`, `creator` (as needed).

### Show

`GET /api/v1/contracts/{contract}` — `contracts.view` — includes `transitions` — cross-tenant **404**.

### Create

`POST /api/v1/contracts` — `contracts.create`

**Body:**

- `title` (required)
- `contract_category_id` (required)
- `counterparty_name` (required)
- `counterparty_kind` (`person`\|`organization`\|`other`, default `organization`)
- `employee_id` (nullable)
- `organization_unit_id` (nullable)
- `start_date` (required)
- `end_date` (nullable)
- `value` (nullable)
- `currency` (optional; default config SAR)
- `notes` (nullable)

**Never accept:** `tenant_id`, `contract_number`, `status`, `created_by`, `renewed_from_contract_id`.

Creates `draft` + initial transition row. **Audit:** `CONTRACT_CREATED`.

### Update

`PATCH /api/v1/contracts/{contract}` — `contracts.update`

**Only while `status = draft`.** Same editable fields as create (except immutables).

**Audit:** `CONTRACT_UPDATED`.

### Delete

`DELETE /api/v1/contracts/{contract}` — `contracts.delete`

Only `draft` with no non-create transitions (never left draft). Otherwise `422 CONTRACT_DELETE_FORBIDDEN`.

**Audit:** `CONTRACT_DELETED`.

---

## Lifecycle action endpoints

All: `POST`, optional body `{ "comment": "…" }`, policy + status guard, append **immutable** `contract_status_transitions` row (tenant, from/to, actor, comment, correlation ID, timestamp), audit.

| Route | Permission | From → To | Comment |
|---|---|---|---|
| `/contracts/{contract}/submit-review` | `contracts.review` | `draft` → `in_review` | optional |
| `/contracts/{contract}/return-draft` | `contracts.review` | `in_review` → `draft` | **required** |
| `/contracts/{contract}/approve` | `contracts.approve` | `in_review` → `approved` | optional |
| `/contracts/{contract}/sign` | `contracts.sign` | `approved` → `signed` | optional (manual attestation — **not** e-sign) |
| `/contracts/{contract}/execute` | `contracts.execute` | `signed` → `executing` | optional |
| `/contracts/{contract}/close` | `contracts.close` | `executing` → `closed` | optional |
| `/contracts/{contract}/cancel` | `contracts.cancel` | `draft`\|`in_review`\|`approved` → `cancelled` | **required** |
| `/contracts/{contract}/renew` | `contracts.renew` | see below | optional on source |

**Sign semantics:** records that signing occurred **outside** the platform. Retains attestor (`actor`) + time via transition/audit. No cryptographic payload.

**Idempotency (generic transitions):** repeating an action when already past that edge → `422 CONTRACT_INVALID_STATUS_TRANSITION` (not silent success).

### Renew (transactional, one-child)

`POST /api/v1/contracts/{contract}/renew` — `contracts.renew`

- Source must be `executing` and must not already have a renewal child / not already `renewed`.
- Single DB transaction + `SELECT … FOR UPDATE` on source (BUSINESS_RULES §8).
- Creates successor `draft` (new `CTR-######`, `renewed_from_contract_id` set; field copy matrix per BUSINESS_RULES); transitions source → `renewed`.
- Second renew / concurrent duplicate → `422 CONTRACT_ALREADY_RENEWED` (unique on `renewed_from_contract_id`).
- Failure mid-flight rolls back — source stays `executing`.

**Response:** `{ "source": ContractResource, "successor": ContractResource }`.

Expiry is **not** a public user endpoint; scheduler only. Still appends transition + `CONTRACT_EXPIRED` audit.

---

## Categories endpoints

```text
GET    /api/v1/contract-categories              # contracts.view
POST   /api/v1/contract-categories              # contracts.update  (manage catalog)
GET    /api/v1/contract-categories/{category}   # contracts.view
PATCH  /api/v1/contract-categories/{category}   # contracts.update (name; not code after create)
POST   /api/v1/contract-categories/{id}/activate    # contracts.update → is_active=true
POST   /api/v1/contract-categories/{id}/deactivate  # contracts.update → is_active=false (no contract rewrite)
DELETE /api/v1/contract-categories/{category}   # contracts.update — unused only; else CONTRACT_CATEGORY_IN_USE
```

**Decision:** no separate `contract_categories.*` permissions in Sprint 009 — catalog management uses `contracts.update` (view via `contracts.view`).

List: `search`, `is_active`, pagination; default sort `name`. Selectors for **new** assignment: `is_active=true` only.

---

## Stable error codes

| Code | HTTP | Meaning |
|---|---|---|
| `CONTRACT_NOT_FOUND` | 404 | Prefer generic route 404 |
| `CONTRACT_NUMBER_TAKEN` | 422 | Rare sequence collision |
| `CONTRACT_INVALID_DATE_RANGE` | 422 | `end_date < start_date` |
| `CONTRACT_INVALID_STATUS_TRANSITION` | 422 | Wrong current status / illegal edge |
| `CONTRACT_COMMENT_REQUIRED` | 422 | Return/cancel without comment |
| `CONTRACT_EMPLOYEE_INVALID` | 422 | Missing / wrong tenant / inactive on assign |
| `CONTRACT_ORGANIZATION_INVALID` | 422 | Missing / wrong tenant / inactive on assign |
| `CONTRACT_CATEGORY_INVALID` | 422 | Missing / inactive on assign (or switch to inactive) |
| `CONTRACT_NOT_EDITABLE` | 422 | PATCH outside draft |
| `CONTRACT_DELETE_FORBIDDEN` | 422 | Hard delete not allowed |
| `CONTRACT_ALREADY_RENEWED` | 422 | Source already has a renewal child / is `renewed` |
| `CONTRACT_CATEGORY_IN_USE` | 422 | Hard delete blocked — deactivate instead |
| `CONTRACT_CATEGORY_CODE_TAKEN` / `CONTRACT_CATEGORY_NAME_TAKEN` | 422 | |
| `AUTHORIZATION_DENIED` | 403 | |

Cross-tenant: **404**, no existence leak.

---

## Explicitly not in Sprint 009

- Attachment upload/download endpoints
- Supplier endpoints
- Payment / invoice endpoints
- Public expire/force-status endpoints
- Generic `PATCH { status }`
- E-signature / cryptographic signing APIs