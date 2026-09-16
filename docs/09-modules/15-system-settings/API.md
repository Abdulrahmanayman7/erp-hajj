# System Settings — API

> **Status:** Implemented (Sprint 020 + tenant SMTP)
> **Last updated:** 2026-09-16
> Aligns with [00-tenancy/API.md](../00-tenancy/API.md) path names.

## Endpoints

```text
GET    /api/v1/tenant-settings            # tenant_settings.view
PATCH  /api/v1/tenant-settings            # tenant_settings.update
POST   /api/v1/tenant-settings/test-email # tenant_settings.update
```

- Authenticated tenant user + active tenant middleware.
- **No** `{tenant}` segment; **no** `tenant_id` in body/query (ignored/rejected if sent).
- Standardized envelope ([API_STANDARDS.md](../../04-api/API_STANDARDS.md)).

## GET response `data` (typed)

```json
{
  "general": { "name": "رفيع", "contact_name": null, "contact_email": null, "contact_phone": null },
  "regional": { "timezone": "Asia/Riyadh", "locale": "ar", "locale_editable": false },
  "technical": {
    "status": "unavailable",
    "deliverable": false,
    "mail_mailer": null,
    "mail_host": null,
    "mail_port": null,
    "mail_encryption": null,
    "mail_username": null,
    "mail_password_configured": false,
    "mail_from_address": null,
    "mail_from_name": null
  }
}
```

- `status`: `tenant_smtp` | `server_fallback` | `unavailable`
- **Never** returns `mail_password` (plaintext or ciphertext).
- Empty From fields → server `mail.from.*` at send time.

## PATCH `technical` (SMTP)

Accepted keys: `mail_mailer` (`smtp` only), `mail_host`, `mail_port` (1–65535), `mail_encryption` (`tls`|`ssl`|`none`), `mail_username`, `mail_password`, `mail_password_clear`, `mail_from_address`, `mail_from_name`.

| Rule | Behavior |
|---|---|
| Omit `mail_password` or blank | Keep existing encrypted password |
| Non-empty `mail_password` | Replace |
| `mail_password_clear: true` (without new password) | Clear stored password |
| Incomplete tenant SMTP | Treated as not usable → server fallback / unavailable |

## POST test-email

```json
{ "email": "example@gmail.com" }
```

Uses **saved** tenant SMTP only (no credentials in request). Success **200**; failure **422** with sanitized Arabic message. Audit `TENANT_EMAIL_TEST_SENT` on success (no secrets).

## Future tenant notifications

Use `TenantMailConfigurationResolver` + the overridden `mail` notification channel (`TenantMailChannel`) / `TenantMailer` — do not `Config::set` global SMTP.
