# System Settings — API

> **Status:** Implemented (Sprint 020 + mail sender extension)
> **Last updated:** 2026-09-16
> Aligns with [00-tenancy/API.md](../00-tenancy/API.md) path names.

## Endpoints

```text
GET    /api/v1/tenant-settings     # tenant_settings.view
PATCH  /api/v1/tenant-settings     # tenant_settings.update
```

- Authenticated tenant user + active tenant middleware.
- **No** `{tenant}` segment; **no** `tenant_id` in body/query (ignored/rejected if sent).
- Standardized envelope ([API_STANDARDS.md](../../04-api/API_STANDARDS.md)).

Platform routes (`/api/v1/platform/tenants*`) are **out of scope** for this slice.

## GET response `data` (typed)

```json
{
  "general": {
    "name": "رفيع",
    "contact_name": null,
    "contact_email": null,
    "contact_phone": null
  },
  "regional": {
    "timezone": "Asia/Riyadh",
    "locale": "ar",
    "locale_editable": false
  },
  "technical": {
    "mail_from_address": null,
    "mail_from_name": null
  }
}
```

- Always returns effective stored values (null technical fields = use server `mail.from.*` at send time).
- No secrets, no SMTP host/password, no storage paths, no env dump.

## PATCH request

Partial update. Only mutable fields accepted:

```json
{
  "general": {
    "name": "رفيع",
    "contact_name": "…",
    "contact_email": "ops@example.com",
    "contact_phone": "+966…"
  },
  "regional": {
    "timezone": "Asia/Riyadh"
  },
  "technical": {
    "mail_from_address": "noreply@example.com",
    "mail_from_name": "رفيع ERP"
  }
}
```

### Semantics

| Rule | Behavior |
|---|---|
| Omitted section/field | Unchanged |
| Explicit `null` or blank string on nullable contact_* / mail_from_* | Clears value (server fallback for mail) |
| `regional.locale` present | **422** (immutable via this API) |
| Unknown property | **422** validation / `SETTINGS_UNKNOWN_FIELD` |
| Empty PATCH object | **422 SETTINGS_NO_CHANGES** if body empty of mutable fields |
| `tenant_id` / `tenant_code` / `status` | Rejected if present |
| SMTP credentials (`MAIL_HOST`, passwords, …) | **Never** accepted |

Success: **200** with full effective resource (same shape as GET).

## Error codes

| Code | HTTP | Meaning |
|---|---|---|
| (standard authz) | 401 / 403 | Unauthenticated / missing permission |
| (validation) | 422 | Field validation failures |
| `SETTINGS_UNKNOWN_FIELD` | 422 | Unknown or disallowed property |
| `SETTINGS_IMMUTABLE_FIELD` | 422 | Attempt to change `locale` (or other immutable) |
| `SETTINGS_INVALID_TIMEZONE` | 422 | Non-IANA timezone |
| `SETTINGS_NAME_TAKEN` | 422 | `tenants.name` global unique conflict (or reuse existing unique validation code if already standardized) |
| `TENANT_*` | 403 | Lifecycle blocks (existing middleware) |

Cross-tenant: not applicable via ID — context is always current tenant. Tests still prove Tenant A session cannot observe Tenant B values.

## Correlation ID

Every request carries correlation ID; audit row on PATCH includes it.

## Idempotency

Retrying an identical PATCH after success is safe (same values). No separate Idempotency-Key framework required.
