# Audit Trail — API

> **Status:** Implemented (Sprint 018)
> **Last updated:** 2026-08-12

All endpoints follow [API_STANDARDS.md](../../04-api/API_STANDARDS.md). **Read-only** — no create/update/delete.

## Endpoints

```text
GET /api/v1/audit-logs              # audit_logs.view
GET /api/v1/audit-logs/{auditLog}   # audit_logs.view
```

**Not in Sprint 018:**

```text
GET /api/v1/audit-logs/export       # deferred (audit_logs.export reserved)
```

Route model binding resolves through tenant-scoped query → foreign/missing id → **404**.

## List — `GET /api/v1/audit-logs`

### Query parameters

| Param | Type | Notes |
|---|---|---|
| `search` | string | Optional; matches `event_type`, `entity_number`, `entity_label`, `actor_label` (prefix/LIKE on indexed/string columns — **not** JSON) |
| `event_type` | string | Exact code |
| `actor_user_id` | int | |
| `entity_type` | string | Alias |
| `entity_id` | int | Requires meaningful with `entity_type` |
| `date_from` | date/datetime | Inclusive lower bound on `created_at` (interpret in tenant TZ → UTC) |
| `date_to` | date/datetime | Inclusive upper bound |
| `correlation_id` | string | Exact |
| `page` | int | |
| `per_page` | int | Default **20**, max **100** |

No arbitrary metadata JSON filters in MVP.

### Sort

Default: `created_at DESC`, then `id DESC`.

### List item shape (`data[]`)

```json
{
  "id": 101,
  "event_type": "TASK_ASSIGNED",
  "context_type": "tenant",
  "actor_type": "user",
  "actor_user_id": 5,
  "actor_label": "أحمد علي",
  "entity_type": "task",
  "entity_id": 44,
  "entity_number": "TSK-000044",
  "entity_label": "تجهيز العقود",
  "reason": null,
  "correlation_id": "01J…",
  "source": "http",
  "created_at": "2026-08-12T18:01:00Z"
}
```

List **omits** `metadata`, `before_values`, `after_values`, `ip_address`, `user_agent` (load on details).

## Details — `GET /api/v1/audit-logs/{auditLog}`

Includes list fields plus:

```json
{
  "metadata": { "from_status": "draft", "to_status": "assigned", "employee_id": 12 },
  "before_values": { "status": "draft" },
  "after_values": { "status": "assigned" },
  "ip_address": "203.0.113.10",
  "user_agent": "Mozilla/5.0 …",
  "deep_link": {
    "available": true,
    "entity_type": "task",
    "entity_id": 44
  }
}
```

Rules:

- `deep_link.available` is true only if entity still exists **and** actor may view it under owning module Policy — computed server-side; never raw URLs from DB.
- Sensitive keys already stripped at write; Resource applies sanitizer again (defense in depth).
- Never expose PHP class names, storage paths, tokens, password hashes.

## Envelope

Standard `{ data, meta }` / error envelope. Correlation ID echoed in response headers (existing middleware).

## Errors

| Case | Status | Notes |
|---|---|---|
| Unauthenticated | 401 | |
| Missing `audit_logs.view` | 403 | |
| Cross-tenant / missing id | 404 | |
| Validation on filters | 422 | |

## Recording

**No** public write API. Persistence only via `Core/Audit` from trusted listeners/Actions.

## Per-entity history

Same list endpoint with `entity_type` + `entity_id` (Audit History Panel).
