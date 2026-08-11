# Notifications — API

> **Status:** Specified (Sprint 016) — **not implemented**
> **Last updated:** 2026-08-11
> Base: `/api/v1` · Auth: Sanctum SPA · Envelope: [API_STANDARDS.md](../../04-api/API_STANDARDS.md)

All routes: authenticated + tenant-active. Access is **recipient-owned** (see [PERMISSIONS.md](PERMISSIONS.md)). Cross-tenant / other-user → **404**.

**No** client create/update/delete of notification content. **No** mark-unread.

---

## Endpoints

| Method | Path | Access | Notes |
|---|---|---|---|
| GET | `/notifications` | Own inbox | Paginated |
| GET | `/notifications/unread-count` | Own | `{ "unread_count": N }` |
| GET | `/notifications/{notification}` | Own row | 404 if other user/tenant |
| POST | `/notifications/{notification}/read` | Own row | Idempotent |
| POST | `/notifications/read-all` | Own | Marks all unread for actor |

### Unread count response

```json
{
  "success": true,
  "message": "",
  "data": { "unread_count": 3 }
}
```

Efficient query: `COUNT(*)` where `recipient_user_id = actor` AND `read_at IS NULL` under tenant scope — **do not** load the full inbox.

---

## List filters / sorting

| Param | Notes |
|---|---|
| `unread_only` | `1` / `true` → `read_at IS NULL` |
| `type` | Exact catalog type |
| `severity` | `info` \| `warning` \| `critical` |
| `created_from` / `created_to` | ISO date(time) range on `created_at` |
| `page` / `per_page` | Default per_page 15; max 100 |

**Default sort:** `created_at DESC`, then `id DESC`.

---

## Resource shape

```json
{
  "id": 1,
  "type": "TASK_ASSIGNED",
  "title": "…",
  "body": "…",
  "severity": "info",
  "entity_type": "task",
  "entity_id": 12,
  "read_at": null,
  "created_at": "…",
  "is_read": false
}
```

- Do **not** embed full Task/Contract resources.
- Optional compact `entity_label` only if cheap and already composed into title — prefer title/body alone in MVP.
- Never expose other users’ ids as recipients.

---

## Error codes

| Code | When |
|---|---|
| `NOTIFICATION_NOT_FOUND` | Prefer generic **404** |
| `NOTIFICATION_IMMUTABLE` | Attempt to mutate non-read fields |
| `NOTIFICATION_FORBIDDEN` | Prefer **404** for IDOR (no existence leak) |

Validation errors → standard `422` envelope.

---

## Generation

Internal only (Actions / Jobs / Artisan). **No** `POST /notifications`.

---

## Audit

Mark-read endpoints do **not** emit high-volume audit events (see BUSINESS_RULES).
