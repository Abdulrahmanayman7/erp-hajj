# Dashboard — API

> **Status:** Implemented (Sprint 017)
> **Last updated:** 2026-08-12
> Base: `/api/v1` · Auth: Sanctum SPA · Envelope: [API_STANDARDS.md](../../04-api/API_STANDARDS.md)

## Endpoints

| Method | Path | Permission | Notes |
|---|---|---|---|
| GET | `/dashboard` | `dashboard.view` | Single aggregated operational snapshot |

**No** POST/PATCH/DELETE. **No** per-widget public endpoints in MVP (avoids waterfall + inconsistent auth).

---

## Permission-aware response strategy

**Chosen strategy: omit unauthorized keys/sections entirely.**

- Do not return `null` placeholders for forbidden modules.
- Do not return `0` counts for modules the User cannot view (information leak).
- Client renders only keys present.

Optional companion map (allowed):

```json
"meta": {
  "generated_at": "2026-08-12T18:00:00+03:00",
  "timezone": "Asia/Riyadh",
  "sections": ["tasks", "contracts", "meetings", "decisions", "inventory", "assets", "notifications"]
}
```

`sections` lists only sections included for this actor (derived from permissions).

---

## Response shape (implementation-ready)

```json
{
  "success": true,
  "message": "",
  "data": {
    "meta": {
      "generated_at": "ISO-8601",
      "timezone": "Asia/Riyadh",
      "sections": ["tasks", "contracts", "meetings", "decisions", "inventory", "assets", "notifications"]
    },
    "kpis": {
      "tasks_overdue": { "value": 3, "label": "المهام المتأخرة", "severity": "critical", "href": "/app/tasks?overdue=1" },
      "tasks_open": { "value": 12, "label": "المهام المفتوحة", "severity": "info", "href": "/app/tasks" },
      "tasks_due_soon": { "value": 4, "label": "مهام مستحقة قريبًا", "severity": "warning", "href": "/app/tasks" },
      "decisions_pending_approval": { "value": 2, "label": "قرارات بانتظار الموافقة", "severity": "warning", "href": "/app/decisions?status=pending_approval" },
      "decisions_approved_open": { "value": 5, "label": "قرارات معتمدة", "severity": "info", "href": "/app/decisions?status=approved" },
      "decisions_with_open_tasks": { "value": 1, "label": "قرارات بمهام مفتوحة", "severity": "warning", "href": "/app/decisions?status=approved" },
      "contracts_executing": { "value": 20, "label": "عقود قيد التنفيذ", "severity": "info", "href": "/app/contracts?status=executing" },
      "contracts_expiring_soon": { "value": 3, "label": "عقود تنتهي قريبًا", "severity": "warning", "href": "/app/contracts?expiring_soon=1" },
      "contracts_expired": { "value": 1, "label": "عقود منتهية", "severity": "critical", "href": "/app/contracts?status=expired" },
      "meetings_today": { "value": 2, "label": "اجتماعات اليوم", "severity": "info", "href": "/app/meetings" },
      "meetings_in_progress": { "value": 1, "label": "اجتماعات جارية", "severity": "info", "href": "/app/meetings?status=in_progress" },
      "inventory_low": { "value": 4, "label": "أرصدة منخفضة", "severity": "warning", "href": "/app/inventory?stock_state=low" },
      "inventory_out": { "value": 1, "label": "أرصدة نافدة", "severity": "critical", "href": "/app/inventory?stock_state=out_of_stock" },
      "inventory_attention": { "value": 5, "label": "تنبيهات المخزون", "severity": "warning", "href": "/app/inventory" },
      "assets_available": { "value": 10, "label": "أصول متاحة", "severity": "info", "href": "/app/assets?status=available" },
      "assets_in_use": { "value": 8, "label": "أصول قيد الاستخدام", "severity": "info", "href": "/app/assets?status=in_use" },
      "assets_maintenance": { "value": 2, "label": "أصول في الصيانة", "severity": "warning", "href": "/app/assets?status=maintenance" },
      "custodies_overdue": { "value": 2, "label": "عُهد متأخرة", "severity": "critical", "href": "/app/assets" },
      "custodies_due_soon": { "value": 3, "label": "عُهد يقترب موعد إرجاعها", "severity": "warning", "href": "/app/assets" }
    },
    "attention": [
      {
        "type": "TASK_OVERDUE",
        "severity": "critical",
        "title": "5 مهام متأخرة",
        "subtitle": "تتطلب متابعة فورية",
        "count": 5,
        "entity_type": null,
        "entity_id": null,
        "href": "/app/tasks?overdue=1"
      }
    ],
    "today": {
      "meetings_today": [
        {
          "id": 12,
          "number": "MTG-000012",
          "title": "…",
          "scheduled_at": "ISO-8601",
          "status": "scheduled",
          "href": "/app/meetings/12"
        }
      ],
      "tasks_due_today": [],
      "contracts_expiring": [],
      "meetings_upcoming_7d": []
    },
    "work": {
      "my_tasks": {
        "open": 2,
        "overdue": 1,
        "href": "/app/tasks?assigned_to_me=1"
      }
    },
    "resources": {
      "my_custodies": {
        "active": 1,
        "overdue": 0,
        "href": "/app/my-custodies"
      }
    },
    "notifications": {
      "unread_count": 3,
      "href": "/app/notifications"
    }
  }
}
```

### Notes

- Any `kpis.*` key appears only if permitted.
- `work.my_tasks` / `resources.my_custodies` appear only when actor has linked Employee **and** corresponding `*.view`.
- `href` values are **server-built allow-listed paths** (relative app paths). Never accept client-supplied URLs.
- KPI `value` is always a non-negative integer **count** (never a mixed-unit quantity).
- `inventory_attention.value` = `inventory_low.value + inventory_out.value` when both keys present; if only one stock state section is computed, set accordingly.

---

## Query parameters

**None** in MVP.

Unknown query keys ignored or `422` per project convention — prefer ignore for forward compatibility.

---

## Errors

| Code | Status | When |
|---|---|---|
| `AUTH_UNAUTHENTICATED` | 401 | Guest |
| `AUTHORIZATION_DENIED` | 403 | Missing `dashboard.view` or inactive gates |
| Tenant lifecycle codes | 403 | Existing tenancy middleware |
| `NOT_FOUND` | 404 | N/A for collection endpoint |
| Standard 500 envelope | 500 | Aggregation failure (fail closed whole response) |

---

## Generation

Internal Actions/aggregators only. No client-created Dashboard rows.

## Audit

No audit event for routine Dashboard GET.
