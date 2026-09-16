# Production mail — Hostinger SMTP & tenant settings

> **Status:** Operational guidance  
> **Last updated:** 2026-09-16

## Purpose

Password-set invitations (`send_invite` on user create) and forgot-password mail reuse the Auth reset notification, delivered through **tenant-aware** mail resolution:

```text
Complete tenant SMTP (Settings → إعدادات البريد الإلكتروني)
    ↓ primary
Server .env mail configuration
    ↓ fallback
MAIL_MAILER=log|array
    ↓ NOT real delivery → invite_sent=false + INVITE_MAILER_UNAVAILABLE
```

## Preferred path (no .env edit)

1. Deploy code + run `php artisan migrate --force` (adds nullable SMTP columns only).
2. Log in as Tenant Owner.
3. **الإعدادات → إعدادات البريد الإلكتروني**.
4. Enter SMTP (example Hostinger values below), From address/name, save.
5. **إرسال رسالة تجريبية** to a real inbox.
6. Create a user with invitation — should work even if server still has `MAIL_MAILER=log`.

### Example Hostinger values (placeholders only)

| Field | Example |
|---|---|
| طريقة الإرسال | SMTP |
| خادم SMTP | `smtp.hostinger.com` |
| المنفذ | `465` |
| التشفير | SSL |
| اسم مستخدم SMTP | `noreply@YOUR-DOMAIN.com` |
| كلمة مرور SMTP | mailbox password |
| بريد المرسل | usually same as username |
| اسم المرسل | e.g. رفيع ERP |

Providers may require From to match the authenticated mailbox — the UI documents this; the system does not silently rewrite From.

## Server fallback (.env)

Optional when tenant SMTP is empty/incomplete:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_USERNAME=noreply@YOUR-DOMAIN.com
MAIL_PASSWORD=YOUR_MAIL_PASSWORD
MAIL_SCHEME=smtps
MAIL_FROM_ADDRESS="noreply@YOUR-DOMAIN.com"
MAIL_FROM_NAME="${APP_NAME}"
```

Then `php artisan optimize:clear` (or `config:clear` + `config:cache`).

Confirm `FRONTEND_URL` matches the SPA origin so reset links open correctly.

## Security notes

- SMTP password is encrypted at rest; API returns `mail_password_configured` only — never the secret.
- Blank password on save keeps the existing secret; use «مسح كلمة المرور المحفوظة» to clear.
- Audit may record `mail_auth_updated` without any password value.
- Tenant A credentials are never used for Tenant B (isolated per-send mailer).

## Related

- [docs/09-modules/15-system-settings/](../09-modules/15-system-settings/)
- [docs/09-modules/02-users-and-authorization/BUSINESS_RULES.md](../09-modules/02-users-and-authorization/BUSINESS_RULES.md)
- [ADR-0016](../10-decisions/ADR-0016-TYPED-TENANT-SETTINGS-AND-RESOLUTION.md)
- [DEPLOYMENT.md](DEPLOYMENT.md)
