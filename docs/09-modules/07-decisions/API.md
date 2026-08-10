# Decisions — API

> **Status:** Specified (Sprint 011) — **not implemented**  
> **Last updated:** 2026-08-10  
> Base: `/api/v1` · Auth: Sanctum SPA · Envelope: [API_STANDARDS.md](../../04-api/API_STANDARDS.md)

All routes: authenticated + tenant-active middleware. Authorization via `DecisionPolicy` (capabilities only). Cross-tenant → **404**.

---

## Endpoints

| Method | Path | Permission | Notes |
|---|---|---|---|
| GET | `/decisions` | `decisions.view` | Paginated list |
| POST | `/decisions` | `decisions.create` | Standalone **or** from recommendation |
| GET | `/decisions/{decision}` | `decisions.view` | Details + transitions |
| PATCH | `/decisions/{decision}` | `decisions.update` | Draft content only |
| DELETE | `/decisions/{decision}` | `decisions.delete` | Untouched draft only |
| POST | `/decisions/{decision}/submit` | `decisions.update` | → `pending_approval` |
| POST | `/decisions/{decision}/return-draft` | `decisions.approve` | → `draft` (comment required) |
| POST | `/decisions/{decision}/approve` | `decisions.approve` | → `approved` |
| POST | `/decisions/{decision}/cancel` | `decisions.update` | → `cancelled` (draft \| pending_approval) |
| POST | `/decisions/{decision}/close` | `decisions.close` | → `closed` (from approved) |

**No** generic `PATCH …/status`.  
**No** `/activate`, `/reject` (return-draft covers revision).  
**No** nested Meetings conversion route required — conversion is `POST /decisions` with `source_recommendation_id`.

---

## Create — `POST /decisions`

### Standalone body

```json
{
  "title": "تعميم ساعات الدوام في الموسم",
  "body": "…",
  "notes": null,
  "organization_unit_id": 12,
  "issued_by_employee_id": 5,
  "responsible_employee_id": 8,
  "effective_date": "2026-08-15",
  "due_date": "2026-09-01"
}
```

### From recommendation

```json
{
  "source_recommendation_id": 44,
  "title": "optional override",
  "body": "optional override",
  "notes": null,
  "organization_unit_id": null,
  "issued_by_employee_id": null,
  "responsible_employee_id": null,
  "effective_date": null,
  "due_date": null
}
```

**Rules:**

- Omit `source_recommendation_id` → standalone.
- When set: validate final recommendation, completed parent meeting, same tenant, uniqueness → else stable errors.
- Prefill missing title/body/responsible/org from recommendation/meeting when not overridden.
- Initial status: **`draft`**.
- Assign `decision_number` via sequence.
- Audit: `DECISION_CREATED` and, when sourced, also `DECISION_CREATED_FROM_RECOMMENDATION` **or** a single `DECISION_CREATED` with `source_recommendation_id` in metadata — prefer **one** event `DECISION_CREATED` with metadata flag `from_recommendation: true` to avoid duplicate semantics. Lock: **`DECISION_CREATED`** only; metadata includes `source_recommendation_id` when present.

### Response

`201` + Decision resource (draft).

### Duplicate conversion

If recommendation already has a Decision: **`422`** with code `DECISION_ALREADY_CREATED_FROM_RECOMMENDATION`. Do not silently return the existing Decision as a successful create (clients must GET by filter). Optional future: idempotent GET helper — **not** required in MVP; duplicate create fails loudly.

---

## Update — `PATCH /decisions/{decision}`

Allowed only when `status = draft`. Same content fields as create (except `source_recommendation_id` never patchable).

Rejected fields / locked non-draft → `DECISION_IMMUTABLE` / validation 422.

---

## Lifecycle bodies

| Action | Body |
|---|---|
| submit | `{}` or empty |
| approve | optional `{ "comment": "…" }` |
| return-draft | **required** `{ "comment": "…" }` |
| cancel | optional `{ "comment": "…" }` |
| close | optional `{ "comment": "…" }` |

Each success: update status, append transition row, audit event, return Decision resource.

Invalid transition → `422` `DECISION_INVALID_STATUS_TRANSITION`.

---

## List — `GET /decisions`

### Query filters

| Param | Type | Notes |
|---|---|---|
| `search` | string | `decision_number`, `title` |
| `status` | string\|array | |
| `organization_unit_id` | id | |
| `responsible_employee_id` | id | |
| `source_recommendation_id` | id | |
| `has_source_recommendation` | bool | optional convenience |
| `effective_date_from` / `effective_date_to` | date | |
| `due_date_from` / `due_date_to` | date | |
| `page` / `per_page` | int | Standard pagination |

Lock list filter: **`meeting_id`** — decisions linked via recommendation to that meeting (join; same tenant).

### Sorting

Default: `created_at` **desc** (newest first).  
Secondary stable: `id` desc.  
Allow `sort=decision_number` desc as alternate if needed — default remains newest first.

---

## Details — `GET /decisions/{decision}`

Resource includes:

- All Decision fields
- Compact: `organization_unit` `{id,name}`
- Compact: `issued_by_employee`, `responsible_employee` `{id,name,…}`
- Compact: `created_by` user summary
- Compact: `source_recommendation` `{id,title,status,meeting_id}` when set
- Compact derived: `source_meeting` `{id,meeting_number,title,status}` when recommendation present
- `status_transitions` timeline (details only; not on list)

**Do not** include tasks, task counts, or fake progress.

---

## Error codes

| Code | When |
|---|---|
| `DECISION_NOT_FOUND` | Missing / cross-tenant (prefer generic 404 envelope) |
| `DECISION_NUMBER_TAKEN` | Should not occur if sequence correct — defensive |
| `DECISION_INVALID_STATUS_TRANSITION` | Illegal action for current status |
| `DECISION_RECOMMENDATION_INVALID` | Missing / not final / meeting not completed / wrong tenant |
| `DECISION_ALREADY_CREATED_FROM_RECOMMENDATION` | Unique conversion violated |
| `DECISION_EMPLOYEE_INVALID` | Issuer/responsible foreign or inactive on assign |
| `DECISION_ORGANIZATION_INVALID` | Org foreign or inactive on assign |
| `DECISION_INVALID_DATE_RANGE` | `due_date < effective_date` |
| `DECISION_IMMUTABLE` | Edit / delete / wrong field on locked Decision |
| `DECISION_CLOSE_NOT_ALLOWED` | Reserved for future Tasks gate; unused in Sprint 011 close path except documentation |

Use standard validation envelope for field errors (`422`). Unauthorized → `403`. Unauthenticated → `401`.

---

## Audit events

| Event | When |
|---|---|
| `DECISION_CREATED` | Create (metadata notes recommendation id if any) |
| `DECISION_UPDATED` | Draft PATCH |
| `DECISION_SUBMITTED` | submit |
| `DECISION_RETURNED_TO_DRAFT` | return-draft |
| `DECISION_APPROVED` | approve |
| `DECISION_CANCELLED` | cancel |
| `DECISION_CLOSED` | close |
| `DECISION_DELETED` | hard delete untouched draft |

No `DECISION_ACTIVATED` / `DECISION_REJECTED` (those statuses/actions are not in MVP).

---

## Resource shape (illustrative)

```json
{
  "id": 1,
  "decision_number": "DEC-000001",
  "title": "…",
  "body": "…",
  "notes": null,
  "status": "draft",
  "source_recommendation_id": 44,
  "organization_unit_id": 12,
  "issued_by_employee_id": 5,
  "responsible_employee_id": 8,
  "effective_date": "2026-08-15",
  "due_date": "2026-09-01",
  "created_by": { "id": 3, "name": "…" },
  "organization_unit": { "id": 12, "name": "…" },
  "issued_by_employee": { "id": 5, "name": "…" },
  "responsible_employee": { "id": 8, "name": "…" },
  "source_recommendation": { "id": 44, "title": "…", "status": "final", "meeting_id": 9 },
  "source_meeting": { "id": 9, "meeting_number": "MTG-000012", "title": "…", "status": "completed" },
  "status_transitions": [],
  "created_at": "…",
  "updated_at": "…"
}
```
